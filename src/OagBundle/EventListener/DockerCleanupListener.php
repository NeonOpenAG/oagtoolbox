<?php

namespace OagBundle\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class DockerCleanupListener {

    public function onKernelTerminate(PostResponseEvent $event) {
        exec('docker rm $(docker ps --filter=status=exited --filter=status=created -q)');
        exec('docker rmi $(docker images -a --filter=dangling=true -q)');
    }

}
