<?php

namespace App\Controllers;

use App\Models\Survey;
use App\Models\Question;
use App\Models\Answer;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\Message;
use Longman\TelegramBot\Entities\ServerResponse;

class SurveyController
{
    protected $entityManager;
    protected $entityRepository;

    public function __construct()
    {
        $this->entityManager = DbContext::get_entity_manager();
        $this->entityRepository = $this->entityManager->getRepository("App\Models\Survey");
    }

    /**
     * @return Survey[]
     */
    public function getAll()
    {
        $entities = $this->entityRepository->findBy(['deleted' => 0]);
        return $entities;
    }
    public function getById($id)
    {
        $entity = $this->entityRepository->find($id);
        return $entity;
    }
    public function getByName($name): Survey
    {
        $entity = $this->entityRepository->findOneBy(['name' => $name]);
        return $entity;
    }

    /**
     * delete entity
     * @param $id
     */
    public function deleteEntity($id)
    {
        $entity = $this->entityRepository->find($id);
        // $entity->delete();
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
        return $entity;
    }

    /**
     * function to handle response in conversation
     *
     * @param Conversation $conversation
     * @param string $text
     * @param Message? $message
     * @return ServerResponse
     **/
    public function handleConversation(Conversation $conversation, string $text, ?Message $message) : ServerResponse
    {
        // Load any existing notes from this conversation
        $notes = &$conversation->notes;
        !is_array($notes) && $notes = [];

        // Load the current state of the conversation
        $state = $notes['state'] ?? 0;

        $text = json_decode("\"$text\"");

        if ($state == 0) {
            $surveys = $this->getAll();
            $surveyTitles = [];
            foreach ($surveys as $s) {
                array_push($surveyTitles, $s->getName());
            }
            //if no input or input doesn't match survey selection, request survey selection
            if ($text === '' || !in_array($text, $surveyTitles, true)) {
                $keyboard = array_map(function ($v) {
                    return [['text' => $v, 'callback_data' => $v]];
                }, $surveyTitles);
                return Request::sendMessage([
                    'chat_id' => $conversation->getChatId(),
                    'text'    => 'Select registration',
                    'reply_markup' => new InlineKeyboard(...$keyboard),
                ]);
            } else {
                //else select survey and add to notes, update state return first question
                $notes['survey'] = $text;
                $conversation->update();

                $survey = $this->getByName($text);
                $questions = $survey->getQuestions();
                if ($questions == null || count($questions) == 0) {
                    $conversation->stop();
                    return Request::sendMessage([
                        'chat_id' => $conversation->getChatId(),
                        'text'    => 'Survey not currently available',
                    ]);
                } else {
                    $q = $questions[0];
                    $result = $this->askQuestion($q, $conversation->getChatId());
                    $notes['state'] = 1;
                    $conversation->update();
                    return $result;
                }
            }
        } else {
            //mid conversation, i.e. survey is selected and being carried out
            //get active question
            $survey = $this->getByName($notes['survey']);
            $activeSurveyQuestion = $survey->getSurveyQuestions()->get($state - 1);

            //if answer is not valid repeat question
            if (!$this->validAnswer($activeSurveyQuestion->getQuestion(), $text, $message)) {
                return $this->askQuestion($activeSurveyQuestion->getQuestion(), $conversation->getChatId());
            } else {
                //save answer
                //@TODO check if new answer or update and handle accordingly
                $answer = new Answer();
                $answer->setUserId($conversation->getUserId());
                $answer->setSurveyQuestion($activeSurveyQuestion);
                $answer->setText($this->getAnswer($activeSurveyQuestion->getQuestion(), $text, $message));
                $this->entityManager->persist($answer);
                $this->entityManager->flush();

                //continue to next question & update state

                if ($survey->getSurveyQuestions()->count() == $state) {
                    $out_text = 'Registration complete:' . PHP_EOL;
                    $hasFile = false;
                    $data = [];
                    foreach ($survey->getSurveyQuestions() as $sq) {
                        $a = $sq->getAnswersFromUser($conversation->getUserId());
                        $answerText = $a != null ? $a->getText() : "-";
                        if ($sq->getQuestion()->getType() == "file") {
                            $hasFile = true;
                            $data['photo']   = $answerText;
                        }
                        $out_text .= PHP_EOL . $sq->getQuestion()->getText() . ': ' . $answerText;
                    }

                    $data[$hasFile ? 'caption' : 'text'] = $out_text;
                    $data['chat_id'] = $conversation->getChatId();
                    $conversation->stop();

                    return $hasFile ? Request::sendPhoto($data) : Request::sendMessage($data);
                } else {
                    //update state, return question
                    $result = $this->askQuestion($survey->getQuestions()[$state], $conversation->getChatId());
                    $notes['state'] = $state + 1;
                    $conversation->update();
                    return $result;
                }
            }
        }
    }

    function askQuestion($q, int $chatId)
    {
        if (empty($q->getChoices())) {
            return Request::sendMessage([
                'chat_id' => $chatId,
                'text'    => $q->getText(),
            ]);
        } else {
            $keyboard = array_map(function ($v) {
                return [['text' => $v, 'callback_data' => $v]];
            }, $q->getChoices());

            return Request::sendMessage([
                'chat_id' => $chatId,
                'text'    => $q->getText(),
                'reply_markup' => new InlineKeyboard(...$keyboard),
            ]);
        }
    }

    function validAnswer($question, $text, $message): bool
    {
        if (empty($text)) return false;
        switch ($question->getType()) {
            case null:
            case "text":
                return true;
            case "choice":
                return in_array($text, $question->getChoices(), true);
            case "file":
                return $message != null && $message->getPhoto() != null;
            case "location":
                return $message != null && $message->getLocation() != null;
            case "contact":
                return $message != null && $message->getContact() != null;
        }
    }

    function getAnswer($question, $text, $message): string
    {
        if ($message == null) return $text;
        switch ($question->getType()) {
            case null:
            case "text":
            case "choice":
                return $text;
            case "file":
                return $message->getPhoto()[0]->getFileId();
            case "location":
                return "lng: {$message->getLocation()->getLongitude()}, lat: {$message->getLocation()->getLatitude()}";
            case "contact":
                return $message->getContact()->getPhoneNumber();
        }
    }
}
