<?php

namespace OagBundle\Controller;

use OagBundle\Service\Classifier;
use OagBundle\Service\Docker;
use OagBundle\Service\Geocoder;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

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
        $images = $srvDocker->listImages();
        $geocoderStatus = $srvGeocoder->status();
        $classifierStatus = $srvClassifier->status();

        // Dockers
        $dockerNames = $this->getParameter('docker_names');
        $imageData = [];
        foreach ($dockerNames as $name) {
            $status = in_array('openagdata/' . $name, $images);
            $imageData[] = [
                'name' => $name,
                'status' => $status,
            ];
            if (!$status) {
                $srvDocker->pullImage('openagdata/' . $name, true);
            }
        }

        $output = [];
        exec('ps -ef | grep "docker pull"', $output);

        $data = [
            'containers' => $containers,
            'images' => $images,
            'all_images' => implode(', ', $images),
            'docker_names' => $dockerNames,
            'process_stat' => $output,
            'image_data' => $imageData,
            'container_json' => json_encode($containers, JSON_PRETTY_PRINT),
            'image_json' => json_encode($images, JSON_PRETTY_PRINT),
            'status' => [
                'geocoder' => json_encode($geocoderStatus),
                'classifier' => json_encode($classifierStatus),
            ],
        ];
        return $data;
    }
}
