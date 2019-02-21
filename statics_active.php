<html>
<head>
	<meta charset="UTF-8">
	<title>Statics</title>
	<link rel="stylesheet" type="text/css" href="easyUI/themes/default/easyui.css">
	<link rel="stylesheet" type="text/css" href="easyUI/themes/icon.css">
	<script type="text/javascript" src="easyUI/jquery.min.js"></script>
	<script type="text/javascript" src="easyUI/jquery.easyui.min.js"></script>
	<script src="highcharts/highcharts.js"></script>
	<script src="highcharts/modules/exporting.js"></script>
</head>
<body>
	<div id="head_bar" style="font-size:12px">
		<div id="pie_area_select" class="easyui-combotree" style="width:150px;"></div>
	</div>
	<div id="line_grid_active"></div>
<script>
/*
 * 显示等待界面
 * @param require string strMsg,界面要显示的信息
 * @param optional bool bAddCount = false,是否同时增加隐藏次数，用于在调用HideFullMask时确认是否真正需要隐藏屏蔽层
 * @return void
 */
function SetFullMask(strMsg,bAddCount)
{
	if (typeof(m_oFullMaskTip) == "undefined")
	{
		m_oFullMaskTip = new function()
		{
			var m_oMask	= $('<div class="window-mask" style="z-index:99999"></div>').appendTo('body');
			var m_oTip	= $('<div id="full_mask" class="datagrid-mask-msg panel-body" style="left:49%;top:49%;z-index:99999;white-spacing:nowrap;overflow:hidden;height:auto;"></div>').appendTo('body');
			var m_astrStack = [];
			
			this.Show = function(strMsg,bAddCount)
			{
				if (bAddCount == true)
					m_astrStack.push(strMsg);
				m_oMask.css
				({
					width	:	'100%',
					height	:	$(document).height()
				});
				m_oMask.show();
				m_oTip.html(strMsg).show();
			}
			
			this.Hide = function(bSubCount,bForceClose)
			{
				var strLastTip = '';
				if (m_astrStack.length > 0)
				{
					if (bSubCount == true)
						strLastTip = m_astrStack.pop();
					strLastTip = m_astrStack[m_astrStack.length - 1];
				}
				if (m_astrStack.length == 0 || bForceClose == true)
				{
					m_oMask.hide();
					m_oTip.hide();
					m_astrStack = [];
				}
				else
					m_oTip.html(strLastTip);
			}
			
			this.SetText = function(strMsg)
			{
				if (strMsg == undefined)
					strMsg = ''
				console.log(strMsg);
				m_oTip.html(strMsg);
			}
		};
	}
	m_oFullMaskTip.Show(strMsg,bAddCount);
};

/*
 * 隐藏显示等待界面
 * @param optional bool bSubCount = false,是否同时减少隐藏次数的操作，用于确认是否真正需要隐藏屏蔽层
 * @param optional bool bForceClose = false,是否强制关闭屏蔽层,默认[否]
 * @return void
 */
function HideFullMask(bSubCount,bForceClose)
{
	if (typeof(m_oFullMaskTip) != "undefined")
		m_oFullMaskTip.Hide(bSubCount,bForceClose);
};

