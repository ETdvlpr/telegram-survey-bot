<?php
require_once __DIR__ . '/vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
use Longman\TelegramBot\Conversation;
use App\Controllers\SurveyController;
var_dump($_GET);

$config = require __DIR__ . '/config.php';

$telegram = new Longman\TelegramBot\Telegram($config['api_key'], $config['bot_username']);

// Enable admin users
$telegram->enableAdmins($config['admins']);

// Add commands paths containing your custom commands
$telegram->addCommandsPaths($config['commands']['paths']);

// Enable MySQL if required
$telegram->enableMySql($config['mysql']);


$conversation = new Conversation($_GET['user_id'], $_GET['chat_id']);
$surveyCon = new SurveyController();
$result = $surveyCon->handleConversation($conversation, $_GET['text'], null);
var_dump($result);
?>