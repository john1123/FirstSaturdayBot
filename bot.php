<?php

/** Токен, поучаенный у @BotFather */
define('TELEGRAM_BOT_TOKEN', '!!!Укажите ваш токен здесь!!!!!');
/** Имя файла бота */
define('FILENAME_BOT', 'bot.php');
/** Имя файла для генерации xls-таблицы */
define('FILENAME_RESULT', 'result.php');

include('test.php');include('TestApi.php');include('vendor/john1123/logger/src/File.php');
//include('vendor/autoload.php');
include('IngressProfile.php');
include('Storage.php');


use Telegram\Bot\Api;
use John1123\Logger\File as Logger;

$logger = new Logger('./data/bot_' . date('Ymd') . '.log');

$telegram = new Api(TELEGRAM_BOT_TOKEN);
//$result = $telegram -> getWebhookUpdates();
//$result = $telegram -> getWebhookUpdates('Событие создать Simferopol FS');
//$result = $telegram -> getWebhookUpdates('Событие удалить Simferopol FS1');
$result = $telegram -> getWebhookUpdates('Начать');

$text = $result["message"]["text"];
$chat_id = $result["message"]["chat"]["id"];
if (strlen($chat_id) < 1) {
    die('Ошибка. Скрипт нельзя вызывать непосредственно из браузера! Установите вебхук вызвав https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://<адрес и путь к скрипту>/bot.php');
}

$nickName = strlen($result["message"]["from"]["username"]) > 0 ? $result["message"]["from"]["username"] : '';
$fullUser = $result["message"]["from"]["first_name"] . (strlen($nickName) > 0 ? ' (@' . $nickName . ')' : '');

$keyboard = [['Состояние'],['Помощь']];

