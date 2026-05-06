<?php
defined( 'ACCESS' ) or die( 'HTTP/1.0 401 Unauthorized.<br />' );

// ============================================================
// Remux 缓存手动删除 API
// 用户点击"删除缓存"按钮时调用，仅删除指定媒体的 remux 缓存
// 返回 JSON: { success, message }
// ============================================================

$IDMEDIA = 0;
if( array_key_exists( 'idmedia', $G_DATA ) ){
    $IDMEDIA = intval( $G_DATA[ 'idmedia' ] );
}

header('Content-Type: application/json; charset=utf-8');

if( $IDMEDIA <= 0 ){
    echo json_encode( array(
        'success' => false,
        'message' => '参数错误：无效的 idmedia'
    ));
    exit();
}

$remux_base = PPATH_CACHE . DS . 'remux' . DS . $IDMEDIA;
$remux_pid_file = $remux_base . '/.remux_pid';

// 先杀掉 FFmpeg 进程（如果有）
if( file_exists( $remux_pid_file ) ){
    $old_pid = intval( trim( file_get_contents( $remux_pid_file ) ) );
    if( $old_pid > 0 ){
        $pid_check = trim( shell_exec( "ps -p $old_pid -o pid= 2>/dev/null" ) );
        if( $pid_check == $old_pid ){
            exec( "kill $old_pid 2>/dev/null" );
        }
    }
}

// 删除 remux 缓存目录
if( is_dir( $remux_base ) ){
    exec( "rm -rf " . escapeshellarg( $remux_base ) . " 2>/dev/null" );
    $remux_debug = PPATH_CACHE . '/remux_debug.txt';
    @file_put_contents( $remux_debug, "REMUX DELETE: deleted cache for idmedia=$IDMEDIA\n", FILE_APPEND );
    echo json_encode( array(
        'success' => true,
        'message' => '缓存已删除'
    ));
} else {
    echo json_encode( array(
        'success' => false,
        'message' => '缓存不存在'
    ));
}

// 清理全局状态文件
$remux_global_state = PPATH_CACHE . DS . 'remux' . DS . '.remux_current';
if( file_exists( $remux_global_state ) ){
    $gs = json_decode( file_get_contents( $remux_global_state ), true );
    if( is_array( $gs ) && isset( $gs['idmedia'] ) && $gs['idmedia'] == $IDMEDIA ){
        @unlink( $remux_global_state );
    }
}

exit();
