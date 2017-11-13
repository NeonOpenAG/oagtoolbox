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
class Change
{
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
     * @ORM\ManyToOne(targetEntity="\OagBundle\Entity\OagFile", inversedBy="changes")
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
     * @ORM\ManyToMany(targetEntity="OagBundle\Entity\Geolocation")
     * @ORM\JoinTable(name="change_geoloc_add")
     */
    private $addedGeolocs;

    /**
     * @ORM\ManyToMany(targetEntity="OagBundle\Entity\Geolocation")
     * @ORM\JoinTable(name="change_geoloc_remove")
     */
    private $removedGeolocs;

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get timestamp
     *
     * @return \DateTime
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * Set timestamp
     *
     * @param \DateTime $timestamp
     *
     * @return Change
     */
    public function setTimestamp($timestamp)
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * Get activityId
     *
     * @return string
     */
    public function getActivityId()
    {
        return $this->activityId;
    }

    /**
     * Set activityId
     *
     * @param string $activityId
     *
     * @return Change
     */
    public function setActivityId($activityId)
    {
        $this->activityId = $activityId;

        return $this;
    }

    /**
     * Get file
     *
     * @return OagFile
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Set file
     *
     * @param OagFile $file
     *
     * @return Change
     */
    public function setFile($file)
    {
        $this->file = $file;

        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getAddedTags()
    {
        return $this->addedTags;
    }

    /**
     * @param ArrayCollection|array $addedTags
     */
    public function setAddedTags($addedTags)
    {
        if (is_array($addedTags)) {
            $addedTags = new ArrayCollection($addedTags);
        }
        $this->addedTags = $addedTags;
    }

    /**
     * @return ArrayCollection
     */
    public function getRemovedTags()
    {
        return $this->removedTags;
    }

    /**
     * @param ArrayCollection|array $removedTags
     */
    public function setRemovedTags($removedTags)
    {
        if (is_array($removedTags)) {
            $removedTags = new ArrayCollection($removedTags);
        }
        $this->removedTags = $removedTags;
    }

    /**
     * @return ArrayCollection
     */
    public function getAddedGeolocs()
    {
        return $this->addedGeolocs;
    }

    /**
     * @param ArrayCollection|array $addedGeolocs
     */
    public function setAddedGeolocs($addedGeolocs)
    {
        if (is_array($addedGeolocs)) {
            $addedGeolocs = new ArrayCollection($addedGeolocs);
        }
        $this->addedGeolocs = $addedGeolocs;
    }

    /**
     * @return ArrayCollection
     */
    public function getRemovedGeolocs()
    {
        return $this->removedGeolocs;
    }

    /**
     * @param ArrayCollection|array $removedGeolocs
     */
    public function setRemovedGeolocs($removedGeolocs)
    {
        if (is_array($removedGeolocs)) {
            $removedGeolocs = new ArrayCollection($removedGeolocs);
        }
        $this->removedGeolocs = $removedGeolocs;
    }

}

