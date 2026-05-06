<?php

	defined( 'ACCESS' ) or die( 'HTTP/1.0 401 Unauthorized.<br />' );
	//admin
	check_mod_admin();
	
	$dlna_active = defined( 'DLNA_ACTIVE' ) && DLNA_ACTIVE;
	
	// Check if SSDP daemon is running
	$daemon_running = false;
	$daemon_pid = 0;
	$pid_file = '/tmp/phpmediaserver-ssdpd.pid';
	if( file_exists( $pid_file ) ){
		$daemon_pid = (int)trim( file_get_contents( $pid_file ) );
		if( $daemon_pid > 0 && file_exists( '/proc/' . $daemon_pid ) ){
			$daemon_running = true;
		}
	}
	if( !$daemon_running ){
		// Fallback: check by process name
		$output = array();
		exec( 'pgrep -f "ssdpd.php" 2>/dev/null', $output );
		if( is_array( $output ) && count( $output ) > 0 ){
			$daemon_running = true;
			$daemon_pid = (int)$output[0];
		}
	}
	
	// Get local IP
	$local_ip = '';
	if( defined( 'DLNA_BINDIP' ) ){
		$local_ip = DLNA_BINDIP;
	}else{
		$output = array();
		exec( "hostname -I 2>/dev/null | awk '{print \$1}'", $output );
		if( is_array( $output ) && count( $output ) > 0 ){
			$local_ip = trim( $output[0] );
		}
	}
	
	// Action: trigger SSDP NOTIFY announcement
	$notify_result = '';
	if( array_key_exists( 'notify', $_GET ) && $_GET[ 'notify' ] == '1' ){
		require_once( PPATH_CORE . DS . 'functions.dlna.php' );
		dlna_sddpSend();
		$notify_result = 'SSDP NOTIFY announcement sent. DLNA clients should now discover this server.';
	}
	
?>
<script type="text/javascript">
$(function () {
    
});
function dlna_notify(){
    var url = '<?php echo getURLBase(); ?>?r=r&action=dlnanotify&notify=1';
    loading_show();
    $.get( url )
    .done( function( data ){
        $( '#dResultNotify' ).html( 'SSDP NOTIFY announcement sent!' );
        loading_hide();
        setTimeout( function(){ $( '#dResultNotify' ).html( '' ); }, 3000 );
    });
    return false;
}
function dlna_toggle( el ){
    var new_state = $( el ).prop( 'checked' ) ? 1 : 0;
    var url = '<?php echo getURLBase(); ?>?r=r&action=dlnanotifya&dlna_toggle=' + new_state;
    loading_show();
    $.getJSON( url )
    .done( function( data ){
        if( data.success ){
            $( '#dlnaToggleLabel' ).text( data.dlna_active ? '已启用' : '已禁用' );
            $( '#dlnaToggleLabel' ).attr( 'class', data.dlna_active ? 'toggle-label-on' : 'toggle-label-off' );
            $( '#dlnaToggleResult' ).html( data.message ).css( 'color', '#FFD700' ).fadeIn();
            setTimeout( function(){ $( '#dlnaToggleResult' ).fadeOut(); }, 3000 );
        }else{
            $( '#dlnaToggleResult' ).html( data.message ).css( 'color', '#f44336' ).fadeIn();
            setTimeout( function(){ $( '#dlnaToggleResult' ).fadeOut(); }, 3000 );
            // Revert toggle
            $( el ).prop( 'checked', !new_state );
        }
        loading_hide();
    })
    .fail( function(){
        $( '#dlnaToggleResult' ).html( '请求失败' ).css( 'color', '#f44336' ).fadeIn();
        setTimeout( function(){ $( '#dlnaToggleResult' ).fadeOut(); }, 3000 );
        $( el ).prop( 'checked', !new_state );
        loading_hide();
    });
    return false;
}
</script>

