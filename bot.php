<?php
date_default_timezone_set('Europe/Moscow');

/** Токен, полученный у @BotFather */
define('TELEGRAM_BOT_TOKEN', '792434518:AAFilIOmSe0FiH2lIW3ieWLSKnY0Tz4epTo');

//include('test.php');include('TestApi.php');include('vendor/john1123/logger/src/File.php');
include('vendor/autoload.php');
include('IngressProfile.php');
include('Storage.php');

use Telegram\Bot\Api;
use John1123\Logger\File as Logger;

$logger = new Logger(__DIR__ . '/data/bot_' . date('Ymd') . '.log');

$telegram = new Api(TELEGRAM_BOT_TOKEN);
$result = $telegram -> getWebhookUpdates();
//$result = $telegram -> getWebhookUpdates('Начать');
//$result = $telegram -> getWebhookUpdates('Состояние');
//$result = $telegram -> getWebhookUpdates('Событие создать "IngressFS - Simferopol - February 2020" 24.01.2020 10:00 21:00');
//$result = $telegram -> getWebhookUpdates('Событие удалить Simferopol FS1');
//$result = $telegram -> getWebhookUpdates('IngressFS - Simferopol - February 2020');
//$result = $telegram -> getWebhookUpdates($morkwa1);
//$result = $telegram -> getWebhookUpdates('Результаты');
//$result = $telegram -> getWebhookUpdates('Участники');
//$result = $telegram -> getWebhookUpdates('Помощь');
//$result = $telegram -> getWebhookUpdates('Профиль ' . $profile);

$text = @$result["message"]["text"];
$chatId = @$result["message"]["chat"]["id"];
if (strlen($chatId) < 1) {
    // Вызов без параметров (из Крона)
    // Дёргать оповещения
    $storage = new Storage('');
    $aMessages = $storage->getMessages();
    $cache = [];
    foreach ($aMessages as $msgId => $msgData) {
        if (time() >= strtotime($msgData['when'])) {
            if (preg_match('/^group:(.+)$/m', $msgData['to'], $regs)) {
                $msgEventName = $regs[1];
                if (!array_key_exists($msgEventName, $cache)) {
                    $aUserList = $storage->userList($msgEventName);
                    $cache[$msgEventName] = $aUserList;
                } else {
                    $aUserList = $cache[$msgEventName];
                }
                foreach ($aUserList as $msgChatId => $msgUserData) {
                    $logger->log('Cron. Сообщение: ' . $msgData['text'] . ' по адресу ' . $msgChatId);
                    sendTelegramMessage($msgChatId, $msgData['text']);
                }
                $storage->deleteMessage($msgId);
            }
        }
    }
    die;
}

$nickName = strlen($result["message"]["from"]["username"]) > 0 ? $result["message"]["from"]["username"] : '';
/** @var $fullUser - полное имя текущего пользователя */
$fullUser = $result["message"]["from"]["first_name"] . (strlen($nickName) > 0 ? ' [@' . $nickName . ']' : '');

$aKeyboard = [['Состояние']/*,['Помощь']*/];

$storage = new Storage($chatId);

