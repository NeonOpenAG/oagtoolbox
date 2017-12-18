<?php

namespace OagBundle\Service;

use OagBundle\Entity\EnhancementFile;
use OagBundle\Entity\Geolocation;
use OagBundle\Entity\OagFile;
use OagBundle\Service\CSV;
use OagBundle\Service\TextExtractor\TextifyService;

class Geocoder extends AbstractOagService
{

    public function getStringFixtureData()
    {
        $kernel = $this->getContainer()->get('kernel');
        $path = $kernel->locateResource('@OagBundle/Resources/fixtures/geocoder-string.json');
        $contents = file_get_contents($path);

        return $contents;
    }

    public function getXMLFixtureData()
    {
        $kernel = $this->getContainer()->get('kernel');
        $path = $kernel->locateResource('@OagBundle/Resources/fixtures/geocoder-xml.json');
        $contents = file_get_contents($path);

        return $contents;
    }

    /**
     * Process an OagFile and persist the results as Geolocation objects,
     * attached to the OagFile.
     *
     * @param OagFile $file the file to process
     */
    public function geocodeOagFile(OagFile $file, $country = null)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $geolocRepo = $this->getContainer()->get('doctrine')->getRepository(Geolocation::class);
        $srvOagFile = $this->getContainer()->get(OagFileService::class);

        $xml = $srvOagFile->getContents($file);
        $activities = $this->processXML($xml, $file->getDocumentName(), $country);

        // $file->clearGeolocations();

        foreach ($activities as $activityId => $locations) {
            foreach ($locations as $location) {
                $geoloc = $this->geolocationFromJson($location);
                $geoloc->setIatiActivityId($activityId);

                $file->addGeolocation($geoloc);
            }
        }

