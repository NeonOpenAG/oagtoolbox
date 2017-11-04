<?php

namespace OagBundle\EventListener;

use OagBundle\Service\Docker;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;

class StartupListener {

    private $container;
    private $request;

    public function onKernelRequest(GetResponseEvent $event) {
        if ($this->getContainer()->get('kernel')->getEnvironment() == "test") {
            // Skip this in the test env
            return;
        }

        $request = $this->getRequest()->getCurrentRequest();;
        $route = $request->attributes->get('_route');
        if ($route == 'oag_docker_list' || strpos($route, '_assetic') !== false || $route == '_wdt' || empty($route)) {
            // This is a status URL, so don't do the checks
            return;
        }
        $this->getContainer()->get('logger')->error('+++++ ' . $route . ' +++++');

        $srvDocker = $this->getContainer()->get(Docker::class);
        $_containers = $this->getContainer()->getParameter('docker_names');
        $images = $srvDocker->listImages();

        $containers = $srvDocker->listContainers();
        $this->getContainer()->get('logger')->info(json_encode($containers));

        foreach ($_containers as $container) {
            // Is the image available?
            $imagePulled = false;
            foreach ($images as $image) {
                $this->getContainer()->get('logger')->error('Checking ' . $container . ' against ' . $image);
                if (strpos($image, $container) !== false) {
                    $imagePulled = true;
                    break;
                }
            }
            if (!$imagePulled) {
                $this->getContainer()->get('logger')->error('The image for the ' . $container . ' not present');
                $router = $this->getContainer()->get('router');
                $url = $router->generate('oag_docker_list');
                $response = new RedirectResponse($url);
                $event->setResponse($response);
                return;
            }

            // Is the container running already?
            $name = 'openag_' . $container;
            $this->getContainer()->get('logger')->info($name);
            if (in_array($name, $containers)) {
                if ($containers['openag_' . $container]['status'] == 'running') {
                    $this->getContainer()->get('logger')->debug($name . ' is running.');
                    continue;
                }
                // If the container is created but not started, then note the ID.
                $id = $containers['openag_' . $container]['container_id'];
            }
            else {
                // Create the container if it's not created.
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

            // If an id is set then start that container.
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

    /**
     * @return RequestStack
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param RequestStack $request
     */
    public function setRequest(RequestStack $request)
    {
        $this->request = $request;
    }

}
