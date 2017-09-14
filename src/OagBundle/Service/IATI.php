<?php

namespace OagBundle\Service;

use OagBundle\Entity\Tag;

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
     *   array['description'] Activity Description.
     *   array['tags'] Activity Tags.
     *   array['locations'] Activity Locations.
     */
    public function summariseActivityToArray(\SimpleXMLElement $activity) {
        $simpActivity = array();
        $simpActivity['id'] = $this->getActivityId($activity);
        $simpActivity['name'] = $this->getActivityTitle($activity);
        $simpActivity['description'] = $this->getActivityDescription($activity);
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

        return null;
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
            './description[not(@type)]',
            './description[@type=1]',
            './description'
        );

        $narrativePreference = array(
            './narrative[not(@xml:lang)]',
            './narrative[@xml:lang="en"]', // TODO make this configurable
            './narrative'
        );

        foreach ($descPreference as $descPath) {
            // use the first or look again
            $descs = $activity->xpath($descPath);
            if (count($descs) === 0) continue;
            $desc = $descs[0];

            foreach ($narrativePreference as $narrativePath) {
                // use the first or look again
                $narratives = $desc->xpath($narrativePath);
                if (count($narratives) === 0) continue;
                $narrative = $narratives[0];

                return (string) $narrative;
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
    public function addActivityLocation($activity, $geoloc) {
        $location = $activity->addChild('location');

        $name = $location->addChild('name');
        $name->narrative[] = $geoloc->getName();

        $locId = $location->addChild('location-id');
        $locId->addAtrribute('code', $geoloc->getLocationIdCode());
        $locId->addAtrribute('vocabulary', $geoloc->getLocationIdVocab());

        $point = $location->addChild('point');
        $lat = $geoloc->getPointPosLat();
        $lng = $geoloc->getPointPosLong();
        $point->pos[] = "$lat $lng";
    }

}
