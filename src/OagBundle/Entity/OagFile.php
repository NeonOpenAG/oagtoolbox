<?php

namespace OagBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use OagBundle\Entity\Activity;
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
   * @ORM\ManyToMany(targetEntity="OagBundle\Entity\Activity")
   */
  protected $activities;

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
    $this->activities = new ArrayCollection();
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
   * Get Activities
   *
   * @return string
   */
  public function getActivities() {
    return $this->activities;
  }

  /**
   * @param \OagBundle\Entity\Activity $activity
   * @return bool
   */
  public function hasActivity(Activity $activity) {
    return $this->getActivities()->contains($activity);
  }

  /**
   * @param \OagBundle\Entity\Activity $activity
   */
  public function addActivity(Activity $activity) {
    if (!$this->hasActivity($activity)) {
      $this->activities->add($activity);
    }
  }

  /**
   * @param \OagBundle\Entity\Activity $activity
   */
  public function removeActivity(Activity $activity) {
    if (!$this->hasActivity($activity)) {
      $this->activities->removeElement($activity);
    }
  }

  /**
   * Remove all sectors.
   */
  public function clearActivities() {
    $this->activities->clear();
  }

}
