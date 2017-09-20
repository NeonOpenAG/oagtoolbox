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
        $response = $srvDocker->listContainers();
        $json = preg_split("#\n\s*\n#Uis", $response);
        $data = json_decode($json[1], TRUE);
        return array('json' => json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * @Route("/start")
     */
    public function startAction()
    {
        return $this->render('OagBundle:Docker:start.html.twig', array(
            // ...
        ));
    }

    /**
     * @Route("/stop")
     */
    public function stopAction()
    {
        return $this->render('OagBundle:Docker:stop.html.twig', array(
            // ...
        ));
    }

}
