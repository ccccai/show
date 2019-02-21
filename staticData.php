<?php
/*
	数据统计 
	POST['ids'] - 要统计的id集合，json数组
	POST['start'] - 开始时间的时间戳(到日，处理时自动补齐到当天)
	POST['end'] - 结束时间的时间戳(到日，处理时自动补齐到当天)
	POST['withMatrixInfo'] - [可选]是否返回矩阵描述
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
	$_POST['ids'] = @json_decode($_POST['ids']);
	if ($_POST['ids'] == null || $_POST['ids'] == false)
	{
		echo '{"success":0,"error":"参数错误"}';
		return;
	}
	$_POST['start'] = intval(intval($_POST['start']) / 24) * 24;
	$nTmpEnd = intval(ceil(intval($_POST['end']) / 24)) * 24;
	if ($nTmpEnd == $_POST['end'])
		$_POST['end'] = $nTmpEnd + 3600 * 24;
	else
		$_POST['end'] = $nTmpEnd;
	if ($_POST['start'] > $_POST['end'])
	{
		echo '{"success":0,"error":"时间区间错误"}';
		return;
	}
	else if ($_POST['start'] == $_POST['end'])
		$_POST['end'] += 24 * 3600;
	chdir(dirname(__FILE__));
	include_once('./config.php');
	$g_poDBConfig = CSys_Conf::$DB_Config;
	
	$g_oDBConn = @new mysqli('p:'.$g_poDBConfig['server'],$g_poDBConfig['user'],$g_poDBConfig['psw'],$g_poDBConfig['database']);
	$g_oDBConn->set_charset('utf8');

	$anIds = [];
	$strBaseSql1 = 'SELECT SUM(`UsedTime`) `CalUsedTime`,`MatrixID` FROM `DailyReport_Backup` WHERE `BindRUId` IN (';
	$strBaseSql2 = 'SELECT SUM(`UsedTime`) `CalUsedTime`,`MatrixID` FROM `DailyReport` WHERE `BindRUId` IN (';
	$nTotalCount = 0;
	$mapSummarize = [];//key-单项id value-总值
	$funcQuery = function(&$mapSummarize,$poDBConn,$strSql,$nStartTime)
	{
		$xResult = $poDBConn->query($strSql);
		if ($xResult === FALSE)
		{
			echo '{"success":0,"error_code":'.ERR_INNER_CODE.',"error_desc":"'.ERR_INNER_DESC.'[1]"}';
			exit();
		}
		while ($oRow = $xResult->fetch_array())
		{
			if (isset($mapSummarize[$oRow['MatrixID']]) == false)
				$mapSummarize[$oRow['MatrixID']] = [];
			if (isset($mapSummarize[$oRow['MatrixID']][$nStartTime]) == false)
				$mapSummarize[$oRow['MatrixID']][$nStartTime] = 0;
			$mapSummarize[$oRow['MatrixID']][$nStartTime] += $oRow['CalUsedTime'];
		}
	};
	
	foreach ($_POST['ids'] as $nId)
	{
		++$nTotalCount;
		$nId = intval($nId);
		$anIds[] = $nId;
		if (count($anIds) > 200 || $nTotalCount == count($_POST['ids']))
		{
			$nRangeStart = $_POST['start'];
			$nRangeEnd = $nRangeStart + 3600 * 24;
			while ($nRangeEnd <= $_POST['end'])
			{
				$funcQuery($mapSummarize,$g_oDBConn,$strBaseSql1.implode(',',$anIds).') AND `ReportTime`>='.$nRangeStart.' AND `ReportTime`<'.$nRangeEnd.' GROUP BY `MatrixID`',$nRangeStart);
				$funcQuery($mapSummarize,$g_oDBConn,$strBaseSql2.implode(',',$anIds).') AND `ReportTime`>='.$nRangeStart.' AND `ReportTime`<'.$nRangeEnd.' GROUP BY `MatrixID`',$nRangeStart);
				$nRangeStart = $nRangeEnd;
				$nRangeEnd += 3600 * 24;
			}
			$anIds = [];
		}
	}
	
	if (isset($_POST['withMatrixInfo']) && $_POST['withMatrixInfo'] == 1)
	{
		//查询矩阵表
		$xResult = $g_oDBConn->query('SELECT `Id`,`Name` FROM `MatrixGroup`');
		if ($xResult === FALSE)
		{
			echo '{"success":0,"error_code":'.ERR_INNER_CODE.',"error_desc":"'.ERR_INNER_DESC.'[2]"}';
			exit();
		}
		$mapMatrix = [];
		while ($oRow = $xResult->fetch_array())
			$mapMatrix[$oRow['Id']] = array('name'=>$oRow['Name'],'children'=>[]);
		$xResult = $g_oDBConn->query('SELECT `GroupId`,`Id`,`Name` FROM `ReportMatrix`');
		if ($xResult === FALSE)
		{
			echo '{"success":0,"error_code":'.ERR_INNER_CODE.',"error_desc":"'.ERR_INNER_DESC.'[3]"}';
			exit();
		}
		while ($oRow = $xResult->fetch_array())
			$mapMatrix[$oRow['GroupId']]['children'][$oRow['Id']] = $oRow['Name'];
		//输出
		echo '{"success":1,"statics":'.json_encode($mapSummarize,JSON_UNESCAPED_UNICODE).',"matrix":'.json_encode($mapMatrix,JSON_UNESCAPED_UNICODE).'}';
	}
	else
		echo '{"success":1,"statics":'.json_encode($mapSummarize,JSON_UNESCAPED_UNICODE).'}';
