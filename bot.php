<?php

/** Токен, полученный у @BotFather */
define('TELEGRAM_BOT_TOKEN', '792434518:AAFilIOmSe0FiH2lIW3ieWLSKnY0Tz4epTo');
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
$result = $telegram -> getWebhookUpdates('Начать');
//$result = $telegram -> getWebhookUpdates('Событие создать "Simferopol FS" 01.02.2020 11:00 15:00');
//$result = $telegram -> getWebhookUpdates('Событие удалить Simferopol FS1');
//$result = $telegram -> getWebhookUpdates('Simferopol FS');
//$result = $telegram -> getWebhookUpdates($morkwa3);
//$result = $telegram -> getWebhookUpdates('Сообщение Превед медвед!');

$text = @$result["message"]["text"];
$chatId = @$result["message"]["chat"]["id"];
if (strlen($chatId) < 1) {
    //die('Ошибка. Скрипт нельзя вызывать непосредственно из браузера! Установите вебхук вызвав https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://<адрес и путь к скрипту>/bot.php');
    // Дёргать оповещения
    $storage = new Storage('');
    $aEventsList = $storage->eventList(true);
    foreach ($aEventsList as $eventString => $aEventData) {
        $now = time();
        $aEventAdmins = $aEventData['data']['admin'];
        $eventStart = strtotime($aEventData['data']['start']);
        $eventEnd = strtotime($aEventData['data']['end']);
        if ($now <= $eventEnd && $now >= $eventStart) {
            $logger->log('Текущее событие: ' . $eventString);
            $storage->setEventName($eventString);
            $aMessages = $storage->getMessages(true);
            $aUsers = $storage->userList();
            if (count($aMessages) > 0 && count($aUsers) > 0) {

                foreach ($aMessages as $msgId => $aMsgData) {
                    $author = $aMsgData['author'];
                    $msgText = $aMsgData['text'];

                    foreach ($aUsers as $nickName => $aUserData) {
                        $firstName = $aUserData['firstName'];
                        $chatId = $aUserData['chatId'];

                        $msg = '';
                        if (in_array($author, $aEventAdmins)) {
                            $msg .= '<b>Внимание!</b> '; // Сообщение от админа
                        } else {
                            $msg .= '@' . $author . ': ';
                        }
                        $msg .= $msgText;
                        $telegram->sendMessage([
                            'chat_id' => $chatId,
                            'text' => $msg,
                            'parse_mode'=> 'HTML',
                            'reply_markup' => $telegram->replyKeyboardMarkup([
                                'keyboard' => $aKeyboard,
                                'resize_keyboard' => true,
                                'one_time_keyboard' => false,
                            ])
                        ]);
                        $logger->log('Сообщение: ' . $msg);
                    }
                    $storage->deleteMessage($msgId);
                }
            }
            $a = 2;
        }
    }
    die;
}

/** @var $nickName - никнейм текущего пользователя */
$nickName = strlen($result["message"]["from"]["username"]) > 0 ? $result["message"]["from"]["username"] : '';
/** @var $fullUser - полное имя текущего пользователя */
$fullUser = $result["message"]["from"]["first_name"] . (strlen($nickName) > 0 ? ' (@' . $nickName . ')' : '');

$aKeyboard = [['Состояние'],['Помощь']];

$storage = new Storage($nickName);

/** @var $eventString - мероприятие на которое зарегистрирован пользователь */
$eventString = $storage->userGetRegistration($nickName);
if (strlen($eventString) > 0) {
    $storage->setEventName($eventString);
}
$aFirstRecord = $storage->getAgentData(0);
if (count($aFirstRecord) < 1) {
    array_unshift($aKeyboard, ['Начать']);
}
// Добавляем команды админу
if (isAdmin($nickName)) {
    array_unshift($aKeyboard, ['Результаты']);
    array_unshift($aKeyboard, ['Участники']);
}

