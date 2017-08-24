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
     * Classify an IATI file.
     *
     * @Route("/xml/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function xmlAction(Request $request, OagFile $file) {
        $srvClassifier = $this->get(Classifier::class);
        $srvOagFile = $this->get(OagFileService::class);

        $rawXml = $srvOagFile->getContents($file);

        $json = $srvClassifier->processXML($rawXml); 

        $file->clearSuggestedTags();

        if ($json['status']) {
            throw \RuntimeException('Classifier service exited with a non 0 status');
        }

        foreach ($json['data'] as $block) {
            foreach ($block as $part) {
                foreach ($part as $activityId => $tags) {
                    $this->persistTags($tags, $file, $activityId);
                }
            }
        }

        $em = $this->getDoctrine()->getManager();
        $em->persist($file);
        $em->flush();

        return array('name' => $file->getDocumentName(), 'tags' => $file->getSuggestedTags()->getValues());
    }

    /**
     * Classify an enhancement file.
     *
     * @Route("/text/{id}")
     * @ParamConverter("file", class="OagBundle:OagFile")
     */
    public function textAction(Request $request, OagFile $file) {
        $srvClassifier = $this->get(Classifier::class);
        $srvTextify = $this->get(TextifyService::class);

        $rawText = $srvTextify->stripOagFile($file);

        if ($rawText === false) {
            // textifier failed
            throw \RuntimeException('Unsupported file type to strip text from');
        }

        $json = $srvClassifier->processString($rawText);

        $file->clearSuggestedTags();

        // TODO if $row['status'] == 0
        $this->persistTags($json['data'], $file);

        $em = $this->getDoctrine()->getManager();
        $em->persist($file);
        $em->flush();

        return array('name' => $file->getDocumentName(), 'tags' => $file->getSuggestedTags()->getValues());
    }

    /**
     * Persists Oag tags from API response to database.
     */
    private function persistTags($tags, $file, $activityId = null) {
        $em = $this->getDoctrine()->getManager();
        $tagRepo = $this->container->get('doctrine')->getRepository(Tag::class);

        foreach ($tags as $row) {
            $code = $row['code'];
            if ($code === null) {
                // We get a single array of nulls back if no match is found.
                break;
            }

            $description = $row['description'];
            $confidence = $row['confidence'];

            $vocab = $this->getParameter('classifier')['vocabulary'];
            $vocabUri = $this->getParameter('classifier')['vocabulary_uri'];

            $findBy = array(
                'code' => $code,
                'vocabulary' => $vocab
            );

            // if there is a vocab uri in the config, use it, if not, don't
            if (strlen($vocabUri) > 0) {
                $findBy['vocabulary_uri'] = $vocabUri;
            } else {
                $vocabUri = null;
            }

            // Check that the code exists in the system
            $tag = $tagRepo->findOneBy($findBy);
            if (!$tag) {
                $this->container->get('logger')
                    ->info(sprintf('Creating new code %s (%s)', $code, $description));
                $tag = new Tag();
                $tag->setCode($code);
                $tag->setDescription($description);
                $tag->setVocabulary($vocab, $vocabUri);
                $em->persist($tag);
            }

            $sugTag = new \OagBundle\Entity\SuggestedTag();
            $sugTag->setTag($tag);
            $sugTag->setConfidence($confidence);
            if (!is_null($activityId)) {
                $sugTag->setActivityId($activityId);
            }

            $em->persist($sugTag);
            $file->addSuggestedTag($sugTag);
        }
    }

}
