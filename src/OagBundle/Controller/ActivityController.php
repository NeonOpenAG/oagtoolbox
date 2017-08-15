<?php

namespace OagBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use OagBundle\Entity\OagFile;
use OagBundle\Entity\Sector;
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
        $sectorrepo = $this->container->get('doctrine')->getRepository(Sector::class);

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
            'choices' => array_column($current, 'code', 'description')
        ));


        # suggested sectors
        $suggested = array();
        foreach ($file->getSectors() as $sector) {
            # if it's not from our activity, ignore it
            if ($sector->getId() !== $iatiActivityId) {
                continue;
            }
            $suggested[] = $sector;
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
            $sectors = $otherFile->getSectors();
            $id = $otherFile->getId();

            $formBuilder->add("enhanced_$id", ChoiceType::class, array(
                'expanded' => true,
                'multiple' => true,
                'label' => $name,
                'choices' => array_reduce($sectors->toArray(), function ($result, $item) {
                    $label = $item->getCode()->getDescription();
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

            foreach ($current as $sector) {
                // has a pre-existing one been removed?
                if (!in_array($sector['code'], $data['current'])) {
                    $srvActivity->removeActivitySector($activity, $sector['code'], $sector['vocabulary']);
                }
            }

            // everything else is to be added
            $toAdd = $data['suggested'];
            foreach ($file->getEnhancingDocuments() as $otherFile) {
                $id = $otherFile->getId();
                $toAdd = array_merge($toAdd, $data["enhanced_$id"]);
            }

            foreach ($toAdd as $sectorId) {
                $sector = $sectorrepo->findOneById($sectorId);
                $code = $sector->getCode()->getCode();
                $description = $sector->getCode()->getDescription();
                $srvActivity->addActivitySector($activity, $code, $description);
            }

            dump($activity->asXML());
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
