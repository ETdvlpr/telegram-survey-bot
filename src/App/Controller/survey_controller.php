<?php

namespace App\Controllers;

use App\Models\Survey;
use App\Models\Question;
use App\Models\Answer;
use Longman\TelegramBot\Conversation;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\KeyboardButton;
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
    public function handleConversation(Conversation $conversation, string $text, ?Message $message): ServerResponse
    {
        // Load any existing notes from this conversation
        $notes = &$conversation->notes;
        !is_array($notes) && $notes = [];

        // Load the current state of the conversation
        $state = $notes['state'] ?? 0;

        $text = "" . json_decode("\"$text\"");

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
                        if ($sq->getQuestion()->getType() == "image") {
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
        if (!empty($q->getChoices())) {
            $keyboard = array_map(function ($v) {
                return [['text' => $v, 'callback_data' => $v]];
            }, $q->getChoices());

            return Request::sendMessage([
                'chat_id' => $chatId,
                'text'    => $q->getText(),
                'reply_markup' => new InlineKeyboard(...$keyboard),
            ]);
        } else {
            if ($q->getType() == 'contact') {
                return Request::sendMessage([
                    'chat_id' => $chatId,
                    'text'    => $q->getText(),
                    'reply_markup' => (new Keyboard(
                        (new KeyboardButton('Share Contact'))->setRequestContact(true)
                    ))
                        ->setOneTimeKeyboard(true)
                        ->setResizeKeyboard(true)
                        ->setSelective(true),
                ]);
            }
            return Request::sendMessage([
                'chat_id' => $chatId,
                'text'    => $q->getText(),
                'reply_markup' => Keyboard::remove(['selective' => true]),
            ]);
        }
    }

    function validAnswer($question, $text, $message): bool
    {
        if (empty($text) && $text !== "0" && in_array($question->getType(), ["text", "number", "choice"], true)) return false;
        ///@TODO add default cases
        switch ($question->getType()) {
            case null:
            case "text":
                return true;
            case "number":
                return is_numeric($text);
            case "choice":
                return in_array($text, $question->getChoices(), true);
            case "image":
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
            case "number":
            case "choice":
                return $text;
            case "image":
                return $message->getPhoto()[0]->getFileId();
            case "location":
                return "lng: {$message->getLocation()->getLongitude()}, lat: {$message->getLocation()->getLatitude()}";
            case "contact":
                return $message->getContact()->getPhoneNumber();
        }
    }

    function initialize()
    {
        $questions = [
            ['text' => 'ስም', 'type' => 'text', 'choices' => []],
            ['text' => 'ስራ', 'type' => 'choice', 'choices' => ["የመንግስት", "የግል", "ንግድ", "ለትርፍ ያልተቋቋመ", "የውጪ ድርጅት"]],
            ['text' => 'ኃይማኖት', 'type' => 'choice', 'choices' => ["ኦርቶዶክስ", "ፕሮቴስታንት", "ሙስሊም", "ካቶሊክ", "ሌላ"]],
            ['text' => 'የሚፈልጉት ሰራተኛ ዓይነት', 'type' => 'choice', 'choices' => ["ሞግዚት", "ሁለገብ የቤት ውስጥ ረዳት", "የመኖሪያ ቤት ጽዳት", "የመኖሪያ ቤት ልብስ አጣቢ", "የመኖሪያ ቤት ምግብ አብሳይ", "የቢሮ ጽዳት", "የሆቴል ጽዳት", "የሆቴል ምግብ አብሳይ", "የቢሮ ተላላኪ"]],
            ['text' => 'የሚፈልጉት የቅጥር ሁኔታ', 'type' => 'choice', 'choices' => ["ቋሚ", "ተመላላሽ", "የትርፍ ሰዓት", "ጊዜያዊ"]],
            ['text' => 'የመኖሪያ አካባቢ: ክ/ከተማ', 'type' => 'text', 'choices' => []],
            ['text' => 'የመኖሪያ አካባቢ: ወረዳ', 'type' => 'text', 'choices' => []],
            ['text' => 'የቤተሰብ ብዛት: አዋቂዎች', 'type' => 'number', 'choices' => []],
            ['text' => 'የቤተሰብ ብዛት: ልጆች', 'type' => 'number', 'choices' => []],
            ['text' => 'አድራሻ', 'type' => 'contact', 'choices' => []],
            ['text' => 'የመክፈል አቅም (ከፍተኛ)', 'type' => 'number', 'choices' => []],
            ['text' => 'ተጨማሪ ጥያቄ(ፍላጎት) ካለዎት በአጭሩ ያስቀምጡ', 'type' => 'text', 'choices' => []],
            ['text' => 'ዕድሜ', 'type' => 'text', 'choices' => []],
            ['text' => 'የሚፈልጉት የስራ ዓይነት', 'type' => 'choice', 'choices' => ["ሞግዚት", "ሁለገብ የቤት ውስጥ ረዳት", "የመኖሪያ ቤት ጽዳት", "የመኖሪያ ቤት ልብስ አጣቢ", "የመኖሪያ ቤት ምግብ አብሳይ", "የቢሮ ጽዳት", "የሆቴል ጽዳት", "የሆቴል ምግብ አብሳይ", "የቢሮ ተላላኪ"]],
            ['text' => 'በሚያመለክቱበት የስራ ዘርፍ ተገቢ የስራ ልምድ አለዎት', 'type' => 'choice', 'choices' => ["የለም", "አለ"]],
            ['text' => 'የስራ ልምድ ካለ _____ ዓመት', 'type' => 'number', 'choices' => []],
            ['text' => 'የትምህርት ደረጃ', 'type' => 'choice', 'choices' => ["አንደኛ ደረጃ", "ሁለተኛ ደረጃ", "ሁለተኛ ደረጃ ያጠናቀቀ", "ቴሙስ/ ሌቭል", "ዲፕሎማ", "ዲግሪ"]],
            ['text' => 'በሚያመለክቱበት የስራ ዘርፍ ተገቢ ስልጠና አለዎት', 'type' => 'choice', 'choices' => ["የለም", "አለ"]],
            ['text' => 'የሚፈልጉት የቀጣሪ ዓይነት', 'type' => 'choice', 'choices' => ["ቤተሰብ", "ወንደላጤ/ ሴተላጤ", "ሆቴል", "የግል ድርጅት", "ለትርፍ ያልተቋቋመ", "የህጻናት መዋያ/ ትምህርት ቤት"]],
            ['text' => 'የሚፈልጉት የቅጥር ሁኔታ', 'type' => 'choice', 'choices' => ["ቋሚ", "ተመላላሽ", "የትርፍ ሰዓት", "ጊዜያዊ"]],
            ['text' => 'ድርጅታችን የሚሰጠውን የስራ ላይ ስልጠና እና ምዘና ለማለፍ ፍቃደኛ ነዎት?', 'type' => 'choice', 'choices' => ["አዎ", "አይ"]],
            ['text' => 'አጠቃላይ የጤና ምርመራ አድርገው ያውቃሉ?', 'type' => 'choice', 'choices' => ["አዎ", "አይ"]],
            ['text' => 'አጠቃላይ የጤና ምርመራ ለማድረግ ፈቃደኛ ነዎት?', 'type' => 'choice', 'choices' => ["አዎ", "አይ"]],
            ['text' => 'ከዚህ ቀደም በማንኛውም ሁኔታ ተከሰው/ ታስረው ያውቃሉ?', 'type' => 'choice', 'choices' => ["አዎ", "አይ"]],
            ['text' => 'ስራ ከመጀመርዎት በፊት የአሻራ ምርመራ ለማድረግ ፍቃደኛ ነዎት?', 'type' => 'choice', 'choices' => ["አዎ", "አይ"]],
            ['text' => 'የቀድሞ አሰሪ ስም', 'type' => 'text', 'choices' => []],
            ['text' => 'የቀድሞ አሰሪ ስልክ ቅጥር', 'type' => 'text', 'choices' => []],
            ['text' => 'የሚፈልጉት የደሞዝ መጠን', 'type' => 'number', 'choices' => []],
            ['text' => 'ፎቶ ያስገቡ (6 ወር ያላለፈው)', 'type' => 'image', 'choices' => []],
            ['text' => 'ማንኛውንም አይነት የታደሰ መታወቂያ ያስገቡ (የቀበሌ፣ ፓስፖርት፣ የትምህርት ቤት፣ወዘተ)', 'type' => 'image', 'choices' => []],
        ];
        foreach ($questions as $q) {
            $question = new Question();
            $question->setText($q['text']);
            $question->setType($q['type']);
            $question->setChoices($q['choices']);
            $this->entityManager->persist($question);
            $this->entityManager->flush();
        }
    }
}
