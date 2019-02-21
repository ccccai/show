<?php
/**
 * Created by PhpStorm.
 * User: cc
 * Date: 2018/6/21
 * Time: 16:38
 */
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

$start = 1529337600;
$end = 1529596800;
$sql = 'SELECT `Name`,`OpTime`,`OpCode`,lv.Description 
            FROM `OpReport` LEFT JOIN LookUpValue lv ON lv.`Value`=OpReport.`Name` 
            WHERE OpReport.Name IN 
            (SELECT `Value` FROM LookUpValue lv 
            WHERE lv.Subcategory IS NULL 
            AND lv.Value<>\'SYSTEM_START_QUIT\') 
            AND (`OpCode`=1 OR `OpCode`=2) 
            AND `OpTime`>=' . $start . ' 
            AND `OpTime`<' . $end . ' 
            ORDER BY `BindRUId` ASC,`Name` ASC,`OpTime` ASC';

$funcQuery = function(&$mapSummarize,$poDBConn,$strSql,$aoTimeRange)
{
    $xResult = $poDBConn->query($strSql);
    if ($xResult === FALSE)
    {
        echo '{"success":0,"error_code":'.ERR_INNER_CODE.',"error_desc":"'.ERR_INNER_DESC.'[1]"}';
        exit();
    }
    $nNodeStartAdjust = -1;
    $nNodeEndAdjust = -1;
    $nNodeStartOrg = -1;
    $nNodeEndOrg = -1;
    $strCurName = '';

    /*
     * $oRow :
     * [0] => com.qiyi.video.child
        [Name] => com.qiyi.video.child
        [OpTime] => 1529441077
        [OpCode] => 1
        [Description] => 爱奇艺动画屋
    */
    while ($oRow = $xResult->fetch_array())
    {
        //第一次循环，初始化各值
        if ($strCurName != $oRow['Description'])
        {
            $nNodeStartAdjust = -1;
            $nNodeEndAdjust = -1;
            $nNodeStartOrg = -1;
            $nNodeEndOrg = -1;
            $strCurName = $oRow['Description'];
        }
        /*
         * OpCode为1时，是打开状态，为2时是关闭*/
        if ($oRow['OpCode'] == 1)
        {
            if ($nNodeEndAdjust > -1)
            {
                $nNodeEndAdjust = -1;
                $nNodeEndOrg = -1;
            }
            //$nNodeStartAdjust、$nNodeStartOrg 打开时间
            $nNodeStartAdjust = $oRow['OpTime'];
            $nNodeStartOrg = $nNodeStartAdjust;
        }
        else
        {
            //$nNodeEndAdjust、$nNodeEndOrg 关闭时间
            $nNodeEndAdjust = $oRow['OpTime'];
            $nNodeEndOrg = $nNodeEndAdjust;
        }
        if ($nNodeStartAdjust == -1 || $nNodeEndAdjust == -1)
            continue;

        $nCurTimeOrg = $nNodeStartOrg;
        $nAddTimeBase = $nNodeStartOrg - (($nNodeStartOrg % 86400 + 28800) % 86400);
        $index = 0;
        while ($nCurTimeOrg < $nNodeEndOrg)
        {

            //全日计算
            if ($nNodeEndOrg > $nNodeStartOrg)
            {
                if ($nNodeEndOrg <= $nAddTimeBase + 86400)
                    $nFullCount = $nNodeEndOrg;
                else
                    $nFullCount = $nAddTimeBase + 86400;
                if ($nNodeStartOrg < $nAddTimeBase)
                    $nFullCount -= $nAddTimeBase;
                else
                    $nFullCount -= $nNodeStartOrg;
                if ($nFullCount > 0)
                {
                    if (isset($mapSummarize[$strCurName]) == false)
                        $mapSummarize[$strCurName] = [];
                    if (isset($mapSummarize[$strCurName][$nAddTimeBase]) == false)
                        $mapSummarize[$strCurName][$nAddTimeBase] = 0;
                    $mapSummarize[$strCurName][$nAddTimeBase] += $nFullCount;
                    $index++ ;
                }
            }

            $nAddTimeBase += 86400;
            $nCurTimeOrg = $nAddTimeBase;
        }
    }
};

$aoTimeRange[] = array(intval($start), intval($end));
$funcQuery($mapSummarize, $g_oDBConn, $sql, $aoTimeRange);
$index = 0;
$dateArr = [];
for ($dayStart = $start; $dayStart <= $end; $dayStart += 86400) {
    $dayEnd = $dayStart + 86400;
    $dateArr[$index] = intval($dayStart);
    $allData['datetime'][$index] = date('Y-m-d', $dayStart);
    $index++;
}
//数据拼接 补零
$allDurationData = [];
foreach ($mapSummarize as $key => $value) {
    foreach ($dateArr as $val) {
        if (array_key_exists($val, $value)) {
            $allData['data'][$key][$val][] = $value[$val];
        } else {
            $allData['data'][$key][$val][] = 0;
        }
    }
}
$allData['status'] = 200;
echo json_encode($allData, JSON_UNESCAPED_UNICODE);
exit();