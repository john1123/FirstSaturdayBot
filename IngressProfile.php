<?php

/**
 * Класс для работы с профилями игроков
 */
class IngressProfile
{
    /**
     * Данные отдаваемые праймом. Внимание. Последовательность важна! Порядок менять нельзя.
     */
    protected static $availableParams = [
        'Time Span',
        'Agent Name',
        'Agent Faction',
        'Date (yyyy-mm-dd)',
        'Time (hh:mm:ss)',
        'Level',
        'Lifetime AP',
        'Current AP',
        'Unique Portals Visited',
        'Portals Discovered',
        'Seer Points',
        'XM Collected',
        'OPR Agreements',
        'Distance Walked',
        'Resonators Deployed',
        'Links Created',
        'Control Fields Created',
        'Mind Units Captured',
        'Longest Link Ever Created',
        'Largest Control Field',
        'XM Recharged',
        'Portals Captured',
        'Unique Portals Captured',
        'Mods Deployed',
        'Resonators Destroyed',
        'Portals Neutralized',
        'Enemy Links Destroyed',
        'Enemy Fields Destroyed',
        'Max Time Portal Held',
        'Max Time Link Maintained',
        'Max Link Length x Days',
        'Max Time Field Held',
        'Largest Field MUs x Days',
        'Unique Missions Completed',
        'Hacks',
        'Glyph Hack Points',
        'Longest Hacking Streak',
        'Agents Successfully Recruited',
        'Mission Day(s) Attended',
        'NL-1331 Meetup(s) Attended',
        'First Saturday Events',
        'Clear Fields Events',
        'OPR Live Events',
        'Prime Challenges',
        'Intel Ops Missions',
        'Stealth Ops Missions',
        'Umbra: Unique Resonator Slots Deployed',
        'Didact Fields Created',
        'Recursions',
    ];
    /** @var $firstValuesPhrase - Very first parameters name */
    protected static $firstKeysPhrase = 'Time Span';
    /** @var $firstValuesPhrase - Data is assumed correct only if it contains one of the following values */
    protected static $firstValuesPhrase = [
        'ЗА ВСЕ ВРЕМЯ',
        'ALL TIME',
    ];

    /** Параметры, которые участвуют в подсчёте результатов игроков */
    public static $aDeltaKeys = [
        'Level',
        'Current AP',
        'Distance Walked',
        'Resonators Deployed',
//        'Links Created',
//        'Control Fields Created',
        'Portals Captured',
//        'Unique Portals Captured',
//        'Mods Deployed',
        'Resonators Destroyed',
        'Portals Neutralized',
//        'Hacks',
//        'Glyph Hack Points',
    ];

    /**
     * Данные агента в виде массива
     */
    protected $agentData = [];

    public function __construct($sProfileData)
    {
        if (is_string($sProfileData) && strlen($sProfileData) > 0) {
            $this->agentData = $this->parseProfile($sProfileData);
        } else {
            $this->agentData = (array)$sProfileData;
        }

    }

    /**
     * Возвращяет разницу между "старым" и "новым" профилями. Смотрит только по self::$aDeltaKeys (остальное не сравнивает)
     * Параметры могут передаваться либо строкой (будут парситься) или в виде массивов (парситься НЕ будут)
     */
    public function getDelta($newProfileData, $oldProfileData='')
    {
        if (is_string($newProfileData)) {
            if (strlen($newProfileData) > 0) {
                $aNewProfileData = self::parseProfile($newProfileData);
            } else {
                throw new Exception('Ошибка сравнения. Пустые новые данные');
            }
        } else {
            $aNewProfileData = (array)$newProfileData;
        }

        if (is_string($oldProfileData)) {
            if (strlen($oldProfileData) > 0) {
                $aOldProfileData = $this->parseProfile($oldProfileData);
            } else {
                $aOldProfileData = $this->agentData;
            }
        } else {
            $aOldProfileData = (array)$oldProfileData;
        }

        if (count($oldProfileData) < 1) {
            throw new Exception('Ошибка сравнения. Не с чем сравнивать');
        }

        $aDelta = [];
        foreach (self::$aDeltaKeys as $deltaKey) {
            $valueNew = array_key_exists($deltaKey, $aNewProfileData) ? $aNewProfileData[$deltaKey] : '';
            $valueOld = array_key_exists($deltaKey, $aOldProfileData) ? $aOldProfileData[$deltaKey] : '';

            if (strlen($valueNew) > 0) {
                if (strlen($valueOld) > 0) { // В новом есть, в старом есть
                    if ($valueNew === $valueOld) {continue;}
                    if (preg_match('/\d+/', $valueOld) && preg_match('/\d+/', $valueNew)) {
                        $aDelta[$deltaKey] = (int)$valueNew - (int)$valueOld;
                    } else {
                        $aDelta[$deltaKey] = $valueNew . ' (было: ' . $valueOld . ')';
                    }
                } else { // В новом есть, в старом нет
                    $aDelta[$deltaKey] = $valueNew . ' (новое)';
                }
            } else {
                if (array_key_exists($deltaKey, $aOldProfileData)) { // В новом нет, в старом есть
                    $aDelta[$deltaKey] = '(пусто)';
                } else { // В новом нет, в старом нет
                    // Это как???
                }
            }
        }
        return $aDelta;
    }

    /**
     * Парсит строку с профилем. Возвращает массив ['параметр' => 'значение']
     */
    public static function parseProfile($sProfileData)
    {
        // 1. строка разбивается на две части - названия и значения
        // 2. так как почти все значения (кроме первого) - это и строка без пробелов, делим по пробелам
        //    разделять названия способа нет (не ясен), потому используем названия из массива self::$availableParams
        // 3. Перебираем значения от конца к началу и каждому присваиваем название из доступных
        // 4. Помним, что последнее значение (в профиле - первое) - 'За всё время' содержит пробелы!
        if (!is_string($sProfileData) || strlen($sProfileData) < 1) {
            throw new Exception('Ошибка разбора данных профиля. Неверные входные данные');
        }
        $sFullParameters = strstr($sProfileData, self::$firstKeysPhrase);
        $sKeys = $sValues = '';

        foreach (self::$firstValuesPhrase as $firstPhrase) {
            $valuePos = strpos($sFullParameters, $firstPhrase);
            if ($valuePos === false) { continue; }
            $sKeys = trim(substr($sFullParameters, 0, $valuePos));
            $sValues = substr($sFullParameters, $valuePos);
        }
        if (strlen($sValues) === 0) {throw new Exception('Parse error. Unable to find parameters. Possible wrong Time Span');}

        $availableParams = array_reverse(self::$availableParams);
        $aValues = array_reverse(mb_split('\s', $sValues));
        $aProfile = [];
        foreach ($aValues as $vKey => $value) {
            foreach ($availableParams as $key => $possibleParam) {
                if (strpos($sKeys, $possibleParam) === false) {continue;}
                unset($availableParams[$key]); // удаляем, чтобы не присваитвать дважды
                if ($possibleParam == self::$firstKeysPhrase) {
                    $tmp = array_splice($aValues, $vKey);
                    $value = implode(' ', array_reverse($tmp));
                }
                $aProfile[$possibleParam] = $value;
                break;
            }
        }
        return array_reverse($aProfile);
    }

    public function getAgentData() {return $this->agentData;}
}