/**
 * Created by win10 on 2018/5/10.
 */
/** 页码
 *
 * @param pno 当前页
 * @param size 每页的数据条数
 */
function goPage(pno,size) {
    var itable = document.getElementById("myTable");
    var num = itable.rows.length;
    var totalPage = 0;//总页数
    var pageSize = size;//每页显示行数
    //总共分几页
    if ((num - 1) / pageSize > parseInt((num - 1) / pageSize)) {
        totalPage = parseInt((num - 1) / pageSize) + 1;
    } else {
        totalPage = parseInt((num - 1) / pageSize);
    }
    var currentPage = pno;//当前页数
    var startRow = (currentPage - 1) * pageSize + 2;//开始显示的行
    var endRow = startRow + pageSize - 1;//结束显示的行
    endRow = (endRow > num) ? num : endRow;
    //遍历显示数据实现分页
    for (var i = 2; i < (num + 1); i++) {
        var irow = itable.rows[i - 1];
        if (i >= startRow && i <= endRow) {
            irow.style.display = "table-row";
        } else {
            irow.style.display = "none";
        }
    }
    var tempStr = "<span style='margin-right: 10px'>共" + totalPage + "页</span>";
    if (currentPage > 1) {
        tempStr += "<span class='page_btn btn btn-default btn-sm' href='#' onClick='goPage(" + (1) + "," + size + ")'>首页</span>";
        tempStr += "<span class='page_btn btn btn-default btn-sm' href='#' onClick='goPage(" + (Number(currentPage) - 1) + "," + size + ")'>上一页</span>";
    } else {
        tempStr += "<span class='page_btn btn btn-default btn-sm'>首页</span>"
        tempStr += "<span class='page_btn btn btn-default btn-sm'>上一页</span>"
    }
    tempStr += "<input type='text' class='page_input' id='pageInput' onclick='bindSelect()' value='" + currentPage + "' onkeypress='BindEnter(this," + totalPage + "," + size + ")'/>";
    if (currentPage < totalPage) {
        tempStr += "<span class='page_btn btn btn-default btn-sm' href='#' onClick='goPage(" + (Number(currentPage) + 1) + "," + size + ")'>下一页</span>";
        tempStr += "<span class='page_btn btn btn-default btn-sm' href='#' onClick='goPage(" + (totalPage) + "," + size + ")'>尾页</span>";
    } else {
        tempStr += "<span class='page_btn btn btn-default btn-sm'>下一页</span>"
        tempStr += "<span class='page_btn btn btn-default btn-sm'>尾页</span>"
    }
    document.getElementById("tablePage").innerHTML = tempStr;
}
function bindSelect() {
    document.getElementById("pageInput").focus();
    document.getElementById("pageInput").select();
}
function BindEnter(e,totalPage,size){
    if (event.keyCode == 13) {
        event.cancelBubble = true;
        event.returnValue = false;
        var p;
        if(e.value > totalPage){
            p = totalPage;
        }else if(e.value <= 0){
            p = 1;
        }else{
            p = e.value;
        }
        goPage(p,size);
    }
}