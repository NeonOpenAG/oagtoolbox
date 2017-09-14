<?php

namespace OagBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use OagBundle\Entity\SuggestedTag;
use OagBundle\Entity\FileType;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints\Range;

/**
 * EnhancementFile
 *
 * @ORM\Table(name="enhancement_file")
 * @ORM\Entity(repositoryClass="OagBundle\Repository\EnhancementFileRepository")
 */
class EnhancementFile {

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\ManyToMany(targetEntity="OagFile", mappedBy="enhancingDocuments")
     * */
    private $iatiParents;

    /**
     * @ORM\ManyToMany(targetEntity="OagBundle\Entity\SuggestedTag", cascade={"persist"})
     */
    protected $suggestedTags;

    /**
     * @ORM\ManyToMany(targetEntity="OagBundle\Entity\Geolocation", cascade={"persist"})
     */
    protected $geolocations;

    /**
     * @var string
     *
     * @ORM\Column(name="documentName", type="string", length=1024)
     */
    private $documentName;

    /**
     * @var string
     *
     * @ORM\Column(name="iatiActivityId", type="string", length=1024, nullable=true)
     */
    private $iatiActivityId;

    /**
     * @var string
     *
     * @ORM\Column(name="mimeType", type="string", length=1024)
     */
    private $mimeType;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="timestamp", type="datetime")
     */
    private $uploadDate;

    public function __construct() {
        $this->suggestedTags = new ArrayCollection();
        $this->iatiParents = new ArrayCollection();
        $this->geolocations = new ArrayCollection();
        $this->iatiActivityId = null;
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Set path
     *
     * @param string $path
     *
     * @return EnhancementFile
     */
    public function setDocumentName($documentName) {
        $this->documentName = $documentName;

        return $this;
    }

    /**
     * Get path
     *
     * @return string
     */
    public function getDocumentName() {
        return $this->documentName;
    }

    /**
     * Set path
     *
     * @param string $path
     *
     * @return EnhancementFile
     */
    public function setMimeType($mimeType) {
        $this->mimeType = $mimeType;

        return $this;
    }

    /**
     * Get path
     *
     * @return string
     */
    public function getMimeType() {
        return $this->mimeType;
    }

    /**
     * Get Tags
     *
     * @return ArrayCollection
     */
    public function getSuggestedTags() {
        return $this->suggestedTags;
    }

    /**
     * @param \OagBundle\Entity\SuggestedTag $activity
     *
     * @return bool
     */
    public function hasSuggestedTag(SuggestedTag $activity) {
        return $this->getSuggestedTags()->contains($activity);
    }

    /**
     * @param \OagBundle\Entity\SuggestedTag $activity
     */
    public function addSuggestedTag(SuggestedTag $activity) {
        if (!$this->hasSuggestedTag($activity)) {
            $this->suggestedTags->add($activity);
        }
    }

    /**
     * @param \OagBundle\Entity\SuggestedTag $activity
     */
    public function removeSuggestedTag(SuggestedTag $activity) {
        if ($this->hasSuggestedTag($activity)) {
            $this->suggestedTags->removeElement($activity);
        }
    }

    /**
     * Get Geolocations
     *
     * @return string
     */
    public function getGeolocations() {
        return $this->geolocations;
    }

    /**
     * @param \OagBundle\Entity\Geolocation $geolocation
     * @return bool
     */
    public function hasGeolocation(Geolocation $geolocation) {
        return $this->getGeolocations()->contains($geolocation);
    }

    /**
     * @param \OagBundle\Entity\Geolocation $geolocation
     */
    public function addGeolocation(Geolocation $geolocation) {
        if (!$this->hasGeolocation($geolocation)) {
            $this->geolocations->add($geolocation);
        }
    }

    /**
     * @param \OagBundle\Entity\Geolocation $geolocation
     */
    public function removeGeolocation(Geolocation $geolocation) {
        if (!$this->hasGeolocation($geolocation)) {
            $this->geolocations->removeElement($geolocation);
        }
    }

    /**
     * Remove all geolocations.
     */
    public function clearGeolocations() {
        $this->geolocations->clear();
    }

    /**
     * Remove all suggested tags.
     */
    public function clearSuggestedTags() {
        $this->suggestedTags->clear();
    }

    /**
     * Get parent documents
     *
     * @return ArrayCollection
     */
    public function getIatiParents() {
        return $this->iatiParents;
    }

    /**
     * Set IATI parents array
     *
     * @param ArrayCollection $iatiParents
     */
    public function setIatiParents(ArrayCollection $iatiParents) {
        $this->iatiParents = $iatiParents;
    }

    /**
     * Gets the date the EnhancementFile was uploaded.
     *
     * @return \DateTime
     */
    public function getUploadDate() {
        return $this->uploadDate;
    }

    /**
     * Sets the date the EnhancementFile was uploaded.
     *
     * @param \DateTime $uploadDate
     */
    public function setUploadDate(\DateTime $uploadDate) {
        $this->uploadDate = $uploadDate;
    }

    public function getIatiActivityId() {
        return $this->iatiActivityId;
    }

    public function setIatiActivityId($iatiActivityId) {
        $this->iatiActivityId = $iatiActivityId;
    }

}
