<?php

// src/OagBundle/Service/Geocoder.php

namespace OagBundle\Service;

class Cove extends AbstractAutoService {

    public function processUri($uri) {
        // TODO - fetch file, cache it, check content type, decode and then pass to cove line at a time
        $data = file_get_contents($uri);
        return $this->autocodeXml($data);
    }

    public function processString($text) {
        if (!$this->isAvailable()) {
            $this->getContainer()->get('session')->getFlashBag()->add("warning", "CoVE docker not available, using fixtures.");
            return json_encode($this->getFixtureData(), true);
        }

        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("pipe", "w"),
        );

        $oag = $this->getContainer()->getParameter('oag');
        $cmd = $oag['cove']['cmd'];
        $this->getContainer()->get('logger')->debug(
            sprintf('Command: %s', $cmd)
        );

        $process = proc_open($cmd, $descriptorspec, $pipes);

        if (is_resource($process)) {
            $this->getContainer()->get('logger')->info(sprintf('Writting %d bytes of data', strlen($text)));
            fwrite($pipes[0], $text);
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

            return $data;
        } else {
            // TODO Better exception handling.
            throw new \RuntimeException('CoVE Failed to start');
        }
    }

    public function processXML($contents) {
        // TODO - implement fetching this result from CoVE
        return json_encode($this->getFixtureData(), true);
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
