<?php

/**
 * Класс для хранения данных
 * Используется самое простейшее хранилище - json файлы со всеми своими нинусами (например потребление памяти)
 */
class Storage
{
    /** Файл с данными игроков */
    protected static $dataFile = 'statistics.json';
    /** Файл с сообщениями */
    protected static $messagesFile = 'messages.json';
    /** Файл с событиями */
    protected static $eventsFile = 'events.json';
    /** Файл с пользователями */
    protected static $usersFile = 'users.json';

    /** папка где хранятся данные. должна быть открыта для записи! */
    protected static $dataDir = 'data/';

    protected $username;

    public function __construct($username)
    {
        $this->username = $username;
    }


    /**
     * Получить все сообщения в виде массива
     */
    public function getMessages($fullData=false)
    {
        $sFilename = self::$dataDir . self::$messagesFile;
        $sContents = @file_get_contents($sFilename);
        $aMessages = strlen($sContents) > 0 ? json_decode($sContents, true) : [];

        $aText = [];
        if ($fullData === false) {
            foreach ($aMessages as $key => $value) {
                $aText[$key] = ''
//                    . $value['date']
//                    . ' '
                    . $value['time']
                    . ' @'
                  . $value['author']
                  . ': '
                  . $value['text'];
            }
            $aMessages = $aText;
        }
        return $aMessages;
    }

