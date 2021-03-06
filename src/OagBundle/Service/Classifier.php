<?php

namespace OagBundle\Service;

use OagBundle\Entity\EnhancementFile;
use OagBundle\Entity\OagFile;
use OagBundle\Entity\Tag;
use OagBundle\Service\TextExtractor\TextifyService;

class Classifier extends AbstractOagService
{

    public function processUri($sometext = '')
    {
        // TODO implement non-fixture process
        return json_decode($this->getStringFixtureData(), true);
    }

    public function getStringFixtureData()
    {
        $kernel = $this->getContainer()->get('kernel');
        $path = $kernel->locateResource('@OagBundle/Resources/fixtures/text.classifier.json');
        $contents = file_get_contents($path);
        return $contents;
    }

    public function getXMLFixtureData()
    {
        $kernel = $this->getContainer()->get('kernel');
        $path = $kernel->locateResource('@OagBundle/Resources/fixtures/before_enrichment_activities.classifier.json');
        $contents = file_get_contents($path);
        return $contents;
    }

    /**
     * Classify an OagFile and attach the resulting SuggestedTag objects to it.
     *
     * @param OagFile $oagFile the file to classify
     */
    public function classifyOagFile(OagFile $oagFile)
    {
        $srvOagFile = $this->getContainer()->get(OagFileService::class);
        $srvIati = $this->getContainer()->get(IATI::class);

        // $oagFile->clearSuggestedTags();

        // Extract the narrative elements and check those
        $root = $srvIati->load($oagFile);
        $activities = $srvIati->getActivities($root);
        $this->getContainer()->get('logger')->debug(sprintf('Found %d activities', count($activities)));
        foreach ($activities as $activity) {
            $activityId = $srvIati->getActivityId($activity);
            $description = $srvIati->getActivityDescription($activity);
            $tags = [];
            if ($description) {
                $data = $this->processString((string)$description);
                $tags = $data['data'];
                $this->persistTags($tags, $oagFile, $activityId);
            }
            else {
                $this->getContainer()->get('logger')->warn(sprintf('Activity %d has no description.', $activityId));
            }
        }

        // IATI xml document
        $rawXml = $srvOagFile->getContents($oagFile);
        $jsonResp = $this->processXML($rawXml);

        if ($jsonResp['status']) {
            $this->getContainer()->get('logger')->warn(sprintf('Classifier service could not classify oag file'));
            $this->getContainer()->get('logger')->debug(json_encode($jsonResp));
            throw new \Exception('Classifier service could not classify oag file');
        }

        $resultsData = $jsonResp['data']['results']['data'];
        foreach ($resultsData as $block) {
            foreach ($block as $part) {
                foreach ($part as $activityId => $tags) {
                    $this->persistTags($tags, $oagFile, $activityId);
                }
            }
        }

        $em = $this->getContainer()->get('doctrine')->getManager();
        $em->persist($oagFile);
        $em->flush();
    }

    public function processXML($contents)
    {
        $classifier_parameters = $this->getContainer()->getParameter('oag');
        $uri = $classifier_parameters['classifier']['xml'];

        $request = curl_init();
        curl_setopt($request, CURLOPT_URL, $uri);
        curl_setopt($request, CURLOPT_POST, true);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);

        $oag = $this->getContainer()->getParameter('oag');
        $key = $oag['classifier']['api_key'];
        $headers = [
            "Content-Type: application/json",
            "x-fc-api-key: " . $key
        ];

        $payload = array(
            'data' => $contents,
            'chunk' => 'true',
            'threshold' => 'low',
            'form' => 'json',
            'xml_input' => 'true',
        );
        $this->getContainer()->get('logger')->info('Accessing XML classifer at ' . $uri);

