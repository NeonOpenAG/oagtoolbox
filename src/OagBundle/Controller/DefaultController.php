<?php

namespace OagBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use OagBundle\Service\Cove;
use Symfony\Component\HttpFoundation\Request;
use OagBundle\Entity\OagFile;
use OagBundle\Service\ActivityService;
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

                return $this->redirect($this->generateUrl('oag_oagfile_source', ['id' => $oagfile->getId()]));
            }
        }

        $sourcedocs = $data = [
            'iatidocs' => $iatidocs,
            'sourceDocs' => $sourceDocs,
            'enhancingDocs' => $enhancingDocs,
            'source_upload_form' => $sourceUploadForm->createView(),
        ];
        return $data;
    }

    /**
     * Load oag files by file type.
     */
    private function loadOagFileByType($type) {
        $repository = $this->getDoctrine()->getRepository(OagFile::class);
        $oagfiles = $repository->findByFileType($type);

        return $oagfiles;
    }

    ///////////////
    // OLD STUFF //
    ///////////////
    /**
     * @Route("/old")
     * @Template
     */
    public function indexOldAction() {

        // TODO - Can we amalgamate these two?
        $em = $this->getDoctrine()->getManager();
        $repository = $this->getDoctrine()->getRepository(OagFile::class);

        $srvOagFile = $this->get(OagFileService::class);

        // Fetch all files.
        $files = array();
        $oagfiles = $repository->findAll();

        // Ensure the Upload and XML directories exist.
        $uploadDir = $this->getParameter('oagfiles_directory');
        $xmldir = $this->getParameter('oagxml_directory');
        if (!is_dir($xmldir)) {
            mkdir($xmldir, 0755, true);
        }

        // Store oag file ids for the delete list/form
        $ids = array();

        foreach ($oagfiles as $oagfile) {
            $path = $uploadDir . '/' . $oagfile->getDocumentName();
            // Document removed from file system, remove from the DB.
            if (!file_exists($path)) {
                $em->remove($oagfile);
                continue;
            } else {
                $data = array();
                $data['file'] = $oagfile->getDocumentName();
                $data['mimetype'] = $oagfile->getMimeType();

                $filename = $srvOagFile->getXMLFileName($oagfile);
                $xmlfile = $xmldir . '/' . $oagfile->getDocumentName();
                if (file_exists($xmlfile)) {
                    $data['xml'] = $xmlfile;
                }

                $activies = $oagfile->getSectors();
                $data['acount'] = count($activies);

                $files[$oagfile->getId()] = $data;
                $ids['Delete ' . $oagfile->getId()] = $oagfile->getId();
            }

            $files[$oagfile->getId()] = $data;
            $ids['Delete ' . $oagfile->getId()] = $oagfile->getId();
        }
        // Flush the entitiy manager to commit delets.
        $em->flush();

        // Build the delete form.
        $defaultData = array();
        $target = $this->generateUrl('oag_default_confirmdelete');
        $formbuilder = $this->createFormBuilder(
            $defaultData, array('action' => $target)
        );
        $formbuilder->add('delete_list', ChoiceType::class, array(
            'choices' => $ids,
            'expanded' => true,
            'multiple' => true,
        ));

        return array(
            'json' => 'Some JSON',
            'status' => 'URI',
            'files' => $files,
            'form' => $formbuilder->getForm()->createView(),
        );
    }

    /**
     * @Route("/confirm_delete")
     * @Template
     */
    public function confirmDeleteAction(Request $request) {
        if ($request->isMethod('POST')) {
            $form = $this->createFormBuilder(null)->getForm();
            $form->handleRequest($request);

            $data = $form->getExtraData();

            if (count($data['delete_list']) == 0) {
                $this->addFlash(
                    'warn', 'No files where specified!'
                );
            }

            $files = [];

            $repository = $this->getDoctrine()->getRepository(OagFile::class);
            foreach ($data['delete_list'] as $id) {
                $oagfile = $repository->findOneBy(array('id' => $id));
                $files[$id] = $oagfile->getDocumentName();
            }

            return array(
                'files' => $files,
                'ids' => implode('+', array_keys($files)),
            );
        }

        return $this->redirectToRoute('app_index');
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
     * @Route("/upload")
     * @Template
     */
    public function uploadAction(Request $request) {

        $em = $this->getDoctrine()->getManager();
        $oagfile = new OagFile();
        $form = $this->createForm(OagFileType::class, $oagfile);
        $form->add('submit', SubmitType::class, array(
            'attr' => array('class' => 'submit'),
        ));

        // TODO Do something if the form is not valid
        if ($request) {
            $form->handleRequest($request);

            // TODO Check for too big files.
            if ($form->isSubmitted() && $form->isValid()) {
                $file = $oagfile->getDocumentName();
                $tmpFile = $oagfile->getDocumentName();
                $oagfile->setMimeType(mime_content_type($tmpFile->getPathname()));

                $filename = $file->getClientOriginalName();

                $file->move(
                    $this->getParameter('oagfiles_directory'), $filename
                );

                $oagfile->setDocumentName($filename);
                $em->persist($oagfile);
                $em->flush();

                return $this->redirect($this->generateUrl('oag_default_index'));
            }
        }

        return array(
            'form' => $form->createView(),
        );
    }

    /**
     * @Route("/activities")
     * @Template
     *
     * TODO - link to a file in the database instead of using fixtures
     */
    public function activitiesAction() {
        $srvActivity = $this->get(ActivityService::class);
        $root = $srvActivity->parseXML($srvActivity->getFixtureData());
        $simple = $srvActivity->summariseToArray($root);

        $files = array();
        $files['non-fixture file name goes here'] = $simple;

        return array(
            'files' => $files
        );
    }

}
