<?php

namespace App\Models;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="survey_question")
 */
class Question extends MyEntity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private $id;

    //json array of choices
    /**
     * @ORM\Column(type="text",nullable=true)
     */
    private ?string $choices;

    /**
     * @ORM\Column(type="text",nullable=true)
     */
    private $text;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $type;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set text.
     *
     * @param string|null $text
     *
     * @return Question
     */
    public function setText($text = null)
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Get text.
     *
     * @return string|null
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * Set type.
     *
     * @param string $type
     *
     * @return Question
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set choices.
     *
     * @param array $choices
     *
     * @return Question
     */
    public function setChoices($choices)
    {
        $this->choices = json_encode($choices, JSON_UNESCAPED_UNICODE);

        return $this;
    }

    /**
     * Get choices.
     *
     * @return array
     */
    public function getChoices()
    {
        return $this->choices != null ? json_decode($this->choices, true) : [];
    }
}
