<?php

namespace OagBundle\Controller;

use OagBundle\Entity\OagFile;
use OagBundle\Form\OagFileType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

/**
 * @Route("/wireframe")
 * @Template
 */
class WireframeController extends Controller
 {

    /**
     * @Route("/")
     */
    public function indexAction() {
        return array();
    }

    /**
     * @Route("/uploadIati")
     */
    public function uploadIatiAction() {
        $oagfile = new OagFile();
        $oagfile->setFileType(OagFile::OAGFILE_IATI_SOURCE_DOCUMENT);
        $sourceUploadForm = $this->createForm(OagFileType::class, $oagfile);
        $sourceUploadForm->add('Upload', SubmitType::class, array(
            'attr' => array('class' => 'submit'),
        ));

        $data = array(
            'source_upload_form' => $sourceUploadForm->createView()
        );

        return $data;
    }

    /**
     * @Route("/uploadEnhancement")
     */
    public function uploadEnhancementAction() {
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
