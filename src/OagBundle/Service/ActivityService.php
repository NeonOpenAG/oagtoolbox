<?php

namespace OagBundle\Service;

/**
 * A service for manipulating and getting data from IATI Activity files after
 * they have been parsed into a SimpleXMLElement object. This also acts as a
 * nice abstraction away from the SimpleXMLElement, in an effort to make
 * dealing with it more predictable.
 */
class ActivityService extends AbstractService {

    const LIBXML_OPTIONS = LIBXML_BIGLINES & LIBXML_PARSEHUGE;

    public function parseXML($string) {
        // helper function to allow for centralised changing of libxml options
        // where appropriate
        return new \SimpleXMLElement($string, self::LIBXML_OPTIONS);
    }

    public function toXML($root) {
        return $root->asXML();
    }

    public function summariseToArray($root) {
        // gets a simplified representation of the data that we can deserialise at the moment
        $activities = array();
        foreach ($this->getActivities($root) as $activity) {
            $simpActivity = array();
            $simpActivity['id'] = $this->getActivityId($activity);
            $simpActivity['name'] = $this->getActivityTitle($activity);
            $simpActivity['sectors'] = $this->getActivitySectors($activity);
            $simpActivity['locations'] = $this->getActivityLocations($activity);
            $activities[] = $simpActivity;
        }
        return $activities;
    }

    public function getFixtureData() {
        $kernel = $this->getContainer()->get('kernel');
        $file = $kernel->locateResource('@OagBundle/Resources/fixtures/before_enrichment_activities.xml');
        $xml = file_get_contents($file);
        return $xml;
    }

    public function getActivities($root) {
        return $root->xpath('/iati-activities/iati-activity');
    }

    public function getActivityId($activity) {
        return (string) $activity->xpath('./iati-identifier')[0];
    }

    public function getActivityTitle($activity) {
        $preference = array(
            './title/narrative[not(@xml:lang)]',
            './title/narrative[@xml:lang="en"]', // TODO make this configurable
            './title/narrative'
        );

        foreach ($preference as $path) {
            $finds = $activity->xpath($path);
            foreach ($finds as $found) {
                // we found a narrative of this type
                $name = (string) $found;
                if (strlen($name) == 0) {
                    // some activities have empty narratives, for reasons unknown
                    continue;
                }
                return $name;
            }
        }

        return 'Unnamed';
    }

    public function getActivitySectors($activity) {
        $currentSectors = array();
        foreach ($activity->xpath('./sector') as $currentSector) {
            $description = (string) $currentSector->xpath('./narrative[1]')[0];
            $code = (string) $currentSector['code'];
            $vocabulary = (string) $currentSector['vocabulary'];

            $currentSectors[] = array(
                'description' => $description,
                'code' => $code,
                'vocabulary' => $vocabulary
            );
        }
        return $currentSectors;
    }

    public function addActivitySector(&$activity, $code, $description, $reason = null) {
        // TODO should we check if it already exists?

        if (is_null($reason)) {
            $reason = 'Classified automatically';
        }

        $vocab = $this->getContainer()->getParameter('classifier')['vocabulary'];
        $vocabUri = $this->getContainer()->getParameter('classifier')['vocabulary_uri'];

        $sector = $activity->addChild('sector');
        $sector->addAttribute('code', $code);
        $sector->addAttribute('vocabulary', $vocab);
        if (strlen($vocabUri) > 0) {
            $sector->addAttribute('vocabulary-uri', $vocabUri);
        }

        // narrative text content is set this way to let simplexml escape it
        // see https://stackoverflow.com/a/555039
        $sector->narrative[] = $description;
        $sector->narrative[0]->addAttribute('xml:lang', 'en');

        $sector->narrative[] = $reason;
        $sector->narrative[1]->addAttribute('xml:lang', 'en');
    }

    public function removeActivitySector(&$activity, $code, $vocabulary) {
        $sector = $activity->xpath("./sector[@code='$code' and @vocabulary='$vocabulary']");

        if (count($sector) < 1) {
            return;
        }

        $sector = $sector[0];
        unset($sector[0]);
    }

    public function getActivityLocations($activity) {
        $currentLocations = array();
        foreach ($activity->xpath('./location') as $currentLocation) {
            $description = (string) $currentLocation->xpath('./name/narrative[1]')[0];
            $code = (string) $currentLocation->xpath('location-id')[0]['code'];
            $vocabulary = (string) $currentLocation->xpath('location-id')[0]['vocabulary'];

            $currentLocations[] = array(
                'description' => $description,
                'code' => $code,
                'vocabulary' => $vocabulary
            );
        }
        return $currentLocations;
    }

    public function addActivityLocation(&$activity, $json) {
        // $location is the JSON assoc-array describing a location as returned by
        // the Geocoder API
        // TODO what is "rollback"?
        // TODO should we check if it already exists?

        $location = $activity->addChild('location');

        $name = $location->addChild('name');
        $name->narrative[] = $json['name'];

        $locId = $location->addChild('location-id');
        $locId->addAttribute('vocabulary', $this->getContainer()->getParameter('geocoder')['id_vocabulary']);
        $locId->addAttribute('code', $json['id']);

        if ($json['geometry']['type'] === 'Point') {
            $point = $location->addChild('point');
            $point->addAttribute('srsName', 'http://www.opengis.net/def/crs/EPSG/0/4326');
            $point->pos[] = implode(' ', $json['geometry']['coordinates']);
        } else {
            // TODO what other possibilites are there?
        }

        $featDeg = $location->addChild('feature-designation');
        $featDeg->addAttribute('code', $json['featureDesignation']['code']);

        // TODO is $json['type'] relevant?

        $actDescript = $location->addChild('activity-description');
        $actDescript->narrative[] = $json['activityDescription'];

        $locClass = $location->addChild('location-class');
        $locClass->addAttribute('code', $json['locationClass']['code']);

        $exactness = $location->addChild('exactness');
        $exactness->addAttribute('code', $json['exactness']['code']);

        // TODO is $json['country'] relevant?
        // TODO check that this isn't dynamic - assuming not, as it is not an array
        $admin1 = $location->addChild('administrative');
        $admin1->addAttribute('code', $json['admin1']['code']);
        $admin1->addAttribute('level', "1");
        $admin1->addAttribute('vocabulary', $this->getContainer()->getParameter('geocoder')['admin_1_vocabulary']);

        $admin2 = $location->addChild('administrative');
        $admin2->addAttribute('code', $json['admin2']['code']);
        $admin2->addAttribute('level', "2");
        $admin2->addAttribute('vocabulary', $this->getContainer()->getParameter('geocoder')['admin_2_vocabulary']);
    }

    public function removeActivityLocation(&$activity, $code) { // TODO $vocabulary
        $location = $activity->xpath("./location/location-id[@code='$code']/..");

        if (count($location) < 1) {
            return;
        }

        $location = $location[0];
        unset($location[0]);
    }

}
