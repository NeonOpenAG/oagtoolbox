<?php

namespace OagBundle\Service;

use OagBundle\Service\TextExtractor\ApplicationOctetStream;
use OagBundle\Service\TextExtractor\ApplicationPdf;
use OagBundle\Service\TextExtractor\ApplicationXml;
use OagBundle\Service\TextExtractor\TextPlain;

class Classifier extends AbstractOagService {

  const LIBXML_OPTIONS = LIBXML_BIGLINES & LIBXML_PARSEHUGE;

  public function processUri($sometext) {
    return $this->processString();
  }

  public function isAvailable() {
    $uri = $this->getUri();
//
//    $request = curl_init();
//    curl_setopt($request, CURLOPT_URL, $uri);
//    curl_exec($request);
//
//    $responseCode = curl_getinfo($request, CURLINFO_HTTP_CODE);
//    curl_close($request);
//    return ($responseCode >= 200 && $responseCode <= 209);
//
    // The classifier is VERY slow to respond, use a port check instead.
    // TODO Does this work with https
    $parts = parse_url($uri);
    $host = $parts['host'];
    $port = 80;
    if (isset($parts['port'])) {
      $port = $parts['port'];
    }
    elseif (isset($parts['scheme']) && $parts['scheme'] == 'https') {
      $port = 443;
    }
    $connection = @fsockopen($host, $port);
    return is_resource($connection);
  }

  public function getFixtureData() {
    $kernel = $this->getContainer()->get('kernel');
    $path = $kernel->locateResource('@OagBundle/Resources/fixtures/before_enrichment_activities.classifier.json');
    $contents = file_get_contents($path);

    return $contents;
  }

  public function processString($contents) {
    if (!$this->isAvailable()) {
      return $this->getFixtureData();
    }

    $uri = $this->getUri();
    $request = curl_init();
    curl_setopt($request, CURLOPT_URL, $uri);
    curl_setopt($request, CURLOPT_POST, true);
    curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
//    curl_setopt($request, CURLOPT_VERBOSE, true);
//    curl_setopt($request, CURLOPT_HEADER, true);
    $this->getContainer()->get('logger')->info('Accessing classifer at ' . $uri);

    $payload = array(
      'text1' => $contents,
      'limit' => 0,
      'anchor' => 0,
      'ext' => 'fc',
      'threshold' => 'low',
      'rollup' => 'true',
      'chunk' => 'True',
    );

    curl_setopt($request, CURLOPT_POSTFIELDS, http_build_query($payload));

    $data = curl_exec($request);
    $responseCode = curl_getinfo($request, CURLINFO_HTTP_CODE);
    curl_close($request);

    $response = array(
      'status' => ($responseCode >= 200 && $responseCode <= 209)?0:1,
    );

    $json = json_decode($data, true);
    if (!is_array($json)) {
      $json = [
        'data' => [
          'code' => '',
          'description' => '',
          'confidence' => '',
        ],
      ];
      $this->getContainer()->get('logger')->error('Classifier failed to process: ' . $data);
    }
    return array_merge($response, $json);
  }

  public function extractSectors($response) {
    // flatten the response to put it in the form $activityId => $arrayOfSectors
    $sectors = array();
    foreach ($response->data as $part) {
      foreach ($part as $activityId => $descriptions) {
        if (!array_key_exists($sectors, $activityId)) {
          $sectors[$activityId] = array();
        }
        $sectors[$activityId] = array_merge($sectors[$activityId], $descriptions);
      }
    }
    return $sectors;
  }

  public function insertSectors($xmlActivities, $sectors) {
    // TODO consider whether sectors are already present
    // TODO confidence should likely be considered in this function
    $root = new \SimpleXMLElement($xmlActivities, self::LIBXML_OPTIONS);

    // just fetch these once
    $vocab = $this->getContainer()->getParameter('vocabulary');
    $vocabUri = $this->getContainer()->getParameter('vocabulary_uri');

    foreach ($sectors as $id => $descriptions) {
      // find the activity with the relevent id
      $activity = $root->xpath("/iati-activities/iati-activity[iati-identifier='$id'");

      if (count(activity < 1)) {
        continue;
      }
      $activity = $activity[0];

      // add each sector
      foreach ($descriptions as $desc) {
        $sector = $activity->addChild('sector');
        $sector->addAttribute('code', $desc->code);
        $sector->addAttribute('vocabulary', $vocab);

        if (strlen($vocabUri) > 0) {
          $sector->addAttribute('vocabulary-uri', $vocabUri);
        }

        // narrative text content is set this way to let simplexml escape it
        // see https://stackoverflow.com/a/555039
        $sector->narrative[] = $desc->description;
        $sector->narrative[0]->addAttribute('xml:lang', 'en');

        $sector->narrative[] = 'Classified automatically';
        $sector->narrative[1]->addAttribute('xml:lang', 'en');
      }
    }

    return $root->asXML();
  }

  public function getName() {
    return 'classifier';
  }

}
