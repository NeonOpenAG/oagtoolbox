<?php

namespace OagBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use OagBundle\Entity\OagFile;
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
                $result[$item->getCode()] = $item->getId();
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
                    $result[$item->getCode()] = $item->getId();
                    return $result;
                }, array())
            ));
        }

        return array(
            'form' => $formBuilder->getForm()->createView()
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
