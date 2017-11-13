<?php

namespace OagBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping as ORM;

/**
 * OagFile
 *
 * @ORM\Table(name="oag_file")
 * @ORM\Entity(repositoryClass="OagBundle\Repository\OagFileRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class OagFile
{

    /**
     * @ORM\ManyToMany(targetEntity="OagBundle\Entity\SuggestedTag", cascade={"persist"})
     */
    protected $suggestedTags;
    /**
     * @ORM\ManyToMany(targetEntity="OagBundle\Entity\Geolocation", cascade={"persist"})
     */
    protected $geolocations;
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;
    /**
     * @ORM\ManyToMany(targetEntity="EnhancementFile", inversedBy="iatiParents", cascade={"persist"})
     */
    private $enhancingDocuments;

    /**
     * @ORM\OneToMany(targetEntity="OagBundle\Entity\Change", mappedBy="file")
     */
    private $changes;

    /**
     * @var string
     *
     * @ORM\Column(name="documentName", type="string", length=1024)
     */
    private $documentName;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="timestamp", type="datetime")
     */
    private $uploadDate;

    /**
     * @var boolean
     *
     * @ORM\Column(name="coved", type="boolean")
     */
    private $coved;

    public function __construct()
    {
        $this->coved = false;
        $this->suggestedTags = new ArrayCollection();
        $this->enhancingDocuments = new ArrayCollection();
        $this->geolocations = new ArrayCollection();
        $this->changes = new ArrayCollection();
    }

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
     * Get path
     *
     * @return string
     */
    public function getDocumentName()
    {
        return $this->documentName;
    }

    /**
     * Set path
     *
     * @param string $path
     *
     * @return OagFile
     */
    public function setDocumentName($documentName)
    {
        $this->documentName = $documentName;

        return $this;
    }

    /**
     * @param \OagBundle\Entity\SuggestedTag $activity
     */
    public function addSuggestedTag(SuggestedTag $activity)
    {
        if (!$this->hasSuggestedTag($activity)) {
            $this->suggestedTags->add($activity);
        }
    }

    /**
     * @param \OagBundle\Entity\SuggestedTag $activity
     *
     * @return bool
     */
    public function hasSuggestedTag(SuggestedTag $activity)
    {
        return $this->getSuggestedTags()->contains($activity);
    }

    /**
     * Get Tags
     *
     * @return ArrayCollection
     */
    public function getSuggestedTags()
    {
        return $this->suggestedTags;
    }

    /**
     * @param \OagBundle\Entity\SuggestedTag $activity
     */
    public function removeSuggestedTag(SuggestedTag $activity)
    {
        if ($this->hasSuggestedTag($activity)) {
            $this->suggestedTags->removeElement($activity);
        }
    }

    /**
     * @param \OagBundle\Entity\Geolocation $geolocation
     */
    public function addGeolocation(Geolocation $geolocation)
    {
        if (!$this->hasGeolocation($geolocation)) {
            $this->geolocations->add($geolocation);
        }
    }

    /**
     * @param \OagBundle\Entity\Geolocation $geolocation
     * @return bool
     */
    public function hasGeolocation(Geolocation $geolocation)
    {
        return $this->getGeolocations()->contains($geolocation);
    }

    /**
     * Get Geolocations
     *
     * @return string
     */
    public function getGeolocations()
    {
        return $this->geolocations;
    }

    /**
     * @param \OagBundle\Entity\Geolocation $geolocation
     */
    public function removeGeolocation(Geolocation $geolocation)
    {
        if (!$this->hasGeolocation($geolocation)) {
            $this->geolocations->removeElement($geolocation);
        }
    }

    public function addEnhancingDocument(EnhancementFile $file)
    {
        if (!$this->hasEnhancingDocument($file)) {
            $this->getEnhancingDocuments()->add($file);
        }
    }

    public function hasEnhancingDocument(EnhancementFile $file)
    {
        return $this->getEnhancingDocuments()->contains($file);
    }

    /**
     * Get enhancing documents
     *
     * @return ArrayCollection
     */
    public function getEnhancingDocuments()
    {
        return $this->enhancingDocuments;
    }

    /**
     * Set enhancements array
     *
     * @param ArrayCollection $iatiParents
     */
    public function setEnhancingDocuments(ArrayCollection $enhancingDocuments)
    {
        $this->enhancingDocuments = $enhancingDocuments;
    }

    public function removeEnhancingDocument(EnhancementFile $file)
    {
        if ($this->hasEnhancingDocument($file)) {
            $this->getEnhancingDocuments()->remove($file);
        }
    }

    /**
     * @return \OagBundle\Entity\Change[]
     */
    public function getChanges()
    {
        return $this->changes;
    }

    /**
     * @param \OagBundle\Entity\Change $change
     */
    public function addChange($change)
    {
        if (!$this->changes->contains($change)) {
            $this->changes->add($change);
        }
    }

    /**
     * Gets the date the OagFile was uploaded.
     *
     * @return \DateTime
     */
    public function getUploadDate()
    {
        return $this->uploadDate;
    }

    /**
     * Sets the date the OagFile was uploaded.
     *
     * @param \DateTime $uploadDate
     */
    public function setUploadDate(\DateTime $uploadDate)
    {
        $this->uploadDate = $uploadDate;
    }

    public function isCoved()
    {
        return $this->coved;
    }

    public function setCoved($coved)
    {
        $this->coved = $coved;
    }

    /**
     * @ORM\PreRemove
     */
    public function tidyUp(LifecycleEventArgs $args)
    {
        // NOTE: this just removes the relationships, not the entities themselves
        $this->clearSuggestedTags();
        $this->clearGeolocations();
        $this->clearEnhancingDocuments();
        $this->clearChanges();
        $args->getObjectManager()->persist($this);
        $args->getObjectManager()->flush($this);
    }

    /**
     * Remove all suggested tags.
     */
    public function clearSuggestedTags()
    {
        $this->suggestedTags->clear();
    }

    /**
     * Remove all geolocations.
     */
    public function clearGeolocations()
    {
        $this->geolocations->clear();
    }

    public function clearEnhancingDocuments()
    {
        $this->getEnhancingDocuments()->clear();
    }

    public function clearChanges()
    {
        foreach ($this->changes as $change) {
            $change->setFile(null);
        }
        $this->changes->clear();
    }

}
