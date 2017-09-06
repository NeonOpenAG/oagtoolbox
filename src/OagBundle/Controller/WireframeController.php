<?php

namespace OagBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 * @Route("/wireframe")
 * @Template
 */
class WireframeController extends Controller
{
    /**
     * @Route("/upload")
     */
    public function uploadAction()
    {
        return array();
    }

    /**
     * @Route("/classifier")
     */
    public function classifierAction()
    {
        return array();
    }

    /**
     * @Route("/classifierSuggestion")
     */
    public function classifierSuggestionAction()
    {
        return array();
    }

    /**
     * @Route("/geocoder")
     */
    public function geocoderAction()
    {
        return array();
    }

    /**
     * @Route("/geocoderSuggestion")
     */
    public function geocoderSuggestionAction()
    {
        return array();
    }

    /**
     * @Route("/improverYourData")
     */
    public function improverYourDataAction()
    {
        return array();
    }

}
