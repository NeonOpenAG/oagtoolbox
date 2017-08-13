<?php

namespace OagBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use OagBundle\Entity\OagFile;

/**
 * @Route("/activity")
 * @Template
 */
class ActivityController extends Controller
 {

    /**
     * Show summary of activity and existing sectors with sectors available from supporting documents.
     *
     * @Route("/enhance/{id}/{iati_activity_id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function enhanceAction(Request $request, OagFile $file, $iati_activity_id) {
        return [];
    }

    /**
     * Process the submission from the enhance function
     *
     * @Route("/merge/{id}/{iati_activity_id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function mergeAction(Request $request, OagFile $file, $iati_activity_id) {
        return [];
    }

}
