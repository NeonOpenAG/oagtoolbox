<?php

namespace OagBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Sector
 *
 * @ORM\Table(name="suggestedsector")
 * @ORM\Entity(repositoryClass="OagBundle\Repository\SuggestedSectorRepository")
 */
class SuggestedSector {

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
     * @ORM\ManyToOne(targetEntity="\OagBundle\Entity\Sector")
     */
    private $sector;

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
     * @return SuggestedSector
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
     * @return \OagBundle\Entity\Sector
     */
    public function getSector() {
        return $this->sector;
    }

    public function setSector(Sector $sector) {
        $this->sector = $sector;
    }

    public function getActivityId() {
        return $this->activityId;
    }

    public function setActivityId($id) {
        $this->activityId = $id;
    }
}
