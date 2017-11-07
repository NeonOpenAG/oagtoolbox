<?php

namespace OagBundle\Controller;

use OagBundle\Entity\OagFile;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * @Template
 * @Route("/devel")
 */
class DevelController extends Controller
{

    /**
     * @Route("/")
     */
    public function indexAction()
    {
        return [];
    }

    /**
     * @Route("/oagFile/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function oagFileAction(OagFile $oagFile)
    {
        return ['oagFile' => $oagFile];
    }

    /**
     * @Route("/enhancementFile")
     */
    public function enhancementFileAction()
    {
        return [];
    }

}
