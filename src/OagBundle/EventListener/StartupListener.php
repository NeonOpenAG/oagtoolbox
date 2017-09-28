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
        $containers = $srvDocker->listContainers();
        
        $_containers = ['cove:live', 'dportal:live', 'nerserver:live', 'geocoder:live'];
        
        foreach ($_containers as $container) {
            $name = 'openag_' . $container;
            if (array_key_exists($name, $containers)) {
                if ($containers['openag_' . $container]['status'] == 'running') {
                    continue;
                }
                $id = $containers['openag_' . $container]['container_id'];
            }
            else {
                // This doesn't work I can't gert the return value back out
                // $func = 'create' . ucfirst($container);
                // $container = $srvDocker->$func;
                // call_user_func(array($srvDocker, $func), $_container);
                switch ($container) {
                    case 'cove':
                        $_container = $srvDocker->createCove();
                        break;
                    case 'dportal':
                        $_container = $srvDocker->createDportal();
                        break;
                    case 'nerserver':
                        $_container = $srvDocker->createNerserver();
                        break;
                    case 'geocoder':
                        $_container = $srvDocker->createGeocode();
                        break;
                }
                $id = $_container['Id'] ?? false;
            }

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
