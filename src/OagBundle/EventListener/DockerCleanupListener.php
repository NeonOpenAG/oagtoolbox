<?php

namespace OagBundle\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DockerCleanupListener {

    private $container;

    public function onKernelTerminate(PostResponseEvent $event) {
        $this->getContainer()->get('logger')->debug(
            exec('docker rm $(docker ps --filter=status=exited --filter=status=created -q)')
        );
        $this->getContainer()->get('logger')->debug(
            exec('docker rmi $(docker images -a --filter=dangling=true -q)')
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
