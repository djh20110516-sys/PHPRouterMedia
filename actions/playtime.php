<?php

	defined( 'ACCESS' ) or die( 'HTTP/1.0 401 Unauthorized.<br />' );
	
	// Define paths (same as index.php)
	if( !defined( 'PPATH_BASE' ) ){
		define( 'PPATH_BASE', dirname( dirname( __FILE__ ) ) );
	}
	if( !defined( 'PPATH_CACHE' ) ){
		define( 'PPATH_CACHE', PPATH_BASE . DS . 'cache' );
	}
	if( !defined( 'DS' ) ){
		define( 'DS', DIRECTORY_SEPARATOR );
	}
	
	set_time_limit(0);
	
	//Safari compatibility: handle Range requests for streaming
	//Safari sends Range: bytes=0-1 to verify server supports byte-range requests
	//If server doesn't respond with 206, Safari refuses to play the video
	//After the probe, Safari may also send Range: bytes=0- for the actual content
	//For passthru() streams, we can't seek to arbitrary byte positions,
	//so we treat all Range requests as "start from 0" and respond with 206
	//using the source file size as an estimated Content-Length
	//Returns: 'probe' = probe handled (already exited), 'range' = Range content request (206 headers sent), FALSE = not a Range request
	function handleSafariRangeProbe($source_file, $content_type = 'video/mp4'){
		if( !isset($_SERVER['HTTP_RANGE']) ) return FALSE;
		if( !preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m) ) return FALSE;
		
		$start = intval($m[1]);
		$end = isset($m[2]) && $m[2] !== '' ? intval($m[2]) : -1;
		
		$fsize = file_exists($source_file) ? filesize($source_file) : 0;
		if( $fsize <= 0 ) $fsize = 999999999;
		
		if( function_exists('apache_setenv') ) @apache_setenv('no-gzip', 1);
		@ini_set('zlib.output_compression', 'Off');
		while( ob_get_level() ) ob_end_clean();
		
		//Safari probe: Range: bytes=0-1 → return 2 bytes and exit
		if( $start === 0 && $end === 1 ){
			header('HTTP/1.1 206 Partial Content');
			header('Content-Range: bytes 0-1/' . $fsize);
			header('Content-Length: 2');
			header('Content-Type: ' . $content_type);
			header('Accept-Ranges: bytes');
			header('Cache-Control: no-cache');
			echo "\x00\x00";
			exit();
		}
		
		//Safari content request with Range (e.g. bytes=0-):
		//We can't seek in a passthru stream, so for start=0 requests,
		//send 206 headers with estimated Content-Length, then fall through
		//to passthru() which will stream the full content from the beginning.
		if( $start === 0 ){
			header('HTTP/1.1 206 Partial Content');
			header('Content-Range: bytes 0-' . ($fsize - 1) . '/' . $fsize);
			header('Content-Length: ' . $fsize);
			return 'range';
		}
		
		return FALSE;
}

// ============================================================
// 重写 HLS m3u8 文件中的分片路径为绝对 URL
// Safari 需要绝对路径才能正确请求 .ts 分片
// 例如: segment000.ts → /cache/hls/52/segment000.ts
// ============================================================
function rewriteHlsM3u8($m3u8_file, $idmedia) {
if (!file_exists($m3u8_file)) {
return "#EXTM3U\n# HLS stream not ready\n";
}
$content = file_get_contents($m3u8_file);
if ($content === false || strlen($content) == 0) {
return "#EXTM3U\n# HLS stream not ready\n";
}
// 替换所有 .ts 分片路径为绝对 URL
// 匹配行首的 segmentXXX.ts 替换为 /cache/hls/{idmedia}/segmentXXX.ts
$hls_url_base = '/cache/hls/' . $idmedia . '/';
$content = preg_replace('/^(segment\d+\.ts)/m', $hls_url_base . '$1', $content);
return $content;
}

