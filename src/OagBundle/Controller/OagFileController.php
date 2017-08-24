<?php

namespace OagBundle\Controller;

use OagBundle\Entity\OagFile;
use OagBundle\Entity\Tag;
use OagBundle\Entity\Geolocation;
use OagBundle\Entity\SuggestedTag;
use OagBundle\Service\ActivityService;
use OagBundle\Service\Classifier;
use OagBundle\Service\Geocoder;
use OagBundle\Service\OagFileService;
use OagBundle\Service\Cove;
use OagBundle\Form\OagFileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use OagBundle\Service\TextExtractor\TextifyService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @Route("/oagFile")
 * @Template
 */
class OagFileController extends Controller
 {

    /**
     * View a specific IATI file.
     *
     * @Route("/iati/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function iatiAction(Request $request, OagFile $file) {
        $data = array();
        $srvActivity = $this->get(ActivityService::class);

        $data['id'] = $file->getId();
        $data['name'] = $file->getDocumentName();
        $data['mimetype'] = $file->getMimeType();
        $data['oagfiles_dir'] = $this->getParameter('oagxml_directory');

        $root = $srvActivity->load($file);
        $srvActivities = $this->get(ActivityService::class);
        $activities = $srvActivities->summariseToArray($root);

        $this->get('logger')->debug(sprintf('IATI Document %s has %d activites', count($activities), $file->getDocumentName()));
        $data['activities'] = $activities;

        $enhancementDocs = $file->getEnhancingDocuments();
        $data['enhancingDocuments'] = $enhancementDocs;

        // New supporting docuemnt form
        $em = $this->getDoctrine()->getManager();
        $oagfile = new OagFile();
        $oagfile->setFileType(OagFile::OAGFILE_IATI_ENHANCEMENT_DOCUMENT);
        $enhancementFileUploadForm = $this->createForm(OagFileType::class, $oagfile);
        $enhancementFileUploadForm->add('Upload', SubmitType::class, array(
            'attr' => array('class' => 'submit'),
        ));
        $data['enhancement_upload_form'] = $enhancementFileUploadForm->createView();

        if ($request) {
            $enhancementFileUploadForm->handleRequest($request);

            // TODO Check for too big files.
            if ($enhancementFileUploadForm->isSubmitted() && $enhancementFileUploadForm->isValid()) {
                $tmpFile = $oagfile->getDocumentName();

                $oagfile->setMimeType(mime_content_type($tmpFile->getPathname()));
                $filename = $tmpFile->getClientOriginalName();

                // Clear existing oagfile with the same name (we don't currently do versioning)
                $files = $em->getRepository('OagBundle:OagFile')
                    ->findByDocumentName($filename);
                foreach ($files as $_file) {
                    $em->remove($_file);
                }
                $em->flush();

                $tmpFile->move(
                    $this->getParameter('oagfiles_directory'), $filename
                );

                $oagfile->setDocumentName($filename);
                $file->addEnhancingDocument($oagfile);
                $em->persist($oagfile);
                $em->flush();

                return $this->redirect($this->generateUrl('oag_oagfile_iati', array('id' => $file->getId())));
            }
        }

        return $data;
    }

    /**
     * View a specific enhancement file.
     *
     * @Route("/enhancement/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function enhancementAction(Request $request, OagFile $file) {
        $srvActivity = $this->get(TextifyService::class);
        $data = array();
        $data['id'] = $file->getId();
        $data['name'] = $file->getDocumentName();
        $data['mimetype'] = $file->getMimeType();
        $data['text'] = $srvActivity->stripOagFile($file);
        $data['tags'] = $file->getSuggestedTags();

        $geolocations = $file->getGeolocations();
        $_geolocations = $this->locationsToArray($file->getGeolocations());

        $data['geolocations'] = $_geolocations;

        return $data;
    }

    /**
     * Download an IATI file.
     *
     * @Route("/download/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function downloadAction(Request $request, OagFile $file) {
        $srvOagFile = $this->get(OagFileService::class);

        return $this->file($srvOagFile->getPath($file));
    }

    /**
     * View the raw content of an IATI file.
     *
     * TODO support OagFiles in general, not just IATI ones
     *
     * @Route("/raw/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function rawAction(Request $request, OagFile $file) {
        $srvOagFile = $this->get(OagFileService::class);

        return new Response(
            $srvOagFile->getContents($file),
            Response::HTTP_OK,
            array('content-type' => 'text/xml')
        );
    }

    /**
     * Flatten a list of geolocations into arrays.
     *
     * @param Geolocation[] $allLocations
     */
    private function locationsToArray($allLocations) {
        $geodata = array();
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
        $data = array();
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
