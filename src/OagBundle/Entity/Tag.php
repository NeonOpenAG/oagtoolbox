<?php

namespace OagBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Code
 *
 * @ORM\Table(name="tag")
 * @ORM\Entity(repositoryClass="OagBundle\Repository\TagRepository")
 */
class Tag {

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
     * @ORM\Column(name="code", type="string", length=32)
     */
    private $code;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=255)
     */
    private $description;

    /**
     * @var string
     *
     * @ORM\Column(name="vocabulary", type="string", nullable=FALSE)
     */
    private $vocabulary;

    /**
     * @var string
     *
     * @ORM\Column(name="vocabulary_uri", type="string", nullable=TRUE)
     */
    private $vocabulary_uri;

    /**
     * Get id
     *
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Set code
     *
     * @param string $code
     *
     * @return Tag
     */
    public function setCode($code) {
        $this->code = $code;

        return $this;
    }

    /**
     * Get code
     *
     * @return string
     */
    public function getCode() {
        return $this->code;
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return Tag
     */
    public function setDescription($description) {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription() {
        return $this->description;
    }

    /**
     * Sets the vocabulary and conditionally vocabulary_uri if invalid.
     *
     * @param string $vocab
     * @param string $vocabUri
     */
    public function setVocabulary($vocab, $vocabUri = null) {
        $this->vocabulary = $vocab;
        if($vocab === "98" || $vocab === "99") {
            if(is_null($vocabUri)) {
                throw new \Exception("Invalid vocabUri provided.");
            }

            $this->vocabulary_uri = $vocabUri;
        }
    }

    public function getVocabulary() {
        return $this->vocabulary;
    }

    public function getVocabularyUri() {
        return $this->vocabulary_uri;
    }

}
