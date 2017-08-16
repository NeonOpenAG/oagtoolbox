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
     * @ORM\ManyToMany(targetEntity="OagBundle\Entity\Sector")
     * @ORM\JoinTable(name="change_sector_add")
     */
    private $addedSectors;

    /**
     * @ORM\ManyToMany(targetEntity="OagBundle\Entity\Sector")
     * @ORM\JoinTable(name="change_sector_remove")
     */
    private $removedSectors;

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
     * @param ArrayCollection|array $addedSectors
     */
    public function setAddedSectors($addedSectors) {
        $this->addedSectors = $addedSectors;
    }

    /**
     * @param ArrayCollection|array $removedSectors
     */
    public function setRemovedSectors($removedSectors) {
        $this->removedSectors = $removedSectors;
    }

}

