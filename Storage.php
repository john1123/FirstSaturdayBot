<?php

/**
 * Класс для хранения данных
 * Используется самое простейшее хранилище - json файлы со всеми своими нинусами (например потребление памяти)
 */
class Storage
{
    /** Файл с данными игроков */
    protected static $messagesFile = 'messages.json';
    /** Файл с сообщениями */
    protected static $dataFile = 'statistics.json';

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
}