$reply = '';
if($text){
    $logger->log('Текст введённый пользователем ' . $fullUser . ': ' . $text);

    // --- НАЧАТЬ ---START
    //
    if ($text == '/start' || mb_strtolower($text,'UTF-8') == "начать") {
        $reply  = 'Добро пожаловать, ' . $fullUser . PHP_EOL;

//        $reply .= 'Для учёта ваших данных, отправляйте копию вашего профиля боту (в текстовом виде).' . PHP_EOL;
//        if (strlen($eventString) > 0) {
//            $storage->deleteAgentData(false);
//            $logger->log('Сброс данных для ' . $nickName);
//        }
        $reply .= '';
        // Если где-то зарегистрированы - отменяем регистрацию
        if (strlen($eventString) > 0) {
            $storage->userUnregister($eventString, $nickName);
        }

        $reply .= getMessagesBlock($storage, isAdmin($nickName));
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $reply,
            'reply_markup' => $telegram->replyKeyboardMarkup([
                'keyboard' => $storage->eventList(),
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ])
        ]);

    //
    } else if (mb_strtolower($text,'UTF-8') == "помощь") {
        $reply  = 'Бот предназначен для отслеживания и учёта изменений игроков Ingress. Для работы нужно в игре скопировать данные профиля в Ingress Prime и как есть отправить их боту.' . PHP_EOL;
        $reply .= 'Исходный код доступен по адресу https://github.com/john1123/FirstSaturdayBot/' . PHP_EOL;

        $reply .= PHP_EOL . 'Доступны следующие команды:' . PHP_EOL;
        $reply .= '<b>Начать</b> - Удалить все запомненные ранее данные (если они были) и начать всё заново. Поаккуратней с ней :)' . PHP_EOL;

        if (isAdmin($nickName)) {
            $reply .= PHP_EOL . 'Команды администратора:' . PHP_EOL;
            $reply .= '<b>Результаты</b> - Скачать xls-файл с результатами всех игроков' . PHP_EOL;
        }

        $reply .= getMessagesBlock($storage, isAdmin($nickName));

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'parse_mode'=> 'HTML',
            'text' => $reply,
            'reply_markup' => $telegram->replyKeyboardMarkup([
                'keyboard' => $aKeyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ])
        ]);

    //
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
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $reply,
            'reply_markup' => $telegram->replyKeyboardMarkup([
                'keyboard' => $aKeyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ])
        ]);


    //
    // Данные профиля
    } else if (preg_match('/(:?\s+\d+){25,}/', $text)) {

        if (strlen($eventString) > 0) {
            $eventData = $storage->eventGet($eventString);

            $eventTimeStart = $eventData['data']['start'];
            $eventTimeEnd = $eventData['data']['end'];

            if (time() < strtotime($eventTimeStart)) {
                $reply .= '' . $eventString . ' ещё не наступил. Мы пришлём вам уведомление, когда оно начнётся.';
            } elseif (time() > strtotime($eventTimeEnd)) {
                $reply .= '' . $eventString . '  уже закончился.';
                // Отменить регистрацию пользователя?
                // $storage->userUnregister($eventName, $nickName);
            } else {
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

            }
            $reply .= getMessagesBlock($storage, isAdmin($nickName));
        } else {
            $reply .= 'Зарегистрируйтесь на одно из событий.';
            $reply .= getMessagesBlock($storage, isAdmin($nickName));
        }

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $reply,
            //'parse_mode'=> 'HTML',
            'reply_markup' => $telegram->replyKeyboardMarkup([
                'keyboard' => $aKeyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ])
        ]);


    // Создание-удаление события
    } else if (preg_match('/^Событие\s+(создать|удалить)\s+(.+)$/ui', $text, $regs)) {
        $reply = 'Недостаточно прав' . PHP_EOL;
        if (isAdmin($nickName) == true) {
            $eventAction = mb_strtolower($regs[1]);
            $eventString   = trim($regs[2]);
            if ($eventAction == 'создать') {
                if (preg_match('/^(?:[\'"]([^\'"]+)[\'"]|(\w+))\s*(.+)$/m', $eventString, $regs)) {
                    $eventName = strlen($regs[1]) > 0 ? $regs[1] : $regs[2];
                    $aParams = explode(' ', $regs[3]);
                    $start = $aParams[0] . ' ' . $aParams[1];
                    $end = $aParams[0] . ' ' . $aParams[2];
                    $aAdmins = [$nickName];
                    for ($i=3; $i<count($aParams); $i++) {
                        $aAdmins[] = $aParams[$i];
                    }
                    $storage->eventAdd([
                        'name' => $eventName,
                        'start' => $start,
                        'end' => $end,
                        'admin' => array_unique($aAdmins),
                    ]);
                    $reply = 'Событие "' . $eventName . '" добавлено';

                } else {
                    // неверный формат. Ожидается:
                    // "Название мероприятия" дд.мм.гггг времяНачала времяКонца админ1 авмин2 админ3 ...
                    $reply = 'Событие не добавлено. ' . $eventString . ' добавлено';
                }
            } elseif ($eventAction == 'удалить') {
                try {
                    $storage->eventDelete($eventString);
                    $reply = 'Событие ' . $eventString . ' удалено';
                } catch (Exception $ex) {
                    $reply = 'Событие не удалено. ' . $ex->getMessage();
                }
            }
        } else {
            $reply = 'Действие доступно только для администраторов';
        }

        $reply .= getMessagesBlock($storage, isAdmin($nickName));
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $reply,
            'parse_mode'=> 'HTML',
            'reply_markup' => $telegram->replyKeyboardMarkup([
                'keyboard' => $aKeyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ])
        ]);
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
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $reply,
            'parse_mode'=> 'HTML',
            'reply_markup' => $telegram->replyKeyboardMarkup([
                'keyboard' => $aKeyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ])
        ]);
    } else if (mb_strtolower($text,'UTF-8') == "сообщения очистить") {
        $reply = 'Недостаточно прав' . PHP_EOL;
        if (isAdmin($nickName) == true) {
            $storage->clearMessages();
            $reply = 'Все сообщения удалены.';
        }
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $reply,
            //'parse_mode'=> 'HTML',
            'reply_markup' => $telegram->replyKeyboardMarkup([
                'keyboard' => $aKeyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ])
        ]);

    // Регистрация на событие
    } else {

        // Если передано имя события - регистрируем пользователя на него
        $aEvents = $storage->eventList();
        if (in_array($text, $aEvents)) {
            $storage->userReset($nickName); // Надо ли?
            $storage->userRegister($text, $nickName, [
                'firstName' => $result['message']['from']['first_name'],
                'chatId' => $result['message']['chat']['id'],
              ]);
            $reply = "Вы успешно зарегистрировались на событие \"<b>".$text."</b>\".";

        } else {
            $reply = "По запросу \"<b>".$text."</b>\" ничего не найдено.";
        }
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $reply,
            'parse_mode'=> 'HTML',
            'reply_markup' => $telegram->replyKeyboardMarkup([
                'keyboard' => $aKeyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ])
        ]);
    }
} else {
    $reply = "Отправьте текстовое сообщение.";
    $telegram->sendMessage([
        'chat_id' => $chatId,
        'text' => $reply,
        //'parse_mode'=> 'HTML',
        'reply_markup' => $telegram->replyKeyboardMarkup([
            'keyboard' => $aKeyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ])
    ]);
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
