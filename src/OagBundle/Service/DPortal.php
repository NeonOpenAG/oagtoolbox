<?php

// src/OagBundle/Service/Geocoder.php

namespace OagBundle\Service;

class DPortal extends AbstractOagService
{

    public function isAvailable()
    {
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

    public function getName()
    {
        return 'dportal';
    }

    /**
     * Export an OagFile to DPortal.
     *
     * @param OagFile $oagfile the OagFile to export
     */
    public function visualise($oagfile)
    {

        $srvOagFile = $this->getContainer()->get(OagFileService::class);

        $xmldir = $this->getContainer()->getParameter('oagxml_directory');
        if (!is_dir($xmldir)) {
            mkdir($xmldir, 0755, true);
        }
        $xmlfile = $xmldir . '/' . $oagfile->getDocumentName();


        $return = 0;
        $output = [];

        $start = exec("openag start dportal", $output, $return);
        $output[] = '-------------------------------';
        $this->getContainer()->get('logger')->info(sprintf('Started dportal: "%s" (%d)', $start, $return));

        $reset = exec("openag reset dportal", $output, $return);
        $output[] = '-------------------------------';
        $this->getContainer()->get('logger')->info(sprintf('Reset dportal: "%s" (%d)', $xmlfile, $return));

        // $output = [];
        $import = exec("openag import dportal " . $xmlfile, $output, $return);
        $this->getContainer()->get('logger')->info(sprintf('Import dportal data: "%s" (%d)', $import, $return));

        foreach ($output as $line) {
            $this->getContainer()->get('logger')->debug($line);
        }
    }

}
