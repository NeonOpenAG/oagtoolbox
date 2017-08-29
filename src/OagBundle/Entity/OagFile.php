<?php

namespace OagBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use OagBundle\Entity\SuggestedTag;
use OagBundle\Entity\FileType;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints\Range;

/**
 * OagFile
 *
 * @ORM\Table(name="oag_file")
 * @ORM\Entity(repositoryClass="OagBundle\Repository\OagFileRepository")
 */
class OagFile {

    const OAGFILE_IATI_DOCUMENT = 1;
    const OAGFILE_IATI_SOURCE_DOCUMENT = 2;
    const OAGFILE_IATI_ENHANCEMENT_DOCUMENT = 4;

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
     * @ORM\ManyToMany(targetEntity="OagFile", inversedBy="iatiParents")
     * */
    private $enhancingDocuments;

    /**
     * @ORM\ManyToMany(targetEntity="OagBundle\Entity\SuggestedTag")
     */
    protected $suggestedTags;

    /**
     * @ORM\ManyToMany(targetEntity="OagBundle\Entity\Geolocation")
     */
    protected $geolocations;

    /**
     * @var string
     *
     * @ORM\Column(name="documentName", type="string", length=1024)
     */
    private $documentName;

    /**
     * @var integer
     *
     * @ORM\Column(name="type", type="integer")
     * @Range(
     *      min = 1,
     *      max = 7,
     *      minMessage = "Invalid type specified",
     *      maxMessage = "Unsupported type.")
     */
    private $fileType;

    /**
     * @var string
     *
     * @ORM\Column(name="mimeType", type="string", length=1024)
     */
    private $mimeType;

    public function __construct() {
        $this->suggestedTags = new ArrayCollection();
        $this->iatiParents = new ArrayCollection();
        $this->enhancingDocuments = new ArrayCollection();
        $this->geolocations = new ArrayCollection();
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
     * @return OagFile
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
     * @return OagFile
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
        if (!$this->hasSuggestedTag($activity)) {
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
     * Get enhancing documents
     *
     * @return ArrayCollection
     */
    public function getEnhancingDocuments() {
        return $this->enhancingDocuments;
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
     * Set enhancements array
     *
     * @param ArrayCollection $iatiParents
     */
    public function setEnhancingDocuments(ArrayCollection $enhancingDocuments) {
        $this->enhancingDocuments = $enhancingDocuments;
    }

    public function addEnhancingDocument(OagFile $file) {
        if (!$this->hasEnhancingDocument($file)) {
            $this->getEnhancingDocuments()->add($file);
        }
    }

    public function removeEnhancingDocument(OagFile $file) {
        if ($this->hasEnhancingDocument($file)) {
            $this->getEnhancingDocuments()->remove($file);
        }
    }

    public function hasEnhancingDocument(OagFile $file) {
        return $this->getEnhancingDocuments()->contains($file);
    }

    public function clearEnhancingDocuments() {
        $this->getEnhancingDocuments()->clear();
    }

    /**
     * Get file type
     *
     * @return integer
     */
    public function getFileType() {
        return $this->fileType;
    }

    /**
     * Set file type
     *
     * @param integer $fileType
     */
    public function setFileType($fileType) {
        $this->fileType = $fileType;
    }

}