    /**
     * Добавить новое сообщение
     *
     * @param String $sMessage Сообщение
     * @param String $type Тип сообщения. Сейчас не используется.
     * @return array Массив сообщений в виде готовых для вывода строк
     */
    public function setMessage($sMessage, $type='text')
    {
        $sFilename = self::$dataDir . self::$messagesFile;
        $sContents = @file_get_contents($sFilename);
        $aMessages = strlen($sContents) > 0 ? json_decode($sContents, true) : [];
        $iId = 0;
        if (count($aMessages) > 0) {
            //ksort($aMessages);
            $sLastKey = array_keys($aMessages)[count($aMessages)-1];
            if (preg_match('/msg(\d+)/', $sLastKey, $regs)) {
                $iId = $regs[1];
            }
        }
        $iId++;
        $aMessages['msg' . $iId] = [
            'date'   => date('d.m.Y'),
            'time'   => date('H:i:s'),
            'author' => $this->username,
            'text'   => $sMessage,
            'type'   => $type,
        ];
        file_put_contents($sFilename, json_encode($aMessages, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Удалить указанное сообщение
     *
     * @param int $msgId Идентификатор удаляемого сообщения
     * @return void
     */
    public function deleteMessage($msgId)
    {
        $sFilename = self::$dataDir . self::$messagesFile;
        $sContents = @file_get_contents($sFilename);
        $aMessages = strlen($sContents) > 0 ? json_decode($sContents, true) : [];
        unset ($aMessages[$msgId]);
        file_put_contents($sFilename, json_encode($aMessages, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Удалить все сообщения
     *
     * @return void
     */
    public function clearMessages()
    {
        if (file_exists(self::$dataDir . self::$messagesFile)) {
            rename(self::$dataDir . self::$messagesFile, self::$dataDir . 'messages_' . date('Ymd_his') . '.json');
        }

    }

    /**
     * Удалить все данные
     *
     * @return void
     */
    public function clearData()
    {
        if (file_exists(self::$dataDir . self::$dataFile)) {
            rename(self::$dataDir . self::$dataFile, self::$dataDir . 'statistics_' . date('Ymd_his') . '.json');
        }
    }

    public static function getAllData()
    {
        $aAllData = [];
        $sFilename = self::$dataDir . self::$dataFile;
        if (file_exists($sFilename)) {
            $sContents = @file_get_contents($sFilename);
            $aAllData = strlen($sContents) > 0 ? (array)json_decode($sContents, true) : [];
        }
        return $aAllData;
    }

    /**
     * Получить данные для агента (указанного в конструкторе)
     *
     * @param int $recordId Какие данные вернуть. 0=первая запись, 1=последняя запись
     * @return array Массив с данными агента
     */
    public function getAgentData($recordId=0)
    {
        $aAgentData = [];
        $sFilename = self::$dataDir . self::$dataFile;
        $sContents = @file_get_contents($sFilename);
        $aAllData = strlen($sContents) > 0 ? json_decode($sContents, true) : [];
        if (count($aAllData) > 0) {
            $aStoredAgentData = array_key_exists($this->username, $aAllData) ? $aAllData[$this->username] : [];
            $aAgentData = array_key_exists($recordId, $aStoredAgentData) ? $aStoredAgentData[$recordId] : [];
        }
        return $aAgentData;
    }

    /**
     * Записать на диск данные агента
     *
     * Если агент не был известен боту, данные сохранятся как самые первые.
     * Иначе как самые последние и будут перезаписываны при последующих выховах
     *
     * @param array $aAgentData Данные для записи
     * @return void
     */
    public function setAgentData(array $aAgentData)
    {
        $aAgentData = [
            'date' => date('d.m.Y'), // date('d.m.Y', strtotime($aAgentData['Date (yyyy-mm-dd)'])),
            'time' => date('H:i:s'), // $aAgentData['Time (hh:mm:ss)'],
            'data' => $aAgentData,
        ];
        $sFilename = self::$dataDir . self::$dataFile;
        $sContents = @file_get_contents($sFilename);
        $aAllData = strlen($sContents) > 0 ? json_decode($sContents, true) : [];
        $aStoredAgentData = array_key_exists($this->username, $aAllData) ? $aAllData[$this->username] : [];
        if (count($aStoredAgentData) > 0) { // Если что-то есть
            $aStoredAgentData[1] = $aAgentData;
        } else {
            $aStoredAgentData[0] = $aAgentData;
        }
        $aAllData[$this->username] = $aStoredAgentData;
        file_put_contents($sFilename, json_encode($aAllData, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Удалить данные агента
     *
     * @param boolean $bLastOnly Удалить только последнюю запись? Если true - самые первые данные не удалятся. Если false - удалится всё. 
     * @return void
     */
    public function deleteAgentData($bLastOnly=true)
    {
        $sFilename = self::$dataDir . self::$dataFile;
        $sContents = @file_get_contents($sFilename);
        $aAllData = strlen($sContents) > 0 ? json_decode($sContents, true) : [];
        if ($bLastOnly) {
            $aStoredAgentData = array_key_exists($this->username, $aAllData) ? $aAllData[$this->username] : [];
            if (array_key_exists(1, $aStoredAgentData)) {
                unset ($aStoredAgentData[1]);
                $aAllData[$this->username] = $aStoredAgentData;
            }
        } else {
            if (array_key_exists($this->username, $aAllData)) {
                unset ($aAllData[$this->username]);
            }
        }
        file_put_contents($sFilename, json_encode($aAllData, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Возвращает всю информацию о указанном событии(мероприятии)
     */
    public function eventGet($eventName)
    {
        $aData = [];
        $sFilename = self::$dataDir . self::$eventsFile;
        $sContents = @file_get_contents($sFilename);
        $aAllData = strlen($sContents) > 0 ? json_decode($sContents, true) : [];
        if (array_key_exists($eventName, $aAllData)) {
            $aData = $aAllData[$eventName];
        }
        return $aData;
    }

    /**
     * Возвращает список известных событий(мероприятий)
     * @return array
     */
    public function eventList()
    {
        $sFilename = self::$dataDir . self::$eventsFile;
        $sContents = @file_get_contents($sFilename);
        $aAllData = strlen($sContents) > 0 ? json_decode($sContents, true) : [];
        return array_keys($aAllData);
    }

    /**
     * Удаляет указанное событие(мероприятие)
     */
    public function eventDelete($eventName)
    {
        $sFilename = self::$dataDir . self::$eventsFile;
        $sContents = @file_get_contents($sFilename);
        $aEvents = strlen($sContents) > 0 ? json_decode($sContents, true) : [];
        if (!array_key_exists($eventName, $aEvents)) {
            throw new Exception('Событие не найдено');
        }
        unset ($aEvents[$eventName]);
        file_put_contents($sFilename, json_encode($aEvents, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Добавляет, создаёт новое событие(мероприятие)
     */
    public function eventAdd(array $aEventData)
    {
        if (!array_key_exists('name', $aEventData)) {
            throw new Exception('Ошибка. Нет имени у события');
        }
        $eventName = $aEventData['name'];
        $aEventData = [
            'date' => date('d.m.Y'),
            'time' => date('H:i:s'),
            'author' => $this->username,
            'data' => $aEventData,
        ];
        $sFilename = self::$dataDir . self::$eventsFile;
        $sContents = @file_get_contents($sFilename);
        $aAllData = strlen($sContents) > 0 ? json_decode($sContents, true) : [];
        $aAllData[$eventName] = $aEventData;
        file_put_contents($sFilename, json_encode($aAllData, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Регистрирует пользователя на указанное событие(мероприятие)
     */
    public function userRegister($eventName, $userName)
    {
        $sFilename = self::$dataDir . self::$usersFile;
        $sContents = @file_get_contents($sFilename);
        $aAllData = strlen($sContents) > 0 ? json_decode($sContents, true) : [];
        if (array_key_exists($eventName, $aAllData)) {
            $aData = (array) $aAllData[$eventName];
            $aData[] = $userName;
        } else {
            $aData = [$userName];
        }
        $aAllData[$eventName] = $aData;
        file_put_contents($sFilename, json_encode($aAllData, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Отменяет регистрацию пользователя на указанное событие(мероприятие) (и только там)
     */
    public function userUnregister($eventName, $userName)
    {
        $sFilename = self::$dataDir . self::$usersFile;
        $sContents = @file_get_contents($sFilename);
        $aAllData = strlen($sContents) > 0 ? json_decode($sContents, true) : [];
        if (array_key_exists($eventName, $aAllData)) {
            $aData = (array) $aAllData[$eventName];
            if (($key = array_search($userName, $aData)) !== false){
                unset ($aData[$key]);
            }
            $aAllData[$eventName] = $aData;
        }
        file_put_contents($sFilename, json_encode($aAllData, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Возвращает название первого найденного события(мероприятие) на которое зарегистрирован пользователь
     */
    public function userGetRegistration($userName)
    {
        $sFilename = self::$dataDir . self::$usersFile;
        $sContents = @file_get_contents($sFilename);
        $aAllData = strlen($sContents) > 0 ? json_decode($sContents, true) : [];
        foreach ($aAllData as $eventName => $aEventUsers) {
            if (($key = array_search($userName, $aEventUsers)) !== false){
                return $eventName;
            }
        }
        return '';
    }

    /**
     * Удалить указанного пользователя отовсюду
     * (отменяет ренистрацию на все события(мероприятия))
     */
    public function userReset($userName)
    {
        $sFilename = self::$dataDir . self::$usersFile;
        $sContents = @file_get_contents($sFilename);
        $aAllData = strlen($sContents) > 0 ? json_decode($sContents, true) : [];
        foreach ($aAllData as $aEventUsers) {
            if (($key = array_search($userName, $aEventUsers)) !== false){
                unset ($aEventUsers[$key]);
            }
        }
        file_put_contents($sFilename, json_encode($aAllData, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * транслит с минусом всесто пробелов
     * предполагается использовать для создания имён файлов с результатами событий
     */
    protected function translit($str) {
        $str = (string) $str; // преобразуем в строковое значение
        $str = strip_tags($str); // убираем HTML-теги
        $str = str_replace(array("\n", "\r"), " ", $str); // убираем перевод каретки
        $str = trim($str); // убираем пробелы в начале и конце строки
        $str = function_exists('mb_strtolower') ? mb_strtolower($str) : strtolower($str); // переводим строку в нижний регистр (иногда надо задать локаль)
        $str = strtr($str, array('а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'j','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'shch','ы'=>'y','э'=>'e','ю'=>'yu','я'=>'ya','ъ'=>'','ь'=>''));
        $str = preg_replace("/[^0-9a-z-_ ]/i", "", $str); // очищаем строку от недопустимых символов
        $str = preg_replace("/\s+/", ' ', $str); // удаляем повторяющие пробелы
        $str = str_replace(" ", "-", $str); // заменяем пробелы знаком минус
        return $str; // возвращаем результат
    }

}