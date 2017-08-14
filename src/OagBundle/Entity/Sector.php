<?php

namespace OagBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use OagBundle\Entity\Code;

/**
 * Sector
 *
 * @ORM\Table(name="activity")
 * @ORM\Entity(repositoryClass="OagBundle\Repository\SectorRepository")
 */
class Sector {

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
     * @ORM\ManyToOne(targetEntity="\OagBundle\Entity\Code", inversedBy="activities")
     * @ORM\JoinColumn(name="sector_id", referencedColumnName="id")
     */
    private $code;

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
     * @return Sector
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

    public function getCode() {
        return $this->code;
    }

    public function setCode(Code $code) {
        $this->code = $code;
    }

    public function getActivityId() {
        return $this->activityId;
    }

    public function setActivityId($id) {
        $this->activityId = $id;
    }

}
