<?php

namespace OagBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use OagBundle\Service\Classifier;
use OagBundle\Service\ActivityService;
use Symfony\Component\HttpFoundation\Request;
use OagBundle\Entity\OagFile;
use OagBundle\Form\MergeActivityType;
use OagBundle\Form\OagFileType;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use OagBundle\Service\TextExtractor\PDFExtractor;
use OagBundle\Service\TextExtractor\RTFExtractor;
use PhpOffice\PhpWord\Shared\ZipArchive;

/**
 * @Route("/classify")
 * @Template
 */
class ClassifyController extends Controller {

  /**
   * @Route("/{fileid}", requirements={"fileid": "\d+"})
   * @Template
   */
  public function indexAction($fileid) {
    $messages = [];
    $classifier = $this->get(Classifier::class);

    $repository = $this->container->get('doctrine')->getRepository(OagFile::class);
    $oagfile = $repository->find($fileid);
    if (!$oagfile) {
      throw $this->createNotFoundException(sprintf('The document %d does not exist', $fileid));
    }
    // TODO - for bigger files we might need send as Uri
    $path = $this->getParameter('oagfiles_directory') . '/' . $oagfile->getDocumentName();
    $mimetype = mime_content_type($path);
    $messages[] = sprintf('File %s detected as %s', $path, $mimetype);

    $isXml = false;
    $sourceFile = tempnam(sys_get_temp_dir(), 'oag') . '.txt';
    switch ($mimetype) {
      case 'application/pdf':
      case 'application/pdf':
      case 'application/x-pdf':
      case 'application/acrobat':
      case 'applications/vnd.pdf':
      case 'text/pdf':
      case 'text/x-pdf':
        // pdf
        $decoder = new PDFExtractor();
        $decoder->setFilename($path);
        $decoder->decode();
        file_put_contents($sourceFile, $decoder->output());
        break;
      case 'application/txt':
      case 'browser/internal':
      case 'text/anytext':
      case 'widetext/plain':
      case 'widetext/paragraph':
      case 'text/plain':
        // txt
        $sourceFile = $path;
        break;
      case 'text/html':
      case 'application/xml':
        // xml
        $sourceFile = $path;
        $isXml = true;
        break;
      case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
      case 'application/msword':
      case 'application/doc':
      case 'application/zip':
        // docx
        // phpword can't save to txt directly
        $tmpRtfFile = dirname($sourceFile) . '/' . basename($sourceFile, '.txt') . '.rtf';
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($path, 'Word2007');
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'RTF');
        $objWriter->save($tmpRtfFile);
        // Now let the switch fall through to decode rtf
        $path = $tmpRtfFile;
      case 'application/rtf':
      case 'application/x-rtf':
      case 'text/richtext':
      case 'text/rtf':
        // rtf
        $decoder = new RTFExtractor();
        $decoder->setFilename($path);
        $decoder->decode();
        file_put_contents($sourceFile, $decoder->output());
        break;
    }
    $this->container->get('logger')->info(sprintf('Processing file %s', $sourceFile));

    $contents = file_get_contents($sourceFile);
    if ($isXml) {
      // TODO hit the XML endpoint...
    }
    $json = $classifier->processString($contents);

    $data = array(
      'messages' => $messages,
      'response' => $json,
    );
    return $data;
  }

  /**
   * @Route("/merge-sectors")
   * @Template
   *
   * Deprecated. Will be replaced by sectorsAction when fully functional.
   */
  public function mergeSectorsAction(Request $request) {
    $classifier = $this->get(Classifier::class);
    $srvActivity = $this->get(ActivityService::class);

    $defaultData = array();
    $options = array();
    $form = $this->createFormBuilder($defaultData, $options)
      ->add('xml', TextareaType::class)
      ->add('json', TextareaType::class)
      ->add('submit', SubmitType::class, array( 'label' => 'Merge Sectors'))
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
        $sectors = $classifier->extractSectors($json);

        $root = $srvActivity->parseXML($rawXML);

        foreach ($srvActivity->getActivities($root) as $activity) {
          $id = $srvActivity->getActivityId($activity);

          if (!array_key_exists($id, $sectors)) {
            continue;
          }

          foreach ($sectors[$id] as $sector) {
            $srvActivity->addActivitySector(
              $activity,
              $sector['code'],
              $sector['description']
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
   * @Route("/sectors")
   * @Template
   *
   * Provides an interface for merging in sectors. Will replace mergeSectors
   * (above) when complete.
   */
  public function sectorsAction(Request $request) {
    $classifier = $this->get(Classifier::class);
    $srvActivity = $this->get(ActivityService::class);

    // TODO let this take a specific XML file as input
    $xml = $srvActivity->getFixtureData();

    $response = $classifier->processXML($xml);
    $allNewSectors = $classifier->extractSectors($response);

    $root = $srvActivity->parseXML($xml);

    $names = array();
    $allCurrentSectors = array();
    $mergeCur = array();
    $mergeNew = array();
    foreach ($srvActivity->getActivities($root) as $activity) {
      // populate arrays with activity information
      $id = $srvActivity->getActivityId($activity);
      $names[$id] = $srvActivity->getActivityName($activity);
      $allCurrentSectors[$id] = $srvActivity->getActivitySectors($activity);

      if (!array_key_exists($id, $allNewSectors)) {
        $allNewSectors[$id] = array();
      }

      $mergeCur[$id] = array();
      $mergeNew[$id] = array();
      foreach ($allCurrentSectors[$id] as $currentSector) {
        $mergeCur[$id][$currentSector['description']] = $currentSector['code'];
      }
      foreach ($allNewSectors[$id] as $newSector) {
        $mergeNew[$id][$newSector['description']] = $newSector['code'];
      }
    }

    $sectorsForm = $this->createForm(MergeActivityType::class, null, array(
      'ids' => $names,
      'current' => $mergeCur,
      'new' => $mergeNew
    ));
    $sectorsForm->handleRequest($request);

    // handle merging as a response
    if ($sectorsForm->isSubmitted() && $sectorsForm->isValid()) {
      $this->addFlash('notice', 'Sector changes have been applied successfully.');

      $data = $sectorsForm->getData();

      foreach ($srvActivity->getActivities($root) as $activity) {
        $id = $srvActivity->getActivityId($activity);

        $revCurrent = $data['current' . $id];
        $revNew = $data['new' . $id];

        foreach ($allCurrentSectors[$id] as $sector) {
          // if status has changed
          if (!in_array($sector['code'], $revCurrent)) {
            $srvActivity->removeActivitySector($activity, $sector['code'], $sector['vocabulary']);
          }
        }

        foreach ($allNewSectors[$id] as $sector) {
          // if status has changed
          if (in_array($sector['code'], $revNew)) {
            $srvActivity->addActivitySector(
              $activity,
              $sector['code'],
              $sector['description']
            );
          }
        }
      }
    }

    $response = array(
      'names' => $names,
      'currentSectors' => $allCurrentSectors,
      'newSectors' => $allNewSectors,
      'form' => $sectorsForm->createView()
    );

    return $response;
  }

}