$storage = new Storage($nickName);
$aFirstRecord = $storage->getAgentData(0);
if (count($aFirstRecord) < 1) {
    array_unshift($keyboard, ['Начать']);
}
if (isAdmin($nickName)) {
    array_unshift($keyboard, ['Результаты']);
    array_unshift($keyboard, ['Участники']);
}
$reply_markup = $telegram->replyKeyboardMarkup([ 'keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false ]);

$reply = '';
if($text){
    $logger->log('Текст введённый пользователем ' . $fullUser . ': ' . $text);
    if ($text == '/start' || mb_strtolower($text,'UTF-8') == "начать") {
        $reply  = 'Добро пожаловать, ' . $fullUser . PHP_EOL;
        $reply .= 'Для учёта ваших данных, отправляйте копию вашего профиля боту (в текстовом виде).' . PHP_EOL;
        $storage->deleteAgentData(false);
        $logger->log('Сброс данных для ' . $nickName);
        $aEventsList = $storage->eventGet();
        $reply_markup = $telegram->replyKeyboardMarkup([ 'keyboard' => $aEventsList, 'resize_keyboard' => true, 'one_time_keyboard' => true ]);

        $reply .= getMessagesBlock($storage, isAdmin($nickName));
        $telegram->sendMessage([ 'chat_id' => $chat_id, 'text' => $reply, 'reply_markup' => $reply_markup ]);

    } else if (mb_strtolower($text,'UTF-8') == "участники") {
        $aAllData = Storage::getAllData();
        $reply .= 'Список участников:' . PHP_EOL;
        if (count($aAllData) > 0) {
            foreach ($aAllData as $username => $aData) {
                $reply .= ' - ';
                $sData = $aData[0]['data']['Agent Faction'] == 'Resistance' ? 'R' : 'E';
                $sData .= $aData[0]['data']['Level'];
                $reply .= $aData[0]['data']['Agent Name'] . '(' . $sData . '), @' . $username . ' (' . count($aData) . ')';
                $reply .= PHP_EOL;
            }
        } else {
            $reply .= ' - список пуст' . PHP_EOL;
        }
        $reply .= getMessagesBlock($storage, isAdmin($nickName));
        $telegram->sendMessage([ 'chat_id' => $chat_id, 'text' => $reply, 'reply_markup' => $reply_markup ]);
    } else if (mb_strtolower($text,'UTF-8') == "результаты") {
        if (isAdmin($nickName) == true) {
            $response = $telegram->sendDocument([
                'chat_id' => $chat_id,
                // result.xls в конце строки добавлен для того, чтобы телеграм видел в конце строки файл эксель.
                // Вроде как без этого не заработает (не уверен)
                'document' => 'https://'.$_SERVER['SERVER_NAME']. str_replace('bot.php', 'result.php', $_SERVER['SCRIPT_NAME']) . '/result.xls',
                'caption' => 'Файл с результатами',
            ]);
        } else {
            $reply = 'Недостаточно прав' . PHP_EOL;
            $telegram->sendMessage([ 'chat_id' => $chat_id, 'text' => $reply, 'reply_markup' => $reply_markup ]);
        }
    } else if (mb_strtolower($text,'UTF-8') == "помощь") {
        $reply  = 'Бот предназначен для отслеживания и учёта изменений игроков Ingress. Для работы нужно в игре скопировать данные профиля в Ingress Prime и как есть отправить их боту.' . PHP_EOL;
        $reply .= 'Исходный код доступен по адресу https://github.com/john1123/FirstSaturdayBot/' . PHP_EOL;

        $reply .= PHP_EOL . 'Доступны следующие команды:' . PHP_EOL;
        $reply .= '<b>Начать</b> - Удалить все запомненные ранее данные (если они были) и начать всё заново. Поаккуратней с ней :)' . PHP_EOL;
        $reply .= '<b>Состояние</b> - Посмотреть текущее состояние и свои достижения, известные боту' . PHP_EOL;
        $reply .= '[<b>Данные профиля</b>] - Передать данные боту. Данные следует скидывать "как есть", без всяких изменений. ';
        $reply .= 'Переданные данные запоминаются ботом и используются в дальнейшей работе' . PHP_EOL;
        $reply .= '<b>Помощь</b> - Список доступных команд. Этот текст' . PHP_EOL;

        if (isAdmin($nickName)) {
            $reply .= PHP_EOL . 'Команды администратора:' . PHP_EOL;
            $reply .= '<b>Сообщение ТЕКСТ</b> - Отправить сообщение ТЕКСТ' . PHP_EOL;
            $reply .= '<b>Сообщение удалить ID</b> - Удалить сообщение' . PHP_EOL;
            $reply .= '<b>Сообщения очистить</b> - Удалить все сообщения' . PHP_EOL;
            $reply .= '<b>Данные очистить</b> - Удалить все данные. ВНИМАНИЕ. Используйте, если вы уверены. Удаляет для всех пользователей.' . PHP_EOL;
            $reply .= '<b>Участники</b> - Все агенты, о ком известно боту' . PHP_EOL;
            $reply .= '<b>Результаты</b> - Скачать xls-файл с результатами всех игроков' . PHP_EOL;
        }

        $reply .= getMessagesBlock($storage, isAdmin($nickName));

        $telegram->sendMessage([ 'chat_id' => $chat_id, 'parse_mode'=> 'HTML', 'text' => $reply, 'reply_markup' => $reply_markup ]);
    } else if (mb_strtolower($text,'UTF-8') == "состояние") {
        $reply = '';
        if (count($aFirstRecord) > 0) {
            $aSecondRecord = $storage->getAgentData(1);
            if (count($aSecondRecord) > 0) {
                $reply .= getDeltaBlock($aSecondRecord['data'], $aFirstRecord['data']);
            }
        } else {
            $reply .= 'Вы не зарегистрированы. Скиньте данные вашего профиля.';
        }

        $reply .= getMessagesBlock($storage, isAdmin($nickName));
        $telegram->sendMessage([ 'chat_id' => $chat_id, 'text' => $reply, 'reply_markup' => $reply_markup ]);

    } else if (preg_match('/^Событие\s+(создать|удалить)\s+(.+)$/ui', $text, $regs)) {
        $reply = 'Недостаточно прав' . PHP_EOL;
        if (isAdmin($nickName) == true) {
            $eventAction = strtolower($regs[1]);
            $eventName   = trim($regs[2]);
            if ($eventAction == 'создать') {
                $storage->eventAdd([
                    'name' => $eventName,
                    'start' => '15.12.2019 11:00',
                    'end' => '15.12.2019 13:00',
                    'admin' => ['MorKwa', 'Lebed'],
                ]);
                $reply = 'Событие ' . $eventName . ' добавлено';
            } elseif ($eventAction == 'удалить') {
                try {
                    $storage->eventDelete($eventName);
                    $reply = 'Событие ' . $eventName . ' удалено';
                } catch (Exception $ex) {
                    $reply = 'Событие не удалено. ' . $ex->getMessage();
                }
            }
        } else {
            $reply = 'Действие доступно только для администраторов';
        }

        $reply .= getMessagesBlock($storage, isAdmin($nickName));
        $telegram->sendMessage([ 'chat_id' => $chat_id, 'parse_mode'=> 'HTML', 'text' => $reply, 'reply_markup' => $reply_markup ]);
    } else if (preg_match('/^Сообщение\s(.+)/ui', $text, $regs)) {
        $reply = 'Недостаточно прав' . PHP_EOL;
        if (isAdmin($nickName) == true) {
            $msgText = trim($regs[1]);
            $reply = '';
            if (preg_match('/^удалить\s([^\s]+)/ui', $msgText, $regs)) {
                $msgId = trim($regs[1]);
                $storage->deleteMessage($msgId);
                $reply .= 'Удаляем сообщение: ' . $msgId;
            } else {
                $storage->setMessage($msgText);
                $reply .= 'Добавляем сообщение.';
            }
        }

        $reply .= getMessagesBlock($storage, isAdmin($nickName));
        $telegram->sendMessage([ 'chat_id' => $chat_id, 'parse_mode'=> 'HTML', 'text' => $reply, 'reply_markup' => $reply_markup ]);
    } else if (mb_strtolower($text,'UTF-8') == "сообщения очистить") {
        $reply = 'Недостаточно прав' . PHP_EOL;
        if (isAdmin($nickName) == true) {
            $storage->clearMessages();
            $reply = 'Все сообщения удалены.';
        }
        $telegram->sendMessage([ 'chat_id' => $chat_id, 'text' => $reply, 'reply_markup' => $reply_markup ]);
    } else if (mb_strtolower($text,'UTF-8') == "данные очистить") {
        $reply = 'Недостаточно прав' . PHP_EOL;
        if (isAdmin($nickName) == true) {
            $storage->clearData();
            $reply = 'Все данные удалены.';
        }
        $telegram->sendMessage([ 'chat_id' => $chat_id, 'text' => $reply, 'reply_markup' => $reply_markup ]);
    } else if (preg_match('/(:?\s+\d+){25,}/', $text)) {
        $aLastData = IngressProfile::parseProfile($text);
        $storage->setAgentData($aLastData);
        $aSecondRecord = $storage->getAgentData(1);
        if (count($aSecondRecord) > 0) {
            $reply .= 'Данные добавлены.' . PHP_EOL;
            $reply .= getDeltaBlock($aLastData, $aFirstRecord['data']);
            $reply .= getMessagesBlock($storage, isAdmin($nickName));
        } else {
            $reply .= 'Данные сохранены.' . PHP_EOL;
            $reply .= getMessagesBlock($storage, isAdmin($nickName));
        }

        $telegram->sendMessage([ 'chat_id' => $chat_id, 'text' => $reply, 'reply_markup' => $reply_markup ]);
    } else {
        $aEvents = $storage->eventList();
        if (in_array($text, $aEvents)) {
            $storage->userReset($nickName);
            $storage->userRegister($text, $nickName);
            $reply = "Вы зарегистрировались на событие \"<b>".$text."</b>\".";
        } else {
            $reply = "По запросу \"<b>".$text."</b>\" ничего не найдено.";
        }
        $telegram->sendMessage([ 'chat_id' => $chat_id, 'parse_mode'=> 'HTML', 'text' => $reply ]);
    }
}else{
    $reply = "Отправьте текстовое сообщение.";
    $telegram->sendMessage([ 'chat_id' => $chat_id, 'text' => $reply, 'reply_markup' => $reply_markup ]);
}
$logger->log('Ответ бота: ' . $reply);

function getMessagesBlock(Storage $storage, $isAdmin=false)
{
    $reply = '';
    $aMessages = $storage->getMessages();
    if (count($aMessages) > 0) {
        $reply .= PHP_EOL . 'Сообщения:' . PHP_EOL;
        foreach ($aMessages as $key => $value) {
            if ($isAdmin) {
                $reply .= ' [' . $key . ']: ';
            } else {
                $reply .= ' - ';
            }
            $reply .= $value . PHP_EOL;
        }
    }
    return $reply;
}

function getDeltaBlock(array $aNewData, array $aOldData)
{
    $reply = '';
    $profile = new IngressProfile($aOldData);
    $aDelta = $profile->getDelta($aNewData);
    if (count($aDelta) > 0) {
        $sOldDate = $aOldData['Date (yyyy-mm-dd)']  . ' ' . $aOldData['Time (hh:mm:ss)'];
        $sNewDate = $aNewData['Date (yyyy-mm-dd)']  . ' ' . $aNewData['Time (hh:mm:ss)'];
        $reply .= PHP_EOL . 'Старые данные: ' . $sOldDate . PHP_EOL;
        $reply .= 'Новые данные: ' . $sNewDate . PHP_EOL;

        $aDiff = [];
        $start_date = new DateTime($sNewDate);
        $since_start = $start_date->diff(new DateTime($sOldDate));
        if ($since_start->y > 0) $aDiff[] = $since_start->y . 'лет.';
        if ($since_start->m > 0) $aDiff[] = $since_start->m . 'мес.';
        if ($since_start->d > 0) $aDiff[] = $since_start->d . 'дн.';
        if ($since_start->h > 0) $aDiff[] = $since_start->h . 'час.';
        if ($since_start->i > 0) $aDiff[] = $since_start->i . 'мин.';
        if ($since_start->s > 0) $aDiff[] = $since_start->s . 'сек.';
        $sDiff = count($aDiff) > 0 ? implode(' ', $aDiff) : 'отсутствует';

        $reply .= 'Разница: ' . $sDiff . PHP_EOL;
        $reply .= PHP_EOL . 'Произошедшие изменения:' . PHP_EOL;
        foreach($aDelta as $key => $value) {
            $reply .= '  - ' . $key . ': ' . (is_int($value) ? number_format($value, 0, ',', '.') : $value) . PHP_EOL;
        }
    } else {
        $reply .= 'Изменений не найдено' . PHP_EOL;
    }
    return $reply;
}

function isAdmin($nickName)
{
    return in_array($nickName, [
        'testNickname', // telegram nicknames without @
    ]);
}