/** @var $eventString - мероприятие на которое зарегистрирован пользователь */
$eventString = $storage->userGetRegistration($chatId);
if (strlen($eventString) > 0) {
    $storage->setEventName($eventString);
}
$aFirstRecord = $storage->getAgentData(0);
//if (count($aFirstRecord) < 1) {
//    array_unshift($aKeyboard, ['Начать']);
//}
// Добавляем команды админу
if (strlen($eventString) > 0 && isAdmin($nickName)) {
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

        $aKeyboard = [$storage->eventList()];

        if (count($aKeyboard[0] ) > 0) {
            $reply .= 'Для начала работы, вам необходимо зарегистрироваться на одно из предстоящих событий. ';
            $reply .= 'Для этого, пожалуйста, выберите событие нажав на соответствующую кнопку.' . PHP_EOL;
        } else {
            if (isAdmin($nickName) == true) {
                $reply .= 'Нет предстоящих событий. Вам необходимо создать хотя бы одно.' . PHP_EOL;
                $reply .= 'Воспользуйтесь командой <b>Создать событие</b>' . PHP_EOL;

            } else {
                $reply .= 'Событий в настоящее время не создано.' . PHP_EOL;
                $reply .= 'Сообщите, пожалуйста, об этом организаторам' . PHP_EOL;
            }
        }
        $reply .= PHP_EOL . 'Вы можете воспользоваться командой <b>Помощь</b> в любой момент для вызова справки.';

        // Если где-то зарегистрированы - отменяем регистрацию
        if (strlen($eventString) > 0) {
            $storage->userUnregister($eventString, $chatId);
        }

        sendTelegramMessage($chatId, $reply, $aKeyboard);

    //
    // -- СОСТОЯНИЕ
    } else if (mb_strtolower($text,'UTF-8') == "состояние") {
        $reply = '';
        if (strlen($eventString) > 0) {
            $aEventData = $storage->eventGet($eventString);
            if (time() < strtotime($aEventData['start'])) {
                $reply .= 'Событие "<b>' . $eventString . '</b>"' . PHP_EOL;
                $reply .= 'Ждём начала.' . PHP_EOL;
                $reply .= 'Осталось ' . date_difference(date('d.m.Y H:i:s'), $aEventData['start']);
            } else if(time() > strtotime($aEventData['end'])) {
                $reply .= 'Уже окончен ' . $eventString . PHP_EOL;
                $reply .= 'С окончания прошло ' . date_difference(date('d.m.Y H:i:s'), $aEventData['end']);
            } else {
                $reply .= 'Сейчас проходит "' . $eventString . '".' . PHP_EOL;
                $reply .= 'Вы можете отправлять статистику боту, обновляя ваш результат.' . PHP_EOL;

                $aOldData = $storage->getAgentData(0);
                if (count($aOldData) > 0) {
                    $aNewData = $storage->getAgentData(1);
                    if (count($aNewData) > 0) {
                        $reply .= 'Ваши последние изменения:' . PHP_EOL;
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
            $aEvents = $storage->eventList();
            $aKeyboard = count($aEvents) > 0 ? [$aEvents] : [];
            if (count($aKeyboard[0] ) > 0) {
                $reply .= 'Для начала работы, вам необходимо зарегистрироваться на одно из предстоящих событий. ';
                $reply .= 'Для этого, пожалуйста, выберите событие нажав на соответствующую кнопку.' . PHP_EOL;
            } else {
                if (isAdmin($nickName) == true) {
                    $reply .= 'Нет предстоящих событий. Вам необходимо создать хотя бы одно.' . PHP_EOL;
                    $reply .= 'Воспользуйтесь командой <b>Создать событие</b>' . PHP_EOL;
                } else {
                    $reply .= 'Событий в настоящее время не создано.' . PHP_EOL;
                    $reply .= 'Сообщите, пожалуйста, об этом организаторам' . PHP_EOL;
                }
            }
        }

        sendTelegramMessage($chatId, $reply, $aKeyboard);

    //
    // -- СБРОС
    } else if (mb_strtolower($text,'UTF-8') == "сброс") {
        if (strlen($eventString) > 0) {
            $reply .= 'Данные успешно очищены.' . PHP_EOL;
            $storage->deleteAgentData(false, $eventString);
        } else {
            $aEvents = $storage->eventList();
            $aKeyboard = count($aEvents) > 0 ? [$aEvents] : [];
            if (count($aKeyboard[0] ) > 0) {
                $reply .= 'Для начала работы, вам необходимо зарегистрироваться на одно из предстоящих событий. ';
                $reply .= 'Для этого, пожалуйста, выберите событие нажав на соответствующую кнопку.' . PHP_EOL;
            } else {
                if (isAdmin($nickName) == true) {
                    $reply .= 'Нет предстоящих событий. Вам необходимо создать хотя бы одно.' . PHP_EOL;
                    $reply .= 'Воспользуйтесь командой <b>Создать событие</b>' . PHP_EOL;

                } else {
                    $reply .= 'Событий в настоящее время не создано.' . PHP_EOL;
                    $reply .= 'Сообщите, пожалуйста, об этом организаторам' . PHP_EOL;
                }
            }
        }
        sendTelegramMessage($chatId, $reply, $aKeyboard);

    //
    // -- ПОМОЩЬ
    } else if (mb_strtolower($text,'UTF-8') == "помощь") {
        $reply  = 'Бот предназначен для отслеживания и учёта изменений игроков Ingress. Для работы нужно в игре скопировать данные профиля в Ingress Prime и как есть отправить их боту.' . PHP_EOL;
        $reply .= 'Доступны следующие команды:' . PHP_EOL . PHP_EOL;
        $reply .= '<b>Начать</b> - Отменить регистрацию на событие. Используйте, чтобы сменить событие.' . PHP_EOL;
        $reply .= '<b>Сброс</b> - Удалить всю свою статистику и начать всё заново.' . PHP_EOL;
        $reply .= '<b>Состояние</b> - Текущее состояние. Зарегистрированы ли вы? Идёт ли событие и т.п.' . PHP_EOL;
        $reply .= '<b>Помощь</b> - Краткое описание команд. Этот текст.' . PHP_EOL;
        $reply .= '<b>Участники</b> - Количество людей зарегистрированных на событие.' . PHP_EOL;

        if (isAdmin($nickName)) {
            $reply .= PHP_EOL;
            $reply .= 'Команды администратора:' . PHP_EOL;
            $reply .= '<b>Участники</b> - Также показывает список участников события скидывавших статистику. В скобках - сколько раз (1-один или 2-много).' . PHP_EOL;
            $reply .= '<b>Профиль ДанныеПрофиля</b> - Позводяет разобрать данные любого профиля (ДанныеПрофиля). Полезна при добавлении игрока в таблицу в ручном режиме.' . PHP_EOL;
            $reply .= '<b>Результаты</b> - Загрузить результаты всех участников события собранные в xls-файл' . PHP_EOL;
            //$reply .= '<b>Событие (создать|удалить)</b> - Создать или удалить новое событие. Имеет формат <i>Событие создать "Название события" ДатаНачала ВремяНачала ВремяКонца</i>. Например "Событие создать "SimferopolFS - Тест" 23.01.2020 10:00 21:00" или "Событие удалить НазваниеСобытия"' . PHP_EOL;
        }

        $reply .= PHP_EOL;
        $reply .= 'Автор бота MorKwa E15 @MorKwa' . PHP_EOL;
        $reply .= 'Исходный код доступен по адресу https://github.com/john1123/FirstSaturdayBot/' . PHP_EOL;

        sendTelegramMessage($chatId, $reply, $aKeyboard);


    //
    // --УЧАСТНИКИ
    } else if (mb_strtolower($text,'UTF-8') == "участники") {
        $reply .= 'Участники "<b>' . $eventString . '</b>".' . PHP_EOL;
        $aUserList = $storage->userList();
        $reply .= 'Заявки: ' . count($aUserList) . PHP_EOL;
        foreach ($aUserList as $aUser) {
            $nn = strlen($aUser['nickName'])  > 0 ? (' [@' . $aUser['nickName'] . ']') : '';
            $un = preg_replace('/[^a-zA-ZА-Яа-я0-9\s\(\)]/u', '?', $aUser['firstName']);
            //$un = strlen($un) !== 0 ? $un : '?';
            $reply .= '- ' . $un . $nn . PHP_EOL;
        }
        $reply .= PHP_EOL;
        if (isAdmin($nickName) == true) {
            if (strlen($eventString) > 0) {
                $aAllData = $storage->getAllData($aAllData);
                $reply .= 'Скинули статистику: ' . count($aAllData) . PHP_EOL;
                if (count($aAllData) > 0) {
                    foreach ($aAllData as $aData) {
                        $agentName = $aData[0]['data']['Agent Name'];
                        $agentLevel = $aData[0]['data']['Level'];
                        $agentFaction = $aData[0]['data']['Agent Faction'];
                        $agentFaction = $agentFaction == 'Enlightened' ? 'E' : 'R';
                        $reply .= '- ' . $agentName . ' ' . $agentFaction . $agentLevel . ' (' . count($aData) . ')' . PHP_EOL;
                    }
                }
            }
        }
        sendTelegramMessage($chatId, $reply, $aKeyboard);

        //
    // --ПРОФИЛЬ
    } else if (preg_match('/^Профиль\s+(.+)/sim', $text, $regs)) {
        $aProfile = IngressProfile::parseProfile($regs[1]);
        $reply .= 'Агент: ' . $aProfile['Agent Name'] . PHP_EOL;
        $reply .= 'Фракция: ' . $aProfile['Agent Faction'] . PHP_EOL;
        foreach (IngressProfile::$aDeltaKeys as $key) {
            $reply .= $key . ': ' . $aProfile[$key] . PHP_EOL;
        }
        sendTelegramMessage($chatId, $reply, $aKeyboard);


        //
    // --РЕЗУЛЬТАТЫ
    } else if (mb_strtolower($text,'UTF-8') == "результаты") {
        if (isAdmin($nickName) == true) {
            $aAllData = $storage->getAllData();
            if (count($aAllData) > 0) {
                $telegram->sendDocument([
                    'chat_id' => $chatId,
                    'document' => 'https://'
                        . $_SERVER['SERVER_NAME']
                        . str_replace('bot.php', 'result.php', $_SERVER['SCRIPT_NAME'])
                        // result.xls в конце строки добавлен для того, чтобы телеграм видел в конце строки файл эксель.
                        . '?file=' . urlencode($eventString) . '/result.xls',
                    'caption' => 'Файл с результатами',
                ]);
            } else {
                $reply .= 'Событие "<b>' . $eventString . '</b>".' . PHP_EOL;
                $reply = 'Файл с результатами пуст' . PHP_EOL;
            }
        } else {
            $reply = 'Недостаточно прав' . PHP_EOL;
        }
        sendTelegramMessage($chatId, $reply, $aKeyboard);


        //
    // Создание-удаление события
    } else if (preg_match('/^Событие\s+(создать|удалить)\s+(.+)$/ui', $text, $regs)) {
        if (isAdmin($nickName) == true) {
            $eventAction = mb_strtolower($regs[1]);
            $eventName   = trim($regs[2]);
            if ($eventAction == 'создать') {
                if (preg_match('/^(?:[\'"]([^\'"]+)[\'"]|(\w+))\s*(.+)$/m', $eventName, $regs)) {
                    $eventName = strlen($regs[1]) > 0 ? $regs[1] : $regs[2];
                    $aParams = explode(' ', $regs[3]);
                    $start = $aParams[0] . ' ' . $aParams[1];
                    $end = $aParams[0] . ' ' . $aParams[2];
                    $aAdmins = [];
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
                        'Начат <b>' . $eventName . '</b>.' . PHP_EOL . PHP_EOL .
                        'Не забудьте взломать стартовый портал и прислать боту статистику.',
                        'group:' . $eventName,
                        $fullUser,
                        ['when' => $start]
                    );
                    $storage->setMessage(
                        'Осталось 10 минут до конца <b>' . $eventName . '</b>.' . PHP_EOL . PHP_EOL .
                        'Взломайте любой портал и отправьте боту статистику.',
                        'group:' . $eventName,
                        $fullUser,
                        ['when' => date('d.m.Y H:i:s', (strtotime($end) - 10*60))]

                    );
                    $storage->setMessage(
                        'Окончен <b>' . $eventName . '</b>.' . PHP_EOL . PHP_EOL .
                        'Статистика больше не принимается.',
                        'group:' . $eventName,
                        $fullUser,
                        ['when' => $end]
                    );
                    $reply = 'Событие "' . $eventName . '" создано.';
                } else {
                    // неверный формат. Ожидается:
                    // "Название мероприятия" дд.мм.гггг времяНачала времяКонца админ1 авмин2 админ3 ...
                    $reply = 'Событие не добавлено.' . PHP_EOL;
                    $reply = 'Не могу разобрать команду.';
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

        if (strlen($eventString) < 1) {
            $aKeyboard = [[]];
        }
        sendTelegramMessage($chatId, $reply, $aKeyboard);


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
                // $storage->userUnregister($eventName, $chatId);
            } else {
                try {
                    $aLastData = IngressProfile::parseProfile($text);
                    $storage->setAgentData($aLastData);
                    $aSecondRecord = $storage->getAgentData(1);
                    if (count($aSecondRecord) > 0) {
                        $reply .= 'Данные добавлены.' . PHP_EOL;
                        $reply .= getDeltaBlock($aLastData, $aFirstRecord['data']);
                    } else {
                        $reply .= 'Полученные данные:' . PHP_EOL;
                        foreach($aLastData as $key => $value) {
                            $reply .= '  - ' . $key . ': ' . (is_int($value) ? number_format($value, 0, ',', '.') : $value) . PHP_EOL;
                        }
                        $reply .= PHP_EOL . 'Данные сохранены.' . PHP_EOL;
                    }
                } catch (Exception $e) {
                    $reply .= 'Ошибка: Данные не могут быть распознаны. ';
                    $reply .= 'Необходимо отправлять боту статистику ЗА ВСЁ ВРЕМЯ';
                }
            }
        } else {
            $reply .= 'Вы не зарегистрированы.' . PHP_EOL;
            $reply .= 'Зарегистрируйтесь на одно из событий используя кнопку ниже.';
            $aKeyboard = [$storage->eventList()];
        }
        sendTelegramMessage($chatId, $reply, $aKeyboard);

    //
    // Регистрация на событие
    } else {

        // Если передано имя события - регистрируем пользователя на него
        $aEvents = $storage->eventList();
        if (in_array($text, $aEvents)) {
            $storage->userReset($chatId); // Надо ли?
            $storage->userRegister($text, $result['message']['chat']['id'], [
                'nickName' => $nickName,
                'firstName' => $result['message']['from']['first_name'],
                'chatId' => $result['message']['chat']['id'],
              ]);
            $eventString = $text;

            $reply  .= "Вы успешно зарегистрировались на \"<b>".$text."</b>\"." . PHP_EOL;
            $aEventData = $storage->eventGet($eventString);
            if (time() < strtotime($aEventData['start'])) {
                $reply  .= "Мы уведомим вас, когда событие начнётся." . PHP_EOL;
            } elseif (time() > strtotime($aEventData['end'])) {
                $reply  .= "Событие уже закончилось!." . PHP_EOL;
            } else {
                $reply  .= "Событие идёт в настоящее время!" . PHP_EOL;
                $reply  .= "Скиньте боту вашу статистику." . PHP_EOL;
            }


        } else {
            $reply = "По запросу \"<b>".$text."</b>\" ничего не найдено.";
        }
        sendTelegramMessage($chatId, $reply, $aKeyboard);
    }
} else {
    $reply = "Отправьте текстовое сообщение.";
    sendTelegramMessage($chatId, '10'.$reply, $aKeyboard);
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
function isAdmin($nickname, $eventname='')
{
    //return true; // DEBUG: все и везде админы!

    /** @var $aAdmins array - Ники из этого списка всегда будут админскими */
    $aAdmins = [
        'MorKwa',
        'testNickname', // telegram nicknames without @
    ];

    global $storage, $eventString;
    // Если событие не указано - берём текущее
    if (strlen($eventname) == 0) {
        $eventname = $eventString;
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

function getMarkup($aKeyboard) {
    if (count($aKeyboard) == 1) {
        return [];
    }
    global $telegram;
    return $telegram->replyKeyboardMarkup([
        'keyboard' => $aKeyboard,
        'resize_keyboard' => true,
        'one_time_keyboard' => true,
    ]);
}
function sendTelegramMessage($chatId, $message, array $aKeyboard=[[]]) {
    global $telegram;
    if (count($aKeyboard[0]) > 0) {
        return $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode'=> 'HTML',
            'reply_markup' => $telegram->replyKeyboardMarkup([
                'keyboard' => $aKeyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ])
        ]);
    } else {
        return $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode'=> 'HTML',
        ]);
    }
}
