<?php

namespace OagBundle\Service;

use OagBundle\Entity\Tag;
use OagBundle\Entity\OagFile;

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

    public function getData(OagFile $oagFile) {
        $data = [];
        // Get activites
        $srvIATI = $this->getContainer()->get(IATI::class);
        $root = $srvIATI->load($oagFile);

        $activities = $this->getActivities($root);
        foreach ($activities as $activity) {
            // Count tags / activity
            $tags = $this->getActivityTags($activity);
            // Count gelocation / activity
            $gelocations = $this->getActivityLocations($activity);
            $data[$this->getActivityId($activity)] = [
                'tags' => $tags,
                'locs' => $gelocations,
            ];
        }

        return $data;
    }

    public function getStats($data) {
        $activitcount = count($data);
        $activitiesWithNoTags = 0;
        $activitiesWithNoLocs = 0;
        $totalTags = 0;
        $totalLocs = 0;
        $averageTags = 0;
        $averageLocs = 0;

        foreach ($data as $activityId => $activityData) {
            $tags = $activityData['tags'];
            $locs = $activityData['locs'];
            $totalTags += count($tags);
            $totalLocs += count($locs);

            if (count($tags) == 0) {
                $activitiesWithNoTags++;
            }
            if (count($locs) == 0) {
                $activitiesWithNoLocs++;
            }
        }

        $averageTags = $activitcount > 0 ? $totalTags / $activitcount : 0;
        $averageLocs = $activitcount > 0 ? $totalLocs / $activitcount : 0;

        $stats = [
            'count' => $activitcount,
            'activitiesWithNoTags' => $activitiesWithNoTags,
            'activitiesWithNoLocs' => $activitiesWithNoLocs,
            'totalTags' => $totalTags,
            'totalLocs' => $totalLocs,
            'averageTags' => $averageTags,
            'averageLocs' => $averageLocs,
        ];

        return $stats;
    }

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
     * Perform an xpath but prioritising different localisations.
     *
     * @param \SimpleXMLElement $activity the activity to xpath within
     * @param string $xpathQuery
     * @param string $namespace
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

            // add the namespace URI to the root <iati-activities> element, if needed
            if(!array_key_exists('openag', $root->getDocNamespaces(FALSE, FALSE))) {
                $root->addAttribute('xmlns:xmlns:openag', $namespaceUri);
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
     *   array['description'] Activity Description.
     *   array['tags'] Activity Tags.
     */
    public function summariseActivityToArray(\SimpleXMLElement $activity) {
        $simpActivity = array();
        $simpActivity['id'] = $this->getActivityId($activity);
        $simpActivity['name'] = $this->getActivityTitle($activity);
        $simpActivity['description'] = $this->getActivityDescription($activity);
        $simpActivity['tags'] = $this->getActivityTags($activity);
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
     * Prioritises globalisation in the following order:
     * 1. No language specified
     * 2. English specified
     * 3. Other language specified
     *
     * Returns null if a <title> with <narrative> is not present.
     *
     * @param \SimpleXMLElement $activity
     * @return string|null
     */
    public function getActivityTitle($activity) {
        return $this->getNarrative($activity, 'title');
    }

    /**
     * Get the description of an IATI activity.
     *
     * Prioritises as generic a description as possible with a non-specific
     * language. Less-generic descriptions in other languages are fallen-back
     * to.
     *
     * Returns null if a <description> with <narrative> is not present.
     *
     * @param \SimpleXMLElement $activity
     * @return string|null
     */
    public function getActivityDescription($activity) {
        $descPreference = array(
            'description[not(@type)]',
            'description[@type=1]',
            'description'
        );

        foreach ($descPreference as $descPath) {
            $narrative = $this->getNarrative($activity, $descPath);

            if (!is_null($narrative)) {
                return $narrative;
            }
        }

        return null;
    }

    /**
     * Get the information on the tags contained in an activity, from prior
     * classification.
     *
     * @param \SimpleXMLElement $activity
     * @return Tag[]
     */
    public function getActivityTags($activity) {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $tagRepo = $this->getContainer()->get('doctrine')->getRepository(Tag::class);

        $currentTags = array();
        foreach ($this->xpathNS($activity, './openag:tag') as $currentTag) {
            // create the tag in the database if it doesn't exist
            $code = (string) $currentTag['code'];
            $desc = (string) $currentTag->xpath('./narrative[1]')[0];
            $vocab = (string) $currentTag['vocabulary'];
            $vocabUri = (string) $currentTag['vocabulary-uri'] ?: null;

            // use the tag if it is already in the database
            $findBy = array(
                'code' => $code,
                'vocabulary' => $vocab
            );

            if (!is_null($vocabUri)) {
                $findBy['vocabulary_uri'] = $vocabUri;
            }

            $dbTag = $tagRepo->findOneBy($findBy);
            if (!$dbTag) {
                // create a tag entity for the database
                $dbTag = new Tag();
                $dbTag->setCode($code);
                $dbTag->setDescription($desc);
                $dbTag->setVocabulary($vocab, $vocabUri);
                $em->persist($dbTag);
            }

            $currentTags[] = $dbTag;
        }

        // flush any tags we have added
        $em->flush();

        return $currentTags;
    }

    
    /**
     * Get the ID of an IATI activity.
     *
     * @param \SimpleXMLElement $activity
     * @return string
     */
    public function getActivityCountryCode($activity) {
        $element = $activity->xpath('./recipient-country')[0];
        $code = (string) $element->attributes()['code'];
        
        return $code;
    }
    
    /**
     * Add a tag to the activity, effectively classifying it in the XML.
     *
     * @param \SimpleXMLElement $activity
     * @param Tag $tag the details of the tag to add
     * @param string $reason a human-readable origin provided in the XML of the tag
     */
    public function addActivityTag($activity, $tag, $reason = null) {
        if (in_array($tag, $this->getActivityTags($activity))) {
            // no duplicates
            return;
        }

        $namespaceUri =  $this->getContainer()->getParameter('classifier')['namespace_uri'];

        $node = $activity->addChild('openag:tag', '', $namespaceUri);
        $node->addAttribute('code', $tag->getCode());
        $node->addAttribute('vocabulary', $tag->getVocabulary());

        if (!is_null($tag->getVocabularyUri())) {
            $node->addAttribute('vocabulary-uri', $tag->getVocabularyUri());
        }

        // narrative text content is set this way to let simplexml escape it
        // see https://stackoverflow.com/a/555039
        // $narrative->addAttribute('xml:lang', 'en');
        $node->addChild('narrative', null, '')[] = $tag->getDescription();

        // add an additional narrative describing changes, optionally
        if (!is_null($reason)) {
            $node->addChild('narrative', null, '')[] = $reason;
        }
    }


    /**
     * Remove a tag to the activity, effectively un-classifying it in the XML.
     *
     * @param \SimpleXMLElement $activity
     * @param Tag $tag the details of the tag to remove
     */
    public function removeActivityTag($activity, $tag) {
        $code = $tag->getCode();
        $vocabulary = $tag->getVocabulary();
        $vocabularyUri = $tag->getVocabularyUri();

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
     * Add a location to an activity in the XML, effectively geocoding it.
     * 
     * @param \SimpleXMLElement $activity
     * @param Geolocation $geoloc
     */
    public function addActivityGeolocation($activity, $geoloc) {
        $location = $activity->addChild('location');

        $name = $location->addChild('name');
        $name->narrative[] = $geoloc->getName();

        $locId = $location->addChild('location-id');
        $locId->addAttribute('code', $geoloc->getLocationIdCode());
        $locId->addAttribute('vocabulary', $geoloc->getLocationIdVocab());

        $featureDes = $location->addChild('feature-designation');
        $featureDes->addAttribute('code', $geoloc->getFeatureDesignation());

        $point = $location->addChild('point');
        $lat = $geoloc->getPointPosLat();
        $lng = $geoloc->getPointPosLong();
        $point->pos[] = "$lat $lng";
    }

    /**
     * Get a summary of an activity's location nodes. Note the distinction
     * between Geolocation and Location in the method's name: this method does
     * not return Geolocation objects, just location tags summarised in
     * associative arrays.
     *
     * @param \SimpleXMLElement $activity
     * @return array[]
     */
    public function getActivityLocations($activity) {
        $locations = array();

        foreach ($activity->xpath('./location') as $location) {
            /*
             * TODO
             * There are other attributes available to us here.
             * http://iatistandard.org/202/activity-standard/iati-activities/iati-activity/location/
             * The rest should be added here as required.
             */

            $simple = array();
            $simple['name'] = $this->getNarrative($location, 'name');
            $simple['description'] = $this->getNarrative($location, 'description');
            $simple['activity-description'] = $this->getNarrative($location, 'activity-description');

            // <point><pos>
            if (count($location->xpath('./point/pos')) > 0) {
                $string = (string) $location->xpath('./point/pos')[0];
                $coords = array_map('floatval', explode(' ', $string));
                $simple['point'] = array('pos' => $coords);
            }

            // <administrative> elements
            $simple['administrative'] = array();
            foreach ($location->xpath('./administrative') as $admin) {
                $adminArray = array(
                    'code' => (string) $admin->attributes()['code'],
                    'vocabulary' => (string) $admin->attributes()['vocabulary']
                );

                if (array_key_exists('level', $admin->attributes())) {
                    $adminArray['level'] = intval((string) $admin->attributes()['level']);
                }

                $simple['administrative'][] = $adminArray;
            }

            // <location-class>
            if (count($location->xpath('./location-class/@code')) > 0) {
                $simple['location-class'] = (string) $location->xpath('./location-class/@code')[0];
            }

            // <feature-designation>
            if (count($location->xpath('./feature-designation/@code')) > 0) {
                $simple['feature-designation'] = (string) $location->xpath('./feature-designation/@code')[0];
            }

            // getNarrative may return null, remove these entries entirely
            $simple = array_filter($simple, function ($val) { return !is_null($val); });

            $locations[] = $simple;
        }

        return $locations;
    }

    /**
     * Get the narrative of an XML element, holding preference to specific
     * languages.
     *
     * @param \SimpleXMLElement $element
     * @param string the element name to get the narrative of
     * @return \SimpleXMLElement[]
     */
    public function getNarrative(\SimpleXMLElement $element, $elementName) {
        $preference = array(
            "./$elementName/narrative[not(@xml:lang)]",
            "./$elementName/narrative[@xml:lang=\"en\"]", // TODO make this configurable
            "./$elementName/narrative"
        );

        foreach ($preference as $potential) {
            $results = $element->xpath($potential);
            foreach ($results as $found) {
                $string = (string) $found;
                if (strlen($string) > 0) {
                    return $string;
                }
            }
        }

        return null;
    }

}