//action
	//idmedia= file avi
	//idmediainfo
	//timeplayed = seconds from start
	//timetotal 
	//quality = quality sd|hd
	//audiotrack = audio track list to ffmpeg
	//subtrack = sub track number
	//bitrate = bitrate 1500
	//acodec = audiocodec
	
	$HTMLRESULT = '';
	if( array_key_exists( 'idmedia', $G_DATA ) ){
        $IDMEDIA = $G_DATA[ 'idmedia' ];
	}else{
        $IDMEDIA = '';
	}
	
	if( array_key_exists( 'idmediainfo', $G_DATA ) ){
        $IDMEDIAINFO = $G_DATA[ 'idmediainfo' ];
	}else{
        $IDMEDIAINFO = '';
	}
	
	if( $IDMEDIA > 0
	&& ( $mi = sqlite_media_getdata( $IDMEDIA ) ) != FALSE 
	&& is_array( $mi )
	&& count( $mi ) > 0
	&& file_exists( $mi[ 0 ][ 'file' ] )
	&& getFileMimeTypeVideo( $mi[ 0 ][ 'file' ] )
	){
        $FMEDIA = $mi[ 0 ][ 'file' ];
        $IDMEDIAINFO = $mi[ 0 ][ 'idmediainfo' ];
	}elseif( $IDMEDIAINFO > 0
	&& ( $mi = sqlite_media_getdata_mediainfo( $IDMEDIAINFO ) ) != FALSE 
	&& is_array( $mi )
	&& count( $mi ) > 0
	&& file_exists( $mi[ 0 ][ 'file' ] )
	&& getFileMimeTypeVideo( $mi[ 0 ][ 'file' ] )
	){
        $FMEDIA = $mi[ 0 ][ 'file' ];
        $IDMEDIA = $mi[ 0 ][ 'idmedia' ];
	}else{
        $FMEDIA = FALSE;
	}
	
	if( $FMEDIA == FALSE ){
        echo get_msg( 'DEF_NOTEXIST' );
	}elseif( !file_exists( $FMEDIA ) ){
        echo get_msg( 'DEF_FILENOTEXIST' );
	}else{
        //EXTRA VARS
        $title = '';
        $ACTIONINFO = '';
        $filename = $FMEDIA;
        $TIMEBLOCK = O_VIDEO_TIMEBLOCK;
        $G_MODE = 'webm';
        if( array_key_exists( 'mode', $G_DATA ) 
        && strlen( $G_DATA[ 'mode' ] ) > 0
        ){
            $G_MODE = $G_DATA[ 'mode' ];
        }
        $G_TIME = 0;
        if( array_key_exists( 'timeplayed', $G_DATA ) 
        && is_numeric( $G_DATA[ 'timeplayed' ] )
        && (int)$G_DATA[ 'timeplayed' ] > -1
        ){
            $G_TIME = (int)$G_DATA[ 'timeplayed' ];
        }elseif( array_key_exists( 'timeplayed', $G_DATA ) 
        && is_numeric( $G_DATA[ 'timeplayed' ] )
        && (int)$G_DATA[ 'timeplayed' ] == -1
        ){
            $G_TIME = sqlite_played_status( $IDMEDIA );
            $time = ffmpeg_file_info_lenght_seconds( $FMEDIA );
            if( $time > 0 
            && $G_TIME > ( $time - ( $time / 10 ) ) 
            ){
                $G_TIME = 0;
            }
        }
        $G_QUALITY = 'sd';
        if( array_key_exists( 'quality', $G_DATA ) 
        && $G_DATA[ 'quality' ] == 'hd'
        ){
            $G_QUALITY = 'hd';
        }
        $bitrate = 1500;
        if( array_key_exists( 'bitrate', $G_DATA ) 
        && is_numeric( $G_DATA[ 'bitrate' ] )
        ){
            $bitrate = (int)$G_DATA[ 'bitrate' ];
        }
        if( array_key_exists( 'audiotrack', $G_DATA ) 
        && is_numeric( $G_DATA[ 'audiotrack' ] )
        ){
            $audiotrack = (int)$G_DATA[ 'audiotrack' ];
        }else{
            $audiotrack = 1;
        }
        
        if( array_key_exists( 'subtrack', $G_DATA ) 
        && is_numeric( $G_DATA[ 'subtrack' ] )
        ){
            $subtrack = (int)$G_DATA[ 'subtrack' ];
        }else{
            $subtrack = -1;
        }
        
        if( !file_exists( $filename )
        ){
            $ACTIONINFO = get_msg( 'DEF_FILENOTEXIST' );
        }
        
        if( $ACTIONINFO != '' ){
            echo $ACTIONINFO;
        }else{
            
            $dir = $filename;
            
            //pregenerate $segment
            if( $G_TIME > 0 ){
                $extra_params = "-ss " . $G_TIME;
            }else{
                $extra_params = "";
                $G_TIME = 0;
            }
            
            //SET PLAYTIME
            if( !isset( $time ) ){
                $time = ffmpeg_file_info_lenght_seconds( $dir );
            }
            sqlite_played_replace( $IDMEDIA, $G_TIME, $time );
            
            //variable bitrate to max especified
            if( $G_QUALITY != 'hd' ){
                $G_FFMPEGLVL = '3.1';
                $minbitrate = O_VIDEO_SD_MINBRATE;
                $maxbitrate = O_VIDEO_SD_MAXBRATE;
                $QUALITY = '-vf scale=-2:' . O_VIDEO_SD_HEIGHT;
                $VIDEOHEIGHT = O_VIDEO_SD_HEIGHT;
                //$encoder = 'libvpx';
            }else{
                $G_FFMPEGLVL = '4.0';
                $minbitrate = O_VIDEO_HD_MINBRATE;
                $maxbitrate = O_VIDEO_HD_MAXBRATE;
                $QUALITY = '-vf scale=-2:' . O_VIDEO_HD_HEIGHT;
                //$encoder = 'libvpx-vp9';
                $VIDEOHEIGHT = O_VIDEO_HD_HEIGHT;
            }
            //$QUALITY = '';
            
            //audio +more vol
            $audiovol = O_VIDEO_EXTRA_VOLUME;
            
            //audio track (change 1 to num video tracks)
            $audiotrack = ' -map 0:0 -map 0:' . ( $audiotrack ) . ' ';
            
            //subs track (testing)
            if( is_numeric( $subtrack )
            && $subtrack > -1 
            ){
                //TESTING (check if video is resized/scaled with filter_complex)
                $subtrack = ' -filter_complex "[0:v][0:s:' . $subtrack . ']overlay=(main_w-overlay_w)/2:main_h-overlay_h,scale=trunc(iw/2)*2:trunc(ih/2)*2,scale=-2:' . $VIDEOHEIGHT . '"';
                //$subtrack = ' -vf subtitles="' . escapeshellarg( $dir ) . '":si=' . $subtrack . ' ';
                //$subtrack = ' -filter_complex "[0:v][0:s:0]overlay[v]" -map [v] ';
                //$subtrack = ' -vf subtitles=' . escapeshellarg( $dir ) . ' ';
                //$subtrack = ' -vf "[0:0][0:' . $subtrack . ']overlay[0]" -map [0] ';
                //$subtrack = ' -copyts -vf "subtitles=' . escapeshellarg( $dir ) . ',setpts=PTS-STARTPTS" -sn ';
                //$subtrack = '';
                
                //quality and scale in filter_complex
                $QUALITY = '';
                $SCALE = '';
                
                /*
                //with subs overlay cant change -vf size
                $width = round( ( O_VIDEO_SD_HEIGHT * 16 ) / 9 );
                //round to par
                if( ( $width % 2 ) == 1 ) $width++;
                //scale without video filters (need to add video filter in same filter_complex)
                //$SCALE = " -s " . $width . "x" . O_VIDEO_SD_HEIGHT;
                $SCALE = "";
                */
            }else{
                $subtrack = '';
                //basic Scale (no use in hardsubs)
                $SCALE = " -vf 'scale=trunc(iw/2)*2:trunc(ih/2)*2' ";
            }
            
            switch( $G_MODE ){
                //TEST IOS
                case 'mp4ios':
                    handleSafariRangeProbe($dir, 'video/mp4');
                    //testing
                    //$encoder_outformat = 'mpegts';
                    $encoder_outformat = 'mp4';
                    //$encoder = 'h264';
                    $encoder = 'libx264';
                    $AUDIOCODEC = 'aac';
                    //$AUDIOCODEC = 'mp3';
                    //$AUDIOCODEC = 'opus';
                    //EXTRA
                    $G_FFMPEGLVL = '3.0';
                    $minbitrate = '1M';
                    $maxbitrate = '1M';
                    $QUALITY = '';
                    //$cmd = O_FFMPEG . " -nostdin -re " . $extra_params . " -i " . escapeshellarg( $dir ) . " " . $subtrack . " " . $audiotrack . " -c:v " . $encoder . " -quality realtime -b:v " . $minbitrate . " -maxrate " . $minbitrate . " -movflags +faststart -bufsize 1000k -g 74 -strict experimental -pix_fmt yuv420p " . $SCALE . " -aspect 16:9 -level " . $G_FFMPEGLVL . " -profile:v baseline -level 3.0 -preset ultrafast -tune zerolatency -af 'volume=" . $audiovol . "' -c:a " . $AUDIOCODEC . " -ab 128k -f " . $encoder_outformat . " -movflags frag_keyframe+empty_moov - ";
                    $cmd = O_FFMPEG . " -nostdin -re " . $extra_params . " -i " . escapeshellarg( $dir ) . " " . $subtrack . " " . $audiotrack . '-strict experimental -pix_fmt yuv420p -profile:v baseline -level 3.0 -acodec aac -ar 44100 -ac 2 -ab 128k -f ' . $encoder_outformat . " -movflags frag_keyframe+empty_moov - ";
                    
                    header('Content-type: video/mp4');
                break;
                case 'hls':
                    // ============================================================
                    // 流式 HLS (HTTP Live Streaming) for iPad Safari
                    // ============================================================
                    // 设计原则:
                    // 1. 后台启动 FFmpeg，不阻塞 PHP 响应
                    // 2. 等待第一个分片生成后立即返回 m3u8
                    // 3. 只保留最近 3 个分片，自动删除旧分片
                    // 4. 使用锁文件防止重复启动 FFmpeg
                    // 5. 切换到不同媒体时自动清理旧 FFmpeg 进程
                    // ============================================================
                    // ============================================================
                    // 关键修复: 清除 index.php 预设的 Content-Type: text/html
                    // Safari 的 HLS 解析器必须收到 application/vnd.apple.mpegurl
                    // 否则会拒绝流并显示加载转圈
                    // ============================================================
                    header_remove('Content-Type');
                    while (ob_get_level()) ob_end_clean();
                    
                    error_log("HLS MODE TRIGGERED: idmedia=$IDMEDIA, dir=$dir");
                    
                    $hls_base = PPATH_CACHE . DS . 'hls' . DS . $IDMEDIA;
                    $hls_stream = $hls_base . '/stream.m3u8';
                    $hls_segment = $hls_base . '/segment%03d.ts';
                    $hls_lock = $hls_base . '/.hls_lock';
                    $hls_pid = $hls_base . '/.hls_pid';
                    
                    // 全局 HLS 状态文件（记录当前正在处理的 media ID）
                    $hls_global_state = PPATH_CACHE . DS . 'hls' . DS . '.hls_current';
                    
                    // ============================================================
                    // 跨媒体清理 + 服务端保活检测
                    // 1. 不同媒体 → 杀掉旧 FFmpeg
                    // 2. 同一媒体但超过 10 分钟无访问 → 杀掉旧 FFmpeg（客户端已失联）
                    // ============================================================
                    if (file_exists($hls_global_state)) {
                        $global_state = json_decode(file_get_contents($hls_global_state), true);
                        $should_kill = false;
                        $kill_reason = '';
                        if (is_array($global_state)
                            && isset($global_state['idmedia'])
                            && isset($global_state['pid'])
                            && $global_state['pid'] > 0
                        ) {
                            $old_media_id = $global_state['idmedia'];
                            $old_pid = intval($global_state['pid']);
                            // 检查 1: 不同媒体
                            if ($old_media_id != $IDMEDIA) {
                                $should_kill = true;
                                $kill_reason = "switched to media=$IDMEDIA";
                            }
                            // 检查 2: 同一媒体但超过 10 分钟无新请求（客户端已失联）
                            if (!$should_kill && $old_media_id == $IDMEDIA
                                && isset($global_state['last_access'])
                                && (time() - intval($global_state['last_access'])) > 600
                            ) {
                                $should_kill = true;
                                $kill_reason = "stale (no access for >10min)";
                            }
                            if ($should_kill) {
                                $pid_check = trim(shell_exec("ps -p $old_pid -o pid= 2>/dev/null"));
                                if ($pid_check == $old_pid) {
                                    error_log("HLS: Killing old FFmpeg PID=$old_pid for media=$old_media_id ($kill_reason)");
                                    exec("kill $old_pid 2>/dev/null");
                                }
                                // 清理旧媒体的 HLS 目录
                                $old_hls_base = PPATH_CACHE . DS . 'hls' . DS . $old_media_id;
                                if (is_dir($old_hls_base)) {
                                    exec("rm -rf " . escapeshellarg($old_hls_base) . " 2>/dev/null");
                                }
                            }
                        }
                    }
                    
                    // 确保目录存在
                    if (!is_dir($hls_base)) {
                        @mkdir($hls_base, 0755, true);
                    }
                    
                    // ============================================================
                    // 更新全局状态 last_access（保活检测用）
                    // ============================================================
                    if (file_exists($hls_global_state)) {
                        $gs = json_decode(file_get_contents($hls_global_state), true);
                        if (is_array($gs) && isset($gs['idmedia']) && $gs['idmedia'] == $IDMEDIA) {
                            $gs['last_access'] = time();
                            file_put_contents($hls_global_state, json_encode($gs));
                        }
                    }
                    
                    // ============================================================
                    // 检查是否已有正在运行的 FFmpeg 进程（同一媒体）
                    // ============================================================
                    $ffmpeg_running = false;
                    $ffmpeg_pid = 0;
                    if (file_exists($hls_pid)) {
                        $old_pid = intval(trim(file_get_contents($hls_pid)));
                        if ($old_pid > 0) {
                            // 检查进程是否存在
                            $pid_check = trim(shell_exec("ps -p $old_pid -o pid= 2>/dev/null"));
                            if ($pid_check == $old_pid) {
                                $ffmpeg_running = true;
                                $ffmpeg_pid = $old_pid;
                            }
                        }
                    }
                    
                    // ============================================================
                    // 如果 m3u8 已存在且 FFmpeg 正在运行，直接返回 m3u8
                    // ============================================================
                    if (file_exists($hls_stream) && $ffmpeg_running) {
                        error_log("HLS: m3u8 exists and FFmpeg running (PID=$ffmpeg_pid), returning m3u8");
                        header('Content-Type: application/vnd.apple.mpegurl');
                        header('Cache-Control: no-cache');
                        header('Access-Control-Allow-Origin: *');
                        echo rewriteHlsM3u8($hls_stream, $IDMEDIA);
                        exit();
                    }
                    
                    // ============================================================
                    // 如果 m3u8 存在但 FFmpeg 已停止
                    // 检查是否有 ENDLIST（FFmpeg 正常完成）→ 返回完整 m3u8
                    // 没有 ENDLIST → 旧缓存（被杀死或崩溃），删除重启
                    // ============================================================
                    if (file_exists($hls_stream) && !$ffmpeg_running) {
                        $m3u8_check = file_get_contents($hls_stream);
                        $has_endlist = (strpos($m3u8_check, '#EXT-X-ENDLIST') !== false);
                        if ($has_endlist) {
                            error_log("HLS: m3u8 exists with ENDLIST, returning completed m3u8");
                            header('Content-Type: application/vnd.apple.mpegurl');
                            header('Cache-Control: no-cache');
                            header('Access-Control-Allow-Origin: *');
                            echo rewriteHlsM3u8($hls_stream, $IDMEDIA);
                            exit();
                        }
                        // 没有 ENDLIST → 旧缓存，删除并重启
                        error_log("HLS: stale m3u8 (no ENDLIST), deleting and restarting FFmpeg");
                        exec("rm -rf " . escapeshellarg($hls_base) . " 2>/dev/null");
                        @mkdir($hls_base, 0755, true);
                        $ffmpeg_running = false;
                        $ffmpeg_pid = 0;
                    }
                    
                    // ============================================================
                    // 关键修复：如果 FFmpeg 正在运行但 m3u8 还没生成（还在等待第一个分片），
                    // 不要启动新的 FFmpeg！等待 m3u8 生成或返回准备中消息。
                    // 这是防止 CPU 拉爆的关键：避免竞态条件下重复启动 FFmpeg
                    // ============================================================
                    if ($ffmpeg_running && !file_exists($hls_stream)) {
                        error_log("HLS: FFmpeg already running (PID=$ffmpeg_pid) but m3u8 not ready yet, waiting...");
                        // 等待 m3u8 生成（最多 30 秒）
                        $wait_time = 0;
                        $max_wait = 30;
                        $wait_interval = 500000;
                        while ($wait_time < $max_wait) {
                            if (file_exists($hls_stream) && filesize($hls_stream) > 50) {
                                $m3u8_content = file_get_contents($hls_stream);
                                if (strpos($m3u8_content, '.ts') !== false) {
                                    error_log("HLS: m3u8 ready after waiting {$wait_time}s (existing FFmpeg PID=$ffmpeg_pid)");
                                    header('Content-Type: application/vnd.apple.mpegurl');
                                    header('Cache-Control: no-cache');
                                    header('Access-Control-Allow-Origin: *');
                                    echo rewriteHlsM3u8($hls_stream, $IDMEDIA);
                                    exit();
                                }
                            }
                            usleep($wait_interval);
                            $wait_time += 0.5;
                        }
                        // 超时，返回准备中消息
                        error_log("HLS: Timeout waiting for m3u8 from existing FFmpeg PID=$ffmpeg_pid");
                        header('Content-Type: text/plain; charset=utf-8');
                        echo "HLS 流正在准备中，请稍后刷新...\n";
                        echo "等待时间: {$wait_time}秒\n";
                        exit();
                    }
                    
                    // ============================================================
                    // 检测视频 codec，决定使用直通还是转码
                    // ============================================================
                    $hwcodec = trim(shell_exec(O_FFPROBE . " -v error -select_streams v:0 -show_entries stream=codec_name -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($dir)));
                    
                    // ============================================================
                    // 关键修复: 使用 -map 只选择视频和音频流，排除字幕流
                    // MKV 文件经常有几十条字幕流，不排除的话 FFmpeg 会全部转成 WebVTT，
                    // 导致:
                    //   1. FFmpeg 启动初始化极慢（解析每个字幕流）
                    //   2. 首分片生成延迟 >30 秒 → PHP 超时 → Safari 转圈
                    //   3. 产生大量无用的 .vtt 文件拖累 I/O
                    // ============================================================
                    $stream_map = "-map 0:v:0 -map 0:a:0";
                    
                    // 如果是 H.264，使用直通模式（零 CPU 消耗）
                    if ($hwcodec === 'h264' || $hwcodec === 'avc1') {
                        $video_cmd = "-c:v copy";
                        error_log("HLS: H.264 detected, using stream copy (zero CPU)");
                    } else {
                        // 其他格式需要转码为 H.264
                        // HEVC 使用 hevc_rkmpp 硬件解码加速（仅在 RK3528A 等 Rockchip 平台有效）
                        if ($hwcodec === 'hevc') {
                            $video_cmd = "-c:v hevc_rkmpp";
                            error_log("HLS: HEVC detected, using hw decode hevc_rkmpp + sw encode libx264");
                        } else {
                            $video_cmd = "";
                            error_log("HLS: non-H.264 detected ($hwcodec), using sw decode + sw encode libx264");
                        }
                        $video_cmd .= " -c:v libx264 -preset superfast -b:v " . $maxbitrate . " -maxrate " . $maxbitrate . " -bufsize 8000k -g 50";
                    }
                    
                    // ============================================================
                    // 后台启动 FFmpeg（不阻塞 PHP 响应）
                    // ============================================================
                    // 流式 HLS 参数说明:
                    //   -hls_time 4: 每个分片 4 秒（快速启动）
                    //   -hls_playlist_type event: 事件模式，保留所有分片，无滑动窗口
                    //     修复 live 模式导致 Safari 播放跳转的问题
                    //   -hls_list_size 0: 保留所有分片到播放列表（event 模式需要）
                    //   -hls_flags delete_segments: 配合 event 模式，退出后在 playstop 里清理
                    //   -hls_flags delete_segments: 自动删除旧分片
                    //   -hls_init_time 2: 第一个分片 2 秒（极速启动）
                    //   -hls_playlist_type event: 事件模式，m3u8 持续更新
                    //   -hls_segment_filename: 分片文件名模板
                    //   -loglevel warning: 减少日志输出
                    //   -sn: 排除所有字幕流（防止 67 个字幕流拖死 FFmpeg）
                    //   -max_muxing_queue_size 1024: 防止 muxing queue 溢出
                    // ============================================================
                    // 重要: HLS 模式下不使用 -ss 参数！
                    // HLS 协议原生支持 seek（Safari 通过 m3u8 播放列表处理）
                    // 使用 -ss 会导致:
                    //   1. 不同 timeplayed 值启动不同 FFmpeg 进程 → CPU 拉爆
                    //   2. m3u8 无法反映 seek 偏移 → Safari 播放混乱
                    // ============================================================
                    $cmd = O_FFMPEG . " -nostdin -fflags +genpts" .
                           " -i " . escapeshellarg($dir) .
                           " " . $stream_map .
                           " -sn" .
                           " " . $video_cmd .
                           " -ac 2 -c:a aac -b:a 128k" .
                           " -f hls" .
                           " -hls_time 4" .
                           " -hls_list_size 0" .
                           " -hls_flags delete_segments" .
                           " -hls_playlist_type event" .
                           " -hls_init_time 2" .
                           " -start_number 0" .
                           " -max_muxing_queue_size 1024" .
                           " -loglevel warning" .
                           " -hls_segment_filename " . escapeshellarg($hls_segment) .
                           " " . escapeshellarg($hls_stream);
                    
                    // 后台执行 FFmpeg
                    // 使用 nohup 确保进程在 PHP 结束后继续运行
                    // 输出重定向到 /dev/null 避免阻塞
                    $bg_cmd = "nohup " . $cmd . " > " . escapeshellarg($hls_base . '/ffmpeg.log') . " 2>&1 &";
                    exec($bg_cmd, $bg_output, $bg_returncode);
                    
                    // ============================================================
                    // 修复: PID 检测竞态条件
                    // nohup 启动后进程可能还没起来，需要等待后重试
                    // ============================================================
                    $pid = 0;
                    $pid_retries = 10; // 最多重试 10 次（5 秒）
                    for ($i = 0; $i < $pid_retries; $i++) {
                        $pid_cmd = "ps aux | grep '" . addslashes($hls_stream) . "' | grep -v grep | awk '{print \$2}' | head -1";
                        $pid = trim(shell_exec($pid_cmd));
                        if (!empty($pid) && is_numeric($pid)) {
                            file_put_contents($hls_pid, $pid);
                            $now = time();
                            // 记录全局状态（用于跨媒体清理 + 保活检测）
                            $global_state = json_encode(array(
                                'idmedia' => $IDMEDIA,
                                'pid' => intval($pid),
                                'started' => $now,
                                'last_access' => $now
                            ));
                            file_put_contents($hls_global_state, $global_state);
                            error_log("HLS: FFmpeg started with PID=$pid for media=$IDMEDIA (retry=$i)");
                            break;
                        }
                        usleep(500000); // 等 0.5 秒再试
                    }
                    if (empty($pid) || !is_numeric($pid)) {
                        error_log("HLS: Failed to detect FFmpeg PID for media=$IDMEDIA");
                    }
                    
                    // ============================================================
                    // 等待第一个分片生成
                    // HEVC 转码可能较慢，因此加大超时到 60 秒
                    // ============================================================
                    $wait_time = 0;
                    $max_wait = 60; // 最多等待 60 秒（HEVC 转码慢）
                    $wait_interval = 500000; // 0.5 秒
                    
                    while ($wait_time < $max_wait) {
                        // 检查 m3u8 文件是否存在且非空
                        if (file_exists($hls_stream) && filesize($hls_stream) > 50) {
                            $m3u8_content = file_get_contents($hls_stream);
                            // 检查是否有至少一个分片
                            if (strpos($m3u8_content, '.ts') !== false) {
                                error_log("HLS: First segment ready after {$wait_time}s");
                                break;
                            }
                        }
                        // 检查 FFmpeg 是否还活着（如果挂了就不等了）
                        if (!empty($pid) && is_numeric($pid)) {
                            $alive = trim(shell_exec("ps -p $pid -o pid= 2>/dev/null"));
                            if ($alive != $pid) {
                                error_log("HLS: FFmpeg died after {$wait_time}s, aborting wait");
                                break;
                            }
                        }
                        usleep($wait_interval);
                        $wait_time += 0.5;
                    }
                    
                    // ============================================================
                    // 返回 m3u8 播放列表（路径已重写为绝对 URL）
                    // ============================================================
                    if (file_exists($hls_stream) && filesize($hls_stream) > 50) {
                        $m3u8_content = file_get_contents($hls_stream);
                        if (strpos($m3u8_content, '.ts') !== false) {
                            header('Content-Type: application/vnd.apple.mpegurl');
                            header('Cache-Control: no-cache');
                            header('Access-Control-Allow-Origin: *');
                            echo rewriteHlsM3u8($hls_stream, $IDMEDIA);
                            error_log("HLS: Returning m3u8 (waited {$wait_time}s)");
                            exit();
                        }
                    }
                    
                    // ============================================================
                    // 生成失败或超时 — 返回正确的 m3u8 格式让 Safari 重试
                    // 之前返回 text/plain 导致 Safari 永远不理解
                    // 现在返回一个空的 m3u8，Safari 会定期重播（retry）
                    // ============================================================
                    error_log("HLS: Timeout or failed after {$wait_time}s for media=$IDMEDIA");
                    header('Content-Type: application/vnd.apple.mpegurl');
                    header('Cache-Control: no-cache');
                    header('Access-Control-Allow-Origin: *');
                    header('Retry-After: 5');
                    echo "#EXTM3U\n";
                    echo "# HLS transcoding in progress, please retry\n";
                    echo "#EXT-X-TARGETDURATION:4\n";
                    echo "#EXT-X-ALLOW-CACHE:NO\n";
                    echo "#EXTINF:4,\n";
                    echo "# Waiting for first segment...\n";
                    exit();
                break;
                case 'hw':
                    //Safari probe: must respond 206 to Range: bytes=0-1
                    handleSafariRangeProbe($dir, 'video/mp4');
                    //rkmpp hardware accelerated decode + libx264 encode to MP4
                    //Detect video codec and use matching rkmpp hardware decoder
                    $hwcodec = trim(shell_exec(O_FFPROBE . " -v error -select_streams v:0 -show_entries stream=codec_name -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($dir)));
                    $hwdecoder = 'h264_rkmpp';
                    if ($hwcodec == 'hevc') $hwdecoder = 'hevc_rkmpp';
                    elseif ($hwcodec == 'vp8') $hwdecoder = 'vp8_rkmpp';
                    elseif ($hwcodec == 'vp9') $hwdecoder = 'vp9_rkmpp';
                    //Skip subtitle burn-in for hw mode (filter_complex incompatible with hw frames)
                    $subtrack = '';
                    //hwdownload: DRM_PRIME → NV12 system memory
                    //libx264 accepts NV12 natively, no yuv420p conversion needed
                    //Removed unnecessary scale filter - only wastes CPU on already-even dimensions
                    $hw_vf = " -vf 'hwdownload,format=nv12' ";
                    $encoder_outformat = 'mp4';
                    $encoder = 'libx264';
                    $AUDIOCODEC = 'aac';
                    //Quality optimization (Plan A+):
                    //  -superfast: better motion estimation than ultrafast, eliminates macroblocking
                    //  -No zerolatency: enables rc_lookahead for smart bitrate allocation
                    //  -g 250: fewer keyframes, more bits for actual picture content
                    //  -bufsize 8000k: 1s buffer for better VBV rate control
                    //  -No profile restriction: x264 auto-selects High+CABAC (saves ~15% bits vs baseline)
                    //no -re: let pipeline run at full speed for smoother frame delivery
                    //+genpts: ensure PTS timestamps for MP4 container compatibility
                    //+default_base_moof: required for iPad Safari fMP4 playback
                    $cmd = O_FFMPEG . " -nostdin -fflags +genpts -c:v " . $hwdecoder . " " . $extra_params . " -i " . escapeshellarg( $dir ) . " " . $audiotrack . " " . $hw_vf . " -c:v " . $encoder . " -b:v " . $maxbitrate . " -maxrate " . $maxbitrate . " -bufsize 8000k -g 250 -strict experimental -preset superfast -af 'volume=" . $audiovol . "' -c:a " . $AUDIOCODEC . " -ab 128k -f " . $encoder_outformat . " -movflags empty_moov+frag_keyframe+default_base_moof - ";
                    header('Content-type: video/mp4');
                    header('Accept-Ranges: bytes');
                    header('Cache-Control: no-cache');
                    //Force disable gzip for streaming
                    if( function_exists( 'apache_setenv' ) ) @apache_setenv('no-gzip', 1);
                    @ini_set('zlib.output_compression', 'Off');
                    while( ob_get_level() ) ob_end_clean();
                    sqlite_db_close();
                    if( $_SERVER['REQUEST_METHOD'] != 'HEAD' ){
                        if( ( $idplaying = sqlite_playing_insert( USERNAME, $IDMEDIA, FALSE, 'WEB-' . $G_QUALITY . '-' . $G_MODE, getmypid() ) ) != FALSE ){
                            $CHECKPLAYING = TRUE;
                            register_shutdown_function( function( $idplaying ){
                                    sqlite_playing_delete( $idplaying );
                                }, 
                            $idplaying );
                        }else{
                            $CHECKPLAYING = FALSE;
                            $idplaying = FALSE;
                        }
                        set_time_limit(0);
                        passthru( $cmd, $cmdok );
                        if( $CHECKPLAYING && $idplaying ){
                            sqlite_playing_delete( $idplaying );
                        }
                    }
                    exit();
                break;
                //TEST KODI
                case 'direct':
                    // ============================================================
                    // 如果 remux 预缓存已完成，直接提供缓存的 MP4 文件
                    // （原始文件为 MKV 时 iPad Safari 无法播放，缓存 MP4 则可）
                    // ============================================================
                    $remux_base = PPATH_CACHE . DS . 'remux' . DS . $IDMEDIA;
                    $remux_file = $remux_base . '/stream.mp4';
                    $remux_done = $remux_base . '/.remux_done';
                    $remux_debug = PPATH_CACHE . '/remux_debug.txt';
                    if( file_exists( $remux_done ) && file_exists( $remux_file ) ){
                        @file_put_contents($remux_debug, "DIRECT: remux cache hit, serving cached MP4 for idmedia=$IDMEDIA\n", FILE_APPEND);
                        goto serve_remux_file;
                    }
                    
                    //direct streaming with HTTP Range support for seeking
                    //Disable output compression for proper Range seeking
                    if( function_exists( 'apache_setenv' ) ) @apache_setenv( 'no-gzip', 1 );
                    @ini_set( 'zlib.output_compression', 'Off' );
                    while( ob_get_level() ) ob_end_clean();
                    sqlite_db_close();
                    $file = $dir;
                    $mime = getFileMimeType( $file );
                    $fsize = filesize( $file );
                    // Trick NPlayer URL Scheme into accepting non-MP4 formats:
                    // 1. Return video/mp4 MIME so NPlayer starts buffering
                    // 2. Add Content-Disposition: attachment so NPlayer's download manager
                    //    intercepts MKV and other non-native formats
                    $ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
                    if( $ext == 'mkv' || $ext == 'matroska' ){
                        $mime = 'video/mp4';
                    }
                    header( 'Content-type: ' . $mime );
                    header( 'Accept-Ranges: bytes' );
                    header( 'Cache-Control: no-cache' );
                    // Force NPlayer download manager to intercept MKV (and similar) formats
                    if( $ext == 'mkv' || $ext == 'matroska' ){
                        header( 'Content-Disposition: attachment; filename="' . basename( $file ) . '"' );
                    }
                    
                    if( isset( $_SERVER['HTTP_RANGE'] ) 
                    && preg_match( '/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches )
                    ){
                        $start = max( intval( $matches[1] ), 0 );
                        $end = $matches[2] !== '' ? intval( $matches[2] ) : $fsize - 1;
                        header( 'HTTP/1.1 206 Partial Content' );
                        header( "Content-Range: bytes $start-$end/$fsize" );
                        header( 'Content-Length: ' . ( $end - $start + 1 ) );
                        if( $_SERVER['REQUEST_METHOD'] != 'HEAD' ){
                            $fp = fopen( $file, 'rb' );
                            if( $fp ){
                                fseek( $fp, $start );
                                $remaining = $end - $start + 1;
                                while( $remaining > 0 && !feof( $fp ) && !connection_aborted() ){
                                    $readsize = min( 8192, $remaining );
                                    echo fread( $fp, $readsize );
                                    $remaining -= $readsize;
                                    flush();
                                }
                                fclose( $fp );
                            }
                        }
                    }else{
                        header( 'Content-Length: ' . $fsize );
                        if( $_SERVER['REQUEST_METHOD'] != 'HEAD' ){
                            $fp = fopen( $file, 'rb' );
                            if( $fp ){
                                while( !feof( $fp ) && !connection_aborted() ){
                                    echo fread( $fp, 8192 );
                                    flush();
                                }
                                fclose( $fp );
                            }
                        }
                    }
                    exit();
                break;
                case 'remux':
                    $remux_debug = PPATH_CACHE . '/remux_debug.txt';
                    @file_put_contents($remux_debug, "REMUX ENTERED: " . date('H:i:s') . " idmedia=$IDMEDIA method={$_SERVER['REQUEST_METHOD']} range=" . (isset($_SERVER['HTTP_RANGE'])?$_SERVER['HTTP_RANGE']:'NONE') . " ua=" . (isset($_SERVER['HTTP_USER_AGENT'])?substr($_SERVER['HTTP_USER_AGENT'],0,60):'N/A') . "\n", FILE_APPEND);
                    
                    // ============================================================
                    // Safari Range probe handling (bytes=0-1)
                    // Safari sends Range: bytes=0-1 to verify 206 support.
                    // We handle this manually instead of using handleSafariRangeProbe()
                    // because that function also handles bytes=0- content requests
                    // using the SOURCE file size, which is wrong for remux output.
                    // ============================================================
                    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
                        $start = intval($m[1]);
                        $end = isset($m[2]) && $m[2] !== '' ? intval($m[2]) : -1;
                        if ($start === 0 && $end === 1) {
                            @file_put_contents($remux_debug, "REMUX PROBE OK\n", FILE_APPEND);
                            if( function_exists('apache_setenv') ) @apache_setenv('no-gzip', 1);
                            @ini_set('zlib.output_compression', 'Off');
                            while( ob_get_level() ) ob_end_clean();
                            header('HTTP/1.1 206 Partial Content');
                            header('Content-Range: bytes 0-1/999999999');
                            header('Content-Length: 2');
                            header('Content-Type: video/mp4');
                            header('Accept-Ranges: bytes');
                            header('Cache-Control: no-cache');
                            echo "\x00\x00";
                            exit();
                        }
                    }
                    
                    // ============================================================
                    // remux 模式：后台 FFmpeg 预缓存 + 直连播放
                    // 使用 +faststart 生成标准 MP4，完成后通过 serve_remux_file 直连
                    // ============================================================
                    // 设计原则:
                    // 1. 后台启动 FFmpeg (nohup)，不阻塞 PHP 响应
                    // 2. 使用 +faststart 生成标准 MP4（Safari 兼容性最好）
                    //    - +faststart 将 moov atom 移到文件头部
                    //    - Safari 需要 moov 在文件头才能正确解析
                    // 3. 首次请求：启动 FFmpeg 后立即返回"准备中"消息
                    // 4. 后续请求：检查 .remux_done 标记，命中缓存后 serve_remux_file
                    // 5. 使用锁文件防止重复启动 FFmpeg
                    // 6. 切换到不同媒体时自动清理旧 FFmpeg 进程
                    // ============================================================
                    
                    $remux_base = PPATH_CACHE . DS . 'remux' . DS . $IDMEDIA;
                    $remux_file = $remux_base . '/stream.mp4';
                    $remux_pid = $remux_base . '/.remux_pid';
                    $remux_done = $remux_base . '/.remux_done';
                    
                    // 全局 remux 状态文件（记录当前正在处理的 media ID）
                    $remux_global_state = PPATH_CACHE . DS . 'remux' . DS . '.remux_current';
                    
                    // ============================================================
                    // 跨媒体清理 + 服务端保活检测
                    // 1. 不同媒体 → 杀掉旧 FFmpeg
                    // 2. 同一媒体但超过 10 分钟无访问 → 杀掉旧 FFmpeg（客户端已失联）
                    // ============================================================
                    if (file_exists($remux_global_state)) {
                        $global_state = json_decode(file_get_contents($remux_global_state), true);
                        $should_kill = false;
                        $kill_reason = '';
                        if (is_array($global_state)
                            && isset($global_state['idmedia'])
                            && isset($global_state['pid'])
                            && $global_state['pid'] > 0
                        ) {
                            $old_media_id = $global_state['idmedia'];
                            $old_pid = intval($global_state['pid']);
                            // 检查 1: 不同媒体
                            if ($old_media_id != $IDMEDIA) {
                                $should_kill = true;
                                $kill_reason = "switched to media=$IDMEDIA";
                            }
                            // 检查 2: 同一媒体但超过 10 分钟无新请求（客户端已失联）
                            if (!$should_kill && $old_media_id == $IDMEDIA
                                && isset($global_state['last_access'])
                                && (time() - intval($global_state['last_access'])) > 600
                            ) {
                                $should_kill = true;
                                $kill_reason = "stale (no access for >10min)";
                            }
                            if ($should_kill) {
                                $pid_check = trim(shell_exec("ps -p $old_pid -o pid= 2>/dev/null"));
                                if ($pid_check == $old_pid) {
                                    @file_put_contents($remux_debug, "REMUX: Killing old FFmpeg PID=$old_pid for media=$old_media_id ($kill_reason)\n", FILE_APPEND);
                                    exec("kill $old_pid 2>/dev/null");
                                }
                                // 清理旧媒体的 remux 目录
                                $old_remux_base = PPATH_CACHE . DS . 'remux' . DS . $old_media_id;
                                if (is_dir($old_remux_base)) {
                                    exec("rm -rf " . escapeshellarg($old_remux_base) . " 2>/dev/null");
                                }
                            }
                        }
                    }
                    
                    // 确保目录存在
                    if (!is_dir($remux_base)) {
                        @mkdir($remux_base, 0755, true);
                    }
                    
                    // ============================================================
                    // 更新全局状态 last_access（保活检测用）
                    // ============================================================
                    if (file_exists($remux_global_state)) {
                        $gs = json_decode(file_get_contents($remux_global_state), true);
                        if (is_array($gs) && isset($gs['idmedia']) && $gs['idmedia'] == $IDMEDIA) {
                            $gs['last_access'] = time();
                            file_put_contents($remux_global_state, json_encode($gs));
                        }
                    }
                    
                    // ============================================================
                    // 检查是否已有正在运行的 FFmpeg 进程（同一媒体）
                    // ============================================================
                    $ffmpeg_running = false;
                    $ffmpeg_pid = 0;
                    if (file_exists($remux_pid)) {
                        $old_pid = intval(trim(file_get_contents($remux_pid)));
                        if ($old_pid > 0) {
                            $pid_check = trim(shell_exec("ps -p $old_pid -o pid= 2>/dev/null"));
                            if ($pid_check == $old_pid) {
                                $ffmpeg_running = true;
                                $ffmpeg_pid = $old_pid;
                            }
                        }
                    }
                    
                    // ============================================================
                    // 如果 MP4 已生成完毕，直接提供 Range 支持的文件服务
                    // ============================================================
                    if (file_exists($remux_done) && file_exists($remux_file)) {
                        @file_put_contents($remux_debug, "REMUX: cache hit, serving cached file\n", FILE_APPEND);
                        goto serve_remux_file;
                    }
                    
                    // ============================================================
                    // 如果 MP4 存在但 .remux_done 不存在（FFmpeg 被杀死或崩溃）
                    // 删除并重启
                    // ============================================================
                    if (file_exists($remux_file) && !$ffmpeg_running) {
                        @file_put_contents($remux_debug, "REMUX: stale file (no done marker), deleting and restarting\n", FILE_APPEND);
                        exec("rm -rf " . escapeshellarg($remux_base) . " 2>/dev/null");
                        @mkdir($remux_base, 0755, true);
                        $ffmpeg_running = false;
                        $ffmpeg_pid = 0;
                    }
                    
                    // ============================================================
                    // 如果 FFmpeg 正在运行但文件还没生成完 → 返回准备中消息
                    // 客户端会在 Retry-After 后重试
                    // ============================================================
                    if ($ffmpeg_running && !file_exists($remux_done)) {
                        @file_put_contents($remux_debug, "REMUX: FFmpeg running (PID=$ffmpeg_pid), returning preparing\n", FILE_APPEND);
                        while( ob_get_level() ) ob_end_clean();
                        header('Content-Type: text/plain; charset=utf-8');
                        header('Retry-After: 5');
                        echo "REMUX 转封装正在准备中，请稍后刷新...\n";
                        exit();
                    }
                    
                    // ============================================================
                    // 启动后台 FFmpeg（不阻塞 PHP 响应）
                    // 使用 +faststart 生成标准 MP4（Safari 兼容性最好）
                    // +faststart 将 moov atom 移到文件头部，Safari 需要此结构
                    // 注意：+faststart 需要二次扫描，文件写完才能播放
                    // 因此首次请求返回准备中消息，后续请求从缓存提供
                    // ============================================================
                    @file_put_contents($remux_debug, "REMUX: starting background FFmpeg\n", FILE_APPEND);
                    
                    $remux_log = $remux_base . '/ffmpeg.log';
                    // 使用 +faststart 生成标准 MP4
                    // Safari 需要 moov atom 在文件头部才能正确解析
                    // 检测源文件编码，HEVC 时加 -tag:v hvc1（Safari 只认 hvc1 不认 hev1）
                    $vcodec = trim(shell_exec(O_FFPROBE . " -v error -select_streams v:0 -show_entries stream=codec_name -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($dir)));
                    $vtag = ($vcodec === 'hevc') ? ' -tag:v hvc1' : '';
                    $cmd = O_FFMPEG . " -nostdin -fflags +genpts -loglevel warning " . $extra_params . " -i " . escapeshellarg( $dir )
                         . " " . $audiotrack
                         . " -c:v copy" . $vtag . " -ac 2 -c:a aac -b:a 128k -avoid_negative_ts make_zero -y -f mp4 -movflags +faststart " . escapeshellarg($remux_file);
                    
                    @file_put_contents($remux_debug, "REMUX CMD: " . $cmd . "\n", FILE_APPEND);
                    
                    // 写入包装 shell 脚本，确保 FFmpeg 完成后创建 .remux_done 标记
                    // 脚本内容: 执行 FFmpeg → 记录退出码 → 成功则创建.done标记 → 退出
                    $wrapper_script = $remux_base . '/remux.sh';
                    $wrapper_content = "#!/bin/sh\n"
                        . $cmd . " 2>> " . escapeshellarg($remux_log) . "\n"
                        . "ret=\$?\n"
                        . "echo \"[REMUX_WRAPPER] FFmpeg exited with code=\$ret, time=\$(date '+%H:%M:%S')\" >> " . escapeshellarg($remux_log) . "\n"
                        . "if [ \$ret -eq 0 ]; then\n"
                        . "  touch " . escapeshellarg($remux_done) . "\n"
                        . "  echo \"[REMUX_WRAPPER] .remux_done created\" >> " . escapeshellarg($remux_log) . "\n"
                        . "fi\n"
                        . "exit \$ret\n";
                    file_put_contents($wrapper_script, $wrapper_content);
                    chmod($wrapper_script, 0755);
                    
                    // 后台执行包装脚本，stdout+stderr 都追加到日志文件
                    $bg_cmd = "nohup " . escapeshellarg($wrapper_script) . " >> " . escapeshellarg($remux_log) . " 2>&1 &";
                    exec($bg_cmd, $bg_output, $bg_returncode);
                    
                    // ============================================================
                    // PID 检测：nohup 启动后进程可能还没起来，需要等待后重试
                    // ============================================================
                    $pid = 0;
                    $pid_retries = 10;
                    for ($i = 0; $i < $pid_retries; $i++) {
                        $pid_cmd = "ps aux | grep '" . addslashes($remux_file) . "' | grep -v grep | awk '{print \$2}' | head -1";
                        $pid = trim(shell_exec($pid_cmd));
                        if (!empty($pid) && is_numeric($pid)) {
                            file_put_contents($remux_pid, $pid);
                            $now = time();
                            $global_state = json_encode(array(
                                'idmedia' => $IDMEDIA,
                                'pid' => intval($pid),
                                'started' => $now,
                                'last_access' => $now
                            ));
                            file_put_contents($remux_global_state, $global_state);
                            @file_put_contents($remux_debug, "REMUX: FFmpeg started with PID=$pid for media=$IDMEDIA (retry=$i)\n", FILE_APPEND);
                            break;
                        }
                        usleep(500000);
                    }
                    if (empty($pid) || !is_numeric($pid)) {
                        @file_put_contents($remux_debug, "REMUX: Failed to detect FFmpeg PID for media=$IDMEDIA\n", FILE_APPEND);
                    }
                    
                    // ============================================================
                    // 立即返回准备中消息（不等待文件生成）
                    // 客户端会在 Retry-After: 5 秒后重试
                    // 后续请求会检查 .remux_done 标记，命中缓存后直接 serve_remux_file
                    // ============================================================
                    @file_put_contents($remux_debug, "REMUX: returning preparing message\n", FILE_APPEND);
                    while( ob_get_level() ) ob_end_clean();
                    header('Content-Type: text/plain; charset=utf-8');
                    header('Retry-After: 5');
                    echo "REMUX 转封装正在准备中，请稍后刷新...\n";
                    exit();
                    
                serve_remux_file:
                    // ============================================================
                    // 提供完整的 HTTP Range 支持的文件服务（缓存命中时使用）
                    // 支持 seek 跳转（Safari 的字节级 Range 请求）
                    // ============================================================
                    @file_put_contents($remux_debug, "REMUX: serving cached file\n", FILE_APPEND);
                    
                    sqlite_db_close();
                    
                    if( $_SERVER['REQUEST_METHOD'] != 'HEAD' ){
                        if( ( $idplaying = sqlite_playing_insert( USERNAME, $IDMEDIA, FALSE, 'WEB-' . $G_QUALITY . '-' . $G_MODE, getmypid() ) ) != FALSE
                        ){
                            register_shutdown_function( function( $idplaying ){
                                    sqlite_playing_delete( $idplaying );
                                },
                            $idplaying );
                        }
                    }
                    
                    $file = $remux_file;
                    $fsize = filesize($file);
                    
                    while( ob_get_level() ) ob_end_clean();
                    if( function_exists( 'apache_setenv' ) ){
                        @apache_setenv('no-gzip', 1);
                        @apache_setenv('no-buffer', 1);
                    }
                    @ini_set('zlib.output_compression', 'Off');
                    @ini_set('output_buffering', 'Off');
                    
                    header('Content-Type: video/mp4', true);
                    header('Accept-Ranges: bytes');
                    header('Cache-Control: no-cache');
                    
                    if( isset( $_SERVER['HTTP_RANGE'] )
                    && preg_match( '/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches )
                    ){
                        $start = max( intval( $matches[1] ), 0 );
                        $end = $matches[2] !== '' ? intval( $matches[2] ) : $fsize - 1;
                        if ($start >= $fsize) {
                            header('HTTP/1.1 416 Range Not Satisfiable');
                            header('Content-Range: bytes */' . $fsize);
                            exit();
                        }
                        header('HTTP/1.1 206 Partial Content');
                        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fsize);
                        header('Content-Length: ' . ( $end - $start + 1 ) );
                        if( $_SERVER['REQUEST_METHOD'] != 'HEAD' ){
                            $fp = fopen( $file, 'rb' );
                            if( $fp ){
                                fseek( $fp, $start );
                                $remaining = $end - $start + 1;
                                while( $remaining > 0 && !feof( $fp ) && !connection_aborted() ){
                                    $readsize = min( 8192, $remaining );
                                    echo fread( $fp, $readsize );
                                    $remaining -= $readsize;
                                    flush();
                                }
                                fclose( $fp );
                            }
                        }
                    }else{
                        header('Content-Length: ' . $fsize);
                        if( $_SERVER['REQUEST_METHOD'] != 'HEAD' ){
                            $fp = fopen( $file, 'rb' );
                            if( $fp ){
                                while( !feof( $fp ) && !connection_aborted() ){
                                    echo fread( $fp, 8192 );
                                    flush();
                                }
                                fclose( $fp );
                            }
                        }
                    }
                    exit();
                //TEST KODI
                case 'fast':
                    //fast way to kodi
                    $encoder_outformat = 'matroska';
                    $encoder = 'libx264'; //fastest ???
                    //all tracks ???
                    //" . $subtrack . " " . $audiotrack . "
                    $cmd = O_FFMPEG . " -nostdin " . $extra_params . " -i " . escapeshellarg( $dir ) . " -vcodec " . $encoder . " -crf 23 -preset ultrafast -c:a copy -f " . $encoder_outformat . " - ";
                    
                    header('Content-type: video/matroska');
                break;
                case 'mp4':
                    //Safari probe: must respond 206 to Range: bytes=0-1
                    handleSafariRangeProbe($dir, 'video/mp4');
                    //mp4 H.264 software encoding
                    $encoder_outformat = 'mp4';
                    $encoder = 'libx264';
                    $AUDIOCODEC = 'aac';
                    //no -re: full speed pipeline for smoother frame delivery
                    //no -profile:v baseline: let libx264 choose better profile
                    //no -pix_fmt yuv420p: libx264 accepts NV12 natively
                    //+genpts: ensure PTS timestamps for MP4 container
                    //+default_base_moof: required for iPad Safari fMP4 playback
                    $cmd = O_FFMPEG . " -nostdin -fflags +genpts " . $extra_params . " -i " . escapeshellarg( $dir ) . " " . $subtrack . " " . $audiotrack . " -c:v " . $encoder . " -b:v " . $maxbitrate . " -maxrate " . $maxbitrate . " -bufsize 5000k -g 74 -strict experimental " . $SCALE . " -aspect 16:9 -preset ultrafast -tune zerolatency -af 'volume=" . $audiovol . "' -c:a " . $AUDIOCODEC . " -ab 128k -f " . $encoder_outformat . " -movflags empty_moov+frag_keyframe+default_base_moof - ";
                    //die( $cmd );
                    header('Content-type: video/mp4');
                    header('Accept-Ranges: bytes');
                    header('Cache-Control: no-cache');
                    //Force disable gzip for streaming
                    if( function_exists( 'apache_setenv' ) ) @apache_setenv('no-gzip', 1);
                    @ini_set('zlib.output_compression', 'Off');
                    while( ob_get_level() ) ob_end_clean();
                    sqlite_db_close();
                    if( $_SERVER['REQUEST_METHOD'] != 'HEAD' ){
                        if( ( $idplaying = sqlite_playing_insert( USERNAME, $IDMEDIA, FALSE, 'WEB-' . $G_QUALITY . '-' . $G_MODE, getmypid() ) ) != FALSE ){
                            $CHECKPLAYING = TRUE;
                            register_shutdown_function( function( $idplaying ){
                                    sqlite_playing_delete( $idplaying );
                                }, 
                            $idplaying );
                        }else{
                            $CHECKPLAYING = FALSE;
                            $idplaying = FALSE;
                        }
                        set_time_limit(0);
                        passthru( $cmd, $cmdok );
                        if( $CHECKPLAYING && $idplaying ){
                            sqlite_playing_delete( $idplaying );
                        }
                    }
                    exit();
                break;
                case 'mp4amdgpu':
                    handleSafariRangeProbe($dir, 'video/mp4');
                    //testing
                    //$encoder_outformat = 'mpegts';
                    $encoder_outformat = 'mp4';
                    //$encoder = 'h264';
                    //$encoder = 'libx264';
                    //AMDGPU
                    $encoder = 'h264_vaapi';
                    //$encoder = 'hevc_vaapi';
                    $AUDIOCODEC = 'aac';
                    //$AUDIOCODEC = 'mp3';
                    //$AUDIOCODEC = 'opus';
                    //FORCE NO FILTERS SCALE
                    $SCALE = '';
                    //$cmd = O_FFMPEG . " -nostdin -re " . $extra_params . " -hwaccel vaapi -hwaccel_device /dev/dri/renderD128 -hwaccel_output_format vaapi -i " . escapeshellarg( $dir ) . " " . $subtrack . " " . $audiotrack . " -c:v " . $encoder . " -quality realtime -b:v " . $minbitrate . " -maxrate " . $minbitrate . " -movflags +faststart -bufsize 1000k -g 74 -strict experimental -pix_fmt yuv420p " . $SCALE . " -aspect 16:9 -level " . $G_FFMPEGLVL . " -profile:v baseline -level 3.0 -preset ultrafast -tune zerolatency -af 'volume=" . $audiovol . "' -c:a " . $AUDIOCODEC . " -ab 64k -f " . $encoder_outformat . " -movflags frag_keyframe+empty_moov - ";
                    //ONLY IF DECODER IS FULL COMPATIBLE
                    //$cmd = O_FFMPEG . " -nostdin -hwaccel vaapi -hwaccel_device /dev/dri/renderD128 -hwaccel_output_format vaapi -i " . escapeshellarg( $dir ) . " -c:v h264_vaapi -b:v 5M -maxrate 10M -af 'volume=" . $audiovol . "' -c:a " . $AUDIOCODEC . " -ab 64k -f " . $encoder_outformat . " -movflags frag_keyframe+empty_moov - ";
                    //WITH OPTIONAL DECODER COMPATIBLE
                    $cmd = O_FFMPEG . " -nostdin " . $extra_params . " -init_hw_device vaapi=foo:/dev/dri/renderD128 -hwaccel vaapi -hwaccel_output_format vaapi -hwaccel_device foo -i " . escapeshellarg( $dir ) . " -filter_hw_device foo -vf 'format=nv12|vaapi,hwupload' " . $subtrack . " " . $audiotrack . " -c:v " . $encoder . " -b:v 5M -maxrate 10M " . $SCALE . " -aspect 16:9 -af 'volume=" . $audiovol . "' -c:a " . $AUDIOCODEC . " -ab 128k -f " . $encoder_outformat . " -movflags frag_keyframe+empty_moov - ";
                    //die( $cmd );

                    header('Content-type: video/mp4');
                break;
                case 'mp4amdgpurel':
                    //testing, less decode on hard
                    //$encoder_outformat = 'mpegts';
                    $encoder_outformat = 'mp4';
                    //$encoder = 'h264';
                    //$encoder = 'libx264';
                    //AMDGPU
                    $encoder = 'h264_vaapi';
                    //$encoder = 'hevc_vaapi';
                    $AUDIOCODEC = 'aac';
                    //$AUDIOCODEC = 'mp3';
                    //$AUDIOCODEC = 'opus';
                    //FORCE NO FILTERS SCALE
                    $SCALE = '';
                    //WITH OPTIONAL DECODER COMPATIBLE
                    $cmd = O_FFMPEG . " -nostdin " . $extra_params . " -hwaccel vaapi -i " . escapeshellarg( $dir ) . " " . $subtrack . " " . $audiotrack . " -c:v " . $encoder . " -b:v 5M -maxrate 10M " . $SCALE . " -aspect 16:9 -af 'volume=" . $audiovol . "' -c:a " . $AUDIOCODEC . " -ab 128k -f " . $encoder_outformat . " -movflags frag_keyframe+empty_moov - ";
                    //die( $cmd );

                    header('Content-type: video/mp4');
                break;
                case 'mp4nvidia':
                    handleSafariRangeProbe($dir, 'video/mp4');
                    //testing
                    //$encoder_outformat = 'mpegts';
                    $encoder_outformat = 'mp4';
                    //$encoder = 'h264';
                    //$encoder = 'libx264';
                    //AMDGPU
                    $encoder = 'h264_nvenc';
                    //$encoder = 'hevc_vaapi';
                    $AUDIOCODEC = 'aac';
                    //$AUDIOCODEC = 'mp3';
                    //$AUDIOCODEC = 'opus';
                    //FORCE NO FILTERS SCALE
                    $SCALE = '';
                    //FORCE MAX 1080
                    $VIDEOHEIGHT = '1080';
                    //$QUALITY = '-vf scale=-2:' . $VIDEOHEIGHT;
                    $QUALITY = '';
		    if( is_numeric( $subtrack )
                    && $subtrack > -1
                    ){
                        //TESTING (check if video is resized/scaled with filter_complex)
                        $subtrack = ' -filter_complex "[0:v][0:s:' . $subtrack . ']overlay=(main_w-overlay_w)/2:main_h-overlay_h,scale=trunc(iw/2)*2:trunc(ih/2)*2,scale=-2:' . $VIDEOHEIGHT . '"';
                    }else{
                        $subtrack = '';
                        //basic Scale (no use in hardsubs)
                        //$SCALE = " -vf 'scale=trunc(iw/2)*2:trunc(ih/2)*2' ";
                    }
			    
		    //BASE OLD
                    //$cmd = O_FFMPEG . " -nostdin " . $extra_params . " -hwaccel cuvid -c:v h264_cuvid -i " . escapeshellarg( $dir ) . " " . $subtrack . " " . $audiotrack . " -c:v " . $encoder . " -b:v 5M -maxrate 10M " . $SCALE . " -aspect 16:9 -af 'volume=" . $audiovol . "' -c:a " . $AUDIOCODEC . " -ab 128k -f " . $encoder_outformat . " -movflags frag_keyframe+empty_moov+faststart - ";
                    //BASE
                    //$cmd = O_FFMPEG . " -nostdin " . $extra_params . " -hwaccel cuda -i " . escapeshellarg( $dir ) . " " . $subtrack . " " . $audiotrack . " -c:v " . $encoder . " -b:v 5M -maxrate 10M " . $SCALE . " -aspect 16:9 -af 'volume=" . $audiovol . "' -c:a " . $AUDIOCODEC . " -ab 128k -f " . $encoder_outformat . " -movflags frag_keyframe+empty_moov - ";
		    //BASE CUDA with 10bit support and max 1080p
                    $cmd = O_FFMPEG . " -nostdin " . $extra_params . " -hwaccel cuda -hwaccel_output_format cuda -i " . escapeshellarg( $dir ) . " " . $subtrack . " " . $audiotrack . " -vf scale_cuda=-2:1080:interp_algo=lanczos,scale_cuda=format=yuv420p -c:v " . $encoder . " -b:v 5M -maxrate 10M -bufsize 50000k " . $SCALE . " " . $QUALITY . " -af 'volume=" . $audiovol . "' -c:a " . $AUDIOCODEC . " -ab 128k -f " . $encoder_outformat . " -movflags frag_keyframe+empty_moov+faststart - ";
			    
		    //die( $cmd );

                    header('Content-type: video/mp4');
                break;
                case 'mp4v4l2':
                    handleSafariRangeProbe($dir, 'video/mp4');
                    //V4L2 hardware encoding (RK3528A/树莓派等ARM平台)
                    $encoder_outformat = 'mp4';
                    $encoder = 'h264_v4l2m2m';
                    $AUDIOCODEC = 'aac';
                    $cmd = O_FFMPEG . " -nostdin " . $extra_params . " -i " . escapeshellarg( $dir ) . " " . $subtrack . " " . $audiotrack . " -c:v " . $encoder . " -b:v 5M -maxrate 10M -bufsize 5000k -pix_fmt yuv420p " . $SCALE . " -aspect 16:9 -af 'volume=" . $audiovol . "' -c:a " . $AUDIOCODEC . " -ab 128k -f " . $encoder_outformat . " -movflags frag_keyframe+empty_moov - ";
                    //die( $cmd );
                    
                    header('Content-type: video/mp4');
                break;
                case 'webm2':
                    //slow
                    $AUDIOCODEC = 'libvorbis';
                    $encoder_outformat = 'webm';
                    $encoder = 'libvpx-vp9'; //webm 9
                    //better compatible
                    $G_FFMPEGLVL = '4.0';
                    //Forced
                    //-hwaccel cuvid -c:v h264_cuvid
                    //Decode on GPU
                    //-hwaccel cuda -hwaccel_output_format cuda
                    $cmd = O_FFMPEG . " -nostdin " . $extra_params . " -i " . escapeshellarg( $dir ) . " " . $subtrack . " " . $audiotrack . " -c:v " . $encoder . " -b:v 5M -maxrate 10M -bufsize 5000k " . $SCALE . " -aspect 16:9 -af 'volume=" . $audiovol . "' -c:a " . $AUDIOCODEC . " -ab 128k -f " . $encoder_outformat . " -movflags frag_keyframe+empty_moov - ";
                    //die( $cmd );
                    
                    header('Content-type: video/webm');
                break;
                case 'webm':
                default:
                    //better option
                    $AUDIOCODEC = 'libvorbis';
                    $encoder_outformat = 'webm';
                    $encoder = 'libvpx';
                    $cmd = O_FFMPEG . " -nostdin " . $extra_params . " -i " . escapeshellarg( $dir ) . " " . $subtrack . " " . $audiotrack . " -c:v " . $encoder . " -cpu-used 5 -quality realtime -b:v " . $minbitrate . " -maxrate " . $minbitrate . " -bufsize 1000k -pix_fmt yuv420p " . $SCALE . " -aspect 16:9 -preset baseline " . $QUALITY . " -level " . $G_FFMPEGLVL . " -af 'volume=" . $audiovol . "' -c:a " . $AUDIOCODEC . " -f " . $encoder_outformat . " - ";
                    //die( $cmd );
                    
                    header('Content-type: video/webm');
            }
            
            //headers
            if( function_exists( 'apache_setenv' ) ) @apache_setenv('no-gzip', 1);
            @ini_set('zlib.output_compression', 'Off');
            header('Content-disposition: inline');
            header("Content-Transfer-Encoding: ­binary");
            
            //force close db
            sqlite_db_close();
            //die( $cmd );
            
            //passthru
            if( $_SERVER['REQUEST_METHOD'] != 'HEAD' ){
                
                //SET PLAYING NOW
                if( ( $idplaying = sqlite_playing_insert( USERNAME, $IDMEDIA, FALSE, 'WEB-' . $G_QUALITY . '-' . $G_MODE, getmypid() ) ) != FALSE 
                ){
                    $CHECKPLAYING = TRUE;
                    register_shutdown_function( function( $idplaying ){
                            sqlite_playing_delete( $idplaying );
                        }, 
                    $idplaying );
                }else{
                    $CHECKPLAYING = FALSE;
                    $idplaying = FALSE;
                }
                
                //ACTION
                passthru( $cmd, $cmdok );
                
                //REMOVE PLAYING NOW
                if( $CHECKPLAYING
                && $idplaying
                ){
                    sqlite_playing_delete( $idplaying );
                }
            }
        }
    }
    exit();
?>