        curl_setopt($request, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($request, CURLOPT_HTTPHEADER, $headers);
        $data = curl_exec($request);
        $responseCode = curl_getinfo($request, CURLINFO_HTTP_CODE);
        curl_close($request);

        $response = array(
            'status' => ($responseCode >= 200 && $responseCode <= 209) ? 0 : 1,
        );

        if (!$response['status']) {
            $this->getContainer()->get('logger')->error(sprintf('Classifier response: %d (%d)', $responseCode, $data));
        }
        $this->getContainer()->get('logger')->debug($data);

        $json = json_decode($data, true);
        if (!is_array($json)) {
            $json = array(
                'data' => array(
                    'code' => '',
                    'description' => '',
                    'confidence' => '',
                ),
            );
            $this->getContainer()->get('logger')->error('Classifier failed to return json. (' . $data . ')');
            $log = [
                'class' => get_class(),
                'uri' => $uri,
                'payload' => $payload,
                'data' => $data,
            ];
            $this->logData($log);
        }

        return array_merge($response, $json);
    }

    /**
     * Persists Oag tags from API response to database.
     *
     * @param array                   $tags       an array of tags, as represented by the Classifier's JSON
     * @param OagFile|EnhancementFile $file       the OagFile or EnhancementFile to suggest the tags to
     * @param string                  $activityId the activity ID the tags apply to, if they are specific
     */
    private function persistTags($tags, $file, $activityId = null)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $tagRepo = $this->getContainer()->get('doctrine')->getRepository(Tag::class);

