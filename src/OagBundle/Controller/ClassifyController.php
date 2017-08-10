<?php

namespace OagBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use OagBundle\Service\Classifier;
use OagBundle\Entity\Sector;
use OagBundle\Entity\Activity;
use OagBundle\Service\ActivityService;
use Symfony\Component\HttpFoundation\Request;
use OagBundle\Entity\OagFile;
use OagBundle\Form\MergeActivityType;
use OagBundle\Form\OagFileType;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Response;
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
     * Reads a document and attempts to pass it through the classifier.
     *
     * @Route("/{id}", requirements={"id": "\d+"})
     * @Template
     */
    public function indexAction($id) {
        $messages = [];
        $classifier = $this->get(Classifier::class);

        $oagfilerepo = $this->container->get('doctrine')->getRepository(OagFile::class);
        $oagfile = $oagfilerepo->find($id);
        if (!$oagfile) {
            throw $this->createNotFoundException(sprintf('The document %d does not exist', $id));
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

        $em = $this->getDoctrine()->getManager();

        // Clear sectors from the file
        $oagfile->clearActivities();
        $sectorrepo = $this->container->get('doctrine')->getRepository(Sector::class);
        $activityrepo = $this->container->get('doctrine')->getRepository(Activity::class);


        // TODO if $row['status'] == 0
        foreach ($json['data'] as $row) {
            $code = $row['code'];
            $description = $row['description'];
            $confidence = $row['confidence'];

            // Check that the sector exists in the system
            $sector = $sectorrepo->findOneByCode($code);
            if (!$sector) {
                $this->container->get('logger')
                    ->info(sprintf('Creating new sector %s (%s)', $code, $description));
                $sector = new Sector();
                $sector->setCode($code);
                $sector->setDescription($description);
                $em->persist($sector);
            }

            $activity = $activityrepo->findOneBySector($sector);
            if ($activity && $oagfile->hasActivity($activity)) {
                $activity->setConfidence($confidence);
            } else {
                $activity = new \OagBundle\Entity\Activity();
                $activity->setSector($sector);
                $activity->setConfidence($confidence);
            }
            $em->persist($activity);
            $oagfile->addActivity($activity);
        }
        $em->persist($oagfile);
        $em->flush();

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
            ->add('submit', SubmitType::class, array('label' => 'Merge Sectors'))
            ->getForm();

        $form->handleRequest($request);

        $response = array(
            'form' => $form->createView()
        );

        if ($form->isSubmitted()) {
            $rawXML = $form->getData()['xml'];
            $rawJson = $form->getData()['json'];

            if (strlen($rawXML) === 0 || strlen($rawJson) === 0) {
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
                            $activity, $sector['code'], $sector['description']
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
     * @Route("/sectors/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     * @Template
     *
     * Provides an interface for merging in sectors. Will replace mergeSectors
     * (above) when complete.
     */
    public function sectorsAction(Request $request, OagFile $file) {
        /**
         * var \OagBundle\Service\Classifier
         */
        $classifier = $this->get(Classifier::class);
        $srvActivity = $this->get(ActivityService::class);

        $path = $this->getParameter('oagfiles_directory') . '/' . $file->getDocumentName();
        $xml = file_get_contents($path);
        $root = $srvActivity->parseXML($xml);

        $response = $classifier->processXML($xml);
        $allNewSectors = $classifier->extractSectors($response); // suggested

        $names = array(); // $id => $name
        $allCurrentSectors = array(); // in XML now
        $mergeCur = array(); // to create checkboxes
        $mergeNew = array();
        foreach ($srvActivity->getActivities($root) as $activity) {
            // populate arrays with activity information
            $id = $srvActivity->getActivityId($activity);
            $names[$id] = $srvActivity->getActivityTitle($activity);
            $allCurrentSectors[$id] = $srvActivity->getActivitySectors($activity);

            if (!array_key_exists($id, $allNewSectors)) {
                $allNewSectors[$id] = array();
            }

            $mergeCur[$id] = array_column($allCurrentSectors[$id], 'code', 'description');
            $mergeNew[$id] = array();
            foreach ($allNewSectors[$id] as $newSector) {
                $desc = $newSector['description'];
                $conf = round((floatval($newSector['confidence']) * 100));
                $code = $newSector['code'];
                $label = "$desc ($conf% confident)";
                $mergeNew[$id][$label] = $code;
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
                            $activity, $sector['code'], $sector['description']
                        );
                    }
                }
            }

            // download generated XML
            $modifiedXML = $srvActivity->toXML($root);
            $response = new Response($modifiedXML);

            $disposition = $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'modified.xml'
            );

            $response->headers->set('Content-Disposition', $disposition);
            return $response;
        }

        // Get documents that have classifications
        // TODO skip XML documents
        /*
          $documentNames = [];
          $counts = [];
          $ids = [];
          $includes = [];
          $allfiles = $this->getDoctrine()
          ->getManager()
          ->getRepository('OagBundle:OagFile')
          ->findAll();
          foreach ($allfiles as $_file) {
          if (count($_file->getActivities()) > 0) {
          $documentNames[] = $_file->getDocumentName();
          $counts[] = count($_file->getActivities());
          $ids[] = $_file->getId();
          $includes = '';
          }
          }

          $mergeForm = $this->createForm(MergeActivityType::class, null, array(
          'ids' => $ids,
          'names' => $documentNames,
          'counts' => $counts,
          'includes' => $includes,
          ));
          $mergeForm->handleRequest($request);

          // handle merging as a response
          if ($mergeForm->isSubmitted() && $mergeForm->isValid()) {

          }
         */

        $response = array(
            'form' => $sectorsForm->createView(),
            'enhancementFiles' => $enhancementFiles,
        );

        return $response;
    }


}
