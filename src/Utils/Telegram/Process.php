<?php
declare(strict_types=1);

namespace App\Utils\Telegram;

use Telegram\Bot\Api;
use App\Models\Setting;
use Exception;

final class Process
{
    public static function commandBot()
    {
      
        $bot = new Api(Setting::obtain('telegram_bot_token'));
        $bot->addCommands(
            [
                Commands\PingCommand::class,
                Commands\StartCommand::class,
            ]
        );
        $update = $bot->commandsHandler(true);
        $Message = $update->getMessage();
        
        if ($update->getCallbackQuery() !== null) {
            new Callbacks\Callback($bot, $update->getCallbackQuery());
        } else if ($Message->getReplyToMessage() != null) {
            if (preg_match("/[#](.*)/", $Message->getReplyToMessage()->getText(), $match)) {
                new Callbacks\ReplayTicket($bot, $Message, $match[1]);
            }
        }
    }
}
