<?php

	defined( 'ACCESS' ) or die( 'HTTP/1.0 401 Unauthorized.<br />' );
	
	//action
	//idmedia
	//timeplayed = seconds from start
	//timetotal = seconds total
	
	$HTMLRESULT = '';
	if( array_key_exists( 'idmedia', $G_DATA ) ){
        $IDMEDIA = $G_DATA[ 'idmedia' ];
	}else{
        $IDMEDIA = '';
	}
	
	if( $IDMEDIA > 0
	&& ( $mi = sqlite_media_getdata( $IDMEDIA ) ) != FALSE 
	&& is_array( $mi )
	&& count( $mi ) > 0
	){
        $IDMEDIAINFO = $mi[ 0 ][ 'idmediainfo' ];
        $TIME = 0;
        if( array_key_exists( 'timeplayed', $G_DATA ) 
        && is_numeric( $G_DATA[ 'timeplayed' ] )
        ){
            $TIME = (int)$G_DATA[ 'timeplayed' ];
        }
        $TIMETOTAL = 0;
        if( array_key_exists( 'timetotal', $G_DATA ) 
        && is_numeric( $G_DATA[ 'timetotal' ] )
        ){
            $TIMETOTAL = (int)$G_DATA[ 'timetotal' ];
        }
        if( $TIMETOTAL == 0 ){
            $TIMETOTAL = ffmpeg_file_info_lenght_seconds( $mi[ 0 ][ 'file' ] );
        }
        sqlite_played_update( $IDMEDIA, $TIME, $TIMETOTAL );
        
        // ============================================================
        // HLS 退出清理：杀掉对应 FFmpeg 进程并删除缓存
        // ============================================================
        $hls_base = PPATH_CACHE . DS . 'hls' . DS . $IDMEDIA;
        $hls_pid = $hls_base . '/.hls_pid';
        $hls_global_state = PPATH_CACHE . DS . 'hls' . DS . '.hls_current';
        
        if (file_exists($hls_pid)) {
            $old_pid = intval(trim(file_get_contents($hls_pid)));
            if ($old_pid > 0) {
                exec("kill $old_pid 2>/dev/null");
            }
        }
        // 清理 HLS 缓存目录
        if (is_dir($hls_base)) {
            exec("rm -rf " . escapeshellarg($hls_base) . " 2>/dev/null");
        }
        // 如果全局状态指向当前媒体，也清理全局状态
        if (file_exists($hls_global_state)) {
            $global_state = json_decode(file_get_contents($hls_global_state), true);
            if (is_array($global_state)
                && isset($global_state['idmedia'])
                && $global_state['idmedia'] == $IDMEDIA
            ) {
                @unlink($hls_global_state);
            }
        }
        
        // ============================================================
        // REMUX 退出清理：杀掉对应 FFmpeg 进程（但不删除已完成的缓存）
        // 用户通过"删除缓存"按钮手动删除 remux 缓存，不在退出时自动删除
        // ============================================================
        $remux_base = PPATH_CACHE . DS . 'remux' . DS . $IDMEDIA;
        $remux_pid = $remux_base . '/.remux_pid';
        $remux_global_state = PPATH_CACHE . DS . 'remux' . DS . '.remux_current';
        
        if (file_exists($remux_pid)) {
            $old_pid = intval(trim(file_get_contents($remux_pid)));
            if ($old_pid > 0) {
                exec("kill $old_pid 2>/dev/null");
            }
            // 删除 pid 文件（但保留缓存文件和目录）
            @unlink($remux_pid);
        }
        // 不删除 remux 缓存目录（手动管理）
        // 如果全局状态指向当前媒体，也清理全局状态
        if (file_exists($remux_global_state)) {
            $global_state = json_decode(file_get_contents($remux_global_state), true);
            if (is_array($global_state)
                && isset($global_state['idmedia'])
                && $global_state['idmedia'] == $IDMEDIA
            ) {
                @unlink($remux_global_state);
            }
        }
        
        echo get_msg( 'DEF_ELEMENTUPDATED' ) . $TIME;
	}else{
        echo get_msg( 'DEF_NOTEXIST' );
	}
?>

