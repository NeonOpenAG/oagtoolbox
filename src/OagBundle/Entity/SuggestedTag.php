<?php

namespace OagBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * SuggestedTag
 *
 * @ORM\Table(name="suggestedtag")
 * @ORM\Entity(repositoryClass="OagBundle\Repository\SuggestedTagRepository")
 */
class SuggestedTag {

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="confidence", type="decimal", precision=17, scale=14)
     */
    private $confidence;

    /**
     * @ORM\ManyToOne(targetEntity="\OagBundle\Entity\Tag", cascade={"persist"})
     */
    private $tag;

    /**
     * @var string
     *
     * @ORM\Column(name="activityId", type="string", nullable=TRUE)
     */
    private $activityId;

    /**
     * Get id
     *
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Set confidence
     *
     * @param string $confidence
     *
     * @return SuggestedTag
     */
    public function setConfidence($confidence) {
        $this->confidence = $confidence;

        return $this;
    }

    /**
     * Get confidence
     *
     * @return string
     */
    public function getConfidence() {
        return $this->confidence;
    }

    /**
     * @return \OagBundle\Entity\Tag
     */
    public function getTag() {
        return $this->tag;
    }

    public function setTag(Tag $tag) {
        $this->tag = $tag;
    }

    public function getActivityId() {
        return $this->activityId;
    }

    public function setActivityId($id) {
        $this->activityId = $id;
    }

    public function toString() {
        return sprintf('%s, %s, %s', $this->getTag(), $this->getConfidence(), $this->getActivityId());
    }

}
