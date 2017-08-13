<?php

namespace OagBundle\Service;

use OagBundle\Service\TextExtractor\ApplicationOctetStream;
use OagBundle\Service\TextExtractor\ApplicationPdf;
use OagBundle\Service\TextExtractor\ApplicationXml;
use OagBundle\Service\TextExtractor\TextPlain;
use OagBundle\Entity\OagFile;
use OagBundle\Entity\Code;
use OagBundle\Entity\Sector;
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

    public function processOagFile(OagFile $file) {
        // TODO Swap this to use the texitfy service
        $path = $this
                ->getContainer()
                ->getParameter('oagfiles_directory') . '/' . $file->getDocumentName();
        $mimetype = $file->getMimeType();

        $this->getContainer()
            ->get('logger')
            ->info(sprintf('File %s detected as %s', $path, $mimetype));

        $isXml = false;
        $sourceFile = tempnam(sys_get_temp_dir(), 'oag') . '.txt';
        switch ($mimetype) {
            case 'application/pdf':
            case 'application/pdf':
            case 'application/x-pdf':
            case 'application/acrobat':
            case 'applications/vnd.pdf':
            case 'text/pdf':
            case 'text/x-pdf':
                // pdf
                $decoder = new PDFExtractor();
                $decoder->setFilename($path);
                $decoder->decode();
                file_put_contents($sourceFile, $decoder->output());
                break;
            case 'application/txt':
            case 'browser/internal':
            case 'text/anytext':
            case 'widetext/plain':
            case 'widetext/paragraph':
            case 'text/plain':
                // txt
                $sourceFile = $path;
                break;
            case 'text/html':
            case 'application/xml':
                // xml
                $sourceFile = $path;
                $isXml = true;
                break;
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
            case 'application/msword':
            case 'application/doc':
            case 'application/zip':
                // docx
                // phpword can't save to txt directly
                $tmpRtfFile = dirname($sourceFile) . '/' . basename($sourceFile, '.txt') . '.rtf';
                $phpWord = \PhpOffice\PhpWord\IOFactory::load($path, 'Word2007');
                $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'RTF');
                $objWriter->save($tmpRtfFile);
                // Now let the switch fall through to decode rtf
                $path = $tmpRtfFile;
            case 'application/rtf':
            case 'application/x-rtf':
            case 'text/richtext':
            case 'text/rtf':
                // rtf
                $decoder = new RTFExtractor();
                $decoder->setFilename($path);
                $decoder->decode();
                file_put_contents($sourceFile, $decoder->output());
                break;
        }
        $this->getContainer()->get('logger')->info(sprintf('Processing file %s', $sourceFile));

        $contents = file_get_contents($sourceFile);
        if ($isXml) {
            // TODO hit the XML endpoint...
            $json = $this->processXML($contents);
        } else {
            $json = $this->processString($contents);
        }

        return $json;
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
