<?php

namespace OagBundle\Service;

/**
 * A service for manipulating and getting data from IATI Activity files after
 * they have been parsed into a SimpleXMLElement object.
 */
class ActivityService extends AbstractService {

  const LIBXML_OPTIONS = LIBXML_BIGLINES & LIBXML_PARSEHUGE;

  public function parseXML($string) {
    // helper function to allow for centralised changing of libxml options
    // where appropriate
    return new \SimpleXMLElement($string, self::LIBXML_OPTIONS);
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

}
