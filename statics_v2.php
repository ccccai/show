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
		总机器人数/总注册手机端:<?php
			chdir(dirname(__FILE__));
			include_once('./config.php');
			$g_poDBConfig = CSys_Conf::$DB_Config;

			$g_oDBConn = @new mysqli('p:'.$g_poDBConfig['server'],$g_poDBConfig['user'],$g_poDBConfig['psw'],$g_poDBConfig['database']);
			$g_oDBConn->set_charset('utf8');
			// $xResult = $g_oDBConn->query('SELECT COUNT(`UId`) `UTotal` FROM `RUserBase`');
			$xResult = $g_oDBConn->query('SELECT COUNT(`RUId`) `UTotal` FROM `MRBind`');
			if ($xResult === FALSE)
				echo 'Nan/';
			else
			{
				$oRow = $xResult->fetch_array();
				echo $oRow['UTotal'].'/';
			}
			$xResult = $g_oDBConn->query('SELECT COUNT(`UId`) `UTotal` FROM `MUserBase`');
			if ($xResult === FALSE)
				echo 'Nan';
			else
			{
				$oRow = $xResult->fetch_array();
				echo $oRow['UTotal'];
			}
		?>&nbsp;
		<div id="pie_area_select" class="easyui-combotree" style="width:150px;"></div>
	</div>
	<div style="height:350px;margin-top:10px;">
		<div id="pie_matrix_area" style="float:left;width:50%;height:100%"></div>
		<div id="pie_matrix_full" style="float:left;width:50%;height:100%"></div>
	</div>
	<div id="line_grid_area"></div>
	<div id="line_grid_full"></div>
	<div id="line_grid_full1"></div>
	<div id="line_grid_mobile"></div>
	<div id="look_line"></div>
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
//				console.log(strMsg);
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
			url : '/staticArea.php',
			async : true,
			cache : false,
			type  : 'GET',
			success:function(oRsp)
			{
				oRspJson = JSON.parse(oRsp);
//				console.log(oRspJson);
				if (oRspJson.success)
				{
					m_oBindData = oRspJson;
					var oTreeData = [{text:'全部',id:0,children:m_oBindData.area}];
//					console.log(oTreeData);
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
//			console.log(strDate);
            var today = Date.parse(new Date()) / 1000;
            var nStartDate = Date.parse(new Date(strDate)) / 1000;
			if (strDate == undefined || strDate == '')
			{
				alert('请选择开始统计日期！');
				return;
			}else if(nStartDate > today){
                alert("开始统计日期应于当前日期之前！");
                return;
            }
//			console.log(nStartDate);
			strDate = m_domEndDateBox.datebox('getValue');
			if (strDate == undefined || strDate == '')
			{
				alert('请选择结束统计日期');
				return;
			}
			var nEndDate = Date.parse(new Date(strDate)) / 1000 + 86400;
			var astrRange = [];
			var strStartTime = m_domStartTimeRange.timespinner('getValue');
//			console.log(strStartTime);
			if (strDate == undefined || strDate == '')
			{
				alert('请选择开始统计时间段');
				return;
			}

//            var time = "2016-06-20 11:00:00";
//            var times = Date.parse(new Date(time.replace(/-/g, "/"))) / 1000;//指定时间的毫秒数
//            console.log(times);
            var unixTimestamp = new Date( 1497880992*1000 ) ;
            commonTime = unixTimestamp.toLocaleString();
//            console.log(commonTime);

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
//			console.log(astrRange);
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
//			console.log(m_poCurRec);
			//请求数据
			SetFullMask('后台统计数据中，请稍候');
			$.ajax({
				url : '/staticData_V2.php',
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
					console.log(oRspJson);
					if (oRspJson.success)
					{
						m_mapTime = {};
						for (var strStaticsName in oRspJson.statics)
						{
							m_mapTime[strStaticsName] = {atotal:0,ftotal:0,detail:oRspJson.statics[strStaticsName]};
							for (var strRspTimeKey in oRspJson.statics[strStaticsName])
							{
								m_mapTime[strStaticsName].atotal += oRspJson.statics[strStaticsName][strRspTimeKey].area;
								m_mapTime[strStaticsName].ftotal += oRspJson.statics[strStaticsName][strRspTimeKey].full;
							}
						}
						//构造饼状图
						var oDrawData = [];
						var nTotal = 0;
						for (var strStaticsName in m_mapTime)
							nTotal += m_mapTime[strStaticsName].atotal;
						for (var strStaticsName in m_mapTime)
							oDrawData.push([strStaticsName + '(' + (m_mapTime[strStaticsName].atotal * 100 / nTotal).toFixed(2) + '%)',m_mapTime[strStaticsName].atotal]);
//						funcDrawPie('pie_matrix_area','各项在指定时间区间的总计',oDrawData);
						funcDrawSometime('pie_matrix_area','各应用使用次数总计',oRspJson.someTime);
//						console.log(oRspJson.someTime);
//						console.log(oDrawData);
						oDrawData = [];
						nTotal = 0;
						for (var strStaticsName in m_mapTime)
							nTotal += m_mapTime[strStaticsName].ftotal;
						for (var strStaticsName in m_mapTime)
							oDrawData.push([strStaticsName + '(' + (m_mapTime[strStaticsName].ftotal * 100 / nTotal).toFixed(2) + '%)',m_mapTime[strStaticsName].ftotal]);
                        funcDrawPie('pie_matrix_full','各应用使用时长总计',oDrawData);
						//构造时间线图
						var aoX = [];
						var oParseToDate = new Date();
						var aoYArea = [{name:'总计(仅指定时间区间)',data:[]}];
						var aoYFull = [{name:'总计(全日)',data:[]}];
						for (var strStaticsName in m_mapTime)
						{
							aoYArea.push({name:strStaticsName,data:[]});
							aoYFull.push({name:strStaticsName,data:[]});
						}
//                        console.log(aoYFull);
                        while (nStartDate < nEndDate)
						{
							//X
							oParseToDate.setTime(nStartDate * 1000);
							var oNewNode = {text:(oParseToDate.getMonth() + 1) + '月' + oParseToDate.getDate() + '日',
											timestamp:nStartDate};
							aoX.push(oNewNode.text);
							//Y
							var nTotalArea = 0;
							var nTotalFull = 0;
							for (var nLoop = 1;nLoop < aoYArea.length;++nLoop)
							{
								if (m_mapTime[aoYArea[nLoop].name].detail[nStartDate] != undefined)
								{
									aoYArea[nLoop].data.push(m_mapTime[aoYArea[nLoop].name].detail[nStartDate].area);
									aoYFull[nLoop].data.push(parseInt((m_mapTime[aoYArea[nLoop].name].detail[nStartDate].full / 60).toFixed(0)));
									nTotalArea += m_mapTime[aoYArea[nLoop].name].detail[nStartDate].area;
									nTotalFull += m_mapTime[aoYArea[nLoop].name].detail[nStartDate].full;
								}
								else
								{
									aoYArea[nLoop].data.push(0);
									aoYFull[nLoop].data.push(0);
								}
							}
							aoYArea[0].data.push(nTotalArea);
							aoYFull[0].data.push(parseInt((nTotalFull / 60).toFixed(0)));

							nStartDate += 86400;
						}
                        var OpenRate = [{name:'开机率',data:[]}];
                        for (var nLoop = 0;nLoop < oRspJson.openRate.y.length;++nLoop){
                            OpenRate[0].data.push((oRspJson.openRate.y[nLoop]) );
                        }

                        var Mobile = [{name:'手机使用率',data:[]}];
                        for (var nLoop = 0;nLoop < oRspJson.mobile.y.length;++nLoop){
                            Mobile[0].data.push((oRspJson.mobile.y[nLoop]) );
                        }

                        var Look = [];
                        var aa = oRspJson.look.lookY;
                        for (var i = 0; i < aa.length; i++) {
                            Look.push({name:aa[i].name,data:aa[i].data});
                        };
                       // funDrawLine('line_grid_area','仅统计每日指定时间区间',aoX,aoYArea);
						funDrawLine('line_grid_full','各应用每天使用时长折线图',aoX,aoYFull);
                        funDrawOpenRate('line_grid_full1','机器人每天开机率折线图',oRspJson.openRate.x,OpenRate);
                        funDrawMobile('line_grid_mobile','手机用户活跃率折线图',oRspJson.mobile.x,Mobile);
                        funDrawLook('look_line','各应用每日使用占比折线图',oRspJson.look.lookX,Look);
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
	var funcDrawPie = function(strDomId,strTitle,aoData)
	{
		Highcharts.chart(strDomId,{
			chart: {
				plotBackgroundColor: null,
				plotBorderWidth: null,
				plotShadow: false,
				type: 'pie'
			},
			title: {
				text: strTitle
			},
			tooltip: {
				pointFormat: '时长:{point.y}s',// ({point.percentage:.1f}%)'
			},
			plotOptions: {
				pie: {
					allowPointSelect: true,
					cursor: 'pointer',
					showInLegend:true,
					dataLabels: {
						enabled: false
					}
				}
			},
			series: [{
				type: 'pie',
				data: aoData
			}]
		});
	};
	var funcDrawSometime = function(strDomId,strTitle,aoData)
	{
		Highcharts.chart(strDomId,{
			chart: {
				plotBackgroundColor: null,
				plotBorderWidth: null,
				plotShadow: false,
				type: 'pie'
			},
			title: {
				text: strTitle
			},
			tooltip: {
				pointFormat: '使用次数:{point.y}次',// ({point.percentage:.1f}%)'
			},
			plotOptions: {
				pie: {
					allowPointSelect: true,
					cursor: 'pointer',
					showInLegend:true,
					dataLabels: {
						enabled: false
					}
				}
			},
			series: [{
				type: 'pie',
				data: aoData
			}]
		});
	};

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
					text: '使用时长(min)'
				},
				plotLines: [{
					value: 0,
					width: 1,
					color: '#808080'
				}]
			},
			tooltip: {
				valueSuffix: 'min'
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

    var funDrawOpenRate = function(strDomId,strTitle,paoX,paoY)
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
                    text: '开机率(%)'
                },
                plotLines: [{
                    value: 0,
                    width: 1,
                    color: '#808080'
                }]
            },
            tooltip: {
                valueSuffix: '%'
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
    var funDrawMobile = function(strDomId,strTitle,paoX,paoY)
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
                    text: '使用率(%)'
                },
                plotLines: [{
                    value: 0,
                    width: 1,
                    color: '#808080'
                }]
            },
            tooltip: {
                valueSuffix: '%'
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
    var funDrawLook = function(strDomId,strTitle,paoX,paoY)
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
                    text: '使用次数占比(%)'
                },
                plotLines: [{
                    value: 0,
                    width: 1,
                    color: '#808080'
                }]
            },
            tooltip: {
                valueSuffix: '%'
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
