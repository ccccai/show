<?php
header('Access-Control-Allow-Origin:*');
ini_set('max_execution_time', '0');
/*
	数据统计
	POST['ids'] - 要统计的机器人用户id集合，json数组
	POST['start'] - 开始时间的时间戳(到日，处理时自动调整到当天开始时点)
	POST['end'] - 结束时间的时间戳(到日，处理时自动调整到当天开始时点)
	POST['timeRange'] - 时间段数组，单个区间使用【开始时间点秒数+":"+结束时间点秒数】
*/
//if (isset($_POST['ids']) == false) {
//    echo '{"success":0,"error":"参数错误"}';
//    return;
//}
//if (isset($_POST['dates']) == false || is_array($_POST['dates']) == false) {
//    echo '{"success":0,"error":"参数错误"}';
//    return;
//}
//$_POST['ids'] = @json_decode($_POST['ids']);
//if ($_POST['ids'] == null || $_POST['ids'] == false) {
//    echo '{"success":0,"error":"参数错误"}';
//    return;
//}
//if (isset($_POST['app']) == false) {
//    echo '{"success":0,"error":"参数错误"}';
//    return;
//}
function d($string)
{
    print_r('<pre>');
    print_r($string);
    print_r('</pre>');
    die();
}

chdir(dirname(__FILE__));
include_once('./config.php');
$g_poDBConfig = CSys_Conf::$DB_Config;

$g_oDBConn = @new mysqli('p:' . $g_poDBConfig['server'], $g_poDBConfig['user'], $g_poDBConfig['psw'], $g_poDBConfig['database']);
$g_oDBConn->set_charset('utf8');

$dateValue = [1529510400];
$app = '全部';
$anIds = [];
$nTotalCount = 0;
$mapSummarize = [];//key-上报名 value-{key-日期时间戳 value-单日统计}
$aoTimeRange = [];
$allData = [];
$allTimeData = [];
$allApp = [];

$funcQuery = function (&$mapSummarize, $poDBConn, $strSql, $aoTimeRange) {
    $xResult = $poDBConn->query($strSql);
    if ($xResult === FALSE) {
        echo '{"success":0,"error_code":' . ERR_INNER_CODE . ',"error_desc":"' . ERR_INNER_DESC . '[1]"}';
        exit();
    }
    $nNodeStartAdjust = -1;
    $nNodeEndAdjust = -1;
    $nNodeStartOrg = -1;
    $nNodeEndOrg = -1;
    $strCurName = '';
    while ($oRow = $xResult->fetch_array()) {
        if ($strCurName != $oRow['Description']) {
            $nNodeStartAdjust = -1;
            $nNodeEndAdjust = -1;
            $nNodeStartOrg = -1;
            $nNodeEndOrg = -1;
            $strCurName = $oRow['Description'];
        }
        if ($oRow['OpCode'] == 1) {
            if ($nNodeEndAdjust > -1) {
                $nNodeEndAdjust = -1;
                $nNodeEndOrg = -1;
            }
            $nNodeStartAdjust = $oRow['OpTime'];
            $nNodeStartOrg = $nNodeStartAdjust;
        } else {
            $nNodeEndAdjust = $oRow['OpTime'];
            $nNodeEndOrg = $nNodeEndAdjust;
        }
        if ($nNodeStartAdjust == -1 || $nNodeEndAdjust == -1)
            continue;

        //调整开始区间
        $nTmpTime = ($nNodeStartAdjust % 86400 + 28800) % 86400;
        $anTimes = [];
        foreach ($aoTimeRange as $poGap) {
            if ($nTmpTime >= $poGap[0] && $nTmpTime < $poGap[1]) {
                $anTimes[] = $nNodeStartAdjust;
                break;
            } else if ($nTmpTime < $poGap[0])
                $anTimes[] = $nNodeStartAdjust + $poGap[0] - $nTmpTime;
            else
                $anTimes[] = $nNodeStartAdjust - $nTmpTime + 86400 + $poGap[0];//天数后移1天
        }
        $nNodeStartAdjust = min($anTimes);
        //调整结束区间
        $nTmpTime = ($nNodeEndAdjust % 86400 + 28800) % 86400;
        $anTimes = [];
        foreach ($aoTimeRange as $poGap) {
            if ($nTmpTime >= $poGap[0] && $nTmpTime < $poGap[1]) {
                $anTimes[] = $nNodeEndAdjust;
                break;
            } else if ($nTmpTime < $poGap[0])
                $anTimes[] = $nNodeEndAdjust - $nTmpTime - 86400 + $poGap[1];//天数前移1天
            else
                $anTimes[] = $nNodeEndAdjust - $nTmpTime + $poGap[1];
        }
        $nNodeEndAdjust = max($anTimes);
        //统计
        $nCurTimeArea = $nNodeStartAdjust;
        $nCurTimeOrg = $nNodeStartOrg;
        $nAddTimeBase = $nNodeStartOrg - (($nNodeStartOrg % 86400 + 28800) % 86400);
        while ($nCurTimeOrg < $nNodeEndOrg) {
            //区间分割
            if ($nNodeStartAdjust < $nNodeEndAdjust) {
                foreach ($aoTimeRange as $poGap) {
                    $nGapStart = $poGap[0] + $nAddTimeBase;
                    $nGapEnd = $poGap[1] + $nAddTimeBase;
                    $nInGapStartTime = $nCurTimeArea;
                    $nInGapEndTime = $nNodeEndAdjust;
                    if ($nGapStart > $nInGapStartTime)
                        $nInGapStartTime = $nGapStart;
                    if ($nGapEnd < $nInGapEndTime)
                        $nInGapEndTime = $nGapEnd;
                    if ($nInGapStartTime >= $nInGapEndTime)
                        continue;
                    if (isset($mapSummarize[$strCurName]) == false)
                        $mapSummarize[$strCurName] = [];
                    if (isset($mapSummarize[$strCurName][$nAddTimeBase]) == false)
                        $mapSummarize[$strCurName][$nAddTimeBase] = array('area' => 0);
                    $mapSummarize[$strCurName][$nAddTimeBase]['area'] += $nInGapEndTime - $nInGapStartTime;
                }
            }

            $nAddTimeBase += 86400;
            $nCurTimeArea = $nAddTimeBase;
            $nCurTimeOrg = $nAddTimeBase;
        }
    }
};
//各app各天时长求和
function durationSum($value)
{
    $oneTimeDataSum = [];
    foreach ($value as $ke => $val) {
        foreach ($val as $k => $v) {
            if (!isset($oneTimeDataSum[$k])) {
                foreach ($v as $area) {
                    $oneTimeDataSum[$k] = $area;
                }
            } else {
                foreach ($v as $area) {
                    $oneTimeDataSum[$k]['area'] = $oneTimeDataSum[$k]['area'] + $area['area'];
                }
            }
        }
    }
    return $oneTimeDataSum;
}

