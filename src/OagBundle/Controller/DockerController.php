<?php

namespace OagBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use OagBundle\Service\Docker;

/**
 * @Template
 * @Route("/docker")
 */
class DockerController extends Controller
{
    /**
     * @Route("/")
     */
    public function listAction()
    {
        $srvDocker = $this->get(Docker::class);
        $containers = $srvDocker->listContainers();
        
        $data = [
            'containers' => $containers,
            'json' => json_encode($containers, JSON_PRETTY_PRINT),
        ];
        return $data;
    }
}