        foreach ($tags as $row) {
            if (!isset($row['code']) || $row['code'] === null) {
                // We get a single array of nulls back if no match is found.
                $this->getContainer()->get('logger')->debug('Classifier found no matches: ' . json_encode($row));
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
            }

            $em->persist($tag);
            $em->flush();

            $this->getContainer()->get('logger')
                ->debug(sprintf('Persisting %s (%s) at %s for %s', $code, $description, $confidence, $activityId));
            $sugTag = new \OagBundle\Entity\SuggestedTag();
            $sugTag->setTag($tag);
            $sugTag->setConfidence($confidence);
            if (!is_null($activityId)) {
                $sugTag->setActivityId($activityId);
            }
            $em->persist($sugTag);
            $em->flush();

            $file->addSuggestedTag($sugTag);
            $em->persist($file);
            $em->flush();
        }
    }

    /**
     * Classify an OagFile from raw text associated with it and attach the
     * resulting SuggestedTag objects to it.
     *
     * @param OagFile     $oagFile
     * @param string text
     * @param string      $activityId if the text is specific
     */
    public function classifyOagFileFromText(OagFile $file, $text, $activityId = null)
    {
        // $file->clearSuggestedTags();
        $json = $this->processString($text);

        if ($json['status']) {
            throw new \Exception('Classifier service could not classify text');
        }

        $this->persistTags($json['data'], $file, $activityId);

        $em = $this->getContainer()->get('doctrine')->getManager();
        $em->persist($file);
        $em->flush();

        $count = 0;
        if (isset($json['data'][0]['code'])) {
            $count = count($json['data']);
        }

        return $count;
    }

    public function processString($contents)
    {
        if (!$this->isAvailable()) {
            $this->getContainer()->get('logger')->warn('Classifier using ficture data.');
            return json_decode($this->getStringFixtureData(), true);
        }

        if (empty($contents)) {
            $this->getContainer()->get('logger')->warn('Classifier accessed with no content');
            return false;
        }

        $oag = $this->getContainer()->getParameter('oag');
        $uri = $oag['classifier']['text'];

        // /text/ag_classification
        $key = $oag['classifier']['api_key'];
        $headers = [
            "Content-Type: application/json",
            "x-fc-api-key: " . $key
        ];

        $payload = [
            'text' => $contents,
            'threshold' => $oag['classifier']['threshold'],
            'chunk' => $oag['classifier']['chunk'] ? 'true' : 'false',
            'roll_up' => $oag['classifier']['roll_up'] ? 'true' : 'false',
            'form' => 'json',
        ];

        $request = curl_init();
        curl_setopt($request, CURLOPT_POST, true);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($request, CURLOPT_URL, $uri);
        curl_setopt($request, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($request, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);

        $this->getContainer()->get('logger')->info('Accessing classifer at ' . $uri);
        $this->getContainer()->get('logger')->debug('Classifer sent data. Headers: (' . json_encode($headers) . '), Payload: ' . json_encode($payload));
        $data = curl_exec($request);
        $this->getContainer()->get('logger')->debug('Classifer returned: ' . json_encode($data));

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
            $this->getContainer()->get('logger')->error('Classifier failed to process: ' . $data);
        }
        return array_merge($response, $json);
    }

    public function isAvailable()
    {
        $uri = $this->getUri('xml');
        $parsedUri = $this->parseUri($uri);

        $host = $parsedUri['host'];
        $port = $parsedUri['port'];

        $connection = @fsockopen($host, $port);
        return is_resource($connection);
    }

    public function parseUri($uri)
    {
        // The classifier is VERY slow to respond, use a port check instead.
        // TODO Does this work with https
        $parts = parse_url($uri);
        $host = $parts['host'];
        $port = 80;
        if (isset($parts['port'])) {
            $port = $parts['port'];
        } elseif (isset($parts['scheme']) && $parts['scheme'] === 'https') {
            $port = 443;
        }

        return array(
        'host' => $host,
        'port' => $port,
        );
    }

    /**
     * Classify an EnhancementFile and attach the resulting SuggestedTag objects
     * to it.
     *
     * @param EnhancementFile $enhFile the file to classify
     */
    public function classifyEnhancementFile(EnhancementFile $enhFile, $activityId)
    {
        $srvTextify = $this->getContainer()->get(TextifyService::class);

        // $enhFile->clearSuggestedTags();

        // enhancing/text document
        $rawText = $srvTextify->stripEnhancementFile($enhFile);

        if ($rawText === false) {
            // textifier failed
            throw new \RuntimeException('Unsupported file type to strip text from');
        }

        $json = $this->processString($rawText);

        if ($json['status']) {
            throw new \Exception('Classifier service could not classify enhancement file');
        }

        $this->persistTags($json['data'], $enhFile, $activityId);

        $em = $this->getContainer()->get('doctrine')->getManager();
        $em->persist($enhFile);
        $em->flush();
    }

    public function getName()
    {
        return 'classifier';
    }

    public function status()
    {
        $cmd = 'ps -ef | grep bin/console';
        $output = [];
        $pipes = [];
        $descriptorspec = array(
        0 => array("pipe", "r"),
        1 => array("pipe", "w"),
        2 => array("pipe", "w"),
        );

        $process = proc_open($cmd, $descriptorspec, $pipes);

        if (is_resource($process)) {
            $xml = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            $filenames = [];

            // $em = $this->getContainer()->get('doctrine')->getManager();
            $oagfileRepo = $this->getContainer()->get('doctrine')->getRepository(OagFile::class);
            $lines = explode("\n", $xml);
            foreach ($lines as $line) {
                $start = strpos($line, 'oag:classify');
                if ($start) {
                    $start += 13;
                    $fileid = trim(substr($line, $start));
                    $file = $oagfileRepo->findOneById($fileid);
                    if ($file) {
                        $filenames[] = $file->getDocumentName();
                    } else {
                        $filenames[] = $line;
                    }
                }
            }

            $err = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $return_value = proc_close($process);

            if ($this->getContainer()->getParameter('populate_status')) {
                $filenames = [
                'foo.xml', 'bar.xml'
                ];
            }

            $data = array(
            'filenames' => $filenames,
            'err' => explode("\n", $err),
            'status' => $return_value,
            );

            if (strlen($err)) {
                $this->getContainer()->get('logger')->debug('Error: ' . $err);
            }

            return $data;
        } else {
            // TODO Better exception handling.
        }

        return array(
        'filenames' => [],
        'err' => [],
        'status' => 0,
        );
    }

}
