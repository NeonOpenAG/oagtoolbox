<?php

namespace OagBundle\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Docker\Docker;                   
use Docker\API\Model\ContainerConfig;

class StartupListener {

    private $container;

    public function onKernelRequest(GetResponseEvent $event) {
        $this->getContainer()->get('logger')->debug(
            'Here'
        );
        
    }

    /**
     * Sets the container.
     *
     * @param ContainerInterface|null $container A ContainerInterface instance or null
     */
    public function setContainer(ContainerInterface $container = null) {
        $this->container = $container;
    }

    public function getContainer() {
        return $this->container;
    }

}
