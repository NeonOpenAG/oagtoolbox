<?php

namespace OagBundle\Controller;

use OagBundle\Entity\Change;
use OagBundle\Entity\EnhancementFile;
use OagBundle\Entity\OagFile;
use OagBundle\Entity\RulesetError;
use OagBundle\Form\EnhancementFileType;
use OagBundle\Form\OagFileType;
use OagBundle\Service\Classifier;
use OagBundle\Service\Cove;
use OagBundle\Service\DPortal;
use OagBundle\Service\Geocoder;
use OagBundle\Service\GeoJson;
use OagBundle\Service\IATI;
use OagBundle\Service\OagFileService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @Template
 */
class WireframeController extends Controller
{

    /**
     * @Route("/")
     */
    public function uploadAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $srvCove = $this->get(Cove::class);
        $srvClassifier = $this->get(Classifier::class);
        $srvGeocoder = $this->get(Geocoder::class);
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

            $isValid = $srvCove->validateOagFile($oagfile);
            if (!$isValid) {
                return $this->redirect($this->generateUrl('oag_wireframe_upload'));
            }

            // Kick off these as background commands, they'll run in the background.
            $root = $this->get('kernel')->getRootDir();
            $console = $root . '/../bin/console ';
            exec($console . 'oag:classify ' . $oagfile->getId() . ' > /dev/null &');
            exec($console . 'oag:geocode ' . $oagfile->getId() . ' > /dev/null &');

            return $this->redirect($this->generateUrl('oag_wireframe_improveyourdata', array('id' => $oagfile->getId())));
        }

        $data = array(
            'source_upload_form' => $sourceUploadForm->createView()
        );

        if (!is_null($srvOagFile->getMostRecent())) {
            $data['file'] = $srvOagFile->getMostRecent();
        }

