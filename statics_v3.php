<html>
<meta charset="UTF-8">
<title>应用使用次数统计</title>
<link rel="stylesheet" type="text/css" href="easyUI/themes/default/easyui.css">
<link rel="stylesheet" type="text/css" href="easyUI/themes/icon.css">
<link rel="stylesheet" href="PickMeUp/css/pickmeup.css" type="text/css">
<link rel="stylesheet" href="css/statics.css" type="text/css">
<script type="text/javascript" src="easyUI/jquery.min.js"></script>
<script type="text/javascript" src="easyUI/jquery.easyui.min.js"></script>
<script type="text/javascript" src="PickMeUp/js/pickmeup.min.js"></script>
<script src="highcharts/highcharts.js"></script>
<script src="highcharts/modules/exporting.js"></script>
</head>
<body>
<div id="head_bar" style="font-size:12px">
    总机器人数/总注册手机端:<?php
    chdir(dirname(__FILE__));
    include_once('./config.php');
    $g_poDBConfig = CSys_Conf::$DB_Config;

    $g_oDBConn = @new mysqli('p:' . $g_poDBConfig['server'], $g_poDBConfig['user'], $g_poDBConfig['psw'], $g_poDBConfig['database']);
    $g_oDBConn->set_charset('utf8');
    // $xResult = $g_oDBConn->query('SELECT COUNT(`UId`) `UTotal` FROM `RUserBase`');
    $xResult = $g_oDBConn->query('SELECT COUNT(`RUId`) `UTotal` FROM `MRBind`');
    if ($xResult === FALSE)
        echo 'Nan/';
    else {
        $oRow = $xResult->fetch_array();
        echo $oRow['UTotal'] . '/';
    }
    $xResult = $g_oDBConn->query('SELECT COUNT(`UId`) `UTotal` FROM `MUserBase`');
    if ($xResult === FALSE)
        echo 'Nan';
    else {
        $oRow = $xResult->fetch_array();
        echo $oRow['UTotal'];
    }
    ?>
    <div class="selDate">
        <!--        <a style="margin-left:10px;">请选择地区</a>-->
        <!--        <div id="pie_area_select" class="easyui-combotree"></div>-->
        <a style="margin-left:10px;">请选择日期</a>
        <input class="mulDate" type="text" style="width:200px;">
        <a style="margin-left:10px;">请选择App</a>
        <select class="selApp easyui-combobox" name="sel_app" style="width:200px;">
        </select>
    </div>
    <a class="refreshData l-btn l-btn-small">刷新</a>
    <a class="getData l-btn l-btn-small">统计数据</a>
    <div id="line_total_data"></div>
