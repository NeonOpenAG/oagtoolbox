<?php

namespace OagBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use OagBundle\Service\Classifier;
use OagBundle\Entity\Sector;
use OagBundle\Entity\SuggestedSector;
use OagBundle\Service\ActivityService;
use Symfony\Component\HttpFoundation\Request;
use OagBundle\Entity\OagFile;
use OagBundle\Form\MergeActivityType;
use OagBundle\Form\ListEnhancementDocsType;
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
     * List all activities in a document
     *
     * @Route("/activity/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     *
     * Provides an interface for merging in sectors. Will replace mergeSectors
     * (above) when complete.
     */
    public function activityAction(Request $request, OagFile $file) {
        // Load XML document
        $root = $srvActivity->load($file);

        // Extract each activity
        $srvActivities = $this->get(ActivityService::class);
        $activities = $srvActivities->summariseToArray($root);

        // Render them
        return array('activities' => $activities);
    }

    /**
     * @Route("/sectors/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     *
     * Provides an interface for merging in sectors. Will replace mergeSectors
     * (above) when complete.
     */
    public function sectorsAction(Request $request, OagFile $file) {

        // Get documents that have classifications
        $documentNames = [];
        $allfiles = $this->getDoctrine()
            ->getManager()
            ->getRepository('OagBundle:OagFile')
            ->findAll();
        foreach ($allfiles as $_file) {
            if (count($_file->getSectors()) > 0) {
                $documentNames[$_file->getId()] = sprintf(
                    "%s (%d)", $_file->getDocumentName(), count($_file->getSectors())
                );
            }
        }

        $mergeForm = $this->createForm(ListEnhancementDocsType::class, null, array(
            'documentNames' => $documentNames,
        ));
        $mergeForm->handleRequest($request);

        $documents = [];
        if ($mergeForm->isSubmitted() && $mergeForm->isValid()) {
            $data = $mergeForm->getData();
            foreach ($data as $id => $state) {
                if ($state) {
                    $oagFile = $this->getDoctrine()->getRepository(OagFile::class)->find($id);
                    $documents[$oagFile->getDocumentName()] = $oagFile->getSectors();
                }
            }
        }

        // Load and display XML doc
        $classifier = $this->get(Classifier::class);
        $srvActivity = $this->get(ActivityService::class);
        $srvOagFile = $this->get(ActivityService::class);

        $xml = $srvOagFile->getContents($path);

        $root = $srvActivity->load($file);

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
                $label = "$desc ($conf%)";
                $mergeNew[$id][$label] = $code;
            }
        }


        $sectorsForm = $this->createForm(MergeActivityType::class, null, array_merge(array(
            'ids' => $names,
            'current' => $mergeCur,
            'new' => $mergeNew,
            'documents' => $documents,
        )));
        $sectorsForm->handleRequest($request);

        // handle merging as a response
        if ($sectorsForm->isSubmitted() && $sectorsForm->isValid()) {
            $this->addFlash('notice', 'Sector changes have been applied successfully.');

            $data = $sectorsForm->getData();
            dump($data);

            foreach ($srvActivity->getActivities($root) as $activity) {
                dump($activity);
//                $id = $srvActivity->getActivityId($activity);
//
//                $revCurrent = $data['current' . $id];
//                $revNew = $data['new' . $id];
//
//                foreach ($allCurrentSectors[$id] as $sector) {
//                    // if status has changed
//                    if (!in_array($sector['code'], $revCurrent)) {
//                        $srvActivity->removeActivitySector($activity, $sector['code'], $sector['vocabulary']);
//                    }
//                }
//
//                foreach ($allNewSectors[$id] as $sector) {
//                    // if status has changed
//                    if (in_array($sector['code'], $revNew)) {
//                        $srvActivity->addActivitySector(
//                            $activity, $sector['code'], $sector['description']
//                        );
//                    }
//                }
//            }
//
//            // download generated XML
//            $modifiedXML = $srvActivity->toXML($root);
//            $response = new Response($modifiedXML);
//
//            $disposition = $response->headers->makeDisposition(
//                ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'modified.xml'
//            );
//
//            $response->headers->set('Content-Disposition', $disposition);
//            return $response;
            }
        }


        $response = array(
            'sectorsForm' => $sectorsForm->createView(),
            'enhancementForm' => $mergeForm->createView(),
        );

        return $response;
    }

}
