<?php

namespace OagBundle\Service;

use OagBundle\Service\TextExtractor\ApplicationOctetStream;
use OagBundle\Service\TextExtractor\ApplicationPdf;
use OagBundle\Service\TextExtractor\ApplicationXml;
use OagBundle\Service\TextExtractor\TextPlain;
use Symfony\Component\Cache\Simple\FilesystemCache;

class Classifier extends AbstractOagService {

    public function processUri($sometext) {
        // TODO implement non-fixture process
        return json_decode($this->getFixtureData(), true);
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

    public function getFixtureData() {
        $kernel = $this->getContainer()->get('kernel');
        $path = $kernel->locateResource('@OagBundle/Resources/fixtures/before_enrichment_activities.classifier.json');
        $contents = file_get_contents($path);
        return $contents;
    }

    public function processXML($contents) {
        // TODO implement non-fixture process
        return json_decode($this->getFixtureData(), true);
    }

    public function processString($contents) {
        if (!$this->isAvailable()) {
            // TODO use correct fixture data, the current is not representative of
            // output where just a string is processed
            return json_decode($this->getFixtureData(), true);
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

    public function extractSectors($response) {
        // flatten the response to put it in the form $activityId => $arrayOfSectors
        $sectors = array();
        foreach ($response['data'] as $part) {
            foreach ($part as $activityId => $descriptions) {
                if (!array_key_exists($activityId, $sectors)) {
                    $sectors[$activityId] = array();
                }
                $sectors[$activityId] = array_merge($sectors[$activityId], $descriptions);
            }
        }
        return $sectors;
    }

    public function getName() {
        return 'classifier';
    }

}