<style>
.dlna-info-table {
    width: 100%;
    max-width: 800px;
    margin: 10px auto;
    border-collapse: collapse;
}
.dlna-info-table td {
    padding: 10px 15px;
    border: 1px solid #444;
}
.dlna-info-table td.label {
    font-weight: bold;
    width: 200px;
    background: rgba(255,255,255,0.05);
}
.dlna-info-table td.value {
    font-family: monospace;
}
.status-on {
    color: #FFD700;
    font-weight: bold;
    text-shadow: 0 0 6px rgba(255,215,0,0.5), 0 1px 2px rgba(0,0,0,0.5);
}
.status-off {
    color: #f44336;
    font-weight: bold;
    text-shadow: 0 0 4px rgba(244,67,54,0.3);
}

/* Toggle Switch */
.toggle-wrap {
    display: inline-flex;
    align-items: center;
    gap: 10px;
}
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 26px;
    flex-shrink: 0;
}
.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #666;
    transition: .3s;
    border-radius: 26px;
    border: 1px solid #555;
}
.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}
.toggle-switch input:checked + .toggle-slider {
    background-color: #4CAF50;
    border-color: #4CAF50;
}
.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(22px);
}
.toggle-label-on {
    color: #FFD700;
    font-weight: bold;
    text-shadow: 0 0 6px rgba(255,215,0,0.5), 0 1px 2px rgba(0,0,0,0.5);
}
.toggle-label-off {
    color: #888;
}

.dlna-help-box {
    width: 100%;
    max-width: 800px;
    margin: 20px auto;
    padding: 15px;
    background: rgba(255,255,255,0.05);
    border: 1px solid #444;
    border-radius: 5px;
    line-height: 1.6;
}
.dlna-help-box h3 {
    margin-top: 0;
    color: #ddd;
    border-bottom: 1px solid #444;
    padding-bottom: 8px;
}
.dlna-help-box ul {
    padding-left: 20px;
}
.btn-notify {
    padding: 10px 25px;
    background: #F3F3F3;
    color: black;
    border: 1px solid #e8e8e8;
    border-radius: 0 !important;
    cursor: pointer;
    font-size: 14px;
    margin: 5px;
}
.btn-notify:hover {
    background: #ddd;
}
</style>

<br />

<div id='dResultNotify'></div>

<h2 class='tCenter'>DLNA / UPnP Media Server</h2>

<br />

<table class='dlna-info-table'>
    <tr>
        <td class='label'>DLNA 服务状态</td>
        <td class='value'>
            <div class='toggle-wrap'>
                <label class='toggle-switch'>
                    <input type='checkbox' id='dlnaToggle' onchange='dlna_toggle(this);' <?php echo $dlna_active ? 'checked' : ''; ?>>
                    <span class='toggle-slider'></span>
                </label>
                <span id='dlnaToggleLabel' class='<?php echo $dlna_active ? "toggle-label-on" : "toggle-label-off"; ?>'>
                    <?php echo $dlna_active ? '已启用' : '已禁用'; ?>
                </span>
                <span id='dlnaToggleResult' style='display:none;margin-left:8px;font-size:13px;'></span>
            </div>
        </td>
    </tr>
    <tr>
        <td class='label'>SSDP 发现守护进程</td>
        <td class='value'>
            <?php if( $daemon_running ): ?>
                <span class='status-on'>● 运行中</span>
                <?php if( $daemon_pid > 0 ): ?>
                    <span style='color:#888;font-size:12px;'> (PID: <?php echo $daemon_pid; ?>)</span>
                <?php endif; ?>
            <?php else: ?>
                <span class='status-off'>● 未运行</span>
                <br />
                <span style='color:#aaa;font-size:12px;'>
                    启动: <code>sudo systemctl start phpmediaserver-ssdpd</code>
                </span>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <td class='label'>服务器地址</td>
        <td class='value'>
            <?php 
            if( defined( 'DLNA_WEB_BASESERVER_HTTP' ) ){
                echo DLNA_WEB_BASESERVER_HTTP;
            }
            ?>
            <span style='color:#888;font-size:12px;'> (端口: 8100)</span>
        </td>
    </tr>
    <tr>
        <td class='label'>设备描述 URL</td>
        <td class='value'>
            <a href='<?php echo rtrim(DLNA_WEB_BASESERVER_HTTP, '/') . '/dlna/rootDesc.php'; ?>' target='_blank'>
                <?php echo rtrim(DLNA_WEB_BASESERVER_HTTP, '/') . '/dlna/rootDesc.php'; ?>
            </a>
        </td>
    </tr>
    <tr>
        <td class='label'>绑定 IP / 子网</td>
        <td class='value'>
            <?php 
            if( defined( 'DLNA_BINDIP' ) ){
                echo DLNA_BINDIP;
                $ip_parts = explode( '.', DLNA_BINDIP );
                if( count( $ip_parts ) == 4 ){
                    echo ' <span style="color:#888;font-size:12px;">(允许: ' . $ip_parts[0] . '.' . $ip_parts[1] . '.' . $ip_parts[2] . '.0/24)</span>';
                }
            }
            ?>
        </td>
    </tr>
    <tr>
        <td class='label'>编码模式</td>
        <td class='value'>
            <?php 
            if( defined( 'DLNA_ENCODEMODE' ) ){
                echo DLNA_ENCODEMODE;
                echo ' <span style="color:#888;font-size:12px;">(推荐: direct — 零CPU直出)</span>';
            }
            ?>
        </td>
    </tr>
    <tr>
        <td class='label'>DLNA 用户</td>
        <td class='value'>
            <?php 
            if( defined( 'DLNA_USERNAME' ) ){
                echo DLNA_USERNAME;
            }
            ?>
        </td>
    </tr>
    <tr>
        <td class='label'>UUID</td>
        <td class='value' style='font-size:13px;'>
            <?php 
            require_once( PPATH_CORE . DS . 'functions.dlna.php' );
            echo dlna_get_uuidStr();
            ?>
        </td>
    </tr>
