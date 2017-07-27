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

  public function getFixtureData() {
    $kernel = $this->getContainer()->get('kernel');
    $file = $kernel->locateResource('@OagBundle/Resources/fixtures/before_enrichment_activities.xml');
    $xml = file_get_contents($file);
    return $this->parseXML($xml);
  }

  public function getActivities($root) {
    return $root->xpath('/iati-activities/iati-activity');
  }

  public function getActivityId($activity) {
    return (string)$activity->xpath('./iati-identifier')[0];
  }

  public function getActivityName($activity) {
    // TODO other languages
    $nameElements = $activity->xpath('./title/narrative'); 
    if (count($nameElements) < 1) {
      $name = '';
    } else {
      $name = (string)$nameElements[0];
    }
    return $name;
  }

  public function getActivitySectors($activity) {
    $currentSectors = array();
    foreach ($activity->xpath('./sector') as $currentSector) {
      $description = (string)$currentSector->xpath('./narrative[1]')[0];
      $code = (string)$currentSector['code'];

      $currentSectors[] = array(
        'description' => $description,
        'code' => $code
      );
    }
    return $currentSectors;
  }

  public function addActivitySector(&$activity, $code, $description, $reason=null) {
    if (is_null($reason)) {
      $reason = 'Classified automatically';
    }

    $vocab = $this->getContainer()->getParameter('vocabulary');
    $vocabUri = $this->getContainer()->getParameter('vocabulary_uri');

    $sector = $activity->addChild('sector');
    $sector->addAttribute('code', $code);
    $sector->addAttribute('vocabulary', $vocab);
    if (strlen($vocabUri) > 0) {
      $sector->addAttribute('vocabulary-uri', $vocabUri);
    }

    // narrative text content is set this way to let simplexml escape it
    // see https://stackoverflow.com/a/555039
    $sector->narrative[] = $desc->description;
    $sector->narrative[0]->addAttribute('xml:lang', 'en'); 

    $sector->narrative[] = $reason;
    $sector->narrative[1]->addAttribute('xml:lang', 'en');
  }

  public function removeActivitySector(&$activity, $code) {
    // TODO require vocabulary as well, as codes could overlap
    $sector = $activity->xpath("./sector[@code='$code']");

    if (count($sector) < 1) {
      return;
    }

    $sector = $sector[0];
    unset($sector[0]);
  }

}
