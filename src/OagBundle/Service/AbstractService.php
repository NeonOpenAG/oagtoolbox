<?php

namespace OagBundle\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractService
{

    private $container;

    public function logData($data, $fileprefix = 'data')
    {

        $logDir = $this->getContainer()->get('kernel')->getLogDir() . '/oag';
        $logFile = $logDir . '/' . $fileprefix . '.log';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        if (is_array($data)) {
            if (isset($data['payload]'])) {
                $this->logData($data['payload]'], 'payload');
                unset($data['payload]']);
            }
            $_data = json_encode($data);
        } elseif (is_string($data)) {
            $_data = $data;
        } else {
            var_export($data, true);
        }

        $fh = fopen($logFile, 'w');
        fwrite($fh, $_data);
        fwrite($fh, "\n");
        fclose($fh);
    }

    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Sets the container.
     *
     * @param ContainerInterface|null $container A ContainerInterface instance or null
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

}
