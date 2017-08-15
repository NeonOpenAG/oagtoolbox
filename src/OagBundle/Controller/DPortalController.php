<?php

namespace OagBundle\Controller;

use OagBundle\Service\DPortal;
use OagBundle\Entity\OagFile;
use RuntimeException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/dportal")
 */
class DPortalController extends Controller {

    /**
     * @Route("/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function indexAction(Request $request, OagFile $file) {
        $portal = $this->get(DPortal::class);

        $avaiable = false;
        if ($portal->isAvailable()) {
            $messages[] = 'DPortal is avaialable';
        } else {
            throw new RuntimeException('DPortal is not available in application scope');
        }

        $portal->visualise($file);

        return $this->redirect($this->getParameter('oag')['dportal']['uri']);
    }

}
