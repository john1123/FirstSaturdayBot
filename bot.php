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
$result = $telegram -> getWebhookUpdates();
//$result = $telegram -> getWebhookUpdates('Начать');
//$result = $telegram -> getWebhookUpdates('Состояние');
//$result = $telegram -> getWebhookUpdates('Событие создать "SimferopolFS - Февраль 2020" 01.02.2020 11:00 15:00');
//$result = $telegram -> getWebhookUpdates('Событие удалить Simferopol FS1');
//$result = $telegram -> getWebhookUpdates('SimferopolFS - Февраль 2020');
//$result = $telegram -> getWebhookUpdates($morkwa4);
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
        $aEventAdmins = $aEventData['admins'];
        $eventStart = strtotime($aEventData['start']);
        $eventEnd = strtotime($aEventData['end']);
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
                                'resize_keyboard' => false,
                                'one_time_keyboard' => true,
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
        $reply  .= 'Добро пожаловать, ' . $fullUser . PHP_EOL;
        $reply .= 'Вам необходимо зарегистрироваться на одно из предстоящих событий. ';
        $reply .= 'Пожалуйста, выберите событие нажав на соответствующую кнопку.';

        // Если где-то зарегистрированы - отменяем регистрацию
        if (strlen($eventString) > 0) {
            $storage->userUnregister($eventString, $nickName);
        }
        $aKeyboard = $storage->eventList();

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $reply,
            'reply_markup' => $telegram->replyKeyboardMarkup([
                'keyboard' => $aKeyboard,
                'resize_keyboard' => false,
                'one_time_keyboard' => true,
            ])
        ]);

    //
    // -- ПОМОЩЬ
    } else if (mb_strtolower($text,'UTF-8') == "помощь") {
        $reply  = 'Бот предназначен для отслеживания и учёта изменений игроков Ingress. Для работы нужно в игре скопировать данные профиля в Ingress Prime и как есть отправить их боту.' . PHP_EOL;
        $reply .= 'Исходный код доступен по адресу https://github.com/john1123/FirstSaturdayBot/' . PHP_EOL;

        $reply .= PHP_EOL . 'Доступны следующие команды:' . PHP_EOL;
        $reply .= '<b>Начать</b> - Удалить все запомненные ранее данные (если они были) и начать всё заново. Поаккуратней с ней :)' . PHP_EOL;

        if (isAdmin($nickName)) {
            $reply .= PHP_EOL . 'Команды администратора:' . PHP_EOL;
            $reply .= '<b>Результаты</b> - Скачать xls-файл с результатами всех игроков' . PHP_EOL;
        }

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'parse_mode'=> 'HTML',
            'text' => $reply,
            'reply_markup' => $telegram->replyKeyboardMarkup([
                'keyboard' => $aKeyboard,
                'resize_keyboard' => false,
                'one_time_keyboard' => true,
            ])
        ]);


    //
    // -- СОСТОЯНИЕ
    } else if (mb_strtolower($text,'UTF-8') == "состояние") {
        $reply = '';
        if (strlen($eventString) > 0) {
            $aEventData = $storage->eventGet($eventString);
            if (time() < strtotime($aEventData['start'])) {
                $reply .= 'Ждём начала ' . $eventString . PHP_EOL;
                $reply .= 'До начала осталось ' . date_difference(date('d.m.Y H:i:s'), $aEventData['start']);
            } else if(time() > strtotime($aEventData['end'])) {
                $reply .= 'Уже окончен ' . $eventString . PHP_EOL;
                $reply .= 'С окончания прошло ' . date_difference(date('d.m.Y H:i:s'), $aEventData['end']);
            } else {
                //$reply .= 'Вы зарегистрированы на событие "' . $eventString . '". ';
                $reply .= 'Сейчас проходит ' . $eventString . PHP_EOL;
                $reply .= 'Вы можете отправлять статистику боту, обновляя ваш результат.' . PHP_EOL . PHP_EOL;

                $aOldData = $storage->getAgentData(0);
                if (count($aOldData) > 0) {
                    $aNewData = $storage->getAgentData(1);
                    if (count($aNewData) > 0) {
                        $reply .= getDeltaBlock($aNewData['data'], $aOldData['data']);
                    } else {
                        $reply .= 'Есть начальные данные. ';
                        $reply .= 'Для получения изменений, оправьте боту вашу статистику ещё раз.' . PHP_EOL;
                    }
                } else {
                    $reply .= 'Нет данных. Отправьте боту вашу статистику.' . PHP_EOL;
                }
            }
        } else {
            //
            $reply .= 'Вы не зарегистрированы. Вам надо зарегистрироваться на одно из предстоящих событий.' . PHP_EOL;
            $reply .= 'Выберите одно из событий, нажав кнопку';
            $aKeyboard = $storage->eventList();
        }

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $reply,
            'parse_mode'=> 'HTML',
            'reply_markup' => $telegram->replyKeyboardMarkup([
                'keyboard' => $aKeyboard,
                'resize_keyboard' => false,
                'one_time_keyboard' => true,
            ])
        ]);


    //
    // Данные профиля
    } else if (preg_match('/(:?\s+\d+){25,}/', $text)) {

        if (strlen($eventString) > 0) {
            $eventData = $storage->eventGet($eventString);

            $eventTimeStart = $eventData['start'];
            $eventTimeEnd = $eventData['end'];

            if (time() < strtotime($eventTimeStart)) {
                $reply .= '"' . $eventString . '" ещё не наступил.' . PHP_EOL;
                $reply .= 'Начало через ' . date_difference(date('d.m.Y H:i:s'), $eventTimeStart) . PHP_EOL;
                $reply .= 'Мы пришлём вам уведомление, когда событие начнётся.';
            } elseif (time() > strtotime($eventTimeEnd)) {
                $reply .= '' . $eventString . '  уже закончился.';
                // Отменить регистрацию пользователя?
                // $storage->userUnregister($eventName, $nickName);
            } else {
                try {
                    $aLastData = IngressProfile::parseProfile($text);
                    $storage->setAgentData($aLastData);
                    $aSecondRecord = $storage->getAgentData(1);
                    if (count($aSecondRecord) > 0) {
                        $reply .= 'Данные добавлены.' . PHP_EOL;
                        $reply .= getDeltaBlock($aLastData, $aFirstRecord['data']);
                    } else {
                        $reply .= 'Данные сохранены.' . PHP_EOL;
                    }
                } catch (Exception $e) {
                    $reply .= 'Ошибка: Данные не могут быть распознаны. ';
                    $reply .= 'Необходимо отправлять боту статистику ЗА ВСЁ ВРЕМЯ';
                }
            }
        } else {
            $reply .= 'Вы не зарегистрированы.' . PHP_EOL;
            $reply .= 'Зарегистрируйтесь на одно из событий используя кнопку ниже.';
            $aKeyboard = $storage->eventList();
        }

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $reply,
            'parse_mode'=> 'HTML',
            'reply_markup' => $telegram->replyKeyboardMarkup([
                'keyboard' => $aKeyboard,
                'resize_keyboard' => false,
                'one_time_keyboard' => true,
            ])
        ]);


    // Создание-удаление события
    } else if (preg_match('/^Событие\s+(создать|удалить)\s+(.+)$/ui', $text, $regs)) {
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
                    $storage->eventAdd(
                        $eventName,
                        [
                            'start' => $start,
                            'end' => $end,
                            'admin' => array_unique($aAdmins),
                        ]
                    );
                    $storage->setEventName($eventName);
                    $storage->setMessage(
                        'Начат ' . $eventName . PHP_EOL .
                        'Не забудьте взломать стартовый портал и прислать боту статистику.',
                        $start
                    );

                    $storage->setMessage(
                        'Осталось 10 минут до конца ' . $eventName . PHP_EOL .
                        'Взломайте любой портал и отправьте боту статистику.',
                        date('d.m.Y H:i:s', (strtotime($end) - 10*60))
                    );
                    $storage->setMessage(
                        'Окончен ' . $eventName . PHP_EOL .
                        'Статистика больше не принимается.',
                        $end
                    );
                    $reply = 'Событие "' . $eventName . '" добавлено. ';
                    $reply = 'Используйте формат вида: "Событие создать "Название события" ДатаСобытия ВремяНачала ВремяКонца Админ1 Админ2 ... "';

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

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $reply,
            'parse_mode'=> 'HTML',
            'reply_markup' => $telegram->replyKeyboardMarkup([
                'keyboard' => $aKeyboard,
                'resize_keyboard' => false,
                'one_time_keyboard' => true,
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

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $reply,
            'parse_mode'=> 'HTML',
            'reply_markup' => $telegram->replyKeyboardMarkup([
                'keyboard' => $aKeyboard,
                'resize_keyboard' => false,
                'one_time_keyboard' => true,
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
                'resize_keyboard' => false,
                'one_time_keyboard' => true,
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
            $reply  .= "Вы успешно зарегистрировались на \"<b>".$text."</b>\".";
            $reply  .= "Мы уведомим вас, когда оно начнётся.";

        } else {
            $reply = "По запросу \"<b>".$text."</b>\" ничего не найдено.";
        }
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $reply,
            'parse_mode'=> 'HTML',
            'reply_markup' => $telegram->replyKeyboardMarkup([
                'keyboard' => $aKeyboard,
                'resize_keyboard' => false,
                'one_time_keyboard' => true,
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
            'resize_keyboard' => false,
            'one_time_keyboard' => true,
        ])
    ]);
}
$logger->log('Ответ бота: ' . $reply);

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

        $sDiff = date_difference($sNewDate, $sOldDate);
        $sDiff = strlen($sDiff > 0) ? $sDiff : 'отсутствует';

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
 /**
  * админом может только человек с заполнеными именем пользователя (никнеймом) в Телеграм
 */
function isAdmin($eventname='', $nickname='')
{
    //return true; // DEBUG: все и везде админы!

    /** @var $aAdmins array - Ники из этого списка всегда будут админскими */
    $aAdmins = [
        'testNickname', // telegram nicknames without @
    ];

    global $storage, $nickName, $eventString;
    // Если событие не указано - берём текущее
    if (strlen($eventname) == 0) {
        $eventname = $eventString;
    }
    // Если пользователь не указан - берём текущего
    if (strlen($nickname) == 0) {
        $nickname = $nickName;
    }
    // Если имя пользователя не указано (в настройках Телеграм) - админом он быть не может
    if (strlen($nickname) == 0) {
        return false;
    }
    // Пользователи из этого списка всегда админы
    if (in_array($nickname, $aAdmins)) {
        return true;
    }
    return $storage->isAdmin($eventname, $nickname);
}

/**
 * Возвращает разницу между двумя переданными датами
 */
function date_difference($sDate1, $sDate2)
{
    $aDiff = [];
    $start_date = new DateTime($sDate2);
    $since_start = $start_date->diff(new DateTime($sDate1));
    if ($since_start->y > 0) $aDiff[] = $since_start->y . 'лет.';
    if ($since_start->m > 0) $aDiff[] = $since_start->m . 'мес.';
    if ($since_start->d > 0) $aDiff[] = $since_start->d . 'дн.';
    if ($since_start->h > 0) $aDiff[] = $since_start->h . 'час.';
    if ($since_start->i > 0) $aDiff[] = $since_start->i . 'мин.';
    if ($since_start->s > 0) $aDiff[] = $since_start->s . 'сек.';
    $sDiff = count($aDiff) > 0 ? implode(' ', $aDiff) : '';
    return $sDiff;
}