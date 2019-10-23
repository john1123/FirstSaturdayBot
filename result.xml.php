<?php

include 'IngressProfile.php';
include 'vendor/autoload.php';


$aAgents = [];
$sFilename = 'data/statistics.json';
if (file_exists($sFilename)) {
    $sContents = file_get_contents($sFilename);
    $aAllData = strlen($sContents) > 0 ? (array)json_decode($sContents, true) : [];
    foreach ($aAllData as $username => $aData) {
        if (count($aData) < 2) {
            continue;
        }
        $aAgents[$aData[0]['data']['Agent Faction']][$aData[0]['data']['Agent Name']] = $aData;
    }
}

header("Content-Type:   application/vnd.ms-excel; charset=utf-8");
header("Content-type:   application/x-msexcel; charset=utf-8");
header("Content-Disposition: attachment; filename=result.xls");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private",false);

$data  = '<?xml version="1.0"?>' . PHP_EOL;
$data .= '<?mso-application progid="Excel.Sheet"?>' . PHP_EOL;
$data .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">' . PHP_EOL;
$data .= '    <Styles>' . PHP_EOL;
$data .= '        <Style ss:ID="bold">' . PHP_EOL;
$data .= '            <Font ss:Bold="1"/>' . PHP_EOL;
$data .= '        </Style>' . PHP_EOL;
$data .= '        <Style ss:ID="res">' . PHP_EOL;
$data .= '            <Interior ss:Color="#adc5e7" ss:Pattern="Solid" />' . PHP_EOL;
$data .= '        </Style>' . PHP_EOL;
$data .= '        <Style ss:ID="enl">' . PHP_EOL;
$data .= '            <Interior ss:Color="#e0efd4" ss:Pattern="Solid" />' . PHP_EOL;
$data .= '        </Style>' . PHP_EOL;
$data .= '    </Styles>' . PHP_EOL;
$data .= '    <Worksheet ss:Name="Результаты FS">' . PHP_EOL;
$data .= '        <Table>' . PHP_EOL;
$data .= '            <Row>' . PHP_EOL;
$data .= '                ' . getCell(' ', ['StyleID' => 'bold']) . PHP_EOL;
$data .= '                ' . getCell(' ', ['StyleID' => 'bold']) . PHP_EOL;
$data .= '                ' . getCell('Начало', ['CellPad' => count(IngressProfile::$aDeltaKeys)+1, 'StyleID' => 'bold']) . PHP_EOL;
$data .= '                ' . getCell('Конец',  ['CellPad' => count(IngressProfile::$aDeltaKeys)+1, 'StyleID' => 'bold']) . PHP_EOL;
$data .= '                ' . getCell('Разница', ['CellPad' => count(IngressProfile::$aDeltaKeys)+1, 'StyleID' => 'bold']) . PHP_EOL;
$data .= '            </Row>' . PHP_EOL;
$data .= '            <Row>' . PHP_EOL;
$data .= '                ' . getCell('Имя агента', ['StyleID' => 'bold']) . PHP_EOL;
$data .= '                ' . getCell('Фракция', ['StyleID' => 'bold']) . PHP_EOL;
for ($i=0; $i<3; $i++) {
    $data .= '                ' . getCell('Время', ['StyleID' => 'bold']) . PHP_EOL;
    foreach (IngressProfile::$aDeltaKeys as $key => $value) {
        $data .= '                ' . getCell($value, ['StyleID' => 'bold']) . PHP_EOL;
    }
}
$data .= '            </Row>' . PHP_EOL;
//
foreach ($aAgents as $sFraction => $aFractionAgents) {
    $styleId = $sFraction == 'Resistance' ? 'res' : 'enl';
    foreach ($aFractionAgents as $sAgentName => $aRecord) {
        $data .= '            <Row>' . PHP_EOL;
        $data .= '                ' . getCell($sAgentName, ['StyleID' => $styleId]) . PHP_EOL;
        $data .= '                ' . getCell($sFraction, ['StyleID' => $styleId]) . PHP_EOL;
        $sDatetime = strtotime($aRecord[0]['data']['Date (yyyy-mm-dd)'] . ' ' . $aRecord[0]['data']['Time (hh:mm:ss)']);
        $data .= '                ' . getCell(date('d.m.Y h:i:s', $sDatetime), ['StyleID' => $styleId]) . PHP_EOL;
        foreach (IngressProfile::$aDeltaKeys as $key => $value) {
            $data .= '                ' . getCell($aRecord[0]['data'][$value], ['StyleID' => $styleId]) . PHP_EOL;
        }
        $sDatetime = strtotime($aRecord[0]['data']['Date (yyyy-mm-dd)'] . ' ' . $aRecord[0]['data']['Time (hh:mm:ss)']);
        $data .= '                ' . getCell(date('d.m.Y h:i:s', $sDatetime), ['StyleID' => $styleId]) . PHP_EOL;
        foreach (IngressProfile::$aDeltaKeys as $key => $value) {
            $data .= '                ' . getCell($aRecord[1]['data'][$value], ['StyleID' => $styleId]) . PHP_EOL;
        }
        //
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

        //
        $data .= '                ' . getCell($sDiff, ['StyleID' => $styleId]) . PHP_EOL;
        $profile = new IngressProfile($aRecord[0]['data']);
        $aDelta = $profile->getDelta($aRecord[1]['data']);
        foreach (IngressProfile::$aDeltaKeys as $key => $value) {
            $data .= '                ' . getCell((array_key_exists($value, $aDelta) ? $aDelta[$value] : 0), ['StyleID' => $styleId]) . PHP_EOL;
        }
        $data .= '            </Row>' . PHP_EOL;
    }
}
//
$data .= '        </Table>' . PHP_EOL;
$data .= '    </Worksheet>' . PHP_EOL;
$data .= '</Workbook>' . PHP_EOL;

echo $data;

function getCell($sContent, $aParam=[])
{
    $sStyle=array_key_exists('StyleID', $aParam) ? ' ss:StyleID="' . $aParam['StyleID'] . '"' : '';
    if (array_key_exists('Type', $aParam)) {
        $sData = '<Data ss:Type="' . $aParam['Type'] . '">' . $sContent . '</Data>';
    } else {
        if (preg_match('/^\d+$/', $sContent)) {
            $sData = '<Data ss:Type="Number">' . $sContent . '</Data>';
        } else {
            $sData = '<Data ss:Type="String">' . $sContent . '</Data>';
        }
    }
    $out = '<Cell' . $sStyle . '>' . $sData . '</Cell>';
    $sCellPad=array_key_exists('CellPad', $aParam) ? (int)$aParam['CellPad'] : 0;
    if ($sCellPad > 1) {
        for ($i=0; $i<$sCellPad-1; $i++) {
            //$out .= '<Cell> </Cell>';
            $out .= '<Cell />';
        }
    }
    return $out;
}
?>
