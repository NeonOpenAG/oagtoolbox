<?php

namespace OagBundle\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Represents a service that is enacted on the contents of an OagFile.
 */
abstract class AbstractAutoService extends AbstractOagService {

    /**
     * Process the contents at the given URI.
     *
     * @param string $sometext the URI
     * @return array the json response
     */
    abstract function processUri($sometext);

    /**
     * Process the contents as raw text.
     *
     * @param string $sometext the text
     * @return array the json response
     */
    abstract function processString($sometext);

    /**
     * Process the XML.
     *
     * @param string $sometext the xml
     * @return array the json response
     */
    abstract function processXML($somexml);
}
