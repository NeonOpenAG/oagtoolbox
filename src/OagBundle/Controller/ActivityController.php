<?php

namespace OagBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use OagBundle\Entity\Activity;
use OagBundle\Entity\OagFile;

/**
 * @Route("/activity")
 * @Template
 */
class ActivityController extends Controller
{
    /**
     * @Route("/{id}", requirements={"id": "\d+"})
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function indexAction(Request $request, OagFile $file) {
        $activities = $file->getActivities();

        return $this->render('OagBundle:Activity:index.html.twig', array(
            'activities' => $activities,
        ));
    }

}
