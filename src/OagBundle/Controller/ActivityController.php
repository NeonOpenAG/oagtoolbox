<?php

namespace OagBundle\Controller;

use OagBundle\Entity\Change;
use OagBundle\Entity\Tag;
use OagBundle\Form\MergeActivityType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use OagBundle\Entity\OagFile;
use OagBundle\Entity\SuggestedTag;
use OagBundle\Service\IATI;
use OagBundle\Service\OagFileService;

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
        $sugTagRepo = $this->container->get('doctrine')->getRepository(SuggestedTag::class);
        $changeRepo = $this->container->get('doctrine')->getRepository(Change::class);
        $em = $this->getDoctrine()->getManager();

        # Find activity using the provided ID.
        $root = $srvIATI->load($file);
        $activity = $srvIATI->getActivityById($root, $iatiActivityId);

        # Find past changes made to activity
        $pastChanges = $changeRepo->findBy(array('activityId' => $iatiActivityId));

        # Current activity summarised in array form.
        $activityDetail = $srvIATI->summariseActivityToArray($activity);

        # Create map definition array.
        $mapData = $srvIATI->getActivityMapData($activity);

        # Current tags attached to $activity.
        $currentTags = $srvIATI->getActivityTags($activity);

        # Create a new instance of the form.
        $form = $this->createForm(MergeActivityType::class, null, array_merge(array(
            'currentTags' => $currentTags,
            'iatiActivityId' => $iatiActivityId,
            'file' => $file
        )));

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $toRemove = array();
            foreach ($currentTags as $index => $sugTag) {
                // has a pre-existing one been removed?
                if (!in_array($index, $data['currentTags'])) {
                    $tagCode = $sugTag['code'];
                    $tagDescription = $sugTag['description'];
                    $tagVocab = $sugTag['vocabulary'];
                    $tagVocabUri = $sugTag['vocabulary-uri'];

                    $dbTag = new Tag();
                    $dbTag->setCode($tagCode);
                    $dbTag->setVocabulary($tagVocab, $tagVocabUri);
                    $dbTag->setDescription($tagDescription);
                    $toRemove[] = $dbTag;
                    $em->persist($dbTag);

                    $srvIATI->removeActivityTag($activity, $tagCode, $tagVocab, $tagVocabUri);
                }
            }

            // everything else is to be added
            $toAddIds = $data['suggested'];
            foreach ($file->getEnhancingDocuments() as $otherFile) {
                $id = $otherFile->getId();
                $toAddIds = array_merge($toAddIds, $data["enhanced_$id"]);
            }

            $toAdd = array();
            foreach ($toAddIds as $tagId) {
                $sugTag = $sugTagRepo->findOneById($tagId);
                $tag = $sugTag->getTag();

                if (in_array($tag, $toAdd)) {
                    // no duplicates please
                    continue;
                }

                $toAdd[] = $tag;

                // TODO WARNING - if reusing this code elsewhere than the
                // auto-classifier, ensure that you specify the correct
                // vocabulary and reason for addition
                $code = $tag->getCode();
                $description = $tag->getDescription();
                $srvIATI->addActivityTag($activity, $code, $description);
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

            # Force a redirect on successful submit so that the form is rebuilt.
            return $this->redirect($request->getUri());
        }

        return array(
            'form' => $form->createView(),
            'id' => $file->getId(),
            'pastChanges' => $pastChanges,
            'activity' => $activityDetail,
            'mapdata' => json_encode($mapData, JSON_HEX_APOS + JSON_HEX_TAG + JSON_HEX_AMP + JSON_HEX_QUOT),
        );
    }

}
