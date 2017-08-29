<?php

namespace OagBundle\Service;

/**
 * A service for manipulating and getting data from IATI Activity files after
 * they have been parsed into a SimpleXMLElement object. This also acts as a
 * nice abstraction away from the SimpleXMLElement, in an effort to make
 * dealing with it more predictable.
 *
 * IATI activity files are represented by their 'root' \SimpleXMLElement.
 * Individual activities are represented by their \SimpleXMLElement.
 * All other data is processed as associative arrays.
 */
class IATI extends AbstractService {

    const LIBXML_OPTIONS = LIBXML_BIGLINES & LIBXML_PARSEHUGE;
    const OPENAG_NAMESPACE = 'openag';

    /**
     * Load and parse an IATI OagFile into a SimpleXMLElement object.
     *
     * @param OagFile $oagFile
     * @return \SimpleXMLElement
     */
    public function load($oagFile) {
        $srvOagFile = $this->getContainer()->get(OagFileService::class);

        $contents = $srvOagFile->getContents($oagFile);

        $root = $this->parseXML($contents);

        return $root;
    }

    /**
     * Perform an xpath but with an additional namespace defined.
     *
     * @param \SimpleXMLElement $activity the activity to xpath within
     * @param string $xpathQuery
     * @param string $namespace the name of the namespace
     * @return \SimpleXMLElement[]
     */
    public function xpathNS(\SimpleXMLElement $activity, $xpathQuery, $namespace = 'openag') {
        $namespaceUri =  $this->getContainer()->getParameter('classifier')['namespace_uri'];
        $activity->registerXPathNamespace($namespace, $namespaceUri);
        return $activity->xpath($xpathQuery);
    }

    /**
     * Parse a given string into a \SimpleXMLElement object.
     *
     * @param $string
     *
     * @return \SimpleXMLElement
     */
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
            $this->getContainer()->get('logger')->error('Failed to parse XML: ' . substr($string, 0, 30));
            $this->getContainer()->get('logger')->error('Reason: ' . $ex->getMessage());
        }
    }

    /**
     * Convert a \SimpleXMLElement back into XML.
     *
     * @return string the XML
     */
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
    public function summariseActivityToArray(\SimpleXMLElement $activity) {
        $simpActivity = array();
        $simpActivity['id'] = $this->getActivityId($activity);
        $simpActivity['name'] = $this->getActivityTitle($activity);
        $simpActivity['tags'] = $this->getActivityTags($activity);
        $simpActivity['locations'] = $this->getActivityLocations($activity);
        return $simpActivity;
    }

    /**
     * Return a simplified array representation of the IATI file.
     *
     * @see summariseActivityToArray
     * @param \SimpleXMLElement $root
     * @return array
     */
    public function summariseToArray($root) {
        // gets a simplified representation of the data that we can deserialise at the moment
        $activities = array();
        foreach ($this->getActivities($root) as $activity) {
            $simpActivity = $this->summariseActivityToArray($activity);
            $activities[] = $simpActivity;
        }
        return $activities;
    }

    /**
     * Get XML fixture data to use - an example IATI file's contents.
     *
     * @return string
     */
    public function getFixtureData() {
        $kernel = $this->getContainer()->get('kernel');
        $file = $kernel->locateResource('@OagBundle/Resources/fixtures/before_enrichment_activities.xml');
        $xml = file_get_contents($file);
        return $xml;
    }

    /**
     * Get each activity in an IATI XML file.
     *
     * @param \SimpleXMLElement $root the base of the file once loaded
     * @return \SimpleXMLElement[] one for each activity
     */
    public function getActivities($root) {
        return $root->xpath('/iati-activities/iati-activity');
    }

    /**
     * Get an IATI activity from the file from its ID.
     *
     * @param \SimpleXMLElement $root
     * @param string $id
     * @return \SimpleXMLElement or NULL if missing
     */
    public function getActivityById($root, $id) {
        foreach ($this->getActivities($root) as $activity) {
            if ($this->getActivityId($activity) === $id) {
                return $activity;
            }
        }
        return NULL;
    }

    /**
     * Get the ID of an IATI activity.
     *
     * @param \SimpleXMLElement $activity
     * @return string
     */
    public function getActivityId($activity) {
        return (string) $activity->xpath('./iati-identifier')[0];
    }

    /**
     * Get the title of an IATI activity.
     *
     * @param \SimpleXMLElement $activity
     * @return string
     */
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
     * Creates a definition array to be provided to the NeonMap service,
     * summarising any location data in the activity that can be visualised
     * on a map.
     *
     * @param \SimpleXMLElement $activity
     * @return array see code
     */
    public function getActivityMapData($activity) {
        $activityDetail = $this->summariseActivityToArray($activity);

        $locations = $activityDetail['locations'];
        $location_data = array();
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

        return $map_data = array(
            "type" => "FeatureCollection",
            "features" => $location_data,
        );
    }

    /**
     * Get the information on the tags contained in an activity, from prior
     * classification.
     *
     * @param \SimpleXMLElement $activity
     * @return array
     */
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

    /**
     * Add a tag to the activity, effectively classifying it in the XML.
     *
     * @param \SimpleXMLElement $activity
     * @param string $code the uniquely identifying code of the tag in the vocabulary
     * @param string $description a human-readable description of the tag
     * @param string $reason a human-readable origin provided in the XML of the tag
     */
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


    /**
     * Remove a tag to the activity, effectively un-classifying it in the XML.
     *
     * @param \SimpleXMLElement $activity
     * @param string $code the uniquely identifying code of the tag in the vocabulary
     * @param string $vocabulary the vocabulary the code belongs to
     * @param string $vocabularyUri the URI of the vocabulary, if it is custom
     */
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

    /**
     * Gets summarised representations of the locations in the activity as
     * associative arrays.
     *
     * @param \SimpleXMLElement $activity
     * @return array see code
     */
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

    /**
     * Add a location to an activity in the XML, effectively geocoding it.
     * 
     * @param \SimpleXMLElement $activity
     * @param array $json a representation of the location's properties, as returned from the Geocoder service
     */
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

    /**
     * Remove an activity location from the XML.
     *
     * TODO IMPORTANT - vocabulary should be added to ensure locations are uniquely identified
     *
     * @param string $code the unique code of the location within the vocabulary
     */
    public function removeActivityLocation(&$activity, $code) { // TODO $vocabulary
        $location = $activity->xpath("./location/location-id[@code='$code']/..");

        if (count($location) < 1) {
            return;
        }

        $location = $location[0];
        unset($location[0]);
    }

}
