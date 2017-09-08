<?php

namespace OagBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Change
 *
 * @ORM\Table(name="change")
 * @ORM\Entity(repositoryClass="OagBundle\Repository\ChangeRepository")
 */
class Change {
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="timestamp", type="datetime")
     */
    private $timestamp;

    /**
     * @var string
     *
     * @ORM\Column(name="activity_id", type="string", length=255)
     */
    private $activityId;

    /**
     * @ORM\ManyToOne(targetEntity="\OagBundle\Entity\OagFile")
     */
    private $file;

    /**
     * @ORM\ManyToMany(targetEntity="OagBundle\Entity\Tag")
     * @ORM\JoinTable(name="change_tag_add")
     */
    private $addedTags;

    /**
     * @ORM\ManyToMany(targetEntity="OagBundle\Entity\Tag")
     * @ORM\JoinTable(name="change_tag_remove")
     */
    private $removedTags;

    /**
     * Get id
     *
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Set timestamp
     *
     * @param \DateTime $timestamp
     *
     * @return Change
     */
    public function setTimestamp($timestamp) {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * Get timestamp
     *
     * @return \DateTime
     */
    public function getTimestamp() {
        return $this->timestamp;
    }

    /**
     * Set activityId
     *
     * @param string $activityId
     *
     * @return Change
     */
    public function setActivityId($activityId) {
        $this->activityId = $activityId;

        return $this;
    }

    /**
     * Get activityId
     *
     * @return string
     */
    public function getActivityId() {
        return $this->activityId;
    }

    /**
     * Set file
     *
     * @param OagFile $file
     *
     * @return Change
     */
    public function setFile($file) {
        $this->file = $file;

        return $this;
    }

    /**
     * Get file
     *
     * @return OagFile
     */
    public function getFile() {
        return $this->file;
    }

    /**
     * @param ArrayCollection|array $addedTags
     */
    public function setAddedTags($addedTags) {
        if (is_array($addedTags)) {
            $addedTags = new ArrayCollection($addedTags);
        }
        $this->addedTags = $addedTags;
    }

    /**
     * @return ArrayCollection|array
     */
    public function getAddedTags() {
        return $this->addedTags;
    }

    /**
     * @param ArrayCollection|array $removedTags
     */
    public function setRemovedTags($removedTags) {
        if (is_array($removedTags)) {
            $removedTags = new ArrayCollection($removedTags);
        }
        $this->removedTags = $removedTags;
    }

    /**
     * @return ArrayCollection|array
     */
    public function getRemovedTags() {
        return $this->removedTags;
    }

}

