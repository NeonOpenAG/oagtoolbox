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
    const OPENAG_NAMESPACE = 'openag';

    public function load($oagFile) {
        $srvOagFile = $this->getContainer()->get(OagFileService::class);

        $contents = $srvOagFile->getContents($oagFile);
        $root = $this->parseXML($contents);

        return $root;
    }

    public function xpathNS(\SimpleXMLElement $activity, $xpathQuery, $namespace = 'openag') {
        $namespaceUri =  $this->getContainer()->getParameter('classifier')['namespace_uri'];
        $activity->registerXPathNamespace($namespace, $namespaceUri);
        return $activity->xpath($xpathQuery);
    }

    public function parseXML($string) {


        // helper function to allow for centralised changing of libxml options
        // where appropriate
        try {
            $root = new \SimpleXMLElement($string, self::LIBXML_OPTIONS);
            $activities = $this->getActivities($root);
            $namespaceUri =  $this->getContainer()->getParameter('classifier')['namespace_uri'];
            foreach ($activities as $activity) {
                $activityDocNamespaces = $activity->getDocNamespaces(FALSE, FALSE);
                if(!array_key_exists('openag', $activityDocNamespaces)) {
                    $activity->addAttribute('xmlns:xmlns:openag', $namespaceUri);
                }
            }
            return $root;
        } catch (\Exception $ex) {
            $this->getContainer()->get('logger')->error('YU NO LOG' . $ex->getMessage());
            $this->getContainer()->get('logger')->error('Failed to parse XML: ' . substr($string, 0, 30));
            throw $ex;
        }
        return false;
    }

    public function toXML($root) {
        return $root->asXML();
    }

    /**
     * Returns a summary array for any given activity.
     *
     * @param $activity
     *
     * @return array
     *   Summary of the passed in activity.
     *
     *   array['id'] Activity ID.
     *   array['name'] Activity Name.
     *   array['tags'] Activity Tags.
     *   array['locations'] Activity Locations.
     */
    public function summariseActivityToArray($activity) {
        $simpActivity = array();
        $simpActivity['id'] = $this->getActivityId($activity);
        $simpActivity['name'] = $this->getActivityTitle($activity);
        $simpActivity['tags'] = $this->getActivityTags($activity);
        $simpActivity['locations'] = $this->getActivityLocations($activity);
        return $simpActivity;
    }

    public function summariseToArray($root) {
        // gets a simplified representation of the data that we can deserialise at the moment
        $activities = array();
        foreach ($this->getActivities($root) as $activity) {
            $simpActivity = $this->summariseActivityToArray($activity);
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

    public function getActivityById($root, $id) {
        foreach ($this->getActivities($root) as $activity) {
            if ($this->getActivityId($activity) === $id) {
                return $activity;
            }
        }
        return NULL;
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

    /**
     * Creates a definition array to be provided to the NeonMap service.
     *
     * @param \SimpleXMLElement $activity
     *
     * @return array
     */
    public function getActivityMapData($activity) {
        $activityDetail = $this->summariseActivityToArray($activity);

        $locations = $activityDetail['locations'];
        $location_data = [];
        foreach ($locations as $location) {
            $location_data[] = array(
                "id" => $activityDetail['id'],
                "type" => "Feature",
                "geometry" => array(
                    "type" => "Point",
                    "coordinates" => $location['lonlat'],
                ),
                'properties' => array(
                    'title' => $location['description'],
                    'nid' => $location['code'],
                ),
            );
        }

        return $map_data = [
            "type" => "FeatureCollection",
            "features" => $location_data,
        ];
    }

    public function getActivityTags($activity) {
        $currentTags = array();
        foreach ($this->xpathNS($activity, './openag:tag') as $currentTag) {
            $description = (string) $currentTag->xpath('./narrative[1]')[0];
            $code = (string) $currentTag['code'];
            $vocabulary = (string) $currentTag['vocabulary'];
            $vocabularyUri = (string) $currentTag['vocabulary-uri'] ?: null;

            $currentTags[] = array(
                'description' => $description,
                'code' => $code,
                'vocabulary' => $vocabulary,
                'vocabulary-uri' => $vocabularyUri,
            );
        }

        return $currentTags;
    }

    public function addActivityTag(&$activity, $code, $description, $reason = null) {
        // TODO should we check if it already exists?
        if (is_null($reason)) {
            $reason = 'Classified automatically';
        }

        $vocab = $this->getContainer()->getParameter('classifier')['vocabulary'];
        $vocabUri = $this->getContainer()->getParameter('classifier')['vocabulary_uri'];
        $namespaceUri =  $this->getContainer()->getParameter('classifier')['namespace_uri'];

        $tag = $activity->addChild('openag:tag', '', $namespaceUri);
        $tag->addAttribute('code', $code);
        $tag->addAttribute('vocabulary', $vocab);
        if (strlen($vocabUri) > 0) {
            $tag->addAttribute('vocabulary-uri', $vocabUri);
        }

        # narrative text content is set this way to let simplexml escape it
        # see https://stackoverflow.com/a/555039
        # $narrative->addAttribute('xml:lang', 'en');
        $tag->addChild('narrative', null, '')[] = $description;
        $tag->addChild('narrative', null, '')[] = $reason;
    }

    public function removeActivityTag(&$activity, $code, $vocabulary, $vocabularyUri = null) {
        $path = "./openag:tag[@code='$code' and @vocabulary='$vocabulary']";
        if ($vocabulary === '98' || $vocabulary === '99') {
            if (is_null($vocabularyUri)) {
                throw \Exception('Vocabulary URI must be provided if vocabulary is "98" or "99"');
            }
            $path = "./openag:tag[@code='$code' and @vocabulary='$vocabulary' and @vocabulary-uri='$vocabularyUri']";
        }
        $tag = $this->xpathNS($activity, $path);

        if (count($tag) < 1) {
            return;
        }

        $tag = $tag[0];
        unset($tag[0]);
    }

    public function getActivityLocations($activity) {
        $currentLocations = array();
        foreach ($activity->xpath('./location') as $currentLocation) {
            $description = (string) $currentLocation->xpath('./name/narrative[1]')[0];
            $code = (string) $currentLocation->xpath('location-id')[0]['code'];
            $vocabulary = (string) $currentLocation->xpath('location-id')[0]['vocabulary'];
            $pos = (string)$currentLocation->xpath('point/pos')[0];
            $lonlat = explode(' ', $pos);

            $currentLocations[] = array(
                'description' => $description,
                'code' => $code,
                'vocabulary' => $vocabulary,
                'lonlat' => $lonlat,
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
