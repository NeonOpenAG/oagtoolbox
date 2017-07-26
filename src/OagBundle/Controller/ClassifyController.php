<?php

namespace OagBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use OagBundle\Service\Classifier;
use Symfony\Component\HttpFoundation\Request;
use OagBundle\Entity\OagFile;
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
      // hit the XML endpoint...
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
   */
  public function mergeSectorsAction(Request $request) {
    $classifier = $this->get(Classifier::class);

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

      if(strlen($rawJson) === 0) {
        $this->addFlash("warn", "No json was entered!");
      }

      $json = json_decode($rawJson);
      $sectors = $classifier->extractSectors($response);
      $newXML = $classifier->insertSectors($rawXML, $sectors);
      $response['processed'] = $newXML;
    }

    return $response;
  }

  /**
   * @Route("/sectors")
   * @Template
   */
  public function sectorsAction(Request $request) {
    // TODO consider splitting this into services where appropriate

    // provides an interface for merging in sectors, will eventually replace mergeSectors
    $classifier = $this->get(Classifier::class);

    // TODO let this take a specific XML file as input
    $kernel = $this->get('kernel');
    $file = $kernel->locateResource('@OagBundle/Resources/fixtures/before_enrichment_activities.xml');
    $xml = file_get_contents($file);

    $root = new \SimpleXMLElement($xml, LIBXML_BIGLINES & LIBXML_PARSEHUGE);

    $response = $classifier->getFixtureData();
    $allNewSectors = $classifier->extractSectors($response);

    $names = array();
    $allCurrentSectors = array();
    foreach ($root->xpath('/iati-activities/iati-activity') as $activity) {
      $id = (string)$activity->xpath('./iati-identifier')[0];

      // TODO other languages
      $nameElements = $activity->xpath('./title/narrative'); 
      if (count($nameElements) < 1) {
        $name = '';
      } else {
        $name = (string)$nameElements[0];
      }

      $currentSectors = array();
      foreach ($activity->xpath('./sector') as $currentSector) {
        $description = (string)$currentSector->xpath('./narrative[1]')[0];
        $code = (string)$currentSector['code'];

        $currentSectors[] = array(
          'description' => $description,
          'code' => $code
        );
      }

      $names[$id] = $name;
      $allCurrentSectors[$id] = $currentSectors;
      if (!array_key_exists($id, $allNewSectors)) {
        $allNewSectors[$id] = array();
      }
    }

    $response = array(
      'names' => $names,
      'currentSectors' => $allCurrentSectors,
      'newSectors' => $allNewSectors
    );

    return $response;
  }

}
