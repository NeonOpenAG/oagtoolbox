<?php

namespace OagBundle\Controller;

use OagBundle\Entity\Change;
use OagBundle\Entity\OagFile;
use OagBundle\Form\OagFileType;
use OagBundle\Service\ChangeService;
use OagBundle\Service\DPortal;
use OagBundle\Service\IATI;
use OagBundle\Service\OagFileService;
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
                $oagfile->setUploadDate(new \DateTime('now'));
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
     * @Route("/download/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function downloadAction(Request $request, OagFile $file) {
        $em = $this->getDoctrine()->getManager();
        $fileRepo = $this->getDoctrine()->getRepository(OagFile::class);
        $srvOagFile = $this->get(OagFileService::class);

        $oagfile = new OagFile();
        $oagfile->setFileType(OagFile::OAGFILE_IATI_SOURCE_DOCUMENT);
        $sourceUploadForm = $this->createForm(OagFileType::class, $oagfile);
        $sourceUploadForm->add('Upload', SubmitType::class, array(
            'attr' => array('class' => 'submit'),
        ));
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
            $oagfile->setUploadDate(new \DateTime('now'));
            $em->persist($oagfile);
            $em->flush();

            return $this->redirect($this->generateUrl('oag_cove_oagfile', array('id' => $oagfile->getId())));
        }

        return array(
            'file' => $file,
            'otherFiles' => $fileRepo->findBy(array()), // TODO filter to just IATI files
            'uploadForm' => $sourceUploadForm->createView(),
            'srvOagFile' => $srvOagFile
        );
    }

    /**
     * Download an IATI file.
     *
     * @Route("/downloadFile/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function downloadFileAction(Request $request, OagFile $file) {
        $srvOagFile = $this->get(OagFileService::class);

        return $this->file($srvOagFile->getPath($file));
    }

    /**
     * Delete an IATI file.
     *
     * @Route("/deleteFile/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function deleteFileAction(Request $request, OagFile $file) {
        // TODO implement
        return $this->redirect($this->generateUrl('oag_wireframe_index', array('id' => $file->getId())));
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
     * @Route("/preview/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function previewAction(OagFile $file) {
        if (!$file->hasFileType(OagFile::OAGFILE_IATI_DOCUMENT)) {
            // TODO throw a reasonable error
        }

        $srvDPortal = $this->get(DPortal::class);
        $srvDPortal->visualise($file);

        return array(
            'dPortalUri' => $this->getParameter('oag')['dportal']['uri']
        );
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

        $srvOagFile = $this->get(OagFileService::class);

        return array(
            'file' => $file,
            'classified' => $srvOagFile->hasBeenClassified($file),
            'geocoded' => $srvOagFile->hasBeenGeocoded($file)
        );
    }

}
