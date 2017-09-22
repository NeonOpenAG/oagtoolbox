<?php

namespace OagBundle\Service;

use OagBundle\Entity\EnhancementFile;
use OagBundle\Entity\Geolocation;
use OagBundle\Entity\OagFile;
use OagBundle\Service\CSV;
use OagBundle\Service\TextExtractor\TextifyService;

class Geocoder extends AbstractAutoService {

    public function processString($sometext) {
        // TODO implement non-fixture process
        $json = json_decode($this->getXMLFixtureData(), true);
        $locations = array_column($json, 'locations', 'project_id'); // format as $activityId => $location[]
        return $locations;
    }

    public function processXML($contents) {
        // TODO implement non-fixture process
        $json = json_decode($this->getXMLFixtureData(), true);
        $locations = array_column($json, 'locations', 'project_id'); // format as $activityId => $location[]
        return $locations;
    }

    public function getName() {
        return 'geocoder';
    }

    public function getStringFixtureData() {
        $kernel = $this->getContainer()->get('kernel');
        $path = $kernel->locateResource('@OagBundle/Resources/fixtures/geocoder-string.json');
        $contents = file_get_contents($path);

        return $contents;
    }

    public function getXMLFixtureData() {
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
    public function geocodeOagFile(OagFile $file) {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $geolocRepo = $this->getContainer()->get('doctrine')->getRepository(Geolocation::class);
        $srvOagFile = $this->getContainer()->get(OagFileService::class);

        $xml = $srvOagFile->getContents($file);
        $activities = $this->processXML($xml);

        $file->clearGeolocations();

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

    /**
     * Process text and attach the resulting Geolocation suggestions to an IATI
     * file.
     *
     * @param OagFile $file
     * @param string $text
     * @param string $activityId if the text is specific
     */
    public function geocodeOagFileFromText(OagFile $file, $text, $activityId = null) {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $geolocRepo = $this->getContainer()->get('doctrine')->getRepository(Geolocation::class);
        $srvEnhancementFile = $this->getContainer()->get(EnhancementFileService::class);

        $activities = $this->processString($text);

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

    /**
     * Process an EnhancementFile and persist the results as Geolocation
     * objects, attached to the EnhancementFile.
     *
     * @param EnhancementFile $file the file to process
     */
    public function geocodeEnhancementFile(EnhancementFile $file) {
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

        $activities = $this->processString($rawText);

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

    /**
     * Use a part of the JSON response from the Geocoder to make a Geolocation
     * entity.
     *
     * @param $location a part of the JSON response describing a location
     * @return Geolocation
     */
    private function geolocationFromJson($location) {
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

}
