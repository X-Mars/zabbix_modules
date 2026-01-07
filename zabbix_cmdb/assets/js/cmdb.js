/**
 * CMDB 主机列表页面 JavaScript
 */

var pageData = window.cmdbPageData || {};
var searchTimeout;

function buildUrl(params) {
    var baseParams = {
        action: pageData.action || "cmdb",
        page: params.page || pageData.page,
        per_page: params.per_page || pageData.per_page
    };
    
    if (pageData.search) baseParams.search = pageData.search;
    if (pageData.groupid) baseParams.groupid = pageData.groupid;
    if (pageData.interface_type) baseParams.interface_type = pageData.interface_type;
    if (pageData.sort) baseParams.sort = pageData.sort;
    if (pageData.sortorder) baseParams.sortorder = pageData.sortorder;
    
    // 覆盖特定参数
    for (var key in params) {
        if (params.hasOwnProperty(key)) {
            baseParams[key] = params[key];
        }
    }
    
    var queryParts = [];
    for (var key in baseParams) {
        if (baseParams.hasOwnProperty(key)) {
            var value = baseParams[key];
            if (value !== "" && value !== 0 && value !== "0") {
                queryParts.push(encodeURIComponent(key) + "=" + encodeURIComponent(value));
            }
        }
    }
    
    return "zabbix.php?" + queryParts.join("&");
}

function handleSearchInput(input) {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function() {
        var form = input.closest("form");
        if (form) {
            var pageInput = form.querySelector("input[name=page]");
            if (pageInput) pageInput.value = "1";
            form.submit();
        }
    }, 500);
}

function handleFilterChange() {
    var form = document.querySelector("form");
    if (form) {
        var pageInput = form.querySelector("input[name=page]");
        if (pageInput) pageInput.value = "1";
        form.submit();
    }
}

function changePerPage(value) {
    window.location.href = buildUrl({ per_page: value, page: 1 });
}

function jumpToPage() {
    var inputs = document.querySelectorAll("#jump-page-input");
    var input = inputs[inputs.length - 1] || inputs[0];
    if (input) {
        var page = parseInt(input.value);
        if (page >= 1 && page <= pageData.total_pages) {
            window.location.href = buildUrl({ page: page });
        } else {
            alert("Invalid page number");
        }
    }
}

// 初始化
document.addEventListener("DOMContentLoaded", function() {
    // 页面跳转输入框回车事件
    var jumpInputs = document.querySelectorAll("#jump-page-input");
    for (var i = 0; i < jumpInputs.length; i++) {
        jumpInputs[i].addEventListener("keypress", function(e) {
            if (e.key === "Enter") {
                e.preventDefault();
                jumpToPage();
            }
        });
    }
});