        $em->persist($file);
        $em->flush();
    }

    public function processXML($contents, $filename, $country = null)
    {
        $data = $this->process($contents, $filename, $country);
        $json = json_decode($data['xml'], true);
        if ($json) {
            $locations = array_column($json, 'locations', 'project_id'); // format as $activityId => $location[]
        } else {
            $locations = [];
            $this->getContainer()->get('logger')->error(sprintf('Geocoder failed on %s : %s.', $filename, $json));
        }
        return $locations;
    }

    public function process($contents, $filename, $country)
    {
        $oag = $this->getContainer()->getParameter('oag');
        $openagnerserver = $oag['nerserver']['host'] ?? 'openag_nerserver';
        $openagnerport = $oag['nerserver']['port'] ?? '9000';
        $cmd = str_replace('{COUNTRY}', $country, str_replace('{FILENAME}', $filename, $oag['geocoder']['cmd']));
        $cmd = str_replace('{OPENAG_NERSERVER}', $openagnerserver, $cmd);
        $cmd = str_replace('{OPENAG_PORT}', $openagnerport, $cmd);
        $this->getContainer()->get('logger')->debug(
            sprintf('Command: %s', $cmd)
        );

        if (!$this->isAvailable()) {
            $this->getContainer()->get('session')->getFlashBag()->add("warning", $this->getName() . " docker not available, using fixtures.");
            return json_encode($this->getFixtureData(), true);
        }

        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w"),
        );

        $process = proc_open($cmd, $descriptorspec, $pipes);

        if (is_resource($process)) {
            $this->getContainer()->get('logger')->info(sprintf('Writting %d bytes of data', strlen($contents)));
            fwrite($pipes[0], $contents);
            fclose($pipes[0]);

            $xml = stream_get_contents($pipes[1]);
            $this->getContainer()->get('logger')->info(sprintf('Got %d bytes of data', strlen($xml)));
            fclose($pipes[1]);

            $err = stream_get_contents($pipes[2]);
            $this->getContainer()->get('logger')->info(sprintf('Got %d bytes of error', strlen($err)));
            fclose($pipes[2]);

            $return_value = proc_close($process);

            $data = array(
                'xml' => $xml,
                'err' => explode("\n", $err),
                'status' => $return_value,
            );

            if (strlen($err)) {
                $this->getContainer()->get('logger')->debug('Error: ' . $err);
            }

            return $data;
        } else {
            // TODO Better exception handling.
            throw new \RuntimeException('Geocoder Failed to start');
        }
    }

    public function getName()
    {
        return 'geocoder';
    }

    /**
     * Use a part of the JSON response from the Geocoder to make a Geolocation
     * entity.
     *
     * @param $location a part of the JSON response describing a location
     * @return Geolocation
     */
    private function geolocationFromJson($location)
    {
        $locationIdCode = strval($location['id']);
        $locationIdVocab = $this->getContainer()->getParameter('geocoder')['id_vocabulary'];

        $geoloc = new Geolocation();
        $geoloc->setName($location['name']);
        $geoloc->setLocationIdCode($locationIdCode);
        $geoloc->setLocationIdVocab($locationIdVocab);
        $geoloc->setFeatureDesignation($location['featureDesignation']['code']);
        $geoloc->setPointPosLong($location['geometry']['coordinates'][0]);
        $geoloc->setPointPosLat($location['geometry']['coordinates'][1]);
        // TODO admin1..4
        // TODO country

        return $geoloc;
    }

    /**
     * Process text and attach the resulting Geolocation suggestions to an IATI
     * file.
     *
     * @param OagFile $file
     * @param string $text
     * @param string $activityId if the text is specific
     */
    public function geocodeOagFileFromText(OagFile $file, $text, $activityId = null, $countryCode = false)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $geolocRepo = $this->getContainer()->get('doctrine')->getRepository(Geolocation::class);
        $srvEnhancementFile = $this->getContainer()->get(EnhancementFileService::class);

        $activities = $this->processString($text, 'enhancement.txt', $countryCode);

        $file->clearGeolocations();

        foreach ($activities as $locations) {
            foreach ($locations as $location) {
                $geoloc = $this->geolocationFromJson($location);

                if (!is_null($activityId)) {
                    $geoloc->setIatiActivityId($activityId);
                }

                $file->addGeolocation($geoloc);
            }
        }

        $em->persist($file);
        $em->flush();
    }

    public function processString($sometext, $filename, $country)
    {
        $data = $this->process($sometext, $filename, $country);
        $json = json_decode($data['xml'], true);
        // $json = json_decode($this->getXMLFixtureData(), true);
        if (is_array($json)) {
            $locations = array_column($json, 'locations', 'project_id'); // format as $activityId => $location[]
        } else {
            $this->getContainer()->get('logger')->warn('Geocoder returned no locations.');
            if ($country) {
                $this->getContainer()->get('logger')->warn('Geocoder re-try again without a country code.');
                $locations = $this->processString($sometext, $filename, false);
            } else {
                $locations = [];
            }
        }
        return $locations;
    }

    /**
     * Process an EnhancementFile and persist the results as Geolocation
     * objects, attached to the EnhancementFile.
     *
     * @param EnhancementFile $file the file to process
     */
    public function geocodeEnhancementFile(EnhancementFile $file)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $geolocRepo = $this->getContainer()->get('doctrine')->getRepository(Geolocation::class);
        $srvTextify = $this->getContainer()->get(TextifyService::class);
        $srvEnhancementFile = $this->getContainer()->get(EnhancementFileService::class);

        // enhancing/text document
        $rawText = $srvTextify->stripEnhancementFile($file);

        if ($rawText === false) {
            // textifier failed
            throw new \RuntimeException('Unsupported file type to strip text from');
        }

        $activities = $this->processString($rawText, $file->getDocumentName());

        $file->clearGeolocations();

        foreach ($activities as $locations) {
            foreach ($locations as $location) {
                $geoloc = $this->geolocationFromJson($location);
                $file->addGeolocation($geoloc);
            }
        }

        $em->persist($file);
        $em->flush();
    }

    public function status()
    {
        $cmd = 'docker exec -t openag_geocoder /bin/bash -c "/bin/ps -ef | grep python3"';
        $output = [];
        $pipes = [];
        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w"),
        );

        $process = proc_open($cmd, $descriptorspec, $pipes);

        if (is_resource($process)) {
            $xml = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            $filenames = [];

            $lines = explode("\n", $xml);
            foreach ($lines as $line) {
                $start = strpos($line, '-f');
                if ($start) {
                    $start += 3;
                    $end = strpos($line, '-o');
                    $filename = trim(substr($line, $start, $end - $start));
                    $filenames[] = $filename;
                }
            }

            $err = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $return_value = proc_close($process);

            if ($this->getContainer()->getParameter('populate_status')) {
                $filenames = [
                    'bill.xml', 'ted.xml'
                ];
            }

            $data = array(
                'filenames' => $filenames,
                'err' => explode("\n", $err),
                'status' => $return_value,
            );

            if (strlen($err)) {
                $this->getContainer()->get('logger')->debug('Error: ' . $err);
            }

            return $data;
        } else {
            // TODO Better exception handling.
        }

        return array(
            'filenames' => [],
            'err' => [],
            'status' => 0,
        );
    }

}
