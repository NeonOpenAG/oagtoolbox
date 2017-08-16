<?php

// src/OagBundle/Service/Geocoder.php

namespace OagBundle\Service;

class DPortal extends AbstractOagService {

    public function isAvailable() {
        $name = $this->getName();
        $cmd = sprintf('docker images openagdata/%s |wc -l', $name);
        $output = array();
        $retval = 0;
        $linecount = exec($cmd, $output, $retval);

        if ($retval == 0 && $linecount > 1) {
            $this->getContainer()->get('logger')->debug(
                sprintf('Docker %s is available', $name)
            );
            return true;
        } else {
            $this->getContainer()->get('logger')->info(
                sprintf(
                    'Failed to stat docker %s: %s', $name, json_encode($output)
                )
            );
            return false;
        }
    }

    public function visualise($oagfile) {

        $srvOagFile = $this->getContainer()->get(OagFileService::class);

        $xmldir = $this->getContainer()->getParameter('oagxml_directory');
        if (!is_dir($xmldir)) {
            mkdir($xmldir, 0755, true);
        }
        $xmlfile = $xmldir . '/' . $srvOagFile->getXMLFileName($oagfile);

        $this->getContainer()->get('logger')->info('Starting dportal docker');
        exec("openag start dportal");

        $this->getContainer()->get('logger')->info('Clearng dportal data');
        exec("openag reset dportal");

        $this->getContainer()->get('logger')->info(sprintf('Importing dportal data: %s', $xmlfile));
        exec("openag import dportal " . $xmlfile);
    }

    public function getName() {
        return 'dportal';
    }

}
