<?php

namespace OagBundle\Controller;

use OagBundle\Entity\OagFile;
use OagBundle\Entity\Tag;
use OagBundle\Entity\SuggestedTag;
use OagBundle\Service\Classifier;
use OagBundle\Service\OagFileService;
use OagBundle\Service\TextExtractor\TextifyService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/classify")
 * @Template
 */
class ClassifyController extends Controller {

    /**
     * Classify an OagFile.
     *
     * @Route("/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function oagFileAction(Request $request, OagFile $file) {
        $srvClassifier = $this->get(Classifier::class);
        $srvOagFile = $this->get(OagFileService::class);

        $srvClassifier->classifyOagFile($file);

        return array('name' => $file->getDocumentName(), 'tags' => $file->getSuggestedTags()->getValues());
    }

}
