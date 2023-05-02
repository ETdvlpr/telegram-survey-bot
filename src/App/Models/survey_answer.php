<?php

namespace App\Models;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="survey_answer")
 */
class Answer extends MyEntity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private $id;

    /**
     * @ORM\Column(type="text",nullable=true)
     */
    private $text;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $userId;

    /**
     * @ORM\ManyToOne(targetEntity="SurveyQuestion", inversedBy="answers")
     * @ORM\JoinColumn(name="survey_question_id", referencedColumnName="id", nullable=false)
     * @var SurveyQuestion
     */
    private $surveyQuestion;

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
     * @return Answer
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
     * Set userId.
     *
     * @param string $userId
     *
     * @return Answer
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Get userId.
     *
     * @return string
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * Set deleted.
     *
     * @param bool $deleted
     *
     * @return Answer
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
     * Set surveyQuestion.
     *
     * @param \App\Models\SurveyQuestion $surveyQuestion
     *
     * @return Answer
     */
    public function setSurveyQuestion(\App\Models\SurveyQuestion $surveyQuestion)
    {
        $this->surveyQuestion = $surveyQuestion;

        return $this;
    }

    /**
     * Get surveyQuestion.
     *
     * @return \App\Models\SurveyQuestion
     */
    public function getSurveyQuestion()
    {
        return $this->surveyQuestion;
    }

    /**
     * Get question assosiated with answer
     * 
     * @return Question
     */
    public function getQuestion(): Question
    {
        return $this->surveyQuestion->getQuestion();
    }
}