$appSql = "";
if ($app != "全部") {
    $appSql = 'select UId,Description
        from LookUpValue
        where UId = "' . $app . '"';
} else {
//    $allApp = ["语音交互", "应用市场", "蛋生世界", "小哈读绘本", "爱奇艺动画屋", "蛋生园", "悟空数学", "小哈学绘画", "小哈菠萝树英语", "中华美食", "宝宝写数字", "宝宝拼拼乐", "宝宝学交通工具", "宝宝超市", "多屏互动", "运动加加", "听音乐", "悟空识字", "读绘本"];
    $appSql = "select so.LookUpFunctionValueId,lv.Description
	from StatisticOperation so LEFT JOIN LookUpValue lv on so.LookUpFunctionValueId = lv.UId
	where so.OperationCode='IN' 
	and so.LookUpFunctionValueId <> 5
	and so.LookUpFunctionValueId <> 4
	and lv.Subcategory IS NULL
	and lv.Description IS NOT NULL
	group by LookUpFunctionValueId";
}

$xResultApp = $g_oDBConn->query($appSql);
while ($appRow = $xResultApp->fetch_assoc()) {
    $allApp[] = $appRow['Description'];
}

for ($days = 0; $days < sizeof($dateValue); $days++) {
    $startDate = intval($dateValue[$days]);
    $endDate = $startDate + 86400;

    if ($app != "全部") {
        $strBaseSql = 'SELECT `Name`,`OpTime`,`OpCode`,lv.Description 
            FROM `OpReport` LEFT JOIN LookUpValue lv ON lv.`Value`=OpReport.`Name` 
            WHERE OpReport.Name IN 
            (SELECT `Value` FROM LookUpValue lv 
            WHERE lv.UId = ' . $app . ') 
            AND (`OpCode`=1 OR `OpCode`=2) 
            AND `OpTime`>=' . $startDate . ' 
            AND `OpTime`<' . $endDate . ' 
            ORDER BY `BindRUId` ASC,`Name` ASC,`OpTime` ASC';
    } else {
        $strBaseSql = 'SELECT `Name`,`OpTime`,`OpCode`,lv.Description 
            FROM `OpReport` LEFT JOIN LookUpValue lv ON lv.`Value`=OpReport.`Name` 
            WHERE OpReport.Name IN 
            (SELECT `Value` FROM LookUpValue lv 
            WHERE lv.Subcategory IS NULL 
            AND lv.Value<>\'SYSTEM_START_QUIT\') 
            AND (`OpCode`=1 OR `OpCode`=2) 
            AND `OpTime`>=' . $startDate . ' 
            AND `OpTime`<' . $endDate . ' 
            ORDER BY `BindRUId` ASC,`Name` ASC,`OpTime` ASC';
    }

    $startTime = 21600;
    $endTime = $startTime + 3600;
    for ($time = 0; $time < 17; $time++) {
        $startTime = $startTime + 3600;
        $endTime = $endTime + 3600;
        $aoTimeRange[] = array(intval($startTime), intval($endTime));
        $funcQuery($mapSummarize, $g_oDBConn, $strBaseSql, $aoTimeRange);
        $allData[$time][] = $mapSummarize;
        $mapSummarize = [];
        $aoTimeRange = [];
    }
}
//各app各天时长求和
$allDataSum = [];
for ($i = 0; $i < sizeof($allData); $i++) {
    $allDataSum[] = durationSum($allData[$i]);
}
//数据拼接 补零
$allDurationData = [];
foreach ($allApp as $value) {
    foreach ($allDataSum as $ke => $val) {
        if (array_key_exists($value, $val)) {
            $allDurationData[$value][] = $val[$value]['area'];
        } else {
            $allDurationData[$value][] = 0;
        }
    }
}
echo '{"success":1,"appOpenDuration":' . json_encode($allDurationData, JSON_UNESCAPED_UNICODE) . '}';
