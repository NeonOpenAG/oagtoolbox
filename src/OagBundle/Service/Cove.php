<?php

// src/OagBundle/Service/Geocoder.php

namespace OagBundle\Service;

use OagBundle\Entity\OagFile;

class Cove extends AbstractOagService {

    public function processUri($uri) {
        // TODO - fetch file, cache it, check content type, decode and then pass to cove line at a time
        $data = file_get_contents($uri);
        return $this->autocodeXml($data);
    }

    public function process($contents, $filename) {
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

            $return_value = proc_close($process);

            $data = array(
                'xml' => $xml,
                'err' => explode("\n", $err),
                'status' => $return_value,
            );

            if (strlen($err)) {
                $this->getContainer()->get('logger')->error('Error: ' . $err);
            }

            return $data;
        } else {
            // TODO Better exception handling.
            throw new \RuntimeException('CoVE Failed to start');
        }
    }

    /**
     * Process on OagFile with CoVE.
     *
     * @param OagFile $file
     */
    public function validateOagFile(OagFile $file) {
        $srvOagFile = $this->getContainer()->get(OagFileService::class);
        $srvIATI = $this->getContainer()->get(IATI::class);

        $this->getContainer()->get('logger')->debug(sprintf('Processing %s using CoVE', $file->getDocumentName()));
        // TODO - for bigger files we might need send as Uri
        $contents = $srvOagFile->getContents($file);
        $json = $this->process($contents, $file->getDocumentName());

        $err = array_filter($json['err'] ?? array());
        $status = $json['status'];

        if ($status === 0) {
            // CoVE claims to have processed the XML successfully
            $xml = $json['xml'];
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
                $em = $this->getContainer()->get('doctrine')->getManager();
                $em->persist($file);
                $em->flush();
                $this->getContainer()->get('session')->getFlashBag()->add('info', 'IATI File created/Updated\; ' . $xmlfile);

                return true;
            } else {
                $this->getContainer()->get('session')->getFlashBag()->add('error', 'CoVE returned data that was not XML.');
            }
        } else {
            // CoVE returned with an error, spit out stderr
            foreach ($err as $line) {
                $this->getContainer()->get('session')->getFlashBag()->add('error', $line);
            }
        }

        return false;
    }

    public function getFixtureData() {


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

    public function getName() {
        return 'cove';
    }

}
