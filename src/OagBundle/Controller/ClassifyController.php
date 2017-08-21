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

}