document.onreadystatechange = function()
{
	if (!(document.readyState == 'complete' || document.readyState == 'loaded'))
		return;
	var m_oBindData;//地区数据
	var m_mapTime;//不同项的时间区间
	var m_mapMatrix;//项id映射
	var m_poCurRec;
	//定义更新地区数据函数
	var funcQueryTree = function()
	{
		SetFullMask('地区数据更新中，请稍候');
		$.ajax({
			url : '/show/staticArea.php',
			async : true,
			cache : false,
			type  : 'GET',
			success:function(oRsp)
			{
				oRspJson = JSON.parse(oRsp);
				if (oRspJson.success)
				{
					m_oBindData = oRspJson;
					var oTreeData = [{text:'全部',id:0,children:m_oBindData.area}];
					$('#pie_area_select').combotree('loadData',oTreeData);
					$('#pie_area_select').combotree('setValue','全部');
					m_poCurRec = oTreeData[0];
				}
				else
					alert('更新失败:' + oRspJson.error);
				HideFullMask();
			},
			error:function(strErr)
			{
				HideFullMask();
				alert('更新失败:' + strErr.status + ':' + strErr.statusText);
			}
		})
	}
	//创建界面
	$('#pie_area_select').combotree({
		onSelect:function(poRec){
			m_poCurRec = poRec;
		}
	});
	var m_domRefresh = $('<a style="margin-right:30px;">刷新</a>').appendTo($('#head_bar'));
	m_domRefresh.linkbutton({
		onClick:funcQueryTree,
	});
	$('<a style="margin-left:10px;">选择开始日期</a>').appendTo($('#head_bar'));
	var m_domStartDateBox = $('<input type="text"/>').appendTo($('#head_bar'));
	m_domStartDateBox.datebox({
		editable	:	false,
		width		:	100,
	});
	
	$('<a style="margin-left:10px;">选择结束日期</a>').appendTo($('#head_bar'));
	var m_domEndDateBox = $('<input type="text"/>').appendTo($('#head_bar'));
	m_domEndDateBox.datebox({
		editable	:	false,
		width		:	100,
	});
	
	$('<a style="margin-left:10px;">指定统计的每日时间区间:从</a>').appendTo($('#head_bar'));
	var m_domStartTimeRange = $('<input style="width:70px;">').appendTo($('#head_bar'));
	m_domStartTimeRange.timespinner({
		showSeconds	:	false,
		value		:	"0:0",
	});
	$('<a style="margin-left:10px;">到</a>').appendTo($('#head_bar'));
	var m_domEndTimeRange = $('<input style="width:70px;">').appendTo($('#head_bar'));
	m_domEndTimeRange.timespinner({
		showSeconds	:	false,
		value		:	"23:59",
	});
	
	var m_domDraw = $('<a style="margin-left:30px;">统计数据</a>').appendTo($('#head_bar'));
	m_domDraw.linkbutton({
		onClick:function()
		{
			var strDate = m_domStartDateBox.datebox('getValue');
			if (strDate == undefined || strDate == '')
			{
				alert('请选择开始统计日期');
				return;
			}
			var nStartDate = Date.parse(new Date(strDate)) / 1000;
			strDate = m_domEndDateBox.datebox('getValue');
			if (strDate == undefined || strDate == '')
			{
				alert('请选择结束统计日期');
				return;
			}
			var nEndDate = Date.parse(new Date(strDate)) / 1000 + 86400;
			var astrRange = [];
			var strStartTime = m_domStartTimeRange.timespinner('getValue');
			if (strDate == undefined || strDate == '')
			{
				alert('请选择开始统计时间段');
				return;
			}
			var astrDiv = strStartTime.split(':');
			var nStartTime = parseInt(astrDiv[0]) * 3600 + parseInt(astrDiv[1]);
			var strEndTime = m_domEndTimeRange.timespinner('getValue');
			if (strDate == undefined || strDate == '')
			{
				alert('请选择结束统计时间段');
				return;
			}
			astrDiv = strEndTime.split(':');
			var nEndTime = parseInt(astrDiv[0]) * 3600 + parseInt(astrDiv[1]) * 60 + 60;
			if (nStartTime <= nEndTime)
				astrRange.push(nStartTime + ':' + nEndTime);
			else
			{
				astrRange.push('0:' + nEndTime);
				astrRange.push(nStartTime + ':86400');
			}
			//组装请求的id
			var funcScanIds = function(poRec)
			{
				var anIds = [];
				if (m_oBindData.ids[poRec.id])
					anIds = anIds.concat(m_oBindData.ids[poRec.id]);
				if (poRec.children)
				{
					for (var nLoop = 0;nLoop < poRec.children.length;++nLoop)
						anIds = anIds.concat(funcScanIds(poRec.children[nLoop]));
				}
				return anIds;
			}
			var anIds = funcScanIds(m_poCurRec);
			//请求数据
			SetFullMask('后台统计数据中，请稍候');
			$.ajax({
				url : '/show/staticActive.php',
				async : true,
				cache : false,
				type  : 'POST',
				data  :
				{
					ids:JSON.stringify(anIds),
					start:nStartDate,
					end:nEndDate,
					'timeRange[]':astrRange,
				},
				success:function(oRsp)
				{
					oRspJson = JSON.parse(oRsp);
					if (oRspJson.success)
					{
						m_mapTime = {};//key-time value-上线数
						for (var strUserId in oRspJson.statics)
						{
							var poTimes = oRspJson.statics[strUserId];
							for (var nLoop = poTimes.length - 1;nLoop >= 0;--nLoop)
							{
								if (m_mapTime[poTimes[nLoop]] == undefined)
									m_mapTime[poTimes[nLoop]] = 1;
								else
									++m_mapTime[poTimes[nLoop]];
							}
						}
						//构造时间线图
						var aoX = [];
						var oParseToDate = new Date();
						var aoYFull = [{name:'上线机器人数',data:[]}];
						while (nStartDate < nEndDate)
						{
							//X
							oParseToDate.setTime(nStartDate * 1000);
							var oNewNode = {text:(oParseToDate.getMonth() + 1) + '月' + oParseToDate.getDate() + '日',
											timestamp:nStartDate};
							aoX.push(oNewNode.text);
							//Y
							if (m_mapTime[nStartDate] != undefined)
								aoYFull[0].data.push(m_mapTime[nStartDate]);
							else
								aoYFull[0].data.push(0);
							
							nStartDate += 86400;
						}
						funDrawLine('line_grid_active','上线统计',aoX,aoYFull);
					}
					else
						alert('获取统计数据失败:' + oRspJson.error);
					HideFullMask();
				},
				error:function(strErr)
				{
					HideFullMask();
					alert('获取统计数据失败:' + strErr.status + ':' + strErr.statusText);
				}
			})
		},
	});
	
	$('<hr/>').appendTo($('#head_bar'));
	
	funcQueryTree();//初次加载数据
	
	//定义绘图函数
	var funDrawLine = function(strDomId,strTitle,paoX,paoY)
	{
		Highcharts.chart(strDomId, {
			title: {
				text: strTitle,
			},
			xAxis: {
				categories: paoX
			},
			yAxis: {
				title: {
					text: '数量'
				},
				plotLines: [{
					value: 0,
					width: 1,
					color: '#808080'
				}],
				allowDecimals:false
			},
			tooltip: {
			},
			legend: {
				layout: 'vertical',
				align: 'right',
				verticalAlign: 'middle',
				borderWidth: 0
			},
			series: paoY
		});
	}
}
</script>
</body>
</html>
