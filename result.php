<?php

include 'IngressProfile.php';
include 'Storage.php';
include 'vendor/autoload.php';

if (!array_key_exists('file', $_GET)) {
    die('ERROR. FILE parametre isnot specified');
}
$file = urldecode($_GET['file']);
$eventName = substr($file,0, -11);

$storage = new Storage('');
$storage->setEventName($eventName);

$aAllData = $storage->getAllData();
$aAgents = [];
foreach ($aAllData as $chatName => $aData) {
//    if (count($aData) < 2) {
//        continue;
//    }
    $aAgents[$aData[0]['data']['Agent Faction']][$aData[0]['data']['Agent Name']] = $aData;
}

$document = new \PHPExcel();

$sheet = $document->setActiveSheetIndex(0); // Выбираем первый лист в документе

$sheet->getDefaultStyle()->applyFromArray(array(
    'font' => array(
        'name' => 'Arial',
        'size' => 10,
    )
));

$startColId = $colId = 0; // Начальная координата x
$startRowId = $rowId = 1; // Начальная координата y

$document->getActiveSheet()->setTitle("Результаты " . $eventName);

$i1 = $startColId + 2;
$i2 = $i1 + count(IngressProfile::$aDeltaKeys);
$i3 = $i2 + count(IngressProfile::$aDeltaKeys);
$i4 = $i3 + count(IngressProfile::$aDeltaKeys);

$sheet->mergeCellsByColumnAndRow($colId, $startRowId, $colId+1, $startRowId);
$sheet->mergeCellsByColumnAndRow($i1, $startRowId, $i2, $startRowId);
$sheet->mergeCellsByColumnAndRow($i2+1, $startRowId, $i3+1, $startRowId);
$sheet->mergeCellsByColumnAndRow($i3+2, $startRowId, $i4+2, $startRowId);

$sheet->setCellValueByColumnAndRow($i1, $startRowId, 'Начало')
    ->setCellValueByColumnAndRow($i2+1, $startRowId, 'Конец')
    ->setCellValueByColumnAndRow($i3+2, $startRowId, 'Разница');

$sheet->getStyleByColumnAndRow($i1, $startRowId)->getFont()->setBold(true);
$sheet->getStyleByColumnAndRow($i2+1, $startRowId)->getFont()->setBold(true);
$sheet->getStyleByColumnAndRow($i3+2, $startRowId)->getFont()->setBold(true);

// Перекидываем указатель на следующую строку
$rowId++;
$colId = $startColId;

$sheet->setCellValueByColumnAndRow($colId, $rowId, 'Имя агента');
$sheet->getStyleByColumnAndRow($colId, $rowId)->getFont()->setBold(true);
$colId++;
$sheet->setCellValueByColumnAndRow($colId, $rowId, 'Фракция');
$sheet->getStyleByColumnAndRow($colId, $rowId)->getFont()->setBold(true);
$colId++;

for ($i=0; $i<3; $i++) {
    $sheet->setCellValueByColumnAndRow($colId, $rowId, 'Время');
    $sheet->getStyleByColumnAndRow($colId, $rowId)->getFont()->setBold(true);
    $colId++;
    foreach (IngressProfile::$aDeltaKeys as $key => $value) {
        $sheet->setCellValueByColumnAndRow($colId, $rowId, $value);
        $sheet->getStyleByColumnAndRow($colId, $rowId)->getFont()->setBold(true);
        $colId++;
    }
}

$rowId++;
$colId = $startColId;

foreach ($aAgents as $sFaction => $aFactionAgents) {
    //$styleId = $sFaction == 'Resistance' ? 'res' : 'enl';
    foreach ($aFactionAgents as $sAgentName => $aRecord) {

        $sheet->setCellValueByColumnAndRow($colId++, $rowId, $sAgentName);
        $sheet->setCellValueByColumnAndRow($colId++, $rowId, $sFaction);

        $sDatetime = strtotime($aRecord[0]['data']['Date (yyyy-mm-dd)'] . ' ' . $aRecord[0]['data']['Time (hh:mm:ss)']);
        $sheet->setCellValueByColumnAndRow($colId++, $rowId, date('d.m.Y h:i:s', $sDatetime));
        foreach (IngressProfile::$aDeltaKeys as $key => $value) {
            $sheet->setCellValueByColumnAndRow($colId++, $rowId, $aRecord[0]['data'][$value]);
        }

        if (count($aRecord[1]) > 0) {
            $sDatetime = strtotime($aRecord[1]['data']['Date (yyyy-mm-dd)'] . ' ' . $aRecord[1]['data']['Time (hh:mm:ss)']);
            $sheet->setCellValueByColumnAndRow($colId++, $rowId, date('d.m.Y h:i:s', $sDatetime));
            foreach (IngressProfile::$aDeltaKeys as $key => $value) {
                $sheet->setCellValueByColumnAndRow($colId++, $rowId, $aRecord[1]['data'][$value]);
            }

            $aDiff = [];
            $start_date = new DateTime($aRecord[0]['data']['Date (yyyy-mm-dd)'] . ' ' . $aRecord[0]['data']['Time (hh:mm:ss)']);
            $since_start = $start_date->diff(new DateTime($aRecord[1]['data']['Date (yyyy-mm-dd)'] . ' ' . $aRecord[1]['data']['Time (hh:mm:ss)']));
            if ($since_start->y > 0) $aDiff[] = $since_start->y . 'лет';
            if ($since_start->m > 0) $aDiff[] = $since_start->m . 'мес';
            if ($since_start->d > 0) $aDiff[] = $since_start->d . 'дн';
            if ($since_start->h > 0) $aDiff[] = $since_start->h . 'час';
            if ($since_start->i > 0) $aDiff[] = $since_start->i . 'мин';
            if ($since_start->s > 0) $aDiff[] = $since_start->s . 'сек';

            $sDiff = count($aDiff) > 0 ? implode(', ', $aDiff) : 'отсутствует';
            $sheet->setCellValueByColumnAndRow($colId++, $rowId, $sDiff);

            $profile = new IngressProfile($aRecord[0]['data']);
            $aDelta = $profile->getDelta($aRecord[1]['data']);
            foreach (IngressProfile::$aDeltaKeys as $key => $value) {
                $sheet->setCellValueByColumnAndRow($colId++, $rowId, (array_key_exists($value, $aDelta) ? $aDelta[$value] : 0));
            }
        }

        $rowId++;
        $colId = $startColId;
    }
}

header("Content-Type:   application/vnd.ms-excel; charset=utf-8");
header("Content-type:   application/x-msexcel; charset=utf-8");
header("Content-Disposition: attachment; filename=" . $eventName . ".xls");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private",false);

$objWriter = \PHPExcel_IOFactory::createWriter($document, 'Excel5');
//$objWriter->save("data/result.xls");
$objWriter->save('php://output');
//echo file_get_contents('data/result.xls');
