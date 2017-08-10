<?php

namespace OagBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use OagBundle\Entity\Code;

/**
 * Activity
 *
 * @ORM\Table(name="activity")
 * @ORM\Entity(repositoryClass="OagBundle\Repository\ActivityRepository")
 */
class Activity
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
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set confidence
     *
     * @param string $confidence
     *
     * @return Activity
     */
    public function setConfidence($confidence)
    {
        $this->confidence = $confidence;

        return $this;
    }

    /**
     * Get confidence
     *
     * @return string
     */
    public function getConfidence()
    {
        return $this->confidence;
    }

  public function getCode() {
        return $this->code;
    }

  public function setCode(Code $code) {
        $this->code = $code;
    }

}

