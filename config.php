<?php
//存放更新相关配置信息

class CSys_Conf
{
    //数据库配置
    static public $DB_Config =
        [
             'server'=>'linuxwx.honeybot.cn',
             'user'=>'xu',
             'psw'=>'root',
             'database'=>'honeybot'
//           'server'=>'localhost',
//           'user'=>'root',
//           'psw'=>'root',
//           'database'=>'honeybot'
        ];

    //Redis配置
    static public $Redis_Config =
        [
            'addr'=>'127.0.0.1',
            'port'=>6770
        ];

    static public $Clean_Bind_Tool_Path = '/home/system/server/bin/';//清理绑定工具的路径

    static public $Redis_Key_Prefix = 'dev_';//redis键的前缀，可用于让不同部署独立开来

    static public $Default_Branch_Search_Range = 10000;//默认的搜索矩形中心点向两边各延伸的长度，单位[米]，如果相关接口有传入值，直接使用传入值

    static public $Deposit_Pre_Robot = 49800;//单位[分]

    static public $Rent_Pre_Day = 100;//单位[分]

    static public $Income_Rate = 1;//收益机构分成比

    static public $Max_Rent_Days = 900000;//最大租借天数(含当天)，超出此天数后不能归还，且最早支付的一张押金单无法退

    //微信应用配置
    /*static public $WX_Config =
    [
        'appid'=>'wxf4aa8458e6176499',
        'secret'=>'c9753acb74fa547d280b25cac7410ae1'
    ];*/
};
?>
