<?php

namespace OagBundle\Controller;

use OagBundle\Entity\OagFile;
use OagBundle\Service\ActivityService;
use OagBundle\Service\Classifier;
use OagBundle\Service\Geocoder;
use OagBundle\Entity\Code;
use OagBundle\Entity\Sector;
use OagBundle\Service\TextExtractor\TextifyService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/oagFile")
 * @Template
 */
class OagFileController extends Controller
 {

    /**
     * @Route("/iati/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function iatiAction(Request $request, OagFile $file) {
        $data = [];
        $srvActivity = $this->get(ActivityService::class);

        $data['id'] = $file->getId();
        $data['name'] = $file->getDocumentName();
        $data['mimetype'] = $file->getMimeType();

        $root = $srvActivity->load($file);
        $srvActivities = $this->get(ActivityService::class);
        $activities = $srvActivities->summariseToArray($root);

        $data['activities'] = $activities;

        $enhancementDocs = $file->getEnhancingDocuments();
        $data['enhancingDocuments'] = $enhancementDocs;

        return $data;
    }

    /**
     * @Route("/source/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function sourceAction(Request $request, OagFile $file) {
        return $this->render('OagBundle:OagFile:source.html.twig', array(
            // ...
        ));
    }

    /**
     * @Route("/enhancement/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function enhancementAction(Request $request, OagFile $file) {
        $srvActivity = $this->get(TextifyService::class);
        $data = [];
        $data['id'] = $file->getId();
        $data['name'] = $file->getDocumentName();
        $data['mimetype'] = $file->getMimeType();
        $data['text'] = $srvActivity->stripOagFile($file);

        // Now loop through sectors flattening them (so we can re-use the xectors table template)
        $sectors = $file->getSectors();
        $_sectors = [];
        foreach ($sectors as $sector) {
            $_sectors[] = [
                'id' => $sector->getId(),
                'confidence' => $sector->getConfidence(),
                'code' => $sector->getCode()->getCode(),
                'description' => $sector->getCode()->getDescription(),
            ];
        }
        $data['sectors'] = $_sectors;

        return $data;
    }

    /**
     * @Route("/classify/xml/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function classifyXmlAction(Request $request, OagFile $file) {
        $srvClassifier = $this->get(Classifier::class);

        $path = $this->container->getParameter('oagfiles_directory') . '/' . $file->getDocumentName();
        $rawXml = file_get_contents($path);

        $json = $srvClassifier->processXML($rawXml); 

        $file->clearSectors();

        // TODO if $row['status'] == 0
        foreach ($json['data'] as $part) {
            foreach ($part as $activityId => $sectors) {
                $this->persistSectors($sectors, $file, $activityId);
            }
        }

        $em = $this->getDoctrine()->getManager();
        $em->persist($file);
        $em->flush();

        return ['name' => $file->getDocumentName(), 'sectors' => $json['data']];
    }

    /**
     * @Route("/classify/text/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function classifyTextAction(Request $request, OagFile $file) {
        $srvClassifier = $this->get(Classifier::class);
        $srvTextify = $this->get(TextifyService::class);

        $rawText = $srvTextify->stripOagFile($file);

        if ($rawText === false) {
            // textifier failed
            throw \RuntimeException('Unsupported file type to strip text from');
        }

        $json = $srvClassifier->processString($rawText);
        dump($json);

        $file->clearSectors();

        // TODO if $row['status'] == 0
        $this->persistSectors($json['data'], $file);

        $em = $this->getDoctrine()->getManager();
        $em->persist($file);
        $em->flush();

        return ['name' => $file->getDocumentName(), 'sectors' => $json['data']];
    }

    /**
     * Persists Oag sectors from API response to database.
     */
    private function persistSectors($sectors, $file, $activityId = null) {
        $em = $this->getDoctrine()->getManager();
        $coderepo = $this->container->get('doctrine')->getRepository(Code::class);
        $sectorrepo = $this->container->get('doctrine')->getRepository(Sector::class);

        foreach ($sectors as $row) {
            $code = $row['code'];
            $description = $row['description'];
            $confidence = $row['confidence'];

            // Check that the code exists in the system
            $_code = $coderepo->findOneByCode($code);
            if (!$_code) {
                $this->container->get('logger')
                    ->info(sprintf('Creating new code %s (%s)', $code, $description));
                $_code = new Code();
                $_code->setCode($code);
                $_code->setDescription($description);
                $em->persist($_code);
            }

            $sector = new \OagBundle\Entity\Sector();
            $sector->setCode($_code);
            $sector->setConfidence($confidence);
            if (!is_null($activityId)) {
                $sector->setActivityId($activityId);
            }

            $em->persist($sector);
            $file->addSector($sector);
        }
    }

    /**
     * @Route("/geocode/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function geocodeAction(Request $request, OagFile $file) {
        $srvGeocoder = $this->get(Geocoder::class);
        $json = $srvGeocoder->processOagFile($file);
        return [
            'name' => $file->getDocumentName(),
            'json' => $json,
        ];
    }

}
