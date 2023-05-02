<?php

namespace App\Models;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="survey")
 */
class Survey extends MyEntity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $name;

    /**
     * @ORM\OneToMany(targetEntity="SurveyQuestion", mappedBy="survey")
     * @ORM\OrderBy({"order" = "ASC"})
     * @var Collection
     */
    private $surveyQuestions;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->surveyQuestions = new \Doctrine\Common\Collections\ArrayCollection();
    }

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
     * Set name.
     *
     * @param string $name
     *
     * @return Survey
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set deleted.
     *
     * @param bool $deleted
     *
     * @return Survey
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;

        return $this;
    }

    /**
     * Get deleted.
     *
     * @return bool
     */
    public function getDeleted()
    {
        return $this->deleted;
    }

    /**
     * Add surveyQuestion.
     *
     * @param \App\Models\SurveyQuestion $surveyQuestion
     *
     * @return Survey
     */
    public function addSurveyQuestion(\App\Models\SurveyQuestion $surveyQuestion)
    {
        $this->surveyQuestions[] = $surveyQuestion;

        return $this;
    }

    /**
     * Remove surveyQuestion.
     *
     * @param \App\Models\SurveyQuestion $surveyQuestion
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeSurveyQuestion(\App\Models\SurveyQuestion $surveyQuestion)
    {
        return $this->surveyQuestions->removeElement($surveyQuestion);
    }

    /**
     * Get surveyQuestions.
     *
     * @return \Doctrine\Common\Collections\Collection|Selectable|SurveyQuestion
     */
    public function getSurveyQuestions()
    {
        return $this->surveyQuestions;
    }

    /**
     * Get surveyQuestions.
     *
     * @param Question $q
     * @return SurveyQuestion
     */
    public function getSurveyQuestion(Question $q) : ?SurveyQuestion
    {
        return $this->getSurveyQuestions()->findFirst(function ($sq) use ($q) {
            return $sq == $q->getId();
            // return $sq->getQuestion()->getId() == $q->getId();
        });
    }

    /**
     * Get questions.
     *
     * @return array|Question[]
     */
    public function getQuestions()
    {
        $questions = [];
        foreach ($this->surveyQuestions as $q) {
            array_push($questions, $q->getQuestion());
        }
        return $questions;
    }
}
