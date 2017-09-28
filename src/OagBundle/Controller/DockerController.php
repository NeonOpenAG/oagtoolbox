<?php

namespace OagBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use OagBundle\Service\Docker;
use OagBundle\Service\Geocoder;
use OagBundle\Service\Classifier;

/**
 * @Template
 * @Route("/docker")
 */
class DockerController extends Controller
{
    /**
     * @Route("/")
     */
    public function listAction()
    {
        $srvDocker = $this->get(Docker::class);
        $srvGeocoder = $this->get(Geocoder::class);
        $srvClassifier = $this->get(Classifier::class);

        $containers = $srvDocker->listContainers();
        $geocoderStatus = $srvGeocoder->status();
        $classifierStatus = $srvClassifier->status();

        $data = [
            'containers' => $containers,
            'json' => json_encode($containers, JSON_PRETTY_PRINT),
            'status' => [
                'geocoder' => json_encode($geocoderStatus),
                'classifier' => json_encode($classifierStatus),
            ],
        ];
        return $data;
    }
}
