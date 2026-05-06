<?php
defined( 'ACCESS' ) or die( 'HTTP/1.0 401 Unauthorized.<br />' );

// ============================================================
// Remux 预缓存状态检查 API
// 返回 JSON: { status, done, pid, file_size, started, elpased }
// ============================================================

$IDMEDIA = 0;
if( array_key_exists( 'idmedia', $G_DATA ) ){
    $IDMEDIA = intval( $G_DATA[ 'idmedia' ] );
}

if( $IDMEDIA <= 0 ){
    header('Content-Type: application/json');
    echo json_encode( array( 'error' => 'invalid idmedia' ) );
    exit();
}

$remux_base = PPATH_CACHE . DS . 'remux' . DS . $IDMEDIA;
$remux_file = $remux_base . '/stream.mp4';
$remux_done = $remux_base . '/.remux_done';
$remux_pid  = $remux_base . '/.remux_pid';

$status = array(
    'done'    => false,
    'status'  => 'idle',
    'pid'     => 0,
    'started' => 0,
    'elapsed' => 0,
    'size'    => 0,
    'size_fmt' => '',
);

// 检测完成标记
if( file_exists( $remux_done ) && file_exists( $remux_file ) ){
    $status['done']   = true;
    $status['status'] = 'done';
    $status['size']   = filesize( $remux_file );
    $status['size_fmt'] = formatSizeUnits( $status['size'] );
    
    // 读取全局状态获取开始时间
    $remux_global_state = PPATH_CACHE . DS . 'remux' . DS . '.remux_current';
    if( file_exists( $remux_global_state ) ){
        $gs = json_decode( file_get_contents( $remux_global_state ), true );
        if( is_array( $gs ) && isset( $gs['started'] ) ){
            $status['started'] = $gs['started'];
            $status['elapsed'] = $status['started'] > 0 ? (time() - $status['started']) : 0;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode( $status );
    exit();
}

// 检测 FFmpeg 是否正在运行
$pid = 0;
if( file_exists( $remux_pid ) ){
    $old_pid = intval( trim( file_get_contents( $remux_pid ) ) );
    if( $old_pid > 0 ){
        $pid_check = trim( shell_exec( "ps -p $old_pid -o pid= 2>/dev/null" ) );
        if( $pid_check == $old_pid ){
            $pid = $old_pid;
        }
    }
}

if( $pid > 0 ){
    $status['status'] = 'processing';
    $status['pid']    = $pid;
    
    // 检查输出文件大小
    if( file_exists( $remux_file ) ){
        $status['size'] = filesize( $remux_file );
        $status['size_fmt'] = formatSizeUnits( $status['size'] );
    }
    
    // 读取全局状态获取开始时间
    $remux_global_state = PPATH_CACHE . DS . 'remux' . DS . '.remux_current';
    if( file_exists( $remux_global_state ) ){
        $gs = json_decode( file_get_contents( $remux_global_state ), true );
        if( is_array( $gs ) && isset( $gs['started'] ) ){
            $status['started'] = $gs['started'];
            $status['elapsed'] = time() - $status['started'];
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode( $status );
    exit();
}

// 检查是否有输出文件但没有完成标记（FFmpeg 被杀了）
if( file_exists( $remux_file ) && !file_exists( $remux_done ) ){
    $status['status'] = 'stale';
    $status['size']   = filesize( $remux_file );
    $status['size_fmt'] = formatSizeUnits( $status['size'] );
    
    header('Content-Type: application/json');
    echo json_encode( $status );
    exit();
}

// 没有任何活动
header('Content-Type: application/json');
echo json_encode( $status );
exit();
