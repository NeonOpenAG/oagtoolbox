<?php

namespace OagBundle\Controller;

use OagBundle\Service\DPortal;
use OagBundle\Entity\OagFile;
use RuntimeException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/dportal")
 * @Template
 */
class DPortalController extends Controller {

    /**
     * @Route("/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function oagFileAction(Request $request, OagFile $file) {
        $portal = $this->get(DPortal::class);

        if ($portal->isAvailable()) {
            $messages[] = 'DPortal is avaialable';
        } else {
            throw new RuntimeException('DPortal is not available in application scope');
        }

        $portal->visualise($file);
        $url = \str_replace(
            'SERVER_HOST', $_SERVER['HTTP_HOST'], $this->getParameter('oag')['dportal']['uri']
        );

        return $this->redirect($url);
    }

}
