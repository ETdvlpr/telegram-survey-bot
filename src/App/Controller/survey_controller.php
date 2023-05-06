<?php

namespace App\Controllers;

use App\Models\Survey;
use App\Models\Question;
use App\Models\Answer;
use App\Models\User;
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

    public function getById($id): Survey
    {
        $entity = $this->entityRepository->find($id);
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

        $user = $this->entityManager->getRepository("App\Models\User")->find($conversation->getUserId());

        if (!$user) {
            $user = new User();
            $user->setId($conversation->getUserId());
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        $translator = new Translator($user->getPreferences()['lang']);

        // Load the current state of the conversation
        $state = $notes['state'] ?? 0;

        $text = "" . json_decode("\"$text\"");

        if ($state == 0) {
            $surveys = $this->getAll();
            $surveyIDs = array_map(function ($s) {
                return $s->getId();
            }, $surveys);

            // If no input or input doesn't match survey selection, request survey selection
            if ($text === '' || !in_array($text, $surveyIDs, true)) {
                $keyboard = array_map(function ($v) use ($translator) {
                    return [['text' => $translator->translate($v->getName()), 'callback_data' => $v->getId()]];
                }, $surveys);

                return Request::sendMessage([
                    'chat_id' => $conversation->getChatId(),
                    'text'    => $translator->translate('select_registration'),
                    'reply_markup' => new InlineKeyboard(...$keyboard),
                ]);
            }
            // Select survey and add to notes, update state, and return first question
            $notes['survey'] = $text;
            $conversation->update();

            $survey = $this->getById($text);
            $questions = $survey->getQuestions();
            if ($questions == null || count($questions) == 0) {
                $conversation->stop();
                return Request::sendMessage([
                    'chat_id' => $conversation->getChatId(),
                    'text'    => $translator->translate('survey_not_available'),
                ]);
            }
            $q = $questions[0];
            $result = $this->askQuestion($q, $conversation->getChatId(), $translator);
            $notes['state'] = 1;
            $conversation->update();
            return $result;
        } else {
            //Mid conversation, i.e. survey is selected and being carried out
            //Get active question
            $survey = $this->getById($notes['survey']);
            $activeSurveyQuestion = $survey->getSurveyQuestions()->get($state - 1);

            if (!$this->validAnswer($activeSurveyQuestion->getQuestion(), $text, $message)) {
                return $this->askQuestion($activeSurveyQuestion->getQuestion(), $conversation->getChatId(), $translator);
            }

            $answer = new Answer();
            $answer->setUserId($user->getId())
                ->setSurveyQuestion($activeSurveyQuestion)
                ->setText($this->getAnswer($activeSurveyQuestion->getQuestion(), $text, $message));

            $this->entityManager->persist($answer);
            $this->entityManager->flush();

            if ($survey->getSurveyQuestions()->count() == $state) {
                if ($survey->getName() == "Language") {
                    $user->addPreference('lang', $text == 'English' ? 'en' : 'am');
                    $this->entityManager->persist($user);
                    $this->entityManager->flush();

                    $data['chat_id'] = $conversation->getChatId();
                    $conversation->stop();
                    return Request::sendMessage([
                        'chat_id' => $conversation->getChatId(),
                        'text'    => $translator->translate('language_selected'),
                    ]);
                }
                $out_text = 'Registration complete:' . PHP_EOL;
                $hasFile = false;
                $data = [];

                foreach ($survey->getSurveyQuestions() as $sq) {
                    $a = $sq->getAnswersFromUser($user->getId());
                    $answerText = $a != null ? $a->getText() : "-";

                    if ($sq->getQuestion()->getType() == "image") {
                        $hasFile = true;
                        $data['photo'][] = $answerText;
                    }

                    $out_text .= PHP_EOL . $sq->getQuestion()->getText() . ': ' . $answerText;
                }

                $data[$hasFile ? 'caption' : 'text'] = $out_text;
                $data['chat_id'] = $conversation->getChatId();
                $conversation->stop();
                return $hasFile ? Request::sendMediaGroup(['media' => $data]) : Request::sendMessage($data);
            } else {
                $result = $this->askQuestion($survey->getQuestions()[$state], $conversation->getChatId(), $translator);
                $notes['state'] = $state + 1;
                $conversation->update();
                return $result;
            }
        }
    }

    function askQuestion(Question $q, int $chatId, Translator $translator)
    {
        if (!empty($q->getChoices())) {
            $keyboard = array_map(function ($v) use ($translator) {
                return [['text' => $translator->translate($v), 'callback_data' => $v]];
            }, $q->getChoices());

            return Request::sendMessage([
                'chat_id' => $chatId,
                'text'    => $translator->translate($q->getText()),
                'reply_markup' => new InlineKeyboard(...$keyboard),
            ]);
        } else {
            if ($q->getType() == 'contact') {
                return Request::sendMessage([
                    'chat_id' => $chatId,
                    'text'    => $translator->translate($q->getText()),
                    'reply_markup' => (new Keyboard(
                        (new KeyboardButton($translator->translate('share_contact')))->setRequestContact(true)
                    ))
                        ->setOneTimeKeyboard(true)
                        ->setResizeKeyboard(true)
                        ->setSelective(true),
                ]);
            }
            return Request::sendMessage([
                'chat_id' => $chatId,
                'text'    => $translator->translate($q->getText()),
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
                //accept number aswell
                return ($message != null && $message->getContact() != null) || is_numeric($text);
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
                return ($message != null && $message->getContact() != null) ? $message->getContact()->getPhoneNumber() : $text;
        }
    }

    function initialize()
    {
        $questions = [
            ['id' => '35', 'choices' => '""', 'text' => 'ስም', 'type' => 'text', 'deleted' => '0'],
            ['id' => '36', 'choices' => '["የመንግስት","የግል","ንግድ","ለትርፍ ያልተቋቋመ","የውጪ ድርጅት"]', 'text' => 'ስራ', 'type' => 'choice', 'deleted' => '0'],
            ['id' => '37', 'choices' => '["ኦርቶዶክስ","ፕሮቴስታንት","ሙስሊም","ካቶሊክ","ሌላ"]', 'text' => 'ኃይማኖት', 'type' => 'choice', 'deleted' => '0'],
            ['id' => '38', 'choices' => '["ሞግዚት","ሁለገብ የቤት ውስጥ ረዳት","የመኖሪያ ቤት ጽዳት","የመኖሪያ ቤት ልብስ አጣቢ","የመኖሪያ ቤት ምግብ አብሳይ","የቢሮ ጽዳት","የሆቴል ጽዳት","የሆቴል ምግብ አብሳይ","የቢሮ ተላላኪ"]', 'text' => 'የሚፈልጉት ሰራተኛ ዓይነት', 'type' => 'choice', 'deleted' => '0'],
            ['id' => '39', 'choices' => '["ቋሚ","ተመላላሽ","የትርፍ ሰዓት","ጊዜያዊ"]', 'text' => 'የሚፈልጉት የቅጥር ሁኔታ', 'type' => 'choice', 'deleted' => '0'],
            ['id' => '40', 'choices' => '""', 'text' => 'የመኖሪያ አካባቢ: ክ/ከተማ', 'type' => 'text', 'deleted' => '0'],
            ['id' => '41', 'choices' => '""', 'text' => 'የመኖሪያ አካባቢ: ወረዳ', 'type' => 'text', 'deleted' => '0'],
            ['id' => '42', 'choices' => '""', 'text' => 'የቤተሰብ ብዛት: አዋቂዎች', 'type' => 'number', 'deleted' => '0'],
            ['id' => '43', 'choices' => '""', 'text' => 'የቤተሰብ ብዛት: ልጆች', 'type' => 'number', 'deleted' => '0'],
            ['id' => '44', 'choices' => '""', 'text' => 'ስልክ ቁጥር', 'type' => 'contact', 'deleted' => '0'],
            ['id' => '45', 'choices' => '""', 'text' => 'የመክፈል አቅም (ከፍተኛ)', 'type' => 'number', 'deleted' => '0'],
            ['id' => '46', 'choices' => '""', 'text' => 'ተጨማሪ ጥያቄ(ፍላጎት) ካለዎት በአጭሩ ያስቀምጡ', 'type' => 'text', 'deleted' => '0'],
            ['id' => '47', 'choices' => '""', 'text' => 'ዕድሜ', 'type' => 'text', 'deleted' => '0'],
            ['id' => '48', 'choices' => '["ሞግዚት","ሁለገብ የቤት ውስጥ ረዳት","የመኖሪያ ቤት ጽዳት","የመኖሪያ ቤት ልብስ አጣቢ","የመኖሪያ ቤት ምግብ አብሳይ","የቢሮ ጽዳት","የሆቴል ጽዳት","የሆቴል ምግብ አብሳይ","የቢሮ ተላላኪ"]', 'text' => 'የሚፈልጉት የስራ ዓይነት', 'type' => 'choice', 'deleted' => '0'],
            ['id' => '49', 'choices' => '["የለም","አለ"]', 'text' => 'በሚያመለክቱበት የስራ ዘርፍ ተገቢ የስራ ልምድ አለዎት', 'type' => 'choice', 'deleted' => '0'],
            ['id' => '50', 'choices' => '""', 'text' => 'የስራ ልምድ ካለ _____ ዓመት', 'type' => 'number', 'deleted' => '0'],
            ['id' => '51', 'choices' => '["አንደኛ ደረጃ","ሁለተኛ ደረጃ","ሁለተኛ ደረጃ ያጠናቀቀ","ቴሙስ\\/ ሌቭል","ዲፕሎማ","ዲግሪ"]', 'text' => 'የትምህርት ደረጃ', 'type' => 'choice', 'deleted' => '0'],
            ['id' => '52', 'choices' => '["የለም","አለ"]', 'text' => 'በሚያመለክቱበት የስራ ዘርፍ ተገቢ ስልጠና አለዎት', 'type' => 'choice', 'deleted' => '0'],
            ['id' => '53', 'choices' => '["ቤተሰብ","ወንደላጤ\\/ ሴተላጤ","ሆቴል","የግል ድርጅት","ለትርፍ ያልተቋቋመ","የህጻናት መዋያ\\/ ትምህርት ቤት"]', 'text' => 'የሚፈልጉት የቀጣሪ ዓይነት', 'type' => 'choice', 'deleted' => '0'],
            ['id' => '54', 'choices' => '["ቋሚ","ተመላላሽ","የትርፍ ሰዓት","ጊዜያዊ"]', 'text' => 'የሚፈልጉት የቅጥር ሁኔታ', 'type' => 'choice', 'deleted' => '0'],
            ['id' => '55', 'choices' => '["አዎ","አይ"]', 'text' => 'ድርጅታችን የሚሰጠውን የስራ ላይ ስልጠና እና ምዘና ለማለፍ ፍቃደኛ ነዎት?', 'type' => 'choice', 'deleted' => '0'],
            ['id' => '56', 'choices' => '["አዎ","አይ"]', 'text' => 'አጠቃላይ የጤና ምርመራ አድርገው ያውቃሉ?', 'type' => 'choice', 'deleted' => '0'],
            ['id' => '57', 'choices' => '["አዎ","አይ"]', 'text' => 'አጠቃላይ የጤና ምርመራ ለማድረግ ፈቃደኛ ነዎት?', 'type' => 'choice', 'deleted' => '0'],
            ['id' => '58', 'choices' => '["አዎ","አይ"]', 'text' => 'ከዚህ ቀደም በማንኛውም ሁኔታ ተከሰው/ ታስረው ያውቃሉ?', 'type' => 'choice', 'deleted' => '0'],
            ['id' => '59', 'choices' => '["አዎ","አይ"]', 'text' => 'ስራ ከመጀመርዎት በፊት የአሻራ ምርመራ ለማድረግ ፍቃደኛ ነዎት?', 'type' => 'choice', 'deleted' => '0'],
            ['id' => '60', 'choices' => '""', 'text' => 'የቀድሞ አሰሪ ስም', 'type' => 'text', 'deleted' => '0'],
            ['id' => '61', 'choices' => '""', 'text' => 'የቀድሞ አሰሪ ስልክ ቅጥር', 'type' => 'text', 'deleted' => '0'],
            ['id' => '62', 'choices' => '""', 'text' => 'የሚፈልጉት የደሞዝ መጠን', 'type' => 'number', 'deleted' => '0'],
            ['id' => '63', 'choices' => '""', 'text' => 'ፎቶ ያስገቡ (6 ወር ያላለፈው)', 'type' => 'image', 'deleted' => '0'],
            ['id' => '64', 'choices' => '""', 'text' => 'ማንኛውንም አይነት የታደሰ መታወቂያ ያስገቡ (የቀበሌ፣ ፓስፖርት፣ የትምህርት ቤት፣ወዘተ)', 'type' => 'image', 'deleted' => '0'],
        ];
        $survey = [
            ['id' => '1', 'name' => 'For employers', 'deleted' => '0'],
            ['id' => '2', 'name' => 'For job seekers', 'deleted' => '0']
        ];
        $survey_join_question = [
            ['id' => '5', 'question_id' => '35', 'survey_id' => '1', 'order' => '1', 'required' => '1'],
            ['id' => '6', 'question_id' => '36', 'survey_id' => '1', 'order' => '2', 'required' => '1'],
            ['id' => '7', 'question_id' => '37', 'survey_id' => '1', 'order' => '3', 'required' => '1'],
            ['id' => '8', 'question_id' => '38', 'survey_id' => '1', 'order' => '4', 'required' => '1'],
            ['id' => '9', 'question_id' => '39', 'survey_id' => '1', 'order' => '5', 'required' => '1'],
            ['id' => '10', 'question_id' => '40', 'survey_id' => '1', 'order' => '6', 'required' => '1'],
            ['id' => '11', 'question_id' => '41', 'survey_id' => '1', 'order' => '7', 'required' => '1'],
            ['id' => '12', 'question_id' => '42', 'survey_id' => '1', 'order' => '8', 'required' => '1'],
            ['id' => '13', 'question_id' => '43', 'survey_id' => '1', 'order' => '9', 'required' => '1'],
            ['id' => '14', 'question_id' => '44', 'survey_id' => '1', 'order' => '10', 'required' => '1'],
            ['id' => '15', 'question_id' => '45', 'survey_id' => '1', 'order' => '11', 'required' => '1'],
            ['id' => '16', 'question_id' => '46', 'survey_id' => '1', 'order' => '12', 'required' => '1'],
            ['id' => '17', 'question_id' => '35', 'survey_id' => '2', 'order' => '1', 'required' => '1'],
            ['id' => '18', 'question_id' => '47', 'survey_id' => '2', 'order' => '2', 'required' => '1'],
            ['id' => '19', 'question_id' => '37', 'survey_id' => '2', 'order' => '3', 'required' => '1'],
            ['id' => '20', 'question_id' => '48', 'survey_id' => '2', 'order' => '4', 'required' => '1'],
            ['id' => '21', 'question_id' => '49', 'survey_id' => '2', 'order' => '5', 'required' => '1'],
            ['id' => '22', 'question_id' => '50', 'survey_id' => '2', 'order' => '6', 'required' => '1'],
            ['id' => '23', 'question_id' => '51', 'survey_id' => '2', 'order' => '7', 'required' => '1'],
            ['id' => '24', 'question_id' => '52', 'survey_id' => '2', 'order' => '8', 'required' => '1'],
            ['id' => '25', 'question_id' => '53', 'survey_id' => '2', 'order' => '9', 'required' => '1'],
            ['id' => '26', 'question_id' => '54', 'survey_id' => '2', 'order' => '10', 'required' => '1'],
            ['id' => '27', 'question_id' => '55', 'survey_id' => '2', 'order' => '11', 'required' => '1'],
            ['id' => '28', 'question_id' => '56', 'survey_id' => '2', 'order' => '12', 'required' => '1'],
            ['id' => '29', 'question_id' => '57', 'survey_id' => '2', 'order' => '13', 'required' => '1'],
            ['id' => '30', 'question_id' => '58', 'survey_id' => '2', 'order' => '14', 'required' => '1'],
            ['id' => '31', 'question_id' => '59', 'survey_id' => '2', 'order' => '15', 'required' => '1'],
            ['id' => '32', 'question_id' => '60', 'survey_id' => '2', 'order' => '16', 'required' => '1'],
            ['id' => '33', 'question_id' => '61', 'survey_id' => '2', 'order' => '18', 'required' => '1'],
            ['id' => '34', 'question_id' => '62', 'survey_id' => '2', 'order' => '19', 'required' => '1'],
            ['id' => '35', 'question_id' => '63', 'survey_id' => '2', 'order' => '20', 'required' => '1'],
            ['id' => '36', 'question_id' => '64', 'survey_id' => '2', 'order' => '21', 'required' => '1'],
        ];

        $questions_en = [
            ['id' => '35', 'text' => 'name', 'choices' => '""'],
            ['id' => '36', 'text' => 'job', 'choices' => '["Government","Private","Business","Nonprofit","Foreign Organization"]'],
            ['id' => '37', 'text' => 'religion', 'choices' => '["Orthodox","Protestant","Muslim","Catholic","Other"]'],
            ['id' => '38', 'text' => 'Type of employee you want', 'choices' => '["Babysitter","Multipurpose Domestic Helper","House Cleaner","House Washer","Residential Cook","Office Cleaner","Hotel Cleaner","Hotel Cook","Office Messenger"]'],
            ['id' => '39', 'text' => 'Employment status you want', 'choices' => '["permanent","temporary","part-time","temporary"]'],
            ['id' => '40', 'text' => 'Residential Area: District/City', 'choices' => '""'],
            ['id' => '41', 'text' => 'Residential Area: District', 'choices' => '""'],
            ['id' => '42', 'text' => 'Family Size: Adults', 'choices' => '""'],
            ['id' => '43', 'text' => 'Family Size: Children', 'choices' => '""'],
            ['id' => '44', 'text' => 'phone number', 'choices' => '""'],
            ['id' => '45', 'text' => 'Paying capacity (max)', 'choices' => '""'],
            ['id' => '46', 'text' => 'If you have any additional questions, put them in brief', 'choices' => '""'],
            ['id' => '47', 'text' => 'age', 'choices' => '""'],
            ['id' => '48', 'text' => 'Type of job you want', 'choices' => '["Babysitter","Multipurpose Domestic Helper","House Cleaner","House Washer","Residential Cook","Office Cleaner","Hotel Cleaner","Hotel Cook","Office Messenger"]'],
            ['id' => '49', 'text' => 'Do you have relevant work experience in the field you are applying for', 'choices' => '["none","yes"]'],
            ['id' => '50', 'text' => '_____ years of work experience, if any', 'choices' => '""'],
            ['id' => '51', 'text' => 'Education Level', 'choices' => '["Elementary","Secondary","Graduate","Temus\\/Level", "Diploma", "Degree"]'],
            ['id' => '52', 'text' => 'You have relevant training in the field you are applying for', 'choices' => '["none","yes"]'],
            ['id' => '53', 'text' => 'The type of employer you want', 'choices' => '["Family","Bachelor/Bachelorette","Hotel","Private organization"," Non-Profit","Nursery/School"]'],
            ['id' => '54', 'text' => 'Employment status you want', 'choices' => '["permanent","temporary","part-time","temporary"]'],
            ['id' => '55', 'text' => 'Are you willing to undergo the on-the-job training and assessment provided by our company?', 'choices' => '["Yes","No"]'],
            ['id' => '56', 'text' => 'Have you ever had a general health check?', 'choices' => '["yes","no"]'],
            ['id' => '57', 'text' => 'Are you willing to undergo a general health check?', 'choices' => '["Yes","No"]'],
            ['id' => '58', 'text' => 'Have you ever been charged/arrested under any circumstances before?', 'choices' => '["Yes","No"]'],
            ['id' => '59', 'text' => 'Are you willing to undergo a fingerprint check before starting work?', 'choices' => '["yes","no"]'],
            ['id' => '60', 'text' => 'Previous Employer Name', 'choices' => '""'],
            ['id' => '61', 'text' => 'Previous employer phone hire', 'choices' => '""'],
            ['id' => '62', 'text' => 'Desired salary', 'choices' => '""'],
            ['id' => '63', 'text' => 'Enter photo (not older than 6 months)', 'choices' => '""'],
            ['id' => '64', 'text' => 'Enter any updated ID (Kebele, Passport, School, etc.)', 'choices' => '""'],
        ];
        foreach ($questions as $q) {
            $question = new Question();
            $question->setText($q['text']);
            $question->setType($q['type']);
            $question->setChoices($q['choices']);
            $this->entityManager->persist($question);
            $this->entityManager->flush();
        }
        // foreach ($questions_en as $q) {
        //     echo "UPDATE `survey_question` SET `text` = '$q[text]', choices = '$q[choices]' WHERE `survey_question`.`id` = $q[id];";
        // }
        // $distinctKeys = [];
        // foreach ($questions_en as $en) {
        //     foreach ($questions as $q) {
        //         if ($en['id'] == $q['id']) {
        //             $key = $en['text'];
        //             $translation = $q['text'];
        //             if (!in_array($key, $distinctKeys, true)) {
        //                 echo "INSERT INTO `translation` (`id`, `key`, `language`, `translation`) VALUES (NULL, '{$key}', 'am', '{$translation}');<br>";
        //                 array_push($distinctKeys, $key);
        //             }
        //             $choices = json_decode($q['choices'], true);
        //             if (!empty($choices)) {
        //                 $choices_en = json_decode($en['choices']);
        //                 for ($i = 0; $i < count($choices); $i++) {
        //                     $key = $choices_en[$i];
        //                     $translation = $choices[$i];
        //                     if (!in_array($key, $distinctKeys, true)) {
        //                         echo "INSERT INTO `translation` (`id`, `key`, `language`, `translation`) VALUES (NULL, '{$key}', 'am', '{$translation}');<br>";
        //                         array_push($distinctKeys, $key);
        //                     }
        //                 }
        //             }
        //         }
        //     }
        // }
    }
}
