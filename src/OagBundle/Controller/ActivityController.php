<?php

namespace OagBundle\Controller;

use OagBundle\Entity\Change;
use OagBundle\Entity\OagFile;
use OagBundle\Entity\SuggestedTag;
use OagBundle\Entity\Tag;
use OagBundle\Form\MergeActivityType;
use OagBundle\Service\ChangeService;
use OagBundle\Service\IATI;
use OagBundle\Service\OagFileService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/activity")
 * @Template
 */
class ActivityController extends Controller
 {
    /**
     * Show summary of activity and existing tags with tags available from supporting documents.
     *
     * @Route("/{id}/{iatiActivityId}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function enhanceAction(Request $request, OagFile $file, $iatiActivityId) {
        $srvIATI = $this->get(IATI::class);
        $srvOagFile = $this->get(OagFileService::class);
        $srvChange = $this->get(ChangeService::class);
        $tagRepo = $this->container->get('doctrine')->getRepository(Tag::class);
        $sugTagRepo = $this->container->get('doctrine')->getRepository(SuggestedTag::class);
        $changeRepo = $this->container->get('doctrine')->getRepository(Change::class);
        $em = $this->getDoctrine()->getManager();

        # Find activity using the provided ID.
        $root = $srvIATI->load($file);
        $activity = $srvIATI->getActivityById($root, $iatiActivityId);

        # Find past changes made to activity
        $pastChanges = $changeRepo->findBy(array('activityId' => $iatiActivityId));
        $summarisedHistory = $srvChange->summariseHistory($activity);

        # Current activity summarised in array form.
        $activityDetail = $srvIATI->summariseActivityToArray($activity);

        # Create map definition array.
        $mapData = $srvIATI->getActivityMapData($activity);

        # Current tags attached to $activity.
        $currentTags = $srvIATI->getActivityTags($activity);

        # Create a new instance of the mergeForm for merging in tags.
        $mergeForm = $this->createForm(MergeActivityType::class, null, array_merge(array(
            'currentTags' => $currentTags,
            'iatiActivityId' => $iatiActivityId,
            'file' => $file
        )));

        $mergeForm->handleRequest($request);

        if ($mergeForm->isSubmitted() && $mergeForm->isValid()) {
            $data = $mergeForm->getData();

            $toRemove = array();
            foreach ($currentTags as $tag) {
                // has a pre-existing one been removed?
                if (!in_array($tag, $data['currentTags'])) {
                    $srvIATI->removeActivityTag($activity, $tag);
                    $toRemove[] = $tag;
                }
            }

            // everything else is to be added
            $toAddSuggested = $data['suggested'];
            foreach ($file->getEnhancingDocuments() as $otherFile) {
                $id = $otherFile->getId();
                $toAddSuggested = array_merge($toAddSuggested, $data["enhanced_$id"]);
            }

            $toAdd = array();
            foreach ($toAddSuggested as $sugTag) {
                $tag = $sugTag->getTag();

                if (in_array($tag, $toAdd)) {
                    // no duplicates please
                    continue;
                }

                $toAdd[] = $tag;
                $srvIATI->addActivityTag($activity, $tag, 'Classified automatically');
            }

            $stagedChange = new Change();
            $stagedChange->setAddedTags($toAdd);
            $stagedChange->setRemovedTags($toRemove);
            $stagedChange->setActivityId($iatiActivityId);
            $stagedChange->setTimestamp(new \DateTime("now"));
            $stagedChange->setFile($file);

            $em->persist($stagedChange);
            $em->flush();

            $resultXML = $srvIATI->toXML($root);
            $srvOagFile->setContents($file, $resultXML);

            # Force a redirect on successful submit so that the mergeForm is rebuilt.
            return $this->redirect($request->getUri());
        }

        // fast-forward form (for bringing up-to-date with past changes)
        $fastForwardForm = $this
            ->createFormBuilder(array())
            ->add('submit', SubmitType::class, array( 'label' => 'Re-apply Changes' ))
            ->getForm();

        $fastForwardForm->handleRequest($request);

        if ($fastForwardForm->isSubmitted() && $fastForwardForm->isValid()) {
            // apply changes to XML
            $srvChange->apply($summarisedHistory, $activity);

            // persist the result
            $resultXML = $srvIATI->toXML($root);
            $srvOagFile->setContents($file, $resultXML);

            // force a redirect on successful submit so that form are rebuilt
            return $this->redirect($request->getUri());
        }

        return array(
            'mergeForm' => $mergeForm->createView(),
            'fastForwardForm' => $fastForwardForm->createView(),
            'id' => $file->getId(),
            'pastChanges' => $pastChanges,
            'summarisedHistory' => $summarisedHistory,
            'activity' => $activityDetail,
            'mapdata' => json_encode($mapData, JSON_HEX_APOS + JSON_HEX_TAG + JSON_HEX_AMP + JSON_HEX_QUOT),
        );
    }

}
