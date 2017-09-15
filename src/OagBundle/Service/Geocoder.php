<?php

namespace OagBundle\Service;

use OagBundle\Entity\Geolocation;
use OagBundle\Entity\OagFile;
use OagBundle\Service\CSV;

class Geocoder extends AbstractAutoService {

    public function processUri($sometext) {
        // TODO implement non-fixture process
        $csvService = $this->getContainer()->get(CSV::class);
        return $csvService->toArray($this->getFixtureData(), "\t");
    }

    public function processString($sometext) {
        // TODO implement non-fixture process
        $csvService = $this->getContainer()->get(CSV::class);
        return $csvService->toArray($this->getFixtureData(), "\t");
    }

    public function processXML($contents) {
        // TODO implement non-fixture process
        // TODO how to do this per-activity?
        $csvService = $this->getContainer()->get(CSV::class);
        return $csvService->toArray($this->getFixtureData(), "\t");
    }

    public function getName() {
        return 'geocoder';
    }

    public function getFixtureData() {
        $kernel = $this->getContainer()->get('kernel');
        $path = $kernel->locateResource('@OagBundle/Resources/fixtures/geocoder.tsv');
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
        $em = $this->getContainer()->get('doctrine')->getManager();
        $geolocRepo = $this->getContainer()->get('doctrine')->getRepository(Geolocation::class);
        $srvOagFile = $this->getContainer()->get(OagFileService::class);

        $xml = $srvOagFile->getContents($file);
        $locations = $this->processXML($xml);

        $file->clearGeolocations();

        foreach ($locations as $location) {
            $locationIdCode = $location['geonameId'];
            $locationIdVocab = $this->getContainer()->getParameter('geocoder')['id_vocabulary'];

            $geoloc = $geolocRepo->findOneBy(array(
                'locationIdCode' => $locationIdCode,
                'locationIdVocab' => $locationIdVocab
            ));

            if (!$geoloc) {
                $geoloc = new Geolocation();
                $geoloc->setName($location['toponymName']);
                $geoloc->setLocationIdCode($locationIdCode);
                $geoloc->setLocationIdVocab($locationIdVocab);
                $geoloc->setFeatureDesignation($location['fcode']);
                $geoloc->setPointPosLat($location['lat']);
                $geoloc->setPointPosLong($location['lng']);
                // TODO admin
            }

            $file->addGeolocation($geoloc);
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
        $em = $this->get('doctrine')->getManager();
        $geolocRepo = $this->get('doctrine')->getRepository(Geolocation::class);
        $srvEnhancementFile = $this->getContainer()->get(EnhancementFileService::class);

        $xml = $srvEnhancementFile->getContents($file);
        $locations = $this->processXML($xml);

        $file->clearGeolocations();

        foreach ($locations as $location) {
            $locationIdCode = $location['geonameId'];
            $locationIdVocab = $this->getContainer()->getParameter('geocoder')['id_vocabulary'];

            $geoloc = $geolocRepo->findOneBy(array(
                'locationIdCode' => $locationIdCode,
                'locationIdVocab' => $locationIdVocab
            ));

            if (!$geoloc) {
                $geoloc = new Geolocation();
                $geoloc->setName($location['toponymName']);
                $geoloc->setLocationIdCode($locationIdCode);
                $geoloc->setLocationIdVocab($locationIdVocab);
                $geoloc->setFeatureLocationCode($location['fcode']);
                $geoloc->setFeatureLocationName($location['fcodeName']);
                $geoloc->setPointPosLat($location['lat']);
                $geoloc->setPointPosLong($location['lng']);
                // TODO admin
            }

            $file->addGeolocation($geoloc);
        }

        $em->persist($file);
        $em->flush();
    }

}
