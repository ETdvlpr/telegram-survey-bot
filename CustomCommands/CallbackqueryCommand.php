<?php

/**
 * This file is part of the PHP Telegram Bot example-bot package.
 * https://github.com/php-telegram-bot/example-bot/
 *
 * (c) PHP Telegram Bot Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Callback query command
 *
 * This command handles all callback queries sent via inline keyboard buttons.
 *
 * @see InlinekeyboardCommand.php
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Conversation;
use App\Controllers\SurveyController;

class CallbackqueryCommand extends SystemCommand
{
    /**
     * @var string
     */
    protected $name = 'callbackquery';

    /**
     * @var string
     */
    protected $description = 'Handle the callback query';

    /**
     * @var string
     */
    protected $version = '1.2.0';

    /**
     * Main command execution
     *
     * @return ServerResponse
     * @throws \Exception
     */
    public function execute(): ServerResponse
    {
        //check for currently active conversation and handle accordingly
        $callback_query = $this->getCallbackQuery();
        $callback_data  = $callback_query->getData();
        $conversation =  new Conversation($callback_query->getFrom()->getId(), $callback_query->getMessage()->getChat()->getId(), 'start');
        
        $surveyCon = new SurveyController();
        return $surveyCon->handleConversation($conversation, $callback_data, $callback_query->getMessage())??
         $callback_query->answer([
            'text'       => 'Content of the callback data: ' . $callback_data,
            'show_alert' => true,
            'cache_time' => 5,
        ]);
    }
}
