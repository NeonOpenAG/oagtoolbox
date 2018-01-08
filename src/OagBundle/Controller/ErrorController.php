<?php

namespace OagBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

/**
 * @Template
 * @Route("/error")
 */
class ErrorController extends Controller {

    /**
     * @Route("/download")
     */
    public function downloadAction() {
        return [];
    }

    /**
     * @Route("/delete")
     */
    public function deleteAction() {
        return [];
    }

    /**
     * @Route("/downloadFile")
     */
    public function downloadFileAction() {
        return [];
    }

    /**
     * @Route("/classifier")
     */
    public function classifierAction() {
        return [];
    }

    /**
     * @Route("/classifierSuggestion")
     */
    public function classifierSuggestionAction() {
        return [];
    }

    /**
     * @Route("/geocoder")
     */
    public function geocoderAction() {
        return [];
    }

    /**
     * @Route("/geocoderSuggestion")
     */
    public function geocoderSuggestionAction() {
        return [];
    }

    /**
     * @Route("/preview")
     */
    public function previewAction() {
        return [];
    }

    /**
     * @Route("/improveYourData")
     */
    public function improveYourDataAction() {
        return [];
    }

    /**
     * @Route("/activateFile")
     */
    public function activateFileAction() {
        return [];
    }

}
