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
     * @Route("/classify/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function classifyAction(Request $request, OagFile $file) {
        $srvClassifier = $this->get(Classifier::class);
        $json = $srvClassifier->processOagFile($file);

        $file->clearSectors();
        $em = $this->getDoctrine()->getManager();
        $coderepo = $this->container->get('doctrine')->getRepository(Code::class);
        $sectorrepo = $this->container->get('doctrine')->getRepository(Sector::class);

        // TODO if $row['status'] == 0
        foreach ($json['data'] as $row) {
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

            $sector = $sectorrepo->findOneByCode($_code);
            if ($sector && $file->hasSector($sector)) {
                $sector->setConfidence($confidence);
            } else {
                $sector = new \OagBundle\Entity\Sector();
                $sector->setCode($_code);
                $sector->setConfidence($confidence);
            }
            $em->persist($sector);
            $file->addSector($sector);
        }
        $em->persist($file);
        $em->flush();

        return ['name' => $file->getDocumentName(), 'sectors' => $json['data']];
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