</table>

<br />

<div class='tCenter'>
    <button class='btn-notify' onclick='dlna_notify();'>广播 DLNA 通知</button>
    <?php if( $daemon_running ): ?>
        <a href='<?php echo rtrim(DLNA_WEB_BASESERVER_HTTP, '/') . '/dlna/rootDesc.php'; ?>' target='_blank'>
            <button class='btn-notify'>查看设备描述</button>
        </a>
    <?php endif; ?>
</div>

<div class='dlna-help-box'>
    <h3>什么是 DLNA / UPnP？</h3>
    <p>
        DLNA 是一种媒体共享协议，允许同一局域网内的设备（电视、手机、游戏机等）
        自动发现并播放您服务器上的媒体文件。
    </p>
    
    <h3>支持的客户端</h3>
    <ul>
        <li><strong>NPlayer</strong> (iOS/Android) — 推荐，兼容性最佳</li>
        <li><strong>OPlayer</strong> (iOS) — 轻量播放器，支持 DLNA 浏览</li>
        <li><strong>VLC for Mobile</strong> (iOS/Android)</li>
        <li><strong>Infuse</strong> (iOS/tvOS/Mac)</li>
        <li><strong>Kodi</strong> (所有平台)</li>
        <li>Windows 资源管理器网络栏</li>
    </ul>
    
    <h3>使用说明</h3>
    <ul>
        <li>确保客户端设备和服务器在 <strong>同一局域网</strong> 下</li>
        <li>在 DLNA 客户端中搜索 "<strong>PHPMediaServer</strong>"</li>
        <li>内容按类型分类浏览（喜剧、动作、科幻...）</li>
        <li>播放模式为 <strong>direct</strong>（直连，零CPU占用），如有兼容问题可修改 config.php 中的 DLNA_ENCODEMODE</li>
        <li>SSDP 守护进程需保持运行才能被客户端发现</li>
    </ul>
    
    <h3>守护进程管理</h3>
    <p>
        SSDP 守护进程负责响应局域网内的设备发现请求。
    </p>
    <ul>
        <li>启动: <code>bash dlna/ssdpd.sh start</code></li>
        <li>停止: <code>bash dlna/ssdpd.sh stop</code></li>
        <li>状态: <code>bash dlna/ssdpd.sh status</code></li>
        <li>日志: <code>bash dlna/ssdpd.sh logs</code></li>
        <li>开机自启: <code>sudo systemctl enable phpmediaserver-ssdpd</code> (已设置，如需关闭请运行 <code>sudo systemctl disable phpmediaserver-ssdpd</code>)</li>
    </ul>
</div>

<br />
<?php
?>