</div>
<hr>
<br>
<div id="line_user_times"></div>
<br>
<div id="line_user_avg_times"></div>
</body>
<script>
    //日期选择框配置
    addEventListener('DOMContentLoaded', function () {
        pickmeup('.mulDate', {
//            position: 'right',
            mode: 'multiple',
            hide_on_select: false,
            separator: '、',
            format: 'm/d/Y',
            first_day: 0,
            default_date: false,
            date: []
        });
    });
    /*
     * 显示等待界面
     * @param require string strMsg,界面要显示的信息
     * @param optional bool bAddCount = false,是否同时增加隐藏次数，用于在调用HideFullMask时确认是否真正需要隐藏屏蔽层
     * @return void
     */
    function SetFullMask(strMsg, bAddCount) {
        if (typeof(m_oFullMaskTip) == "undefined") {
            m_oFullMaskTip = new function () {
                var m_oMask = $('<div class="window-mask" style="z-index:99999"></div>').appendTo('body');
                var m_oTip = $('<div id="full_mask" class="datagrid-mask-msg panel-body" style="left:49%;top:49%;z-index:99999;white-spacing:nowrap;overflow:hidden;height:auto;"></div>').appendTo('body');
                var m_astrStack = [];

                this.Show = function (strMsg, bAddCount) {
                    if (bAddCount == true)
                        m_astrStack.push(strMsg);
                    m_oMask.css
                    ({
                        width: '100%',
                        height: $(document).height()
                    });
                    m_oMask.show();
                    m_oTip.html(strMsg).show();
                }

                this.Hide = function (bSubCount, bForceClose) {
                    var strLastTip = '';
                    if (m_astrStack.length > 0) {
                        if (bSubCount == true)
                            strLastTip = m_astrStack.pop();
                        strLastTip = m_astrStack[m_astrStack.length - 1];
                    }
                    if (m_astrStack.length == 0 || bForceClose == true) {
                        m_oMask.hide();
                        m_oTip.hide();
                        m_astrStack = [];
                    }
                    else
                        m_oTip.html(strLastTip);
                }

                this.SetText = function (strMsg) {
                    if (strMsg == undefined)
                        strMsg = ''
                    console.log(strMsg);
                    m_oTip.html(strMsg);
                }
            };
        }
        m_oFullMaskTip.Show(strMsg, bAddCount);
    };
    /*
     * 隐藏显示等待界面
     * @param optional bool bSubCount = false,是否同时减少隐藏次数的操作，用于确认是否真正需要隐藏屏蔽层
     * @param optional bool bForceClose = false,是否强制关闭屏蔽层,默认[否]
     * @return void
     */
    function HideFullMask(bSubCount, bForceClose) {
        if (typeof(m_oFullMaskTip) != "undefined")
            m_oFullMaskTip.Hide(bSubCount, bForceClose);
    };

    document.onreadystatechange = function () {
        if (!(document.readyState == 'complete' || document.readyState == 'loaded'))
            return;
        var m_oBindData;//地区数据
        var m_poCurRec;
        var m_oBindApp;
        var sel_app;

        //加载app选项
        var funcQueryTree = function () {
            SetFullMask('App数据更新中，请稍候');
            $.ajax({
                url: '/staticApp.php',
                async: true,
                cache: false,
                type: 'GET',
                success: function (oRsp) {
                    oRspJson = JSON.parse(oRsp);
                    console.log(oRspJson);
                    if (oRspJson.success) {
                        var data = oRspJson.allApps;
                        var dataList = [];
                        dataList.push({"value": "全部", "text": "全部"});
                        $.each(data, function (index, item) {
                            dataList.push({"value": index, "text": item});
                        });
                        $(".selApp").combobox("loadData", dataList);
                        $(".selApp").combobox('setValue', "全部");
                        sel_app = dataList[0].value;
                    }
                    else
                        alert('更新app数据失败:' + oRspJson.error);
                    HideFullMask();
                },
                error: function (strErr) {
                    HideFullMask();
                    alert('更新失败:' + strErr.status + ':' + strErr.statusText);
                }
            });
        }
        $('.selApp').combobox({
            onSelect: function (app) {
                sel_app = app.value;
            }
        });

        //定义更新地区数据函数
//        var funcQueryTree = function () {
//            SetFullMask('地区数据更新中，请稍候');
//            $.ajax({
//                url: '/staticArea.php',
//                async: true,
//                cache: false,
//                type: 'GET',
//                success: function (oRsp) {
//                    oRspJson = JSON.parse(oRsp);
////                    console.log(oRspJson);
//                    if (oRspJson.success) {
//                        m_oBindData = oRspJson;
//                        var oTreeData = [{text: '全部', id: 0, children: m_oBindData.area}];
//                        $('#pie_area_select').combotree('loadData', oTreeData);
//                        $('#pie_area_select').combotree('setValue', '全部');
//                        m_poCurRec = oTreeData[0];
//                    }
//                    else
//                        alert('更新失败:' + oRspJson.error);
//                    HideFullMask();
//                },
//                error: function (strErr) {
//                    HideFullMask();
//                    alert('更新失败:' + strErr.status + ':' + strErr.statusText);
//                }
//            })
//        }
//        $('#pie_area_select').combotree({
//            onSelect: function (poRec) {
//                m_poCurRec = poRec;
//            }
//        });

        //刷新页面
        function redreshAll() {
            window.location.reload();
        }

        var m_domRefresh = $('.refreshData');
        m_domRefresh.linkbutton({
            onClick: redreshAll,
        });

        var m_domDraw = $('.getData');
        m_domDraw.linkbutton({
            onClick: function () {
                var strDate = $('.mulDate').val();
                if (strDate == undefined || strDate == '') {
                    alert('请选择统计日期');
                    return;
                }
                var get_dates = strDate.split('、');
//                console.log(get_dates);
                var dates = [];
                var datesLength = get_dates.length;
                for (var i = 0; i < datesLength; i++) {
                    var today = Date.parse(new Date()) / 1000;
                    var selDates = Date.parse(new Date(get_dates[i])) / 1000;
//                    console.log("today:" + today + "," + "select:" + selDates);
                    if (selDates > today) {
                        alert("所选日期应于当前日期之前！");
                        return false;
                        break;
                    } else {
                        dates.push(selDates);
                    }

                }
                //请求数据
                SetFullMask('后台统计数据中，请稍候');
                $.ajax({
                    url: '/staticData_V3.php',
                    async: true,
                    cache: false,
                    type: 'POST',
                    data: {
                        app: sel_app,
                        'dates[]': dates,
                    },
                    success: function (oRsp) {
                        oRspJson = JSON.parse(oRsp);
                        console.log(oRspJson);
                        if (oRspJson.success) {
                            aoYUserTimes = [];
                            aoYUserAvgTimes = [];
                            var k = 0;
                            for (var key in oRspJson.appOpenTimes) {
                                aoYUserTimes.push({name: key, data: []});
                                aoYUserAvgTimes.push({name: key, data: []});
                                for (var i = 0; i < 17; i++) {
                                    aoYUserTimes[k].data.push(parseInt(oRspJson.appOpenTimes[key][i]));
                                    aoYUserAvgTimes[k].data.push(parseInt(oRspJson.appOpenTimes[key][i] / datesLength));
                                }
                                k++;
                            }
                            funUserTimesLine('line_user_times', '各应用各时间段总使用次数折线图', aoYUserTimes);
                            funUserAvgTimesLine('line_user_avg_times', '各应用各时间段平均使用次数折线图', aoYUserAvgTimes);
                        }
                        else
                            alert('获取统计数据失败:' + oRspJson.error);
                        HideFullMask();
                    },
                    error: function (strErr) {
                        HideFullMask();
                        alert('获取统计数据失败:' + strErr.status + ':' + strErr.statusText);
                    }
                })
            },
        });

        //初次加载数据
        funcQueryTree();
        //定义绘图函数
        var funUserTimesLine = function (strDomId, strTitle, paoY) {
            Highcharts.chart(strDomId, {
                title: {
                    text: strTitle,
                },
                xAxis: {
                    categories: ['7:00-8:00', '8:00-9:00', '9:00-10:00', '10:00-11:00', '11:00-12:00', '12:00-13:00', '13:00-14:00', '14:00-15:00',
                        '15:00-16:00', '16:00-17:00', '17:00-18:00', '18:00-19:00', '19:00-20:00', '20:00-21:00', '21:00-22:00', '22:00-23:00', '23:00-24:00', '24:00-1:00']
                },
                yAxis: {
                    title: {
                        text: '打开次数(次)'
                    },
                    plotLines: [{
                        value: 0,
                        width: 1,
                        color: '#808080'
                    }]
                },
                tooltip: {
                    valueSuffix: '次'
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
        var funUserAvgTimesLine = function (strDomId, strTitle, paoY) {
            Highcharts.chart(strDomId, {
                title: {
                    text: strTitle,
                },
                xAxis: {
                    categories: ['7:00-8:00', '8:00-9:00', '9:00-10:00', '10:00-11:00', '11:00-12:00', '12:00-13:00', '13:00-14:00', '14:00-15:00',
                        '15:00-16:00', '16:00-17:00', '17:00-18:00', '18:00-19:00', '19:00-20:00', '20:00-21:00', '21:00-22:00', '22:00-23:00', '23:00-24:00', '24:00-1:00']
                },
                yAxis: {
                    title: {
                        text: '打开次数(次)'
                    },
                    plotLines: [{
                        value: 0,
                        width: 1,
                        color: '#808080'
                    }]
                },
                tooltip: {
                    valueSuffix: '次'
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
</html>
