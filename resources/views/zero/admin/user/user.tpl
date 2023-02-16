<!DOCTYPE html>
<html lang="en">
    <head>
        <title>{$config["appName"]} Dashboard</title>
        <link href="/theme/zero/assets/css/zero.css" rel="stylesheet" type="text/css"/>
        <meta charset="UTF-8" />
        <meta name="renderer" content="webkit" />
        <meta name="description" content="Updates and statistics" />
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="format-detection" content="telephone=no,email=no" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no" />
        <meta name="theme-color" content="#3B5598" />
        <meta http-equiv="X-UA-Compatible" content="IE=Edge,chrome=1" />
        <meta http-equiv="Cache-Control" content="no-siteapp" />
        <meta http-equiv="pragma" content="no-cache">
        <meta http-equiv="Cache-Control" content="no-cache, must-revalidate">
        <meta http-equiv="expires" content="0">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" />
        <link href="/theme/zero/assets/plugins/custom/datatables/datatables.bundle.css" rel="stylesheet" type="text/css" />
        <link href="/theme/zero/assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
        <link href="/theme/zero/assets/css/style.bundle.css" rel="stylesheet" type="text/css" />
        <link href="/favicon.png" rel="shortcut icon">
        <link href="/apple-touch-icon.png" rel="apple-touch-icon">
    </head>
	{include file ='admin/menu.tpl'}
                    <div class="app-main flex-column flex-row-fluid" id="kt_app_main">
                        <div class="d-flex flex-column flex-column-fluid mt-10">
                            <div id="kt_app_content" class="app-content flex-column-fluid">
                                <div id="kt_app_content_container" class="app-container container-xxl">

                                    <div class="card">
                                        <div class="card-header">
                                            <div class="card-title text-dark fs-3 fw-bolder">用户列表</div>
                                            <div class="card-toolbar">
                                                <button class="btn btn-primary btn-sm fw-bold">创建用户</button>
                                            </div>
                                        </div>
                                        <div class="card-body" id="KTAdmin_node_record">
                                            {include file='table/table.tpl'}
                                        </div>  
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {include file='admin/script.tpl'}
    </body>
    <script>
        window.addEventListener('load', () => {
            {include file='table/js_2.tpl'}
        })
    </script>
    <script>
        function updateUserStatus(type, id){
            switch (type){
                case 'enable':
                    if ($("#user_enable_"+id).prop("checked")) {
                        var enable = 1;
                    } else {
                        var enable = 0;
                    }
                    $.ajax({
                        type: "PUT",
                        url: "/admin/user/update/status/enable",
                        dataType: "JSON",
                        data: {
                            enable,
                            id,
                        },
                        success: function(data){}
                    });
                    break;
                case 'is_admin':
                if ($("#user_is_admin_"+id).prop("checked")) {
                        var is_admin = 1;
                    } else {
                        var is_admin = 0;
                    }
                    $.ajax({
                        type: "PUT",
                        url: "/admin/user/update/status/is_admin",
                        dataType: "JSON",
                        data: {
                            is_admin,
                            id,
                        },
                        success: function(data){}
                    });
                    break;
            }
        }
    </script>
</html>