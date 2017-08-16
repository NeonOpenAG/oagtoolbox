<?php

namespace OagBundle\Controller;

use OagBundle\Entity\OagFile;
use OagBundle\Service\ActivityService;
use OagBundle\Service\Classifier;
use OagBundle\Service\Geocoder;
use OagBundle\Entity\Code;
use OagBundle\Entity\Sector;
use OagBundle\Entity\Geolocation;
use OagBundle\Service\OagFileService;
use OagBundle\Service\Cove;
use OagBundle\Service\TextExtractor\TextifyService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Collections\ArrayCollection;

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
        $data['oagfiles_dir'] = $this->getParameter('oagxml_directory');

        $path = $this->getParameter('oagxml_directory') . '/' . $file->getDocumentName();
        $xml = file_get_contents($path);

        $root = $srvActivity->parseXML($xml);
        $srvActivities = $this->get(ActivityService::class);
        $activities = $srvActivities->summariseToArray($root);

        $this->get('logger')->debug(sprintf('IATI Document %s has %d activites', count($activities), $file->getDocumentName()));
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
        $cove = $this->get(Cove::class);
        $srvOagFile = $this->get(OagFileService::class);
        $srvActivity = $this->get(ActivityService::class);

        $this->get('logger')->debug(sprintf('Processing %s using CoVE', $file->getDocumentName()));
        // TODO - for bigger files we might need send as Uri
        $path = $this->getParameter('oagfiles_directory') . '/' . $file->getDocumentName();
        $contents = file_get_contents($path);
        $json = $cove->processString($contents);

        $err = array_filter($json['err'] ?? []);
        $status = $json['status'] ?? '';

        // TODO Check status
        foreach ($err as $line) {
            $this->get('session')->getFlashBag()->add('error', $line);
        }

        $xml = $json['xml'];
        if ($srvActivity->parseXML($xml)) {
            $xmldir = $this->getParameter('oagxml_directory');
            if (!is_dir($xmldir)) {
                mkdir($xmldir, 0755, true);
            }
            $filename = $srvOagFile->getXMLFileName($file);
            $xmlfile = $xmldir . '/' . $filename;
            file_put_contents($xmlfile, $xml);

            $oagFile = $this->getDoctrine()->getRepository(OagFile::class)->findOneByDocumentName($filename);
            if (!$oagFile) {
                $oagFile = new OagFile();
                $this->get('logger')->debug('Creating new OagFile ' . $filename);
            }
            $oagFile->setDocumentName($filename);
            $oagFile->setFileType(OagFile::OAGFILE_IATI_DOCUMENT);
            $oagFile->setMimeType('application/xml');
            $em = $this->getDoctrine()->getManager();
            $em->persist($oagFile);
            $em->flush();
            $this->get('session')->getFlashBag()->add('info', 'IATI File created/Updated.');
        } else {
            $this->get('session')->getFlashBag()->add('error', 'CoVE returned data that was not XML.');
        }

        return $this->redirectToRoute('oag_default_index');
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

        $geolocations = $file->getGeolocations();
        $_geolocations = $this->locationsToArray($file->getGeolocations());

        $data['geolocations'] = $_geolocations;

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
        $results = json_decode($json, true);

        $file->clearGeolocations();
        $em = $this->getDoctrine()->getManager();
        $geolocationrepo = $this->container->get('doctrine')->getRepository(Geolocation::class);

        foreach ($results as $activity) {
            $iatiActivityId = $activity['project_id'] ?? null;
            $locations = $activity['locations'];
            foreach ($locations as $location) {
                $locationId = $location['id'];
                $vocabId = '99'; // TODO get a valif vocab id
                // Does this location already exist for this IATI ID?
                $geolocation = $geolocationrepo->findOneBy(
                    array(
                        'iatiActivityId' => $iatiActivityId,
                        'geolocationId' => $locationId,
                        'vocabId' => $vocabId,
                    )
                );

                if (!$geolocation) {
                    $geolocation = new Geolocation();
                    $geolocation->setIatiActivityId($iatiActivityId);
                    $geolocation->setGeolocationId($locationId);
                    $geolocation->setVocabId('99'); // TODO get a valif vocab id
                }
                $geolocation->setName($location['name']);
                $geolocation->setAdminCode1Code($location['admin1']['code']);
                $geolocation->setAdminCode1Name($location['admin1']['name']);
                $geolocation->setAdminCode2Code($location['admin2']['code']);
                $geolocation->setAdminCode2Name($location['admin2']['name']);
                $geolocation->setLatitude($location['geometry']['coordinates'][0]);
                $geolocation->setLongitude($location['geometry']['coordinates'][1]);
                $geolocation->setExactness($location['exactness']['code']);
                $geolocation->setClass($location['locationClass']['code']);
                $geolocation->setDescription($location['locationClass']['description']);
                $em->persist($geolocation);

                if (!$file->hasGeolocation($geolocation)) {
                    $file->addGeolocation($geolocation);
                }
            }
        }
        $em->persist($file);
        $em->flush();

        $geodata = $this->locationsToArray($file->getGeolocations());

        return [
            'name' => $file->getDocumentName(),
            'geolocations' => $geodata,
            'json' => json_encode(json_decode($json, true), JSON_PRETTY_PRINT),
        ];
    }

    private function locationsToArray($allLocations) {
        $geodata = [];
        foreach ($allLocations as $location) {
            $geodata[] = $this->locationToArray($location);
        }
        return $geodata;
    }

    /**
     * Flatten a geolocation as an array
     *
     * @param Geolocation $location
     */
    private function locationToArray(Geolocation $location) {
        $data = [];
        $data['vocab_id'] = $location->getVocabId();
        $data['geolocation_id'] = $location->getGeolocationId();
        $data['name'] = $location->getName();
        $data['admin_code_1_code'] = $location->getAdminCode1Code();
        $data['admin_code_1_name'] = $location->getAdminCode1Name();
        $data['admin_code_2_code'] = $location->getAdminCode2Code();
        $data['admin_code_2_name'] = $location->getAdminCode2Name();
        $data['latitude'] = $location->getLatitude();
        $data['longitude'] = $location->getLongitude();
        $data['exactness'] = $location->getExactness();
        $data['class'] = $location->getClass();
        $data['description'] = $location->getDescription();
        return $data;
    }

}
