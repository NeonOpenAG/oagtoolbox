<?php

namespace OagBundle\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Represents a service that may be enacted on the contents of an OagFIle but
 * that is not guaranteed to always be available.
 */
abstract class AbstractOagService extends AbstractService {

    /**
     * Get the URI endpoint of the external service.
     *
     * @param $key the key in the YAML config file of the URI
     * @return string the URI as a string
     */
    public function getUri($key = 'uri') {
        $oag = $this->getContainer()->getParameter('oag');

        $name = $this->getName();
        $uri = $oag[$name][$key];

        return $uri;
    }

    /**
     * Work out whether the external service is accessible or whether we have
     * to fall back to fixture data.
     *
     * @return boolean TRUE if available, else FALSE
     */
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
    
    public function isCsv($mimetype) {
        $mimes = array('application/vnd.ms-excel', 'text/plain', 'text/csv', 'text/tsv');
        return in_array($mimetype, $mimes);
    }

    public function isXml($mimetype) {
        $mimes = array('application/xml', 'text/xml', 'text/html');
        return in_array($mimetype, $mimes);
    }

    abstract function getName();
}
