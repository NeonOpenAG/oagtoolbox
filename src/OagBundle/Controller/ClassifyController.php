<?php

namespace OagBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use OagBundle\Service\ActivityService;
use Symfony\Component\HttpFoundation\Request;
use OagBundle\Entity\OagFile;


/**
 * @Route("/classify")
 * @Template
 */
class ClassifyController extends Controller {

    /**
     * List all activities in a document
     *
     * @Route("/activity/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     *
     * Provides an interface for merging in sectors. Will replace mergeSectors
     * (above) when complete.
     */
    public function activityAction(Request $request, OagFile $file) {
        // Load XML document
        $root = $srvActivity->load($file);

        // Extract each activity
        $srvActivities = $this->get(ActivityService::class);
        $activities = $srvActivities->summariseToArray($root);

        // Render them
        return array('activities' => $activities);
    }

}
