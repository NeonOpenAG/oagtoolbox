<?php

namespace OagBundle\Controller;

use OagBundle\Entity\OagFile;
use OagBundle\Service\Classifier;
use OagBundle\Service\Geocoder;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/async")
 * @Template
 */
class AsyncController extends Controller
{

    /**
     * @Route("/geocode/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function geocodeAction(OagFile $oagfile)
    {
        $srvGeocoder = $this->get(Geocoder::class);
        $srvGeocoder->geocodeOagFile($oagfile);
    }

    /**
     * @Route("/classify/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function classifyAction(OagFile $oagfile)
    {
        $srvClassifier = $this->get(Classifier::class);
        $srvClassifier->classifyOagFile($oagfile);
    }

    /**
     * @Route("/classifyStatus")
     */
    public function classifyStatusAction()
    {
        $srvClassifier = $this->get(Classifier::class);
        $status = $srvClassifier->status();

        $response = new Response(json_encode($status));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * @Route("/geocodeStatus")
     */
    public function geocodeStatusAction()
    {
        $srvGeocoder = $this->get(Geocoder::class);
        $status = $srvGeocoder->status();

        $response = new Response(json_encode($status));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

}
