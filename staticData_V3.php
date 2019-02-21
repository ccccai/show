<?php
header('Access-Control-Allow-Origin:*');
ini_set('max_execution_time', '0');

if (isset($_POST['dates']) == false || is_array($_POST['dates']) == false) {
    echo '{"success":0,"error":"参数错误"}';
    return;
}
if (isset($_POST['app']) == false) {
    echo '{"success":0,"error":"参数错误"}';
    return;
}
chdir(dirname(__FILE__));
include_once('./config.php');
$g_poDBConfig = CSys_Conf::$DB_Config;
$g_oDBConn = @new mysqli('p:' . $g_poDBConfig['server'], $g_poDBConfig['user'], $g_poDBConfig['psw'], $g_poDBConfig['database']);
$g_oDBConn->set_charset('utf8');

$dateValue = $_POST['dates'];
$app = $_POST['app'];
$allData = [];

if ($app != "全部") {
    $appRes = [];

    $allAppSql = 'select UId,Description
        from LookUpValue
        where UId = "' . $app . '"';
    $xResultApp = $g_oDBConn->query($allAppSql);
    $appRow = $xResultApp->fetch_assoc();
    $allData[$appRow['Description']] = [];

    for ($days = 0; $days < sizeof($dateValue); $days++) {
        $startTime = $dateValue[$days] + 3600 * 6;
        $endTime = $startTime + 3600;

        for ($time = 0; $time < 17; $time++) {
            $startTime = $startTime + 3600;
            $endTime = $endTime + 3600;

            $openTimesSql = 'select LookUpFunctionValueId,Description,COUNT(LookUpFunctionValueId) as count
			from StatisticOperation so LEFT JOIN LookUpValue lv on so.LookUpFunctionValueId = lv.UId
			where so.OperationCode="IN"
			and so.OpTime>="' . $startTime . '"
			and so.OpTime<="' . $endTime . '"
			and so.LookUpFunctionValueId = "' . $app . '"';

            $xResultOpenTimes = $g_oDBConn->query($openTimesSql);
            $oRow = $xResultOpenTimes->fetch_assoc();
            $appRes[$time] = $oRow['count'];
        }
        $allData[$appRow['Description']][] = $appRes;
    }
    //各app各天次数求和
    $dataSum = [];
    foreach ($allData as $key => $value) {
        foreach ($value as $k => $v) {
            if ($dataSum == null) {
                $dataSum[$key] = $v;
            } else {
                for ($i = 0; $i < sizeof($v); $i++) {
                    $dataSum[$key][$i] = $dataSum[$key][$i] + $v[$i];
                }
            }
        }
    }
    echo '{"success":1,"appOpenTimes":' . json_encode($dataSum, JSON_UNESCAPED_UNICODE) . '}';
} else {
    $res = [];
    $allTimes = [];
    $item = [];
    $allTimesArr = [];

    //所有app
    $allAppSql = "select so.LookUpFunctionValueId,lv.Description
	from StatisticOperation so LEFT JOIN LookUpValue lv on so.LookUpFunctionValueId = lv.UId
	where so.OperationCode='IN'
	and so.LookUpFunctionValueId <> 5
	and so.LookUpFunctionValueId <> 4
	and lv.Subcategory IS NULL
	and lv.Description IS NOT NULL
	group by LookUpFunctionValueId";
    $xResultApp = $g_oDBConn->query($allAppSql);

    for ($days = 0; $days < sizeof($dateValue); $days++) {
        $startTime = $dateValue[$days] + 3600 * 6;
        $endTime = $startTime + 3600;

        $i = 0;
        for ($time = 0; $time < 17; $time++) {
            $startTime = $startTime + 3600;
            $endTime = $endTime + 3600;

            $openTimesSql = "select so.LookUpFunctionValueId,lv.Description,COUNT(LookUpFunctionValueId) as count
                     from StatisticOperation so LEFT JOIN LookUpValue lv on so.LookUpFunctionValueId = lv.UId
                     where so.OperationCode='IN'
                     and so.OpTime>=" . $startTime . "
                     and so.OpTime<" . $endTime . "
                     and lv.Subcategory IS NULL 
                     and so.LookUpFunctionValueId <> 5
                     and so.LookUpFunctionValueId <> 4
                     group by LookUpFunctionValueId";

            $xResultOpenTimes = $g_oDBConn->query($openTimesSql);
            $index = 0;

            while ($oRow = $xResultOpenTimes->fetch_array()) {
                $res[$oRow['LookUpFunctionValueId']]['Description'] = $oRow['Description'];
                $res[$oRow['LookUpFunctionValueId']]['count'] = $oRow['count'];
                $index++;
            }

            while ($appRow = $xResultApp->fetch_array()) {
                //没有统计到的app打开次数补为零
                if (!isset($res[$appRow[0]])) {
                    $res[$appRow[0]]['Description'] = $appRow[1];
                    $res[$appRow[0]]['count'] = 0;
                }
            }
            $allTimesArr[] = $res;
            $now = time();
            if ($now > $startTime) {
                $i++;
            }
        }
        if($i){
            //如果统计时间大于当前时间，次数置0
            foreach ($allTimesArr as $allKey => &$allItem) {
                if ($allKey > $i - 1) {
                    foreach ($allItem as &$test) {
                        $test['count'] = 0;
                    }
                }
            }
        }
        foreach ($allTimesArr as $key1 => $value1) {
            if (is_array($value1)) {
                foreach ($value1 as $key2 => $value2) {
                    $item[$value2['Description']][] = $value2['count'];
                }
            }
        }
        $allData[] = $item;
        $allTimesArr = [];
        $item = [];
    }
//各app各天次数求和
    $allDataSum = array();
    foreach ($allData as $key => $value) {
        foreach ($value as $k => $v) {
            if (!isset($allDataSum[$k])) {
                $allDataSum[$k] = $v;
            } else {
                for ($i = 0; $i < 17; $i++) {
                    $allDataSum[$k][$i] = $allDataSum[$k][$i] + $v[$i];
                }
            }
        }
    }
    echo '{"success":1,"appOpenTimes":' . json_encode($allDataSum, JSON_UNESCAPED_UNICODE) . '}';
}
