<?php

namespace OagBundle\Controller;

use OagBundle\Entity\OagFile;
use OagBundle\Service\Geocoder;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/geocode")
 * @Template
 */
class GeocodeController extends Controller {

    /**
     * Geocode an OagFile.
     *
     * @Route("/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function oagFileAction(Request $request, OagFile $file) {
        $srvGeocoder = $this->get(Geocoder::class);
        $srvGeocoder->geocodeOagFile($file);

        return array(
            'name' => $file->getDocumentName(),
            'geolocations' => array_map(array($srvGeocoder, 'locationToArray'), $file->getGeolocations()->getValues()),
        );
    }

}
