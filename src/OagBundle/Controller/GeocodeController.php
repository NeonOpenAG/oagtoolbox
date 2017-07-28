<?php

namespace OagBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;

use OagBundle\Service\ActivityService;
use OagBundle\Service\Geocoder;

/**
 * @Route("/geocode")
 */
class GeocodeController extends Controller
{

  /**
   * @Route("/merge-locations")
   * @Template
   */
  public function mergeLocationsAction(Request $request) {
    $geocoder = $this->get(Geocoder::class);
    $srvActivity = $this->get(ActivityService::class);

    $defaultData = array();
    $options = array();
    $form = $this->createFormBuilder($defaultData, $options)
      ->add('xml', TextareaType::class)
      ->add('json', TextareaType::class)
      ->add('submit', SubmitType::class, array( 'label' => 'Merge Locations' ))
      ->getForm();

    $form->handleRequest($request);

    $response = array(
      'form' => $form->createView()
    );

    if ($form->isSubmitted()) {
      $rawXML = $form->getData()['xml'];
      $rawJson = $form->getData()['json'];

      if(strlen($rawXML) === 0 || strlen($rawJson) === 0) {
        $this->addFlash("warn", "Please fill in both fields.");
      } else {
        $json = json_decode($rawJson, true);
        $locations = $geocoder->extractLocations($json);

        $root = $srvActivity->parseXML($rawXML);

        foreach ($srvActivity->getActivities($root) as $activity) {
          $id = $srvActivity->getActivityId($activity);

          if (!array_key_exists($id, $locations)) {
            continue;
          }

          foreach ($locations[$id] as $location) {
            $srvActivity->addActivityLocation(
              $activity,
              $location
            );
          }
        }

        $newXML = $srvActivity->toXML($root);
        $response['processed'] = $newXML;
      }
    }

    return $response;
  }

}
