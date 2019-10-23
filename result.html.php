<?php

include 'IngressProfile.php';

$val = 'Превед, медвед';
$val = htmlentities(iconv("utf-8", "windows-1251", $val),ENT_QUOTES, "cp1251");

header('Content-Type: text/html; charset=windows-1251');
header('P3P: CP="NOI ADM DEV PSAi COM NAV OUR OTRo STP IND DEM"');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', FALSE);
header('Pragma: no-cache');
header('Content-transfer-encoding: binary');
header('Content-Disposition: attachment; filename=result.xls');
header('Content-Type: application/x-unknown');

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
$a = 1;

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title></title>
</head>
<body>

<table>
    <tr>
        <td colspan="2">&nbsp;</td>
        <td colspan="<?= count(IngressProfile::$aDeltaKeys)+1 ?>">Начало</td>
        <td colspan="<?= count(IngressProfile::$aDeltaKeys)+1 ?>">Конец</td>
        <td colspan="<?= count(IngressProfile::$aDeltaKeys)+1 ?>">Разница</td>
    </tr>
    <tr>
        <td>Имя агента</td>
        <td>Фракция</td>
        <?php for ($i=0; $i<3; $i++) { ?>
        <td>Время</td>
        <?php foreach (IngressProfile::$aDeltaKeys as $key => $value) { ?>
        <td><?= $value ?></td>
        <?php } ?>
        <?php } ?>
    </tr>
<?php

foreach ($aAgents as $sFraction => $aFractionAgents) {
    $out = '';
    foreach ($aFractionAgents as $sAgentName => $aRecord) {
        $out .= '<tr>';
        $out .= '<td>' . $sAgentName . '</td>';
        $out .= '<td>' . $sFraction . '</td>';
        $sDatetime = strtotime($aRecord[0]['data']['Date (yyyy-mm-dd)'] . ' ' . $aRecord[0]['data']['Time (hh:mm:ss)']);
        $out .= '<td>' . date('d.m.Y h:i:s', $sDatetime) . '</td>';
        foreach (IngressProfile::$aDeltaKeys as $key => $value) {
            $out .= '<td>' . $aRecord[0]['data'][$value] . '</td>';
        }
        $sDatetime = strtotime($aRecord[1]['data']['Date (yyyy-mm-dd)'] . ' ' . $aRecord[1]['data']['Time (hh:mm:ss)']);
        $out .= '<td>' . date('d.m.Y h:i:s', $sDatetime) . '</td>';
        foreach (IngressProfile::$aDeltaKeys as $key => $value) {
            $out .= '<td>' . $aRecord[1]['data'][$value] . '</td>';
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
        $out .= '<td>' . $sDiff . '</td>';
        $profile = new IngressProfile($aRecord[0]['data']);
        $aDelta = $profile->getDelta($aRecord[1]['data']);
        foreach (IngressProfile::$aDeltaKeys as $key => $value) {
            $out .= '<td>' . (array_key_exists($value, $aDelta) ? $aDelta[$value] : 0) . '</td>';
        }
        $out .= '</tr>' . PHP_EOL;
    }
    echo $out;
}
?>
</table>

</body>
</html>

