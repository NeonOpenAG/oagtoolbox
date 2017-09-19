<?php

namespace OagBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Geolocation
 *
 * @ORM\Table(name="geolocation")
 * @ORM\Entity(repositoryClass="OagBundle\Repository\GeolocationRepository")
 */
class Geolocation {

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
     * Corresponding IATI activity, if the suggestion is less generic than
     * 'applicable to the entire file of activities'.
     *
     * @ORM\Column(name="iatiActivityId", type="string", length=255, nullable=true)
     */
    private $iatiActivityId;

    /**
     * @var string
     *
     * The name of the location.
     *
     * XPath: ./name/narrative
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @var string
     *
     * XPath: ./location-id/@code
     *
     * @ORM\Column(name="locationIdCode", type="string", length=255)
     */
    private $locationIdCode;

    /**
     * @var string
     *
     * XPath: ./location-id/@vocabulary
     *
     * @ORM\Column(name="locationIdVocab", type="string", length=255)
     */
    private $locationIdVocab;

    /**
     * @var string
     *
     * XPath: ./feature-designation/@code
     *
     * @ORM\Column(name="featureDesignation", type="string", length=255)
     */
    private $featureDesignation;

    /**
     * @var string
     *
     * XPath: ./point/pos (first half)
     *
     * @ORM\Column(name="pointPosLat", type="decimal", precision=14, scale=12)
     */
    private $pointPosLat;

    /**
     * @var string
     *
     * XPath: ./point/pos (second half)
     *
     * @ORM\Column(name="pointPosLong", type="decimal", precision=14, scale=12)
     */
    private $pointPosLong;

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
     * Set locationIdCode
     *
     * @param string $locationIdCode
     *
     * @return Geolocation
     */
    public function setLocationIdCode($locationIdCode)
    {
        $this->locationIdCode = $locationIdCode;

        return $this;
    }

    /**
     * Get locationIdCode
     *
     * @return string
     */
    public function getLocationIdCode()
    {
        return $this->locationIdCode;
    }

    /**
     * Set locationIdVocab
     *
     * @param string $locationIdVocab
     *
     * @return Geolocation
     */
    public function setLocationIdVocab($locationIdVocab)
    {
        $this->locationIdVocab = $locationIdVocab;

        return $this;
    }

    /**
     * Get locationIdVocab
     *
     * @return string
     */
    public function getLocationIdVocab()
    {
        return $this->locationIdVocab;
    }

    /**
     * Set featureDesignation
     *
     * @param string $featureDesignation
     *
     * @return Geolocation
     */
    public function setFeatureDesignation($featureDesignation)
    {
        $this->featureDesignation = $featureDesignation;
    }

    /**
     * Get featureDesignation
     *
     * @return string
     */
    public function getFeatureDesignation()
    {
        return $this->featureDesignation;
    }

    /**
     * Set pointPosLat
     *
     * @param string $pointPosLat
     *
     * @return Geolocation
     */
    public function setPointPosLat($pointPosLat)
    {
        $this->pointPosLat = $pointPosLat;

        return $this;
    }

    /**
     * Get pointPosLat
     *
     * @return string
     */
    public function getPointPosLat()
    {
        return $this->pointPosLat;
    }

    /**
     * Set pointPosLong
     *
     * @param string $pointPosLong
     *
     * @return Geolocation
     */
    public function setPointPosLong($pointPosLong)
    {
        $this->pointPosLong = $pointPosLong;

        return $this;
    }

    /**
     * Get pointPosLong
     *
     * @return string
     */
    public function getPointPosLong()
    {
        return $this->pointPosLong;
    }

}

