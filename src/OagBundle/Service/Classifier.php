<?php

namespace OagBundle\Service;

use OagBundle\Service\TextExtractor\TextifyService;
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
            $json = array(
                'data' => array(
                    'code' => '',
                    'description' => '',
                    'confidence' => '',
                ),
            );
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
            $json = array(
                'data' => array(
                    'code' => '',
                    'description' => '',
                    'confidence' => '',
                ),
            );
            $this->getContainer()->get('logger')->error('Classifier failed to process: ' . $data);
        }
        return array_merge($response, $json);
    }

    /**
     * Classify an OagFile and attach the resulting SuggestedTag objects to it.
     *
     * @param OagFile $oagFile the file to classify
     */
    public function classifyOagFile(OagFile $oagFile) {
        $srvClassifier = $this->getContainer()->get(Classifier::class);
        $srvOagFile = $this->getContainer()->get(OagFileService::class);
        $srvTextify = $this->getContainer()->get(TextifyService::class);

        $oagFile->clearSuggestedTags();

        if (($oagFile->getFileType() & OagFile::OAGFILE_IATI_DOCUMENT) === OagFile::OAGFILE_IATI_DOCUMENT) {
            // IATI xml document
            $rawXml = $srvOagFile->getContents($oagFile);
            $jsonResp = $this->processXML($rawXml);

            if ($jsonResp['status']) {
                throw new \Exception('Classifier service could not classify file');
            }

            foreach ($jsonResp['data'] as $block) {
                foreach ($block as $part) {
                    foreach ($part as $activityId => $tags) {
                        $this->persistTags($tags, $oagFile, $activityId);
                    }
                }
            }
        } else if (($oagFile->getFileType() & OagFile::OAGFILE_IATI_ENHANCEMENT_DOCUMENT) === OagFile::OAGFILE_IATI_ENHANCEMENT_DOCUMENT) {
            // enhancing/text document
            $rawText = $srvTextify->stripOagFile($oagFile);

            if ($rawText === false) {
                // textifier failed
                throw new \RuntimeException('Unsupported file type to strip text from');
            }

            $json = $srvClassifier->processString($rawText);

            // TODO if $row['status'] == 0
            $this->persistTags($json['data'], $oagFile);
        } else {
            throw new \RuntimeException('Unsupported OagFile type to classify');
        }

        $em = $this->getContainer()->get('doctrine')->getManager();
        $em->persist($oagFile);
        $em->flush();
    }

    /**
     * Persists Oag tags from API response to database.
     *
     * @param array $tags an array of tags, as represented by the Classifier's JSON
     * @param OagFile $file the OagFile to suggest the tags to
     * @param string $activityId the activity ID the tags apply to, if they are specific
     */
    private function persistTags($tags, $file, $activityId = null) {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $tagRepo = $this->getContainer()->get('doctrine')->getRepository(Tag::class);

        foreach ($tags as $row) {
            $code = $row['code'];
            if ($code === null) {
                // We get a single array of nulls back if no match is found.
                break;
            }

            $description = $row['description'];
            $confidence = $row['confidence'];

            $vocab = $this->getContainer()->getParameter('classifier')['vocabulary'];
            $vocabUri = $this->getContainer()->getParameter('classifier')['vocabulary_uri'];

            $findBy = array(
                'code' => $code,
                'vocabulary' => $vocab
            );

            // if there is a vocab uri in the config, use it, if not, don't
            if (strlen($vocabUri) > 0) {
                $findBy['vocabulary_uri'] = $vocabUri;
            } else {
                $vocabUri = null;
            }

            // Check that the code exists in the system
            $tag = $tagRepo->findOneBy($findBy);
            if (!$tag) {
                $this->getContainer()->get('logger')
                    ->info(sprintf('Creating new code %s (%s)', $code, $description));
                $tag = new Tag();
                $tag->setCode($code);
                $tag->setDescription($description);
                $tag->setVocabulary($vocab, $vocabUri);
                $em->persist($tag);
            }

            $sugTag = new \OagBundle\Entity\SuggestedTag();
            $sugTag->setTag($tag);
            $sugTag->setConfidence($confidence);
            if (!is_null($activityId)) {
                $sugTag->setActivityId($activityId);
            }

            $em->persist($sugTag);
            $file->addSuggestedTag($sugTag);
        }
    }

    public function getName() {
        return 'classifier';
    }

}
