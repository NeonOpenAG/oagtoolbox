<?php

namespace OagBundle\Service;

use OagBundle\Service\TextExtractor\PDFExtractor;
use OagBundle\Service\TextExtractor\RTFExtractor;
use PhpOffice\PhpWord\IOFactory;
use OagBundle\Entity\OagFile;
use OagBundle\Entity\Tag;
use OagBundle\Entity\SuggestedTag;
use Symfony\Component\Cache\Simple\FilesystemCache;

class Classifier extends AbstractOagService {

    public function processUri($sometext) {
        // TODO implement non-fixture process
        return json_decode($this->getStringFixtureData(), true);
    }

    public function isAvailable() {
        $uri = $this->getUri('xml');

        // The classifier is VERY slow to respond, use a port check instead.
        // TODO Does this work with https
        $parts = parse_url($uri);
        $host = $parts['host'];
        $port = 80;
        if (isset($parts['port'])) {
            $port = $parts['port'];
        } elseif (isset($parts['scheme']) && $parts['scheme'] == 'https') {
            $port = 443;
        }
        $connection = @fsockopen($host, $port);
        return is_resource($connection);
    }

    public function getXMLFixtureData() {
        $kernel = $this->getContainer()->get('kernel');
        $path = $kernel->locateResource('@OagBundle/Resources/fixtures/before_enrichment_activities.classifier.json');
        $contents = file_get_contents($path);
        return $contents;
    }

    public function getStringFixtureData() {
        $kernel = $this->getContainer()->get('kernel');
        $path = $kernel->locateResource('@OagBundle/Resources/fixtures/text.classifier.json');
        $contents = file_get_contents($path);
        return $contents;
    }

    public function processXML($contents) {
        $classifier_parameters = $this->getContainer()->getParameter('classifier');
        $uri = $classifier_parameters['endpoint'];

        $request = curl_init();
        curl_setopt($request, CURLOPT_URL, $uri);
        curl_setopt($request, CURLOPT_POST, true);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $this->getContainer()->get('logger')->info('Accessing classifer at ' . $uri);

        $payload = array(
            'data' => $contents,
            'chunk' => 'true',
            'threshold' => 'low',
            'rollup' => 'false',
            'form' => 'json',
            'xml_input' => 'true',
        );

        curl_setopt($request, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($request, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        $data = curl_exec($request);
        $responseCode = curl_getinfo($request, CURLINFO_HTTP_CODE);
        curl_close($request);

        $response = array(
            'status' => ($responseCode >= 200 && $responseCode <= 209) ? 0 : 1,
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
            $this->getContainer()->get('logger')->error('Classifier failed to process: ' . $data . ' with response code: ' . $responseCode);
        }

        return array_merge($response, $json);
    }

    public function processString($contents) {
        if (!$this->isAvailable()) {
            // TODO use correct fixture data, the current is not representative of
            // output where just a string is processed
            return json_decode($this->getStringFixtureData(), true);
        }

//        $cache = new FilesystemCache();
//        $cachename = 'OagClassifier.' . md5($contents);
//        if ($cache->has($cachename)) {
//            $data = $cache->get('$cachename');
//            $response = array('status' => 2);
//            $this->getContainer()->get('logger')->info('Returning cached data ' . $data);
//        } else {
        $oag = $this->getContainer()->getParameter('oag');
        $uri = $oag['classifier']['text'];
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

        //            $cache->set($cachename, $data);
        $responseCode = curl_getinfo($request, CURLINFO_HTTP_CODE);
        curl_close($request);

        $response = array(
            'status' => ($responseCode >= 200 && $responseCode <= 209) ? 0 : 1,
        );
        //        }

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

    public function extractTags($response) {
        // flatten the response to put it in the form $activityId => $arrayOfTags
        $tags = array();
        foreach ($response['data'] as $part) {
            foreach ($part as $activityId => $descriptions) {
                if (!array_key_exists($activityId, $tags)) {
                    $tags[$activityId] = array();
                }
                $tags[$activityId] = array_merge($tags[$activityId], $descriptions);
            }
        }
        return $tags;
    }

    public function getName() {
        return 'classifier';
    }

}
