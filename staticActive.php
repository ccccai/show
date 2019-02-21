<?php
/*
	日上线机器人统计
	POST['ids'] - 要统计的机器人用户id集合，json数组
	POST['start'] - 开始时间的时间戳(到日，处理时自动调整到当天开始时点)
	POST['end'] - 结束时间的时间戳(到日，处理时自动调整到当天开始时点)
	POST['timeRange'] - 时间段数组，单个区间使用【开始时间点秒数+":"+结束时间点秒数】
*/
	if (isset($_POST['ids']) == false)
	{
		echo '{"success":0,"error":"参数错误"}';
		return;
	}
	if (isset($_POST['start']) == false || isset($_POST['end']) == false)
	{
		echo '{"success":0,"error":"参数错误"}';
		return;
	}
	if (isset($_POST['timeRange']) == false || is_array($_POST['timeRange']) == false)
	{
		echo '{"success":0,"error":"参数错误"}';
		return;
	}
	$_POST['ids'] = @json_decode($_POST['ids']);
	if ($_POST['ids'] == null || $_POST['ids'] == false)
	{
		echo '{"success":0,"error":"参数错误"}';
		return;
	}
	$_POST['start'] = intval(intval($_POST['start']) / 24) * 24;
	$_POST['end'] = intval(intval($_POST['end']) / 24) * 24;
	if ($_POST['start'] > $_POST['end'])
	{
		echo '{"success":0,"error":"时间区间错误"}';
		return;
	}
	$aoTimeRange = [];
	foreach ($_POST['timeRange'] as $strTimeGap)
	{
		$astrGap = explode(':',$strTimeGap);
		if (count($astrGap) != 2)
			continue;
		$aoTimeRange[] = array(intval($astrGap[0]),intval($astrGap[1]));
	}
	chdir(dirname(__FILE__));
	include_once('.//config.php');
	$g_poDBConfig = CSys_Conf::$DB_Config;
	
	$g_oDBConn = @new mysqli('p:'.$g_poDBConfig['server'],$g_poDBConfig['user'],$g_poDBConfig['psw'],$g_poDBConfig['database']);
	$g_oDBConn->set_charset('utf8');
	
	$anIds = [];
	$strBaseSql = 'SELECT `UserId`,`LoginTime`,`LogoutTime` FROM `StaticsLoginout` WHERE `LoginTime`>='.$_POST['start'].' AND `LogoutTime`<'.$_POST['end'].' AND `IsRobot`=1 AND `UserId` IN (';
	$nTotalCount = 0;
	$mapSummarize = [];//key-机器人id value-日期时间戳数组
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
		$nTmpAnother = -1;
		while ($oRow = $xResult->fetch_array())
		{
			$nNodeStartAdjust = $oRow['LoginTime'];
			$nNodeStartOrg = $nNodeStartAdjust;
			$nNodeEndAdjust = $oRow['LogoutTime'];
			$nNodeEndOrg = $nNodeEndAdjust;
			//调整开始区间
			$nTmpTime = ($nNodeStartAdjust % 86400 + 28800) % 86400;
			$anTimes = [];
			foreach ($aoTimeRange as $poGap)
			{
				if ($nTmpTime >= $poGap[0] && $nTmpTime < $poGap[1])
				{
					$anTimes[] = $nNodeStartAdjust;
					break;
				}
				else if ($nTmpTime < $poGap[0])
					$anTimes[] = $nNodeStartAdjust + $poGap[0] - $nTmpTime;
				else
					$anTimes[] = $nNodeStartAdjust - $nTmpTime + 86400 + $poGap[0];//天数后移1天
			}
			$nNodeStartAdjust = min($anTimes);
			//调整结束区间
			$nTmpTime = ($nNodeEndAdjust % 86400 + 28800) % 86400;
			$anTimes = [];
			foreach ($aoTimeRange as $poGap)
			{
				if ($nTmpTime >= $poGap[0] && $nTmpTime < $poGap[1])
				{
					$anTimes[] = $nNodeEndAdjust;
					break;
				}
				else if ($nTmpTime < $poGap[0])
					$anTimes[] = $nNodeEndAdjust - $nTmpTime - 86400 + $poGap[1];//天数前移1天
				else
					$anTimes[] = $nNodeEndAdjust - $nTmpTime + $poGap[1];
			}
			$nNodeEndAdjust = max($anTimes);
			//统计
			$nCurTimeArea = $nNodeStartAdjust;
			$nCurTimeOrg = $nNodeStartOrg;
			$nAddTimeBase = $nNodeStartOrg - (($nNodeStartOrg % 86400 + 28800) % 86400);
			while ($nCurTimeOrg < $nNodeEndOrg)
			{
				//区间出现记录
				if ($nNodeStartAdjust < $nNodeEndAdjust)
				{
					foreach ($aoTimeRange as $poGap)
					{
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
						if (isset($mapSummarize[$oRow['UserId']]) == false)
							$mapSummarize[$oRow['UserId']] = '';
						if (strpos($mapSummarize[$oRow['UserId']],'|'.$nAddTimeBase.'|') === false)
							$mapSummarize[$oRow['UserId']] .= '|'.$nAddTimeBase.'|';
					}
				}
				
				$nAddTimeBase += 86400;
				$nCurTimeArea = $nAddTimeBase;
				$nCurTimeOrg = $nAddTimeBase;
			}
		}
	};
	
	foreach ($_POST['ids'] as $nId)
	{
		++$nTotalCount;
		$nId = intval($nId);
		$anIds[] = $nId;
		if (count($anIds) > 200 || $nTotalCount == count($_POST['ids']))
		{
			$funcQuery($mapSummarize,$g_oDBConn,$strBaseSql.implode(',',$anIds).')',$aoTimeRange);
			$anIds = [];
		}
	}
	
	$nCount = 0;
	foreach ($mapSummarize as $strKey=>$strValue)
	{
		if ($nCount > 0)
			$strJson .= ',';
		$strJson .= '"'.$strKey.'":['.str_replace('||',',',substr($strValue,1,strlen($strValue) - 2)).']';
		++$nCount;
	}
	
	echo $strJson.'}}';