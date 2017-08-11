<?php

namespace OagBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use OagBundle\Entity\Sector;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * OagFile
 *
 * @ORM\Table(name="oag_file")
 * @ORM\Entity(repositoryClass="OagBundle\Repository\OagFileRepository")
 */
class OagFile {

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
     * @ORM\ManyToMany(targetEntity="OagBundle\Entity\Sector")
     */
    protected $sectors;

    /**
     * @var string
     *
     * @ORM\Column(name="documentName", type="string", length=1024)
     */
    private $documentName;

    /**
     * @var string
     *
     * @ORM\Column(name="mimeType", type="string", length=1024)
     */
    private $mimeType;

    public function __construct() {
        $this->sectors = new ArrayCollection();
        $this->iatiParents = new \Doctrine\Common\Collections\ArrayCollection();
        $this->enhancingDocuments = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Get Sectors
     *
     * @return string
     */
    public function getSectors() {
        return $this->sectors;
    }

    /**
     * @param \OagBundle\Entity\Sector $activity
     * @return bool
     */
    public function hasSector(Sector $activity) {
        return $this->getSectors()->contains($activity);
    }

    /**
     * @param \OagBundle\Entity\Sector $activity
     */
    public function addSector(Sector $activity) {
        if (!$this->hasSector($activity)) {
            $this->sectors->add($activity);
        }
    }

    /**
     * @param \OagBundle\Entity\Sector $activity
     */
    public function removeSector(Sector $activity) {
        if (!$this->hasSector($activity)) {
            $this->sectors->removeElement($activity);
        }
    }

    /**
     * Remove all sectors.
     */
    public function clearSectors() {
        $this->sectors->clear();
    }

    public function getIatiParents() {
        return $this->iatiParents;
    }

    public function getEnhancingDocuments() {
        return $this->enhancingDocuments;
    }

    public function setIatiParents(ArrayCollection $iatiParents) {
        $this->iatiParents = $iatiParents;
    }

    public function setEnhancingDocuments(ArrayCollection $enhancingDocuments) {
        $this->enhancingDocuments = $enhancingDocuments;
    }

}