//        $flashbag = $this->get('session')->getFlashBag();
//        $all = $flashbag->peekAll();
//        $flashbag->clear();
//
//        $data['errors'] = $all;
//        $data['errors']['error'] = [];
//        $data['errors']['error'][] = '<p>Line one</p><p>Line two</p><p>Line three</p>';

        return $data;
    }

    /**
     * @Route("/download/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function downloadAction(Request $request, OagFile $file)
    {
        $em = $this->getDoctrine()->getManager();
        $fileRepo = $this->getDoctrine()->getRepository(OagFile::class);
        $srvClassifier = $this->get(Classifier::class);
        $srvCove = $this->get(Cove::class);
        $srvGeocoder = $this->get(Geocoder::class);
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
            $srvGeocoder->geocodeOagFile($oagfile);

            return $this->redirect($this->generateUrl('oag_wireframe_improveyourdata', array('id' => $oagfile->getId())));
        }

        $otherFiles = [];
        $allFiles = $fileRepo->createQueryBuilder('f')
            ->where('f.documentName LIKE :xml')
            ->setParameter('xml', '%.xml')
            ->getQuery()
            ->execute();

        return array(
            'file' => $file,
            'otherFiles' => $allFiles,
            'uploadForm' => $sourceUploadForm->createView(),
            'srvOagFile' => $srvOagFile
        );
    }

    /**
     * Delete an IATI file (from the download page).
     *
     * @Route("/download/{previous_id}/deleteFile/{to_delete_id}")
     * @ParamConverter("previous", class="OagBundle:OagFile", options={"id" = "previous_id"})
     * @ParamConverter("toDelete", class="OagBundle:OagFile", options={"id" = "to_delete_id"})
     */
    public function deleteFileAction(Request $request, OagFile $previous, OagFile $toDelete)
    {
        $em = $this->getDoctrine()->getManager();
        $oagFileRepo = $this->getDoctrine()->getRepository(OagFile::class);
        $srvOagFile = $this->get(OagFileService::class);

        $em->remove($toDelete);
        $em->flush();

        if (count($oagFileRepo->findAll()) === 0) {
            // they deleted the last file, redirect to upload
            return $this->redirect($this->generateUrl('oag_wireframe_upload'));
        } else if ($previous->getId() === $toDelete->getId()) {
            // they deleted the file of the page they're on, redirect to the most recent file
            $latest = $srvOagFile->getMostRecent();
            return $this->redirect($this->generateUrl('oag_wireframe_download', array('id' => $latest->getId())));
        }

        // they deleted another file
        return $this->redirect($this->generateUrl('oag_wireframe_download', array('id' => $previous->getId())));
    }

    /**
     * Download an IATI file.
     *
     * @Route("/downloadFile/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function downloadFileAction(Request $request, OagFile $file)
    {
        $srvOagFile = $this->get(OagFileService::class);

        return $this->file($srvOagFile->getPath($file));
    }

    /**
     * @Route("/classifier/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function classifierAction(OagFile $file)
    {
        $srvIATI = $this->get(IATI::class);
        $root = $srvIATI->load($file);
        $activities = $srvIATI->summariseToArray($root);

        if (count($activities) == 1) {
          return $this->redirectToRoute('oag_wireframe_classifiersuggestion', [
            'id' => $file->getId(),
            'activityId' => $activities[0]['id'],
          ]);
        }

        // work out which activities have suggested locations
        $haveSuggested = array();
        $existingTags = array();
        foreach ($activities as $activity) {
            $suggestedTags = array();

            // suggested on the OagFile for that activity
            foreach ($file->getSuggestedTags()->toArray() as $generic) {
                if (is_null($generic->getActivityId()) || $generic->getActivityId() === $activity['id']) {
                    $suggestedTags[] = $generic;
                }
            }

            // suggested in an EnhancementFile for that activity
            foreach ($file->getEnhancingDocuments() as $enhFile) {
                // if it is only relevant to another activity, ignore
                if ((!is_null($enhFile->getIatiActivityId())) && ($enhFile->getIatiActivityId() !== $activity['id'])) continue;
                $suggestedTags = array_merge($suggestedTags, $enhFile->getSuggestedTags()->toArray());
            }
            
            $_suggestedTags = array_unique($suggestedTags);

            // has at least one suggested tag
            $haveSuggested[$activity['id']] = count($_suggestedTags);
            $existingTags[$activity['id']] = count($activity['tags']);
        }
        
        usort($activities, function ($one, $two) {
            global $haveSuggested, $existingTags;
            
            $oneHs = $haveSuggested[$one['id']];
            $oneEt = $existingTags[$one['id']];
            $twoHs = $haveSuggested[$two['id']];
            $twoEt = $existingTags[$two['id']];
            if ($oneHs < $twoHs) {
                dump("$oneHs < $twoHs");
                return true;
            }
            elseif ($oneHs > $twoHs) {
                dump("$oneHs > $twoHs");
                return false;
            }
            // HS is the same
            if ($oneEt < $twoEt) {
                dump("$oneEt < $twoEt");
                return true;
            }
            elseif ($oneEt > $twoEt) {
                dump("$oneEt > $twoEt");
                return false;
            }
            // everything is the same
            return $one['id'] < $two['id'];
        });

        return array(
            'file' => $file,
            'activities' => $activities,
            'haveSuggested' => $haveSuggested,
            'existingTags' => $existingTags,
        );
    }

    /**
     * @Route("/classifier/{id}/{activityId}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function classifierSuggestionAction(Request $request, OagFile $file, $activityId)
    {
        $em = $this->getDoctrine()->getManager();
        $srvClassifier = $this->get(Classifier::class);
        $srvIATI = $this->get(IATI::class);
        $srvOagFile = $this->get(OagFileService::class);

        $root = $srvIATI->load($file);
        $activity = $srvIATI->getActivityById($root, $activityId);

        if (is_null($activity)) {
            // TODO throw a reasonable error
        }

        $this->get('logger')->debug('Activity count = ' . $activity->count());

        // enhancement file upload form
        $enhFile = new EnhancementFile();
        $enhUploadForm = $this->createForm(EnhancementFileType::class, $enhFile);
        $enhUploadForm->add('Upload', SubmitType::class, array(
            'attr' => array('class' => 'submit')
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
            $srvClassifier->classifyEnhancementFile($enhFile, $activityId);

            $file->addEnhancingDocument($enhFile);
            $em->persist($file);
            $em->flush();

            return $this->redirect($this->generateUrl('oag_wireframe_classifiersuggestion', array('id' => $file->getId(), 'activityId' => $activityId)));
        }

        // paste text form
        $pasteTextForm = $this->createFormBuilder()
            ->add('text', TextareaType::class, array(
                'attr' => array('placeholder' => 'Paste text here')
            ))
            ->add('Suggest more codes', SubmitType::class)
            ->getForm();
        $pasteTextForm->handleRequest($request);
        if ($pasteTextForm->isSubmitted() && $pasteTextForm->isValid()) {
            $data = $pasteTextForm->getData();
            $srvClassifier->classifyOagFileFromText($file, $data['text'], $activityId);
            return $this->redirect($this->generateUrl('oag_wireframe_classifiersuggestion', array('id' => $file->getId(), 'activityId' => $activityId)));
        }

        $currentTags = $srvIATI->getActivityTags($activity);

        // load all suggested tags
        $suggestedTags = $file->getSuggestedTags()->toArray();
        foreach ($file->getEnhancingDocuments() as $enhFile) {
            // if it is only relevant to another activity, ignore
            if ((!is_null($enhFile->getIatiActivityId())) && ($enhFile->getIatiActivityId() !== $activityId)) continue;
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

        // tags add/remove form
        $form = $this->createFormBuilder()
            ->add('tags', ChoiceType::class, array(
                'expanded' => true,
                'multiple' => true,
                'choices' => $allTags,
                'data' => $currentTags, // default current tags to ticked
                'choice_label' => function ($value, $key, $index) {
                    $desc = $value->getDescription();
                    return "$desc";
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
            'enhancementUploadForm' => $enhUploadForm->createView(),
            'pasteTextForm' => $pasteTextForm->createView()
        );
    }

    /**
     * @Route("/geocoder/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function geocoderAction(OagFile $file)
    {
        $srvIATI = $this->get(IATI::class);
        $root = $srvIATI->load($file);
        $activities = $srvIATI->summariseToArray($root);

        if (count($activities) == 1) {
          return $this->redirectToRoute('oag_wireframe_geocodersuggestion', [
            'id' => $file->getId(),
            'activityId' => $activities[0]['id'],
          ]);
        }

        // work out which activities have suggested locations
        $haveSuggested = array();
        $existingTags = array();
        foreach ($activities as $activity) {
            $geocoderGeolocs = array();

            // suggested on the OagFile for that activity
            foreach ($file->getGeolocations()->toArray() as $generic) {
                if (is_null($generic->getIatiActivityId()) || $generic->getIatiActivityId() === $activity['id']) {
                    $geocoderGeolocs[] = $generic;
                }
            }

            // suggested in an EnhancementFile for that activity
            foreach ($file->getEnhancingDocuments() as $enhFile) {
                // if it is only relevant to another activity, ignore
                if ((!is_null($enhFile->getIatiActivityId())) && ($enhFile->getIatiActivityId() !== $activity['id'])) continue;
                $geocoderGeolocs = array_merge($geocoderGeolocs, $enhFile->getGeolocations()->toArray());
            }

            // has at least one suggested location
            $haveSuggested[$activity['id']] = count($geocoderGeolocs);
            $existingTags[$activity['id']] = count($activity['locations']);
        }

        return array(
            'file' => $file,
            'activities' => $activities,
            'haveSuggested' => $haveSuggested,
            'existingTags' => $existingTags,
        );
    }

    /**
     * @Route("/geocoder2/{id}/{activityId}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function geocoder2SuggestionAction(Request $request, OagFile $file, $activityId)
    {
        $em = $this->getDoctrine()->getManager();
        $srvGeocoder = $this->get(Geocoder::class);
        $srvGeoJson = $this->get(GeoJson::class);
        $srvIATI = $this->get(IATI::class);
        $srvOagFile = $this->get(OagFileService::class);

        $root = $srvIATI->load($file);
        $activity = $srvIATI->getActivityById($root, $activityId);

        $currentLocations = $srvIATI->getActivityLocations($activity);
        $suggestedLocations = $file->getGeolocations()->toArray();

        $data = [
            "activityid" => $activityId,
        ];

        foreach ($currentLocations as $loc) {
          $data['current'][] = print_r($loc, true);
        }

        foreach ($suggestedLocations as $loc) {
          $data['suggested'][] = print_r($loc, true);
        }

        return $data;
    }

    /**
     * @Route("/geocoder/{id}/{activityId}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function geocoderSuggestionAction(Request $request, OagFile $file, $activityId)
    {
        $em = $this->getDoctrine()->getManager();
        $srvGeocoder = $this->get(Geocoder::class);
        $srvGeoJson = $this->get(GeoJson::class);
        $srvIATI = $this->get(IATI::class);
        $srvOagFile = $this->get(OagFileService::class);

        $root = $srvIATI->load($file);
        $activity = $srvIATI->getActivityById($root, $activityId);

        if (is_null($activity)) {
            // TODO throw a reasonable error
        }

        // get these but only to display them, not to add/remove them as with the classifier
        $currentLocations = $srvIATI->getActivityLocations($activity);
        $currentLocationsMaps = array();
        foreach ($currentLocations as $index => $curLoc) {
            if (array_key_exists('point', $curLoc)) {
                $pos = $curLoc['point']['pos'];
                $feature = $srvGeoJson->featureFromCoords($pos[1], $pos[0]);
                $featureColl = $srvGeoJson->featureCollection(array($feature));
                $currentLocationsMaps[$index] = json_encode($featureColl, JSON_HEX_APOS + JSON_HEX_TAG + JSON_HEX_AMP + JSON_HEX_QUOT);
            }
        }

        // load all suggested tags
        $geocoderGeolocs[] = array();

        // suggested on the OagFile for that activity
        foreach ($file->getGeolocations()->toArray() as $generic) {
            if (is_null($generic->getIatiActivityId()) || $generic->getIatiActivityId() === $activityId) {
                $geocoderGeolocs[] = $generic;
            }
        }

        // suggested in an EnhancementFile for that activity
        foreach ($file->getEnhancingDocuments() as $enhFile) {
            // if it is only relevant to another activity, ignore
            if ((!is_null($enhFile->getIatiActivityId())) && ($enhFile->getIatiActivityId() !== $activityId)) continue;
            $geocoderGeolocs = array_merge($geocoderGeolocs, $enhFile->getGeolocations()->toArray());
        }

        // no duplicates please
        $geocoderGeolocs = array_unique($geocoderGeolocs, SORT_REGULAR);

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
            $srvGeocoder->geocodeEnhancementFile($enhFile);

            $file->addEnhancingDocument($enhFile);
            $em->persist($file);
            $em->flush();

            return $this->redirect($this->generateUrl('oag_wireframe_geocodersuggestion', array('id' => $file->getId(), 'activityId' => $activityId)));
        }

        // paste text form
        $countryCode = $srvIATI->getActivityCountryCode($activity);
        $pasteTextForm = $this->createFormBuilder()
            ->add('country', ChoiceType::class, array(
                'choices' => array_flip($this->getCountryList()),
                'label' => 'Country',
                'data' => $countryCode,
            ))
            ->add('text', TextareaType::class, array(
                'attr' => array('placeholder' => 'Copy and paste text')
            ))
            ->add('read', SubmitType::class)
            ->getForm();
        $pasteTextForm->handleRequest($request);
        if ($pasteTextForm->isSubmitted() && $pasteTextForm->isValid()) {
            $data = $pasteTextForm->getData();
            $srvGeocoder->geocodeOagFileFromText($file, $data['text'], $activityId, $data['country']);
            return $this->redirect($this->generateUrl('oag_wireframe_geocodersuggestion', array('id' => $file->getId(), 'activityId' => $activityId)));
        }

        // geocoder add/remove form
        $form = $this->createFormBuilder()
            ->add('tags', ChoiceType::class, array(
                'expanded' => true,
                'multiple' => true,
                'choices' => $geocoderGeolocs,
                'data' => array(),
                'choice_label' => function ($value, $key, $index) {
                    $name = $value->getName() . ' [' . $value->getFeatureDesignation() . ']';
                    return "$name";
                },
                'choice_attr' => function ($value, $key, $index) use ($srvGeoJson) {
                    $feature = $srvGeoJson->featureFromGeoloc($value);
                    $featureColl = $srvGeoJson->featureCollection(array($feature));
                    return array(
                        'data-geojson' => json_encode($featureColl, JSON_HEX_APOS + JSON_HEX_TAG + JSON_HEX_AMP + JSON_HEX_QUOT)
                    );
                }
            ))
            ->add('back', SubmitType::class)
            ->add('save', SubmitType::class)
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('back')->isClicked()) {
                return $this->redirect($this->generateUrl('oag_wireframe_geocoder', array('id' => $file->getId())));
            }

            $editedTags = $form->getData()['tags'];

            // tags to add
            $toAdd = array();
            foreach ($geocoderGeolocs as $suggestedGeoloc) {
                if (in_array($suggestedGeoloc, $editedTags)) {
                    $srvIATI->addActivityGeolocation($activity, $suggestedGeoloc);
                    $toAdd[] = $suggestedGeoloc;
                }
            }

            $resultXML = $srvIATI->toXML($root);
            $srvOagFile->setContents($file, $resultXML);

            $change = new Change();
            $change->setAddedGeolocs($toAdd);
            $change->setFile($file);
            $change->setActivityId($activityId);
            $change->setTimestamp(new \DateTime('now'));
            $em->persist($change);
            $em->flush();

            return $this->redirect($this->generateUrl('oag_wireframe_geocoder', array('id' => $file->getId())));
        }

        return array(
            'file' => $file,
            'activity' => $srvIATI->summariseActivityToArray($activity),
            'form' => $form->createView(),
            'currentLocations' => $currentLocations,
            'currentLocationsMaps' => $currentLocationsMaps,
            'enhancementUploadForm' => $enhUploadForm->createView(),
            'pasteTextForm' => $pasteTextForm->createView()
        );
    }

    public function getCountryList()
    {
        $countries = array
        (
            'AF' => 'Afghanistan',
            'AX' => 'Aland Islands',
            'AL' => 'Albania',
            'DZ' => 'Algeria',
            'AS' => 'American Samoa',
            'AD' => 'Andorra',
            'AO' => 'Angola',
            'AI' => 'Anguilla',
            'AQ' => 'Antarctica',
            'AG' => 'Antigua And Barbuda',
            'AR' => 'Argentina',
            'AM' => 'Armenia',
            'AW' => 'Aruba',
            'AU' => 'Australia',
            'AT' => 'Austria',
            'AZ' => 'Azerbaijan',
            'BS' => 'Bahamas',
            'BH' => 'Bahrain',
            'BD' => 'Bangladesh',
            'BB' => 'Barbados',
            'BY' => 'Belarus',
            'BE' => 'Belgium',
            'BZ' => 'Belize',
            'BJ' => 'Benin',
            'BM' => 'Bermuda',
            'BT' => 'Bhutan',
            'BO' => 'Bolivia',
            'BA' => 'Bosnia And Herzegovina',
            'BW' => 'Botswana',
            'BV' => 'Bouvet Island',
            'BR' => 'Brazil',
            'IO' => 'British Indian Ocean Territory',
            'BN' => 'Brunei Darussalam',
            'BG' => 'Bulgaria',
            'BF' => 'Burkina Faso',
            'BI' => 'Burundi',
            'KH' => 'Cambodia',
            'CM' => 'Cameroon',
            'CA' => 'Canada',
            'CV' => 'Cape Verde',
            'KY' => 'Cayman Islands',
            'CF' => 'Central African Republic',
            'TD' => 'Chad',
            'CL' => 'Chile',
            'CN' => 'China',
            'CX' => 'Christmas Island',
            'CC' => 'Cocos (Keeling) Islands',
            'CO' => 'Colombia',
            'KM' => 'Comoros',
            'CG' => 'Congo',
            'CD' => 'Congo, Democratic Republic',
            'CK' => 'Cook Islands',
            'CR' => 'Costa Rica',
            'CI' => 'Cote D\'Ivoire',
            'HR' => 'Croatia',
            'CU' => 'Cuba',
            'CY' => 'Cyprus',
            'CZ' => 'Czech Republic',
            'DK' => 'Denmark',
            'DJ' => 'Djibouti',
            'DM' => 'Dominica',
            'DO' => 'Dominican Republic',
            'EC' => 'Ecuador',
            'EG' => 'Egypt',
            'SV' => 'El Salvador',
            'GQ' => 'Equatorial Guinea',
            'ER' => 'Eritrea',
            'EE' => 'Estonia',
            'ET' => 'Ethiopia',
            'FK' => 'Falkland Islands (Malvinas)',
            'FO' => 'Faroe Islands',
            'FJ' => 'Fiji',
            'FI' => 'Finland',
            'FR' => 'France',
            'GF' => 'French Guiana',
            'PF' => 'French Polynesia',
            'TF' => 'French Southern Territories',
            'GA' => 'Gabon',
            'GM' => 'Gambia',
            'GE' => 'Georgia',
            'DE' => 'Germany',
            'GH' => 'Ghana',
            'GI' => 'Gibraltar',
            'GR' => 'Greece',
            'GL' => 'Greenland',
            'GD' => 'Grenada',
            'GP' => 'Guadeloupe',
            'GU' => 'Guam',
            'GT' => 'Guatemala',
            'GG' => 'Guernsey',
            'GN' => 'Guinea',
            'GW' => 'Guinea-Bissau',
            'GY' => 'Guyana',
            'HT' => 'Haiti',
            'HM' => 'Heard Island & Mcdonald Islands',
            'VA' => 'Holy See (Vatican City State)',
            'HN' => 'Honduras',
            'HK' => 'Hong Kong',
            'HU' => 'Hungary',
            'IS' => 'Iceland',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'IR' => 'Iran, Islamic Republic Of',
            'IQ' => 'Iraq',
            'IE' => 'Ireland',
            'IM' => 'Isle Of Man',
            'IL' => 'Israel',
            'IT' => 'Italy',
            'JM' => 'Jamaica',
            'JP' => 'Japan',
            'JE' => 'Jersey',
            'JO' => 'Jordan',
            'KZ' => 'Kazakhstan',
            'KE' => 'Kenya',
            'KI' => 'Kiribati',
            'KR' => 'Korea',
            'KW' => 'Kuwait',
            'KG' => 'Kyrgyzstan',
            'LA' => 'Lao People\'s Democratic Republic',
            'LV' => 'Latvia',
            'LB' => 'Lebanon',
            'LS' => 'Lesotho',
            'LR' => 'Liberia',
            'LY' => 'Libyan Arab Jamahiriya',
            'LI' => 'Liechtenstein',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'MO' => 'Macao',
            'MK' => 'Macedonia',
            'MG' => 'Madagascar',
            'MW' => 'Malawi',
            'MY' => 'Malaysia',
            'MV' => 'Maldives',
            'ML' => 'Mali',
            'MT' => 'Malta',
            'MH' => 'Marshall Islands',
            'MQ' => 'Martinique',
            'MR' => 'Mauritania',
            'MU' => 'Mauritius',
            'YT' => 'Mayotte',
            'MX' => 'Mexico',
            'FM' => 'Micronesia, Federated States Of',
            'MD' => 'Moldova',
            'MC' => 'Monaco',
            'MN' => 'Mongolia',
            'ME' => 'Montenegro',
            'MS' => 'Montserrat',
            'MA' => 'Morocco',
            'MZ' => 'Mozambique',
            'MM' => 'Myanmar',
            'NA' => 'Namibia',
            'NR' => 'Nauru',
            'NP' => 'Nepal',
            'NL' => 'Netherlands',
            'AN' => 'Netherlands Antilles',
            'NC' => 'New Caledonia',
            'NZ' => 'New Zealand',
            'NI' => 'Nicaragua',
            'NE' => 'Niger',
            'NG' => 'Nigeria',
            'NU' => 'Niue',
            'NF' => 'Norfolk Island',
            'MP' => 'Northern Mariana Islands',
            'NO' => 'Norway',
            'OM' => 'Oman',
            'PK' => 'Pakistan',
            'PW' => 'Palau',
            'PS' => 'Palestinian Territory, Occupied',
            'PA' => 'Panama',
            'PG' => 'Papua New Guinea',
            'PY' => 'Paraguay',
            'PE' => 'Peru',
            'PH' => 'Philippines',
            'PN' => 'Pitcairn',
            'PL' => 'Poland',
            'PT' => 'Portugal',
            'PR' => 'Puerto Rico',
            'QA' => 'Qatar',
            'RE' => 'Reunion',
            'RO' => 'Romania',
            'RU' => 'Russian Federation',
            'RW' => 'Rwanda',
            'BL' => 'Saint Barthelemy',
            'SH' => 'Saint Helena',
            'KN' => 'Saint Kitts And Nevis',
            'LC' => 'Saint Lucia',
            'MF' => 'Saint Martin',
            'PM' => 'Saint Pierre And Miquelon',
            'VC' => 'Saint Vincent And Grenadines',
            'WS' => 'Samoa',
            'SM' => 'San Marino',
            'ST' => 'Sao Tome And Principe',
            'SA' => 'Saudi Arabia',
            'SN' => 'Senegal',
            'RS' => 'Serbia',
            'SC' => 'Seychelles',
            'SL' => 'Sierra Leone',
            'SG' => 'Singapore',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'SB' => 'Solomon Islands',
            'SO' => 'Somalia',
            'ZA' => 'South Africa',
            'GS' => 'South Georgia And Sandwich Isl.',
            'ES' => 'Spain',
            'LK' => 'Sri Lanka',
            'SD' => 'Sudan',
            'SR' => 'Suriname',
            'SJ' => 'Svalbard And Jan Mayen',
            'SZ' => 'Swaziland',
            'SE' => 'Sweden',
            'CH' => 'Switzerland',
            'SY' => 'Syrian Arab Republic',
            'TW' => 'Taiwan',
            'TJ' => 'Tajikistan',
            'TZ' => 'Tanzania',
            'TH' => 'Thailand',
            'TL' => 'Timor-Leste',
            'TG' => 'Togo',
            'TK' => 'Tokelau',
            'TO' => 'Tonga',
            'TT' => 'Trinidad And Tobago',
            'TN' => 'Tunisia',
            'TR' => 'Turkey',
            'TM' => 'Turkmenistan',
            'TC' => 'Turks And Caicos Islands',
            'TV' => 'Tuvalu',
            'UG' => 'Uganda',
            'UA' => 'Ukraine',
            'AE' => 'United Arab Emirates',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'UM' => 'United States Outlying Islands',
            'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan',
            'VU' => 'Vanuatu',
            'VE' => 'Venezuela',
            'VN' => 'Viet Nam',
            'VG' => 'Virgin Islands, British',
            'VI' => 'Virgin Islands, U.S.',
            'WF' => 'Wallis And Futuna',
            'EH' => 'Western Sahara',
            'YE' => 'Yemen',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe',
        );

        return $countries;
    }

    /**
     * @Route("/preview/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function previewAction(OagFile $file)
    {
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
     * @Route("/improveYourData/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function improveYourDataAction(OagFile $file) {
        $srvOagFile = $this->get(OagFileService::class);

        $srvGeocoder = $this->get(Geocoder::class);
        $srvClassifier = $this->get(Classifier::class);
        $srvIati = $this->get(IATI::class);
        $geocoderStatus = $srvGeocoder->status();
        $classifierStatus = $srvClassifier->status();

        $filename = $file->getDocumentName();
        $rulesetErrorRepo = $this->getDoctrine()->getRepository(RulesetError::class);
        $rulesetErrors = $rulesetErrorRepo->findByFilename($filename);

        $router = $this->get('router');

        $classifierUrl = $router->generate(
            'oag_async_classifystatus', array(), UrlGeneratorInterface::ABSOLUTE_URL // This guy right here
        );
        $geocoderUrl = $router->generate(
            'oag_async_geocodestatus', array(), UrlGeneratorInterface::ABSOLUTE_URL // This guy right here
        );
        $reclassifyUrl = $router->generate(
            'oag_async_classify',
            array(
                'id' => $file->getId(),
            ),
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $regeocodeUrl = $router->generate(
            'oag_async_geocode',
            array(
                'id' => $file->getId(),
            ),
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $fileData = $srvIati->getData($file);
        $fileStats = $srvIati->getStats($fileData);
        return array(
            'file' => $file,
            'classified' => $fileStats['activitiesWithNoTags'] == 0,
            'geocoded' => $fileStats['activitiesWithNoLocs'] == 0,
            'status' => [
                'geocoder' => $geocoderStatus,
                'classifier' => $classifierStatus,
            ],
            'classifierUrl' => $classifierUrl,
            'geocoderUrl' => $geocoderUrl,
            'reclassifyUrl' => $reclassifyUrl,
            'regeocodeUrl' => $regeocodeUrl,
            'file_stats' => $fileStats,
            'ruleset_errors' => $rulesetErrors,
        );
    }

}
/* vim: set expandtab ts=4 sw=4: */
