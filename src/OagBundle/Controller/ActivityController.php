<?php

namespace OagBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use OagBundle\Entity\Change;
use OagBundle\Entity\Sector;
use OagBundle\Form\MergeActivityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use OagBundle\Entity\OagFile;
use OagBundle\Entity\SuggestedSector;
use OagBundle\Service\ActivityService;
use OagBundle\Service\OagFileService;

/**
 * @Route("/activity")
 * @Template
 */
class ActivityController extends Controller
 {
    /**
     * Show summary of activity and existing sectors with sectors available from supporting documents.
     *
     * @Route("/enhance/{id}/{iatiActivityId}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function enhanceAction(Request $request, OagFile $file, $iatiActivityId) {
        $srvActivity = $this->get(ActivityService::class);
        $srvOagFile = $this->get(OagFileService::class);
        $sugSectorRepo = $this->container->get('doctrine')->getRepository(SuggestedSector::class);
        $em = $this->getDoctrine()->getManager();

        # Find activity using the provided ID.
        $root = $srvActivity->load($file);
        $activity = $srvActivity->getActivityById($root, $iatiActivityId);

        # Current activity summarised in array form.
        $activityDetail = $srvActivity->summariseActivityToArray($activity);

        # Create map definition array.
        $mapData = $srvActivity->getActivityMapData($activity);

        # Current sectors attached to $activity.
        $currentSectors = $srvActivity->getActivitySectors($activity);

        # Create a new instance of the form.
        $form = $this->createForm(MergeActivityType::class, null, array_merge(array(
            'currentSectors' => $currentSectors,
            'iatiActivityId' => $iatiActivityId,
            'file' => $file
        )));

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $toRemove = array();
            foreach ($currentSectors as $index => $sugSector) {
                // has a pre-existing one been removed?
                if (!in_array($index, $data['currentSectors'])) {
                    $sectorCode = $sugSector['code'];
                    $sectorDescription = $sugSector['description'];
                    $sectorVocab = $sugSector['vocabulary'];
                    $sectorVocabUri = $sugSector['vocabulary-uri'];

                    $dbSector = new Sector();
                    $dbSector->setCode($sectorCode);
                    $dbSector->setVocabulary($sectorVocab, $sectorVocabUri);
                    $dbSector->setDescription($sectorDescription);
                    $toRemove[] = $dbSector;
                    $em->persist($dbSector);

                    $srvActivity->removeActivitySector($activity, $sectorCode, $sectorVocab, $sectorVocabUri);
                }
            }

            // everything else is to be added
            $toAddIds = $data['suggested'];
            foreach ($file->getEnhancingDocuments() as $otherFile) {
                $id = $otherFile->getId();
                $toAddIds = array_merge($toAddIds, $data["enhanced_$id"]);
            }

            $toAdd = array();
            foreach ($toAddIds as $sectorId) {
                $sugSector = $sugSectorRepo->findOneById($sectorId);
                $sector = $sugSector->getSector();

                if (in_array($sector, $toAdd)) {
                    // no duplicates please
                    continue;
                }

                $toAdd[] = $sector;

                // TODO WARNING - if reusing this code elsewhere than the
                // auto-classifier, ensure that you specify the correct
                // vocabulary and reason for addition
                $code = $sector->getCode();
                $description = $sector->getDescription();
                $srvActivity->addActivitySector($activity, $code, $description);
            }

            $stagedChange = new Change();
            $stagedChange->setAddedSectors($toAdd);
            $stagedChange->setRemovedSectors($toRemove);
            $stagedChange->setActivityId($iatiActivityId);
            $stagedChange->setTimestamp(new \DateTime("now"));
            $stagedChange->setFile($file);

            $em->persist($stagedChange);
            $em->flush();

            $resultXML = $srvActivity->toXML($root);
            $srvOagFile->setContents($file, $resultXML);

            # Force a redirect on successful submit so that the form is rebuilt.
            return $this->redirect($request->getUri());
        }

        return array(
            'form' => $form->createView(),
            'id' => $file->getId(),
            'activity' => $activityDetail,
            'mapdata' => json_encode($mapData, JSON_HEX_APOS + JSON_HEX_TAG + JSON_HEX_AMP + JSON_HEX_QUOT),
        );
    }

    /**
     * Process the submission from the enhance function
     *
     * @Route("/merge/{id}/{iatiActivityId}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function mergeAction(Request $request, OagFile $file, $iatiActivityId) {
        return [];
    }

}
