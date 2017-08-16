<?php

namespace OagBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use OagBundle\Entity\Change;
use OagBundle\Entity\Sector;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use OagBundle\Entity\OagFile;
use OagBundle\Entity\SuggestedSector;
use OagBundle\Service\ActivityService;

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
        $sugSectorRepo = $this->container->get('doctrine')->getRepository(SuggestedSector::class);
        $em = $this->getDoctrine()->getManager();

        # find activity
        $root = $srvActivity->load($file);
        $activity = $srvActivity->getActivityById($root, $iatiActivityId);

        # build the form
        $formBuilder = $this->createFormBuilder(array(), array());

        # current sectors
        $current = $srvActivity->getActivitySectors($activity);
        $formBuilder->add('current', ChoiceType::class, array(
            'expanded' => true,
            'multiple' => true,
            'choices' => array_column($current, 'code', 'description'),
            'data' => array_column($current, 'code') // default to ticked
        ));

        # suggested sectors
        $suggested = array();
        foreach ($file->getSuggestedSectors() as $sugSector) {
            # if it's not from our activity, ignore it
            if ($sugSector->getId() !== $iatiActivityId) {
                continue;
            }
            $suggested[] = $sugSector;
        }
        $formBuilder->add('suggested', ChoiceType::class, array(
            'expanded' => true,
            'multiple' => true,
            'choices' => array_reduce($suggested, function ($result, $item) {
                $label = $item->getCode()->getDescription();
                $result[$label] = $item->getId();
                return $result;
            }, array())
        ));

        foreach ($file->getEnhancingDocuments() as $otherFile) {
            $name = $otherFile->getDocumentName();
            $sectors = $otherFile->getSuggestedSectors();
            $id = $otherFile->getId();

            $formBuilder->add("enhanced_$id", ChoiceType::class, array(
                'expanded' => true,
                'multiple' => true,
                'label' => $name,
                'choices' => array_reduce($sectors->toArray(), function ($result, $item) {
                    $label = $item->getSector()->getDescription();
                    $result[$label] = $item->getId();
                    return $result;
                }, array())
            ));
        }

        $formBuilder->add('submit', SubmitType::class, array(
            'label' => 'Merge'
        ));

        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        if ($form->isValid() && $form->isSubmitted()) {
            $data = $form->getData();

            $toRemove = array();
            foreach ($current as $sugSector) {
                // has a pre-existing one been removed?
                if (!in_array($sugSector['code'], $data['current'])) {
                    $sectorCode = $sugSector['code'];
                    $sectorDescription = $sugSector['code'];
                    $sectorVocab = $sugSector['vocabulary'];
                    $sectorVocabUri = $sugSector['vocabulary-uri'];

                    $dbSector = new Sector();
                    $dbSector->setCode($sectorCode);
                    $dbSector->setVocabulary($sectorVocab, $sectorVocabUri);
                    $dbSector->setDescription($sectorDescription);
                    $toRemove[] = $dbSector;
                    $em->persist($dbSector);

                    $srvActivity->removeActivitySector($activity, $sectorCode, $sectorVocab);
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
                $toAdd[] = $sector;

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
        }

        return array(
            'form' => $form->createView()
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
