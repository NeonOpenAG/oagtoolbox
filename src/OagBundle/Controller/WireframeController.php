<?php

namespace OagBundle\Controller;

use OagBundle\Entity\Change;
use OagBundle\Entity\OagFile;
use OagBundle\Form\OagFileType;
use OagBundle\Service\ChangeService;
use OagBundle\Service\IATI;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/wireframe")
 * @Template
 */
class WireframeController extends Controller {

    /**
     * @Route("/")
     */
    public function indexAction() {
        return array();
    }

    /**
     * @Route("/upload")
     */
    public function uploadAction(Request $request) {
        $em = $this->getDoctrine()->getEntityManager();

        $oagfile = new OagFile();
        $oagfile->setFileType(OagFile::OAGFILE_IATI_SOURCE_DOCUMENT);
        $sourceUploadForm = $this->createForm(OagFileType::class, $oagfile);
        $sourceUploadForm->add('Upload', SubmitType::class, array(
            'attr' => array('class' => 'submit'),
        ));

        if ($request) {
            $sourceUploadForm->handleRequest($request);

            // TODO Check for too big files.
            if ($sourceUploadForm->isSubmitted() && $sourceUploadForm->isValid()) {
                $tmpFile = $oagfile->getDocumentName();
                $oagfile->setMimeType(mime_content_type($tmpFile->getPathname()));

                $filename = $tmpFile->getClientOriginalName();

                $tmpFile->move(
                    $this->getParameter('oagfiles_directory'), $filename
                );

                $oagfile->setDocumentName($filename);
                $em->persist($oagfile);
                $em->flush();

                return $this->redirect($this->generateUrl('oag_wireframe_improveyourdata', array('id' => $oagfile->getId())));
            }
        }

        $data = array(
            'source_upload_form' => $sourceUploadForm->createView()
        );

        return $data;
    }

    /**
     * @Route("/classifier/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function classifierAction(OagFile $file) {
        if (!$file->hasFileType(OagFile::OAGFILE_IATI_DOCUMENT)) {
            // TODO throw a reasonable error
        }

        $srvIATI = $this->get(IATI::class);
        $root = $srvIATI->load($file);

        return array(
            'file' => $file,
            'activities' => $srvIATI->summariseToArray($root)
        );
    }

    /**
     * @Route("/classifier/{id}/{activityId}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function classifierSuggestionAction(OagFile $file, $activityId) {
        if (!$file->hasFileType(OagFile::OAGFILE_IATI_DOCUMENT)) {
            // TODO throw a reasonable error
        }

        return array();
    }

    /**
     * @Route("/geocoder")
     */
    public function geocoderAction() {
        return array();
    }

    /**
     * @Route("/geocoderSuggestion")
     */
    public function geocoderSuggestionAction() {
        return array();
    }

    /**
     * @Route("/improveYourData/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function improveYourDataAction(OagFile $file) {
        if (!$file->hasFileType(OagFile::OAGFILE_IATI_DOCUMENT)) {
            // TODO throw a reasonable error
        }

        $srvChange = $this->get(ChangeService::class);
        $changeRepo = $this->getDoctrine()->getRepository(Change::class);

        $changes = $changeRepo->findBy(array( 'file' => $file ));
        $flattened = $srvChange->flatten($changes);

        $classified = count($flattened->getAddedTags()) > 0 || count($flattened->getRemovedTags()) > 0;

        // TODO geocoder modifications are not implemented yet
        //$geocoded = count($flattened->getAddedTags()) > 0 || count($flattened->getRemovedTags()) > 0;
        $geocoded = false;

        return array(
            'file' => $file,
            'classified' => $classified,
            'geocoded' => $geocoded
        );
    }

}
