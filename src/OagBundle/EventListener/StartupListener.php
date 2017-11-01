<?php

namespace OagBundle\EventListener;

use OagBundle\Service\Docker;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class StartupListener {

    private $container;

    public function onKernelRequest(GetResponseEvent $event) {
        if ($this->getContainer()->get('kernel')->getEnvironment() == "test") {
            // Skip this in the test env
            return;
        }
        $srvDocker = $this->getContainer()->get(Docker::class);
        // $containers = $srvDocker->fetchImages();
        $containers = $srvDocker->listContainers();
        $this->getContainer()->get('logger')->info(json_encode($containers));

        $_containers = $this->getContainer()->getParameter('docker_names');

        foreach ($_containers as $container) {
            $name = 'openag_' . $container;
            $this->getContainer()->get('logger')->info($name);
            if (array_key_exists($name, $containers)) {
                if ($containers['openag_' . $container]['status'] == 'running') {
                    $this->getContainer()->get('logger')->debug($name . ' is running.');
                    continue;
                }
                $id = $containers['openag_' . $container]['container_id'];
            }
            else {
                $this->getContainer()->get('logger')->info($container . ' not started');
                // This doesn't work I can't gert the return value back out
                // $func = 'create' . ucfirst($container);
                // $container = $srvDocker->$func;
                // call_user_func(array($srvDocker, $func), $_container);
                switch ($container) {
                    case 'cove:live':
                        $_container = $srvDocker->createCove();
                        break;
                    case 'dportal:live':
                        $_container = $srvDocker->createDportal();
                        break;
                    case 'nerserver:live':
                        $_container = $srvDocker->createNerserver();
                        break;
                    case 'geocoder:live':
                        $_container = $srvDocker->createGeocode();
                        break;
                }
                $id = $_container['Id'] ?? false;
            }

            $this->getContainer()->get('logger')->info('ID: ' . $id);
            if ($id) {
                $srvDocker->startContainer($id);
            }
        }
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
