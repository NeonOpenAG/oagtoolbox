<?php

namespace OagBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use OagBundle\Entity\OagFile;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Sector
 *
 * @ORM\Table(name="sector")
 * @ORM\Entity(repositoryClass="OagBundle\Repository\SectorRepository")
 */
class Sector
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
   * @ORM\Column(name="code", type="string", length=32, unique=true)
   */
    private $code;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=255)
     */
    private $description;

  public function __construct() {
    $this->oagFile = new ArrayCollection();
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
     * Set oagFile
     *
     * @param OagFile $oagFile
     *
     * @return OagFile
     */
    public function setOagFile($oagFile)
    {
        $this->oagFile = $oagFile;

        return $this;
    }

    /**
     * Get oagFile
     *
     * @return OagFile
     */
    public function getOagFile()
    {
        return $this->oagFile;
    }

    /**
     * Set code
     *
     * @param string $code
     *
     * @return Sector
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get code
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return Sector
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

}

