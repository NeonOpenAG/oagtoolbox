<?php

namespace OagBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use OagBundle\Service\Cove;
use Symfony\Component\HttpFoundation\Request;
use OagBundle\Entity\OagFile;
use OagBundle\Service\IATI;
use OagBundle\Service\OagFileService;
use OagBundle\Form\OagFileType;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class DefaultController extends Controller {

    /**
     * @Route("/")
     * @Template
     */
    public function indexAction(Request $request) {
        $repository = $this->getDoctrine()->getRepository(OagFile::class);
        $srvOagFile = $this->get(OagFileService::class);

        // Fetch IATI files.
        $iatidocs = $repository->findByFileType(OagFile::OAGFILE_IATI_DOCUMENT);

        // Fetch source documents
        $sourceDocs = $this->loadOagFileByType(OagFile::OAGFILE_IATI_SOURCE_DOCUMENT);

        // Fetch enhancing documents
        $enhancingDocs = $this->loadOagFileByType(OagFile::OAGFILE_IATI_ENHANCEMENT_DOCUMENT);

        $em = $this->getDoctrine()->getManager();
        $oagfile = new OagFile();
        $oagfile->setFileType(OagFile::OAGFILE_IATI_SOURCE_DOCUMENT);
        $sourceUploadForm = $this->createForm(OagFileType::class, $oagfile);
        $sourceUploadForm->add('Upload', SubmitType::class, array(
            'attr' => array('class' => 'submit'),
        ));

        // TODO Do something if the form is not valid
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

                return $this->redirect($this->generateUrl('oag_cove_oagfile', array('id' => $oagfile->getId())));
            }
        }

        $sourcedocs = $data = array( 
            'iatidocs' => $iatidocs,
            'sourceDocs' => $sourceDocs,
            'enhancingDocs' => $enhancingDocs,
            'source_upload_form' => $sourceUploadForm->createView(),
        );
        return $data;
    }
    
    /**
     * @Route("/delete/{ids}")
     */
    public function deleteAction($ids) {
        $idlist = explode('+', $ids);

        $uploadDir = $this->getParameter('oagfiles_directory');
        $xmldir = $this->getParameter('oagxml_directory');

        $repository = $this->getDoctrine()->getRepository(OagFile::class);
        foreach ($idlist as $id) {
            $oagfile = $repository->findOneBy(array('id' => $id));
            $file = $uploadDir . '/' . $oagfile->getDocumentName();
            $xml = $xmldir . '/' . $oagfile->getDocumentName();
            if (file_exists($file)) {
                unlink($file);
            }
            if (file_exists($xml)) {
                unlink($xml);
            }
        }
        return $this->redirectToRoute('oag_default_index');
    }



    /**
     * Load oag files by file type.
     */
    private function loadOagFileByType($type) {
        $repository = $this->getDoctrine()->getRepository(OagFile::class);
        $oagfiles = $repository->findByFileType($type);

        return $oagfiles;
    }

}
