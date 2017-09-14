<?php

namespace OagBundle\Controller;

use OagBundle\Entity\Change;
use OagBundle\Entity\OagFile;
use OagBundle\Entity\EnhancementFile;
use OagBundle\Form\EnhancementFileType;
use OagBundle\Form\OagFileType;
use OagBundle\Service\ChangeService;
use OagBundle\Service\Classifier;
use OagBundle\Service\Cove;
use OagBundle\Service\DPortal;
use OagBundle\Service\IATI;
use OagBundle\Service\OagFileService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Template
 */
class WireframeController extends Controller {

    /**
     * @Route("/gc/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function gcTest(OagFile $file) {
        $srvGeocoder = $this->get(\OagBundle\Service\Geocoder::class);
        dump($srvGeocoder->geocodeOagFile($file));
        return array();
    }

    /**
     * @Route("/")
     */
    public function uploadAction(Request $request) {
        $em = $this->getDoctrine()->getEntityManager();
        $srvCove = $this->get(Cove::class);
        $srvClassifier = $this->get(Classifier::class);

        $oagfile = new OagFile();
        $sourceUploadForm = $this->createForm(OagFileType::class, $oagfile);
        $sourceUploadForm->add('Upload', SubmitType::class, array(
            'attr' => array('class' => 'submit'),
        ));

	$sourceUploadForm->handleRequest($request);

	// TODO Check for too big files.
	if ($sourceUploadForm->isSubmitted() && $sourceUploadForm->isValid()) {
	    $tmpFile = $oagfile->getDocumentName();
	    $filename = $tmpFile->getClientOriginalName();

	    $tmpFile->move(
		$this->getParameter('oagfiles_directory'), $filename
	    );

	    $oagfile->setDocumentName($filename);
	    $oagfile->setUploadDate(new \DateTime('now'));
	    $em->persist($oagfile);
	    $em->flush();

            if (!$srvCove->validateOagFile($oagfile)) {
                // TODO CoVE failed
            }
            $srvClassifier->classifyOagFile($oagfile);

	    return $this->redirect($this->generateUrl('oag_wireframe_improveyourdata', array('id' => $oagfile->getId())));
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
        $sourceUploadForm = $this->createForm(OagFileType::class, $oagfile);
        $sourceUploadForm->add('Upload', SubmitType::class, array(
            'attr' => array('class' => 'submit'),
        ));
        $sourceUploadForm->handleRequest($request);

        // TODO Check for too big files.
        if ($sourceUploadForm->isSubmitted() && $sourceUploadForm->isValid()) {
            $tmpFile = $oagfile->getDocumentName();

            $filename = $tmpFile->getClientOriginalName();

            $tmpFile->move(
                $this->getParameter('oagfiles_directory'), $filename
            );

            $oagfile->setDocumentName($filename);
            $oagfile->setUploadDate(new \DateTime('now'));
            $em->persist($oagfile);
            $em->flush();

            if (!$srvCove->validateOagFile($oagfile)) {
                // TODO CoVE failed
            }
            $srvClassifier->classifyOagFile($oagfile);

	    return $this->redirect($this->generateUrl('oag_wireframe_improveyourdata', array('id' => $oagfile->getId())));
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
        return $this->redirect($this->generateUrl('oag_wireframe_upload'));
    }

    /**
     * @Route("/classifier/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function classifierAction(OagFile $file) {
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
    public function classifierSuggestionAction(Request $request, OagFile $file, $activityId) {
        $em = $this->getDoctrine()->getManager();
        $srvClassifier = $this->get(Classifier::class);
        $srvIATI = $this->get(IATI::class);
        $srvOagFile = $this->get(OagFileService::class);

        $root = $srvIATI->load($file);
        $activity = $srvIATI->getActivityById($root, $activityId);

        if (is_null($activity)) {
            // TODO throw a reasonable error
        }

        $currentTags = $srvIATI->getActivityTags($activity);

        // load all suggested tags
        $suggestedTags = $file->getSuggestedTags()->toArray();
        foreach ($file->getEnhancingDocuments() as $enhFile) {
            // if it is only relevant to another activity, ignore
            if ((!is_null($enhFile)) && ($enhFile->getIatiActivityId() !== $activityId)) continue;
            $suggestedTags = array_merge($suggestedTags, $enhFile->getSuggestedTags()->toArray());
        }

        // derive the actual tags from these suggested tags
        $classifierTags = array();
        foreach ($suggestedTags as $sugTag) {
            if (in_array($sugTag->getTag(), $classifierTags) || in_array($sugTag->getTag(), $currentTags)) {
                // no duplicates
                continue;
            }

            if ((!is_null($sugTag->getActivityId())) && $sugTag->getActivityId() !== $activityId) {
                // only those generic else specific to this activity
                continue;
            }

            $classifierTags[] = $sugTag->getTag();
        }
        $allTags = array_merge($currentTags, $classifierTags);

        // enhancement upload form
        $enhFile = new EnhancementFile();
        $enhUploadForm = $this->createForm(EnhancementFileType::class, $enhFile);
        $enhUploadForm->add('Upload', SubmitType::class, array(
            'attr' => array('class' => 'submit'),
        ));
        $enhUploadForm->handleRequest($request);
        if ($enhUploadForm->isSubmitted() && $enhUploadForm->isValid()) {
            $tmpFile = $enhFile->getDocumentName();
            $enhFile->setMimeType(mime_content_type($tmpFile->getPathName()));

            $filename = $tmpFile->getClientOriginalName();

            $tmpFile->move(
                $this->getParameter('oagfiles_directory'), $filename
            );

            $enhFile->setDocumentName($filename);
            $enhFile->setUploadDate(new \DateTime('now'));
            $enhFile->setIatiActivityId($activityId);
            $srvClassifier->classifyEnhancementFile($enhFile);

            $file->addEnhancingDocument($enhFile);       
            $em->persist($file);
            $em->flush();

            return $this->redirect($this->generateUrl('oag_wireframe_classifiersuggestion', array('id' => $file->getId(), 'activityId' => $activityId)));
        }

        $form = $this->createFormBuilder()
            ->add('tags', ChoiceType::class, array(
                'expanded' => true,
                'multiple' => true,
                'choices' => $allTags,
                'data' => $currentTags, // default current tags to ticked
                'choice_label' => function ($value, $key, $index) {
                    $desc = $value->getDescription();
                    $vocab = $value->getVocabulary();
                    return "$desc ($vocab)";
                }
            ))
            ->add('back', SubmitType::class)
            ->add('save', SubmitType::class)
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('back')->isClicked()) {
                return $this->redirect($this->generateUrl('oag_wireframe_classifier', array('id' => $file->getId())));
            }

            $editedTags = $form->getData()['tags'];

            // have any current tags been removed
            $toRemove = array();
            foreach ($currentTags as $currentTag) {
                if (!in_array($currentTag, $editedTags)) {
                    $srvIATI->removeActivityTag($activity, $currentTag);
                    $toRemove[] = $currentTag;
                }
            }

            // have any suggsted tags been added
            $toAdd = array();
            foreach ($classifierTags as $suggestedTag) {
                if (in_array($suggestedTag, $editedTags)) {
                    $srvIATI->addActivityTag($activity, $suggestedTag);
                    $toAdd[] = $suggestedTag;
                }
            }

            $resultXML = $srvIATI->toXML($root);
            $srvOagFile->setContents($file, $resultXML);

            $change = new Change();
            $change->setAddedTags($toAdd);
            $change->setRemovedTags($toRemove);
            $change->setFile($file);
            $change->setActivityId($activityId);
            $change->setTimestamp(new \DateTime('now'));
            $em->persist($change);
            $em->flush();

            return $this->redirect($this->generateUrl('oag_wireframe_classifier', array('id' => $file->getId())));
        }

        return array(
            'file' => $file,
            'activity' => $srvIATI->summariseActivityToArray($activity),
            'form' => $form->createView(),
            'enhancementUploadForm' => $enhUploadForm->createView()
        );
    }

    /**
     * @Route("/preview/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function previewAction(OagFile $file) {
        $srvDPortal = $this->get(DPortal::class);
        $srvDPortal->visualise($file);

        $uri = \str_replace(
            'SERVER_HOST', $_SERVER['HTTP_HOST'], $this->getParameter('oag')['dportal']['uri']
        );

        return array(
            'dPortalUri' => $uri,
            'file' => $file
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
        $srvOagFile = $this->get(OagFileService::class);

        return array(
            'file' => $file,
            'classified' => $srvOagFile->hasBeenClassified($file),
            'geocoded' => $srvOagFile->hasBeenGeocoded($file)
        );
    }

}
