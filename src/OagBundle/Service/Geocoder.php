<?php

namespace OagBundle\Service;

use OagBundle\Entity\Geolocation;
use OagBundle\Entity\OagFile;

class Geocoder extends AbstractAutoService {

    public function processUri($sometext) {
        // TODO implement non-fixture process
        return json_decode($this->getFixtureData(), true);
    }

    public function processString($sometext) {
        // TODO implement non-fixture process
        $uri = $this->getUri();

        $json = $this->getFixtureData();

        return json_decode($json, true);
    }

    public function processXML($contents) {
        // TODO implement non-fixture process
        return json_decode($this->getFixtureData(), true);
    }

    public function getName() {
        return 'geocoder';
    }

    public function getFixtureData() {
        $kernel = $this->getContainer()->get('kernel');
        $path = $kernel->locateResource('@OagBundle/Resources/fixtures/geocoder.json');
        $contents = file_get_contents($path);

        return $contents;
    }

    public function processOagFile(OagFile $file) {
        return $this->getFixtureData();
    }

    /**
     * Process an OagFile and persist the results as Geolocation objects,
     * attached to the OagFile.
     *
     * @param OagFile $file the file to process
     */
    public function geocodeOagFile(OagFile $file) {
        $json = $this->processOagFile($file);
        $results = json_decode($json, true);

        $file->clearGeolocations();
        $em = $this->getContainer()->get('doctrine')->getManager();
        $geolocationrepo = $this->getContainer()->get('doctrine')->getRepository(Geolocation::class);

        foreach ($results as $activity) {
            $iatiActivityId = $activity['project_id'] ?? null;
            $locations = $activity['locations'];
            foreach ($locations as $location) {
                $locationId = $location['id'];
                $vocabId = '99'; // TODO get a valif vocab id
                // Does this location already exist for this IATI ID?
                $geolocation = $geolocationrepo->findOneBy(
                    array(
                        'iatiActivityId' => $iatiActivityId,
                        'geolocationId' => $locationId,
                        'vocabId' => $vocabId,
                    )
                );

                if (!$geolocation) {
                    $geolocation = new Geolocation();
                    $geolocation->setIatiActivityId($iatiActivityId);
                    $geolocation->setGeolocationId($locationId);
                    $geolocation->setVocabId('99'); // TODO get a valif vocab id
                }
                $geolocation->setName($location['name']);
                $geolocation->setAdminCode1Code($location['admin1']['code']);
                $geolocation->setAdminCode1Name($location['admin1']['name']);
                $geolocation->setAdminCode2Code($location['admin2']['code']);
                $geolocation->setAdminCode2Name($location['admin2']['name']);
                $geolocation->setLatitude($location['geometry']['coordinates'][0]);
                $geolocation->setLongitude($location['geometry']['coordinates'][1]);
                $geolocation->setExactness($location['exactness']['code']);
                $geolocation->setClass($location['locationClass']['code']);
                $geolocation->setDescription($location['locationClass']['description']);
                $em->persist($geolocation);

                if (!$file->hasGeolocation($geolocation)) {
                    $file->addGeolocation($geolocation);
                }
            }
        }
        $em->persist($file);
        $em->flush();
    }

    /**
     * Flatten a geolocation entity instance to an associative array summarising it.
     *
     * @param Geolocation $location
     * @return array see function for clearest view of keys and values
     */
    public function locationToArray(Geolocation $location) {
        $data = array();
        $data['vocab_id'] = $location->getVocabId();
        $data['geolocation_id'] = $location->getGeolocationId();
        $data['name'] = $location->getName();
        $data['admin_code_1_code'] = $location->getAdminCode1Code();
        $data['admin_code_1_name'] = $location->getAdminCode1Name();
        $data['admin_code_2_code'] = $location->getAdminCode2Code();
        $data['admin_code_2_name'] = $location->getAdminCode2Name();
        $data['latitude'] = $location->getLatitude();
        $data['longitude'] = $location->getLongitude();
        $data['exactness'] = $location->getExactness();
        $data['class'] = $location->getClass();
        $data['description'] = $location->getDescription();
        return $data;
    }

}
