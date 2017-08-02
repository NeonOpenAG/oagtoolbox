<?php

namespace OagBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;

use OagBundle\Form\MergeActivityType;
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

  /**
   * @Route("/locations")
   * @Template
   *
   * Provides an interface for merging in locations. Will replace mergeLocations
   * (above) when complete.
   */
  public function locationsAction(Request $request) {
    $geocoder = $this->get(Geocoder::class);
    $srvActivity = $this->get(ActivityService::class);

    // TODO let this take a specific XML file as input
    $xml = $srvActivity->getFixtureData();
    $root = $srvActivity->parseXML($xml);

    $response = $geocoder->processXML($xml);
    $allNewLocations = $geocoder->extractLocations($response); // suggested

    $names = array(); // $activity id => $activity name
    $allCurrentLocations = array(); // in XML now
    $mergeCur = array(); // to create checkboxes
    $mergeNew = array();
    foreach ($srvActivity->getActivities($root) as $activity) {
      // populate arrays with activity information
      $id = $srvActivity->getActivityId($activity);
      $names[$id] = $srvActivity->getActivityName($activity);
      $allCurrentLocations[$id] = $srvActivity->getActivityLocations($activity);

      if (!array_key_exists($id, $allNewLocations)) {
        $allNewLocations[$id] = array();
      }

      $mergeCur[$id] = array_column($allCurrentLocations[$id], 'code', 'description');
      $mergeNew[$id] = array_column($allNewLocations[$id], 'id', 'name');
    }

    $locationsForm = $this->createForm(MergeActivityType::class, null, array(
      'ids' => $names,
      'current' => $mergeCur,
      'new' => $mergeNew
    ));
    $locationsForm->handleRequest($request);

    // handle merging as a response
    if ($locationsForm->isSubmitted() && $locationsForm->isValid()) {
      $this->addFlash('notice', 'Location changes have been applied successfully.');

      $data = $locationsForm->getData();

      foreach ($srvActivity->getActivities($root) as $activity) {
        $id = $srvActivity->getActivityId($activity);

        $revCurrent = $data['current' . $id];
        $revNew = $data['new' . $id];

        foreach ($allCurrentLocations[$id] as $location) {
          // if status has changed
          if (!in_array($location['code'], $revCurrent)) {
            $srvActivity->removeActivityLocation($activity, $location['code']);
          }
        }

        foreach ($allNewLocations[$id] as $location) {
          // if status has changed
          if (in_array($location['id'], $revNew)) {
            $srvActivity->addActivityLocation(
              $activity,
              $location
            );
          }
        }
      }
    }

    $response = array(
      'form' => $locationsForm->createView()
    );

    return $response;
  }

}
