<?php

namespace App\Models;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;

/**
 * @ORM\Entity
 * @ORM\Table(name="survey_join_question")
 */
class SurveyQuestion
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $order;

    /**
     * @ORM\Column(type="boolean")
     */
    private $required;

    /**
     * @ORM\ManyToOne(targetEntity="Question")
     * @ORM\JoinColumn(name="question_id", referencedColumnName="id", nullable=false)
     * @var Question
     */
    private $question;

    /**
     * @ORM\ManyToOne(targetEntity="Survey", inversedBy="surveyQuestions")
     * @ORM\JoinColumn(name="survey_id", referencedColumnName="id", nullable=false)
     * @var Survey
     */
    private $survey;

    /**
     * @ORM\OneToMany(targetEntity="Answer", mappedBy="surveyQuestion")
     * @ORM\OrderBy({"updatedAt" = "DESC"})
     * @var Collection
     */
    private $answers;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->answers = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set order.
     *
     * @param int $order
     *
     * @return SurveyQuestion
     */
    public function setOrder($order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Get order.
     *
     * @return int
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * Set required.
     *
     * @param bool $required
     *
     * @return SurveyQuestion
     */
    public function setRequired($required)
    {
        $this->required = $required;

        return $this;
    }

    /**
     * Get required.
     *
     * @return bool
     */
    public function getRequired()
    {
        return $this->required;
    }

    /**
     * Set question.
     *
     * @param \App\Models\Question $question
     *
     * @return SurveyQuestion
     */
    public function setQuestion(\App\Models\Question $question)
    {
        $this->question = $question;

        return $this;
    }

    /**
     * Get question.
     *
     * @return \App\Models\Question
     */
    public function getQuestion()
    {
        return $this->question;
    }

    /**
     * Set survey.
     *
     * @param \App\Models\Survey $survey
     *
     * @return SurveyQuestion
     */
    public function setSurvey(\App\Models\Survey $survey)
    {
        $this->survey = $survey;

        return $this;
    }

    /**
     * Get survey.
     *
     * @return \App\Models\Survey
     */
    public function getSurvey()
    {
        return $this->survey;
    }

    /**
     * Add answer.
     *
     * @param \App\Models\Answer $answer
     *
     * @return SurveyQuestion
     */
    public function addAnswer(\App\Models\Answer $answer)
    {
        $this->answers[] = $answer;

        return $this;
    }

    /**
     * Remove answer.
     *
     * @param \App\Models\Answer $answer
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeAnswer(\App\Models\Answer $answer)
    {
        return $this->answers->removeElement($answer);
    }

    /**
     * Get answers.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getAnswers()
    {
        return $this->answers;
    }

    /**
     * Get Answer from specific user.
     *
     * @return ?Answer
     */
    ///@TODO optimize this function
    public function getAnswersFromUser(int $userId)
    {
        foreach ($this->answers as $answer) {
            if ($answer->getUserId() == $userId)
                return $answer;
        }
    }
}
