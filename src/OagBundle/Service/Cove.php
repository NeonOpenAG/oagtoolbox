<?php

// src/OagBundle/Service/Geocoder.php

namespace OagBundle\Service;

use OagBundle\Entity\OagFile;
use OagBundle\Entity\RulesetError;
use RuntimeException;

class Cove extends AbstractOagService {

    private $json;

    public function processUri($uri)
    {
        // TODO - fetch file, cache it, check content type, decode and then pass to cove line at a time
        $data = file_get_contents($uri);
        return $this->autocodeXml($data);
    }


    /**
     * Process on OagFile with CoVE.
     *
     * @param OagFile $file
     */
    public function validateOagFile(OagFile $file)
    {
        $srvOagFile = $this->getContainer()->get(OagFileService::class);
        $srvIATI = $this->getContainer()->get(IATI::class);

        $em = $this->getContainer()->get('doctrine')->getManager();

        $this->getContainer()->get('logger')->debug(sprintf('Processing %s using CoVE', $file->getDocumentName()));
        // TODO - for bigger files we might need send as Uri
        $contents = $srvOagFile->getContents($file);
        $this->json = $this->process($contents, $file->getDocumentName());

        $err = array_filter($this->json['err']);
        $status = $this->json['status'];
        $this->getContainer()->get('logger')->debug('Cove exitied with status ' . $status);

        if (!isset($err['validation_errors']) || count($err['validation_errors']) === 0) {
            // CoVE claims to have processed the XML successfully
            $xml = $this->json['xml'];
            if ($srvIATI->parseXML($xml)) {
                // CoVE actually has returned valid XML
                $xmldir = $this->getContainer()->getParameter('oagxml_directory');
                if (!is_dir($xmldir)) {
                    mkdir($xmldir, 0755, true);
                }

                $filename = $srvOagFile->getXMLFileName($file);
                $xmlfile = $xmldir . '/' . $filename;
                if (!file_put_contents($xmlfile, $xml)) {
                    $this->get('session')->getFlashBag()->add('error', 'Unable to create XML file.');
                    $this->get('logger')->debug(sprintf('Unable to create XML file: %s', $xmlfile));
                    return $this->redirectToRoute('oag_wireframe_upload');
                }
                // else
                if ($this->getContainer()->getParameter('unlink_files')) {
                    unlink($srvOagFile->getPath($file));
                }

                $file->setDocumentName($filename);
                $file->setCoved(true);
                $em->persist($file);
                $em->flush();
                $this->getContainer()->get('session')->getFlashBag()->add('info', 'IATI File created/Updated: ' . basename($xmlfile));

                if (isset($err['ruleset_errors'])) {
                    foreach ($err['ruleset_errors'] as $line) {
                        $ruleseterror = new RulesetError();
                        $ruleseterror->setActivityId($line['id']);
                        $ruleseterror->setPath($line['path']);
                        $ruleseterror->setMessage($line['message']);
                        $ruleseterror->setRule($line['rule']);
                        $ruleseterror->setFilename($filename);
                        $em->persist($ruleseterror);
                        // TODO This is currenlty loosley associated with an activity.
                    }
                    $em->flush();
                    $rulsetErrorCount = count($err['ruleset_errors']);
                    $this->getContainer()->get('session')->getFlashBag()->add(
                            'warn', sprintf(
                                    'CoVE found %d ruleset error%s in the file %s.', $rulsetErrorCount, $rulsetErrorCount > 1 ? 's' : '', $filename
                            )
                    );
                }

                return true;
            } else {
                $this->getContainer()->get('session')->getFlashBag()->add('error', 'CoVE returned data that was not XML.');
            }
        } else {
            // CoVE returned with an error, spit out stderr
            if (isset($err['validation_errors'])) {
                foreach ($err['validation_errors'] as $line) {
                    $this->getContainer()->get('logger')->info(json_encode($line));
                    $this->getContainer()->get('session')->getFlashBag()->add('error', $this->formatValidationError($line));
                }
            }
        }

        return false;
    }

    public function formatRuleError(array $error) {
        return sprintf(
            'Ruleset error in activity %s, %s',
            $error['id'],
            $error['message']
        );
    }

    public function formatValidationError(array $error) {
        return sprintf(
            'Validation error, %s in the xml at %s',
            $error['description'],
            $error['path']
        );
    }

    public function process($contents, $filename)
    {
        $oag = $this->getContainer()->getParameter('oag');
        $cmd = str_replace('{FILENAME}', $filename, $oag['cove']['cmd']);
        $this->getContainer()->get('logger')->debug(
            sprintf('Command: %s', $cmd)
        );

        if (!$this->isAvailable()) {
            $this->getContainer()->get('session')->getFlashBag()->add("warning", $this->getName() . " docker not available, using fixtures.");
            return json_encode($this->getFixtureData(), true);
        }

        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w"),
        );

        $process = proc_open($cmd, $descriptorspec, $pipes);

        if (is_resource($process)) {
            $this->getContainer()->get('logger')->info(sprintf('Writting %d bytes of data', strlen($contents)));
            fwrite($pipes[0], $contents);
            fclose($pipes[0]);

            $xml = stream_get_contents($pipes[1]);
            $this->getContainer()->get('logger')->info(sprintf('Got %d bytes of data', strlen($xml)));
            fclose($pipes[1]);

            $err = stream_get_contents($pipes[2]);
            $this->getContainer()->get('logger')->info(sprintf('Got %d bytes of error', strlen($err)));
            fclose($pipes[2]);

            proc_close($process);

            $data = array(
                'xml' => $xml,
                'err' => json_decode($err, true),
            );

            $validationErrors = $data['err']['validation_errors'];
            $rulesetErrors = $data['err']['ruleset_errors'];

            $data['status'] = (count($validationErrors) + count($rulesetErrors));

            if ( $data['status'] > 0) {
                $this->getContainer()->get('logger')->error('Error: ' . $err);
            }

            return $data;
        } else {
            // TODO Better exception handling.
            throw new RuntimeException('CoVE Failed to start');
        }
    }

    public function getName()
    {
        return 'cove';
    }

    public function getFixtureData()
    {


        // TODO - load from file, can we make this an asset?
        // https://symfony.com/doc/current/best_practices/web-assets.html
        $json = array(
            'xml' => '',
            'errors' => array(
                'err1',
                'err2',
                'err3',
                'err4',
            ),
        );

        return json_encode($json);
    }

    public function getJson()
    {
        return $this->json;
    }

}
