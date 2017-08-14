<?php

namespace OagBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Geolocation
 *
 * @ORM\Table(name="geolocation")
 * @ORM\Entity(repositoryClass="OagBundle\Repository\GeolocationRepository")
 */
class Geolocation
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
     * @ORM\Column(name="iatiActivityId", type="string", length=255, nullable=true)
     */
    private $iatiActivityId;

    /**
     * @var string
     *
     * @ORM\Column(name="geolocationId", type="string", length=255)
     */
    private $geolocationId;

    /**
     * @var string
     *
     * @ORM\Column(name="vocabId", type="string", length=255)
     */
    private $vocabId;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="adminCode1Code", type="string", length=255)
     */
    private $adminCode1Code;

    /**
     * @var string
     *
     * @ORM\Column(name="adminCode1Name", type="string", length=255)
     */
    private $adminCode1Name;

    /**
     * @var string
     *
     * @ORM\Column(name="adminCode2Code", type="string", length=255)
     */
    private $adminCode2Code;

    /**
     * @var string
     *
     * @ORM\Column(name="adminCode2Name", type="string", length=255)
     */
    private $adminCode2Name;

    /**
     * @var string
     *
     * @ORM\Column(name="latitude", type="decimal", precision=14, scale=12)
     */
    private $latitude;

    /**
     * @var string
     *
     * @ORM\Column(name="longitude", type="decimal", precision=14, scale=12)
     */
    private $longitude;

    /**
     * @var string
     *
     * @ORM\Column(name="exactness", type="string", length=255)
     */
    private $exactness;

    /**
     * @var string
     *
     * @ORM\Column(name="class", type="string", length=255)
     */
    private $class;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=255)
     */
    private $description;


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
     * Set iatiActivityId
     *
     * @param string $iatiActivityId
     *
     * @return Geolocation
     */
    public function setIatiActivityId($iatiActivityId)
    {
        $this->iatiActivityId = $iatiActivityId;

        return $this;
    }

    /**
     * Get iatiActivityId
     *
     * @return string
     */
    public function getIatiActivityId()
    {
        return $this->iatiActivityId;
    }

    /**
     * Set geolocationId
     *
     * @param string $geolocationId
     *
     * @return Geolocation
     */
    public function setGeolocationId($geolocationId)
    {
        $this->geolocationId = $geolocationId;

        return $this;
    }

    /**
     * Get geolocationId
     *
     * @return string
     */
    public function getGeolocationId()
    {
        return $this->geolocationId;
    }

    /**
     * Set vocabId
     *
     * @param string $vocabId
     *
     * @return Geolocation
     */
    public function setVocabId($vocabId)
    {
        $this->vocabId = $vocabId;

        return $this;
    }

    /**
     * Get vocabId
     *
     * @return string
     */
    public function getVocabId()
    {
        return $this->vocabId;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Geolocation
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set adminCode1Code
     *
     * @param string $adminCode1Code
     *
     * @return Geolocation
     */
    public function setAdminCode1Code($adminCode1Code)
    {
        $this->adminCode1Code = $adminCode1Code;

        return $this;
    }

    /**
     * Get adminCode1Code
     *
     * @return string
     */
    public function getAdminCode1Code()
    {
        return $this->adminCode1Code;
    }

    /**
     * Set adminCode1Name
     *
     * @param string $adminCode1Name
     *
     * @return Geolocation
     */
    public function setAdminCode1Name($adminCode1Name)
    {
        $this->adminCode1Name = $adminCode1Name;

        return $this;
    }

    /**
     * Get adminCode1Name
     *
     * @return string
     */
    public function getAdminCode1Name()
    {
        return $this->adminCode1Name;
    }

    /**
     * Set adminCode2Code
     *
     * @param string $adminCode2Code
     *
     * @return Geolocation
     */
    public function setAdminCode2Code($adminCode2Code)
    {
        $this->adminCode2Code = $adminCode2Code;

        return $this;
    }

    /**
     * Get adminCode2Code
     *
     * @return string
     */
    public function getAdminCode2Code()
    {
        return $this->adminCode2Code;
    }

    /**
     * Set adminCode2Name
     *
     * @param string $adminCode2Name
     *
     * @return Geolocation
     */
    public function setAdminCode2Name($adminCode2Name)
    {
        $this->adminCode2Name = $adminCode2Name;

        return $this;
    }

    /**
     * Get adminCode2Name
     *
     * @return string
     */
    public function getAdminCode2Name()
    {
        return $this->adminCode2Name;
    }

    /**
     * Set latitude
     *
     * @param string $latitude
     *
     * @return Geolocation
     */
    public function setLatitude($latitude)
    {
        $this->latitude = $latitude;

        return $this;
    }

    /**
     * Get latitude
     *
     * @return string
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * Set longitude
     *
     * @param string $longitude
     *
     * @return Geolocation
     */
    public function setLongitude($longitude)
    {
        $this->longitude = $longitude;

        return $this;
    }

    /**
     * Get longitude
     *
     * @return string
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * Set exactness
     *
     * @param string $exactness
     *
     * @return Geolocation
     */
    public function setExactness($exactness)
    {
        $this->exactness = $exactness;

        return $this;
    }

    /**
     * Get exactness
     *
     * @return string
     */
    public function getExactness()
    {
        return $this->exactness;
    }

    /**
     * Set class
     *
     * @param string $class
     *
     * @return Geolocation
     */
    public function setClass($class)
    {
        $this->class = $class;

        return $this;
    }

    /**
     * Get class
     *
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return Geolocation
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

