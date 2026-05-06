<?php

	defined( 'ACCESS' ) or die( 'HTTP/1.0 401 Unauthorized.<br />' );
	
	//admin
	//check_mod_admin();
	
	//action
	//idmedia
	//idmediainfo
	
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
	&& @file_exists( $mi[ 0 ][ 'file' ] )
	&& getFileMimeTypeVideo( $mi[ 0 ][ 'file' ] )
	){
        $FMEDIA = $mi[ 0 ][ 'file' ];
        $IDMEDIAINFO = $mi[ 0 ][ 'idmediainfo' ];
	}elseif( $IDMEDIAINFO > 0
	&& ( $mi = sqlite_media_getdata_mediainfo( $IDMEDIAINFO ) ) != FALSE 
	&& is_array( $mi )
	&& count( $mi ) > 0
	//&& @file_exists( $mi[ 0 ][ 'file' ] )
	//&& getFileMimeTypeVideo( $mi[ 0 ][ 'file' ] )
	){
        //Get best video quality
        if( count( $mi ) == 1 ){
            $FMEDIA = $mi[ 0 ][ 'file' ];
            $IDMEDIA = $mi[ 0 ][ 'idmedia' ];
        }else{
            $best = $mi[ 0 ];
            foreach( $mi AS $v ){
                if( $v[ 'idmedia' ] != $best[ 'idmedia' ] 
                && ( $lowq = ffmpeg_video_compare( $v[ 'file' ], $best[ 'file' ] ) ) != FALSE
                ){
                    if( $lowq == $best[ 'file' ] ){
                        $best = $v;
                    }
                }
            }
            $FMEDIA = $best[ 'file' ];
            $IDMEDIA = $best[ 'idmedia' ];
        }
        //old, get first
        //$FMEDIA = $mi[ 0 ][ 'file' ];
        //$IDMEDIA = $mi[ 0 ][ 'idmedia' ];
	}else{
        $FMEDIA = FALSE;
	}
	
	if( $FMEDIA == FALSE ){
        echo get_msg( 'DEF_NOTEXIST' );
	}elseif( !file_exists( $FMEDIA ) ){
        echo get_msg( 'DEF_FILENOTEXIST' );
	}else{
        //EXTRA VARS
        $time = ffmpeg_file_info_lenght_seconds( $FMEDIA );
        if( ( $playedtimebefore = sqlite_played_status( $IDMEDIA ) ) <= 0 
        || !is_int( $playedtimebefore )
        ){
            $playedtimebefore = 0;
        }else{
            if( $playedtimebefore > ( $time - ( $time / 10 ) ) ){
                $playedtimebefore = 0;
            }
        }
        sqlite_played_replace( $IDMEDIA, $playedtimebefore, $time );
        $PLAYERSKIPTIME = 10;
        $urlposter = getURLImg( FALSE, $IDMEDIAINFO, 'poster' );
        $urllogo = getURLImg( FALSE, $IDMEDIAINFO, 'logo' );
        $urllandscape = getURLImg( FALSE, $IDMEDIAINFO, 'landscape' );
        $inforul = getURLInfo( FALSE, $IDMEDIAINFO );
        $nextfileinfo = getURLNextInfo( FALSE, $IDMEDIAINFO );
        $backfileinfo = getURLBackInfo( FALSE, $IDMEDIAINFO );
        
        if( $IDMEDIAINFO > 0
        && ( $mi = sqlite_mediainfo_getdata( $IDMEDIAINFO, 1 ) ) != FALSE 
        && is_array( $mi )
        && array_key_exists( 0, $mi )
        && is_array( $mi[ 0 ] )
        && array_key_exists( 'title', $mi[ 0 ] )
        ){
            $title = $mi[ 0 ][ 'title' ];
            if( array_key_exists( 'season', $mi[ 0 ] )
            && array_key_exists( 'episode', $mi[ 0 ] ) 
            && is_numeric( $mi[ 0 ][ 'season' ] )
            && $mi[ 0 ][ 'season' ] > -1
            ){
                $title .= ' ' . $mi[ 0 ][ 'season' ] . 'x' . sprintf( '%02d' , $mi[ 0 ][ 'episode' ] );
            }
            if( array_key_exists( 'titleepisode', $mi[ 0 ] )
            && strlen( $mi[ 0 ][ 'titleepisode' ] ) > 0
            ){
                $title .= ' ' . $mi[ 0 ][ 'titleepisode' ];
            }
            $year = $mi[ 0 ][ 'year' ];
            $rating = $mi[ 0 ][ 'rating' ];
        }else{
            $title = 'No Title';
            $year = '';
            $rating = '';
        }
        
        $audiolist = array();
        $subslist = array();
        $subslistv = array();
        $subslistext = array();
        $AUDIOTRACK = 1;
	
        //LIST OF ENCODERS
        $CODECORDER = array();
        //TEST HARDWARE DECODING AMDGPU
        if( ( defined( 'O_VIDEO_AMDGPU_ENCODE' )
            && O_VIDEO_AMDGPU_ENCODE == TRUE
        )
        || ( defined( 'O_VIDEO_AMDGPU_ENCODE_ADMIN' )
            && O_VIDEO_AMDGPU_ENCODE_ADMIN == TRUE
            && check_user_admin()
        )
        ){
            $CODECORDER[ 'mp4amdgpu' ] = 'mp4';
            $CODECORDER[ 'mp4amdgpurel' ] = 'mp4';
        }
        //TEST HARDWARE DECODING NVIDIA
        if( ( defined( 'O_VIDEO_NVIDIA_ENCODE' )
            && O_VIDEO_NVIDIA_ENCODE == TRUE
        )
        || ( defined( 'O_VIDEO_NVIDIA_ENCODE_ADMIN' )
            && O_VIDEO_NVIDIA_ENCODE_ADMIN == TRUE
            && check_user_admin()
        )
        ){
            $CODECORDER[ 'mp4nvidia' ] = 'mp4';
        }
        $CODECORDER[ 'mp4' ] = 'mp4';
        //TEST HARDWARE DECODING V4L2 (ARM平台如RK3528A)
        if( ( defined( 'O_VIDEO_V4L2_ENCODE' )
            && O_VIDEO_V4L2_ENCODE == TRUE
        )
        || ( defined( 'O_VIDEO_V4L2_ENCODE_ADMIN' )
            && O_VIDEO_V4L2_ENCODE_ADMIN == TRUE
            && check_user_admin()
        )
        ){
            $CODECORDER[ 'mp4v4l2' ] = 'mp4';
        }
        $CODECORDER[ 'webm' ] = 'webm';
        $CODECORDER[ 'webm2' ] = 'webm2';
        //HLS mode for iPad Safari (native support, no JS library needed)
        $CODECORDER[ 'hls' ] = 'application/vnd.apple.mpegurl';
        
        //Add direct mode as first priority (browser hardware decode, 0 CPU)
        $directMime = getFileMimeType( $FMEDIA );
        $directType = str_replace( 'video/', '', $directMime );
        //如果 remux 预缓存已完成，覆盖 source type 为 video/mp4
        //（后端实际通过 serve_remux_file 提供缓存的 MP4，而非原文件）
        $remux_done_check = PPATH_CACHE . DS . 'remux' . DS . $IDMEDIA . DS . '.remux_done';
        $remux_file_check = PPATH_CACHE . DS . 'remux' . DS . $IDMEDIA . DS . 'stream.mp4';
        $remux_codec = 'mp4'; // 默认 type
        if( file_exists( $remux_done_check ) && file_exists( $remux_file_check ) ){
            // 探测缓存 MP4 的视频编码，给 Safari 正确的 codecs 声明（HEVC 必须声明才能触发硬解）
            $probed = trim(shell_exec(O_FFPROBE . " -v error -select_streams v:0 -show_entries stream=codec_name -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($remux_file_check)));
            if( $probed === 'hevc' ){
                $remux_codec = 'mp4; codecs="hvc1"';
            }elseif( $probed === 'h264' ){
                $remux_codec = 'mp4; codecs="avc1"';
            }
            $directType = $remux_codec;
        }
        $directArr = array( 'direct' => $directType );
        //Add remux mode as second priority (stream copy to MP4, zero CPU)
        $remuxArr = array( 'remux' => $remux_codec );
        //Add hw mode as third priority (rkmpp hardware decode + encode to MP4)
        $hwArr = array( 'hw' => 'mp4' );
        $CODECORDER = array_merge( $directArr, $remuxArr, $hwArr, $CODECORDER );
        
        // Check for forced mode parameter
        $forcedMode = '';
        if( array_key_exists( 'mode', $G_DATA ) 
        && strlen( $G_DATA[ 'mode' ] ) > 0
        && array_key_exists( $G_DATA[ 'mode' ], $CODECORDER )
        ){
            $forcedMode = $G_DATA[ 'mode' ];
            $forcedType = $CODECORDER[ $forcedMode ];
            $CODECORDER = array( $forcedMode => $forcedType );
        }else{
            // Default to direct mode - no auto fallback
            $forcedMode = 'direct';
            $forcedType = $CODECORDER[ 'direct' ];
            $CODECORDER = array( 'direct' => $forcedType );
        }
        
        // Build mode switch URLs for dropdown
        $playerBaseUrl = getURLBase() . '?action=player&idmedia=' . $IDMEDIA;
        if( $IDMEDIAINFO > 0 ){
            $playerBaseUrl .= '&idmediainfo=' . $IDMEDIAINFO;
        }
        $urlModeDirect = $playerBaseUrl . '&mode=direct';
        $urlModeRemux = $playerBaseUrl . '&mode=remux';
        $urlModeHw = $playerBaseUrl . '&mode=hw';
        $urlModeMp4 = $playerBaseUrl . '&mode=mp4';
        $urlModeHls = $playerBaseUrl . '&mode=hls';
        $currentModeLabel = strlen( $forcedMode ) > 0 ? strtoupper( $forcedMode ) : '自动';
        
        if( ( $videoinfo = ffprobe_get_data( $FMEDIA, FALSE ) ) != FALSE 
        && is_array( $videoinfo )
        ){
            if( array_key_exists( 'audiotracks', $videoinfo )
            ){
                $audiolist = $videoinfo[ 'audiotracks' ];
                if( defined( 'O_LANG_AUDIO_TRACK' ) 
                && is_array( O_LANG_AUDIO_TRACK )
                ){
                    $num = 1;
                    foreach( $audiolist AS $at ){
                        if( inString( $at, O_LANG_AUDIO_TRACK ) ){
                            $AUDIOTRACK = (int)$num;
                            break;
                        }
                        $num++;
                    }
                }
            }
            
            if( array_key_exists( 'subtracks', $videoinfo )
            ){
                $subslist = $videoinfo[ 'subtracks' ];
            }
            
            if( array_key_exists( 'subtracksv', $videoinfo )
            ){
                $subslistv = $videoinfo[ 'subtracksv' ];
            }
            
            if( array_key_exists( 'codec', $videoinfo )
            && strlen( $videoinfo[ 'codec' ] ) > 0 
            ){
                //TODO ORDER BY CODEC in video
                /*
                if( stripos( $videoinfo[ 'codec' ], 'mpeg' ) !== FALSE
                || stripos( $videoinfo[ 'codec' ], '264' ) !== FALSE
                ){
                    $CODECORDER = array(
                        //url ident = header type
                        'mp4' => 'mp4',
                        'webm' => 'webm',
                        'webm2' => 'webm',
                    );
                }
                */
            }
            
            // Scan for external subtitle files in video directory
            if( $FMEDIA != FALSE ){
                $mediadir = dirname( $FMEDIA );
                $sub_exts = array( 'srt', 'ass', 'vtt', 'sub', 'ssa' );
                if( is_dir( $mediadir ) ){
                    if( ( $dh = opendir( $mediadir ) ) !== FALSE ){
                        while( ( $file = readdir( $dh ) ) !== FALSE ){
                            if( $file == '.' || $file == '..' ) continue;
                            $ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
                            if( in_array( $ext, $sub_exts ) ){
                                $subslistext[ $file ] = $file;
                            }
                        }
                        closedir( $dh );
                    }
                }
            }
        }
?>

<script>	
$(function () {
	// 退出时清理 HLS FFmpeg
	// pagehide 比 beforeunload 更适合 iPad Safari（无论回退/切标签/关闭都触发）
	function hlsCleanup(){
		var stopUrl = '?r=r&action=playstop&timeplayed=' + parseInt( $( '#slideTime' ).val() ) + '&timetotal=<?php echo $time; ?>&idmedia=<?php echo $IDMEDIA; ?>';
		// 方案1: sendBeacon（浏览器保证发出，不阻塞页面）
		if( navigator.sendBeacon ){
			navigator.sendBeacon( stopUrl );
		}
		// 方案2: 同步 XHR（不依赖 async:false 的兼容性问题）
		try{
			var xhr = new XMLHttpRequest();
			xhr.open( 'GET', stopUrl, false ); // false = 同步
			xhr.timeout = 2000;
			xhr.send();
		}catch(e){}
		var videoElement = document.getElementById( 'my-player' );
		if( videoElement ){
            videoElement.pause();
            $( '#my-player' ).off();
            $( '#my-player source' ).off();
            videoElement.src =""; // empty source
            videoElement.load();
        }
	}
	// pagehide: iOS Safari 最可靠（比 beforeunload 更早触发）
	window.addEventListener( 'pagehide', hlsCleanup );
	// beforeunload: 桌面浏览器回退
	$( window ).on( 'beforeunload', hlsCleanup );
	
	//player
	$( '#my-player' ).click( function(){
		if( this.paused ){
			this.play();
		}else{
			this.pause();
		}
	});
	//iOS native fullscreen events
	var videoEl = document.getElementById( 'my-player' );
	if( videoEl ){
		videoEl.addEventListener( 'webkitbeginfullscreen', function(){
			$( '#playerControlFullScreenIco' ).html( '&#10005;' );
		});
		videoEl.addEventListener( 'webkitendfullscreen', function(){
			$( '#playerControlFullScreenIco' ).html( '&#9633;' );
		});
	}
	//mouse move
	$(document).on('mousemove', function() {
		clearTimeout(mousemovetimeout);
		$( "#playerBoxI, #playerBoxC, .menuBoxContainer" ).show();
		$( '#my-player' ).removeClass( 'cursorTransparent' ); 
		$( '#my-player' ).css( 'cursor', 'pointer' ); 
		mousemovetimeout = setTimeout(function() {
			$( "#playerBoxI, #playerBoxC, .menuBoxContainer" ).hide();
			$( '#my-player' ).addClass( 'cursorTransparent' ); 
			$( '#my-player' ).css( 'cursor', 'none' ); 
		}, 2000);
	});
	//buttons
	$( '#playerControlStop' ).click( function(){
        goToURL( '<?php echo $inforul; ?>' );
	});
	//playerControlPlayBack
	$( '#playerControlPlayBack' ).click( function(){
        var seconds = parseInt( $( '#slideTime' ).val() ) - parseInt( playerskiptime );
        if( seconds < 0 ){
            seconds = 0;
        }
        playerTimeChanged( seconds );
	});
	//dSlider
	$( '.dSlider' ).click( function(e) {
        var posX = e.pageX - parseInt( $(this).position().left );
        var posY = e.pageY - parseInt( $(this).position().top );
        var size = parseInt( $(this).width() );
        var pos = parseInt( ( ( 100 * posX ) / size ) );
        var nowtime = parseInt( ( totaltime / 100 ) * pos );
        $( '.dSliderInner' ).css( 'width', pos + '%');
        if( DEBUG ) console.log( 'CHANGED TIME: ' + nowtime );
        playerTimeChanged( nowtime );
        //alert( posX + ' , ' + posY + ' - ' + size + ' - ' + pos + '%' + ' - ' + nowtime + '%' );
    });
    $( ".dSlider" ).mousemove( function(e){
        var posX = e.pageX - parseInt( $(this).position().left );
        var posY = e.pageY - parseInt( $(this).position().top );
        var size = parseInt( $(this).width() );
        var pos = parseInt( ( ( 100 * posX ) / size ) );
        var nowtime = parseInt( ( totaltime / 100 ) * pos );
        $( '.dSlider' ).prop( 'title', secondsTimeSpanToHMS( nowtime ) + ' (' + pos + '%)');
        if( DEBUG ) console.log( 'MOUSE CHANGED TIME: ' + nowtime );
    });
	//playerControlPause
	$( '#playerControlPause' ).click( function(){
        document.getElementById( 'my-player' ).pause();
	});
	//playerControlPlay
	$( '#playerControlPlay' ).click( function(){
        document.getElementById( 'my-player' ).play();
	});
	//playerControlPlayFor
	$( '#playerControlPlayFor' ).click( function(){
        var seconds = parseInt( $( '#slideTime' ).val() ) + parseInt( playerskiptime );
        playerTimeChanged( seconds );
	});
	//Keyboard shortcut: Spacebar to toggle play/pause
	$( document ).on( 'keydown', function( e ){
        if( e.code === 'Space' || e.keyCode === 32 ){
            var targetTag = e.target.tagName.toLowerCase();
            if( targetTag === 'input' || targetTag === 'textarea' || targetTag === 'select' ){
                return; // Don't intercept when typing in form fields
            }
            e.preventDefault();
            var video = document.getElementById( 'my-player' );
            if( video ){
                if( video.paused ){
                    video.play();
                }else{
                    video.pause();
                }
            }
        }
	});
	
	//playerBoxBarControlsButton
	$( '.basecontrols .playerBoxBarControlsButton' ).click( function(){
        $( '.basecontrols .playerBoxBarControlsButton' ).removeClass( 'playerBoxBarControlsButtonSelected' );
		$( this ).addClass( 'playerBoxBarControlsButtonSelected' );
	});
	
	//video events SOURCES
	
	//error source
	$( '#my-player source' ).on( "error", function( event ) {
		if( DEBUG ) console.log( 'VIDEO SOURCE0 error: ' + parseInt( playedtotaltime ) + ' - ' + this.duration + ' - ' + this.networkState + ' S ' + $( this ).attr( 'data-last' ) );
		if( $( this ).attr( 'data-last' ) != '1' ){
			$( this ).remove();
		}
		playererrors++;
		if( playererrors > playererrors_max ){
            send_video_error();
		}else{
            playedtotaltime += playerskiptime;
            //whit errors try playsafe
            playerTimeChanged( playedtotaltime );
        }
	});
	//error source 1
	$( '#my-player source[1]' ).on( "error", function( event ) {
		if( DEBUG ) console.log( 'VIDEO SOURCE1 error: ' + parseInt( playedtotaltime ) + ' - ' + this.duration + ' - ' + this.networkState + ' S ' + $( this ).attr( 'data-last' ) );
		if( $( this ).attr( 'data-last' ) != '1' ){
			$( this ).remove();
		}
		playererrors++;
		if( playererrors > playererrors_max ){
            send_video_error();
		}else{
            playedtotaltime += playerskiptime;
            //whit errors try playsafe
            playerTimeChanged( playedtotaltime );
        }
	});
	//error source 2
	$( '#my-player source[2]' ).on( "error", function( event ) {
		if( DEBUG ) console.log( 'VIDEO SOURCE1 error: ' + parseInt( playedtotaltime ) + ' - ' + this.duration + ' - ' + this.networkState + ' S ' + $( this ).attr( 'data-last' ) );
		if( $( this ).attr( 'data-last' ) != '1' ){
			$( this ).remove();
		}
		playererrors++;
		if( playererrors > playererrors_max ){
            send_video_error();
		}else{
            playedtotaltime += playerskiptime;
            //whit errors try playsafe
            playerTimeChanged( playedtotaltime );
        }
	});
	//play
	$( '#my-player' ).on( "play", function( event ) {
		if( DEBUG ) console.log( 'VIDEO play: ' + parseInt( playedtotaltime ) + ' - ' + this.duration + ' - ' + this.networkState );
		$( '.basecontrols .playerBoxBarControlsButton' ).removeClass( 'playerBoxBarControlsButtonSelected' );
		$( '#playerControlPlay' ).addClass( 'playerBoxBarControlsButtonSelected' );
	});
	//playing
	$( '#my-player source' ).on( "playing", function( event ) {
		if( DEBUG ) console.log( 'VIDEO playing: ' + parseInt( playedtotaltime ) + ' - ' + this.duration + ' - ' + this.networkState );
	});
	//abort
	$( '#my-player source' ).on( "abort", function( event ) {
		if( DEBUG ) console.log( 'VIDEO abort: ' + parseInt( playedtotaltime ) + ' - ' + this.duration + ' - ' + this.networkState );
	});
	//stalled
	$( '#my-player source' ).on( "stalled", function( event ) {
		if( DEBUG ) console.log( 'VIDEO stalled: ' + parseInt( playedtotaltime ) + ' - ' + this.duration + ' - ' + this.networkState );
		playererrors++;
		if( playererrors > playererrors_max ){
            send_video_error();
		}else{
            playedtotaltime += playerskiptime;
            //whit errors try playsafe
            playerTimeChanged( playedtotaltime );
        }
	});
	//suspend
	$( '#my-player source' ).on( "suspend", function( event ) {
		if( DEBUG ) console.log( 'VIDEO suspend: ' + parseInt( playedtotaltime ) + ' - ' + this.duration + ' - ' + this.networkState );
	});
	//emtied
	$( '#my-player source' ).on( "emptied", function( event ) {
		if( DEBUG ) console.log( 'VIDEO emptied: ' + parseInt( playedtotaltime ) + ' - ' + this.duration + ' - ' + this.networkState );
	});
	
	//video events VIDEO
	
	//pause
	$( '#my-player' ).on( "pause", function( event ) {
		if( DEBUG ) console.log( 'VIDEO pause: ' + parseInt( playedtotaltime ) + ' - ' + this.duration + ' - ' + this.networkState );
		$( '.basecontrols .playerBoxBarControlsButton' ).removeClass( 'playerBoxBarControlsButtonSelected' );
		$( '.playerControlPause' ).addClass( 'playerBoxBarControlsButtonSelected' );
	});
	//timeupdate
	$( '#my-player' ).on( "timeupdate", function( event ) {
        var total = parseInt( this.currentTime ) + parseInt( playedtotaltime );
        if( DEBUG ) console.log( 'VIDEO timeupdate: ' + parseInt( total ) + ' - ' + this.duration + ' - ' + this.currentTime + ' - ' + parseFloat( playedtotaltime ) );
		$( '#slideTime' ).val( total );
		$( '#slideTime' ).attr( 'title', secondsTimeSpanToHMS( total ) );
		slideUpdate( total );
		$( '.playerControlTimeNowData' ).html( secondsTimeSpanToHMS( total ) );
		//SUBS CONTROL
		if( subtrack !== false 
		&& subtrack_data != false
		){
            //SUBS TIMER
            var total2 = parseFloat( this.currentTime ) + parseFloat( playedtotaltime );
            show_subs_timed( parseFloat( total2 ) );
		}
	});
	//error video
	$( '#my-player' ).on( "error", function( event ) {
		if( DEBUG ) console.log( 'VIDEO error: ' + parseInt( playedtotaltime ) + ' - ' + this.duration + ' - ' + this.networkState + ' S ' + $( this ).attr( 'data-last' ) );
		playerTimeChanged( playedtotaltime );
	});
	//durationchange
	$( '#my-player' ).on( "durationchange", function( event ) {
		if( DEBUG ) console.log( 'VIDEO durationchange: ' + parseInt( playedtotaltime ) + ' - ' + this.duration + ' - ' + this.networkState );
	});
	//ended
	$( '#my-player' ).on( "ended", function( event ) {
		if( DEBUG ) console.log( 'VIDEO ended: ' + parseInt( playedtotaltime ) + ' - ' + this.duration + ' - ' + this.networkState );
		$.ajax({
			url : '?r=r&action=playstop&timeplayed=<?php echo $time; ?>&timetotal=<?php echo $time; ?>&idmedia=<?php echo $IDMEDIA; ?>',
			type : 'GET',
			dataType : 'json',
			success : function (result) {
			}
		});
		if( $( '#aNextFile' ).length
		){
			location.replace( $( '#aNextFile' ).attr( 'href' ) );
		}else{
			location.replace( $( '#aFileInfo' ).attr( 'href' ) );
		}
	});
	//waiting
	$( '#my-player' ).on( "waiting", function( event ) {
		if( DEBUG ) console.log( 'VIDEO waiting: ' + parseInt( playedtotaltime ) + ' - ' + this.duration + ' - ' + this.networkState );
	});
	//canplay
	$( '#my-player' ).on( "canplay", function( event ) {
		if( DEBUG ) console.log( 'VIDEO canplay: ' + parseInt( playedtotaltime ) + ' - ' + this.duration + ' - ' + this.networkState );
		loading_hide();
		// Direct/Remux mode: auto-seek to resume position after metadata loaded
		// 检测方式：通过 URL 参数判断（modestr 数组可能不包含 remux）
		var _canplaySrc = $( "#my-player source" ).attr( 'src' ) || '';
		var _isNativeResume = ( _canplaySrc.indexOf( 'mode=direct' ) !== -1 || _canplaySrc.indexOf( 'mode=remux' ) !== -1 );
		if( _isNativeResume && parseInt( playedtotaltime ) > 0 ){
			var resumeTime = parseInt( playedtotaltime );
			playedtotaltime = 0;
			this.currentTime = resumeTime;
		}
	});
	//loadeddata
	$( '#my-player' ).on( "loadeddata", function( event ) {
		if( DEBUG ) console.log( 'VIDEO loadeddata: ' + parseInt( playedtotaltime ) + ' - ' + this.duration + ' - ' + this.networkState );
		loading_hide();
	});
	//loadstart
	$( '#my-player' ).on( "loadstart", function( event ) {
		if( DEBUG ) console.log( 'VIDEO loadstart: ' + parseInt( playedtotaltime ) + ' - ' + this.duration + ' - ' + this.networkState );
		loading_show();
	});
	//progress
	$( '#my-player' ).on( "progress", function( event ) {
		if( DEBUG ) console.log( 'VIDEO progress: ' + parseInt( playedtotaltime ) + ' - ' + this.duration + ' - ' + this.networkState );
	});
	//boxInfoOverlay
	
	//Mode switch
	var modestr = ['direct', 'hw', 'mp4'<?php if( defined( 'O_VIDEO_V4L2_ENCODE' ) && O_VIDEO_V4L2_ENCODE == TRUE ){ ?>, 'mp4v4l2'<?php } ?><?php if( array_key_exists('hls', $CODECORDER) ){ ?>, 'hls'<?php } ?>];
	var modenow = 0;
	$( '#playerControlMode' ).click( function(){
		var curSrc = $( "#my-player source" ).attr( 'src' );
		if( typeof curSrc == 'undefined' ) return;
		// 如果当前源是 HLS 或 remux，切出前清理 FFmpeg
		if( curSrc.indexOf( 'mode=hls' ) !== -1 || curSrc.indexOf( 'mode=remux' ) !== -1 ){
			var stopUrl = '?r=r&action=playstop&timeplayed=' + parseInt( $( '#slideTime' ).val() ) + '&timetotal=<?php echo $time; ?>&idmedia=<?php echo $IDMEDIA; ?>';
			if( navigator.sendBeacon ){
				navigator.sendBeacon( stopUrl );
			}
		}
		modenow = ( modenow + 1 ) % modestr.length;
		var mode = modestr[ modenow ];
		var labels = {'direct': '直连', 'hw': '硬解', 'mp4': 'MP4', 'mp4v4l2': 'V4L2', 'hls': 'HLS'};
		$( this ).html( labels[ mode ] || mode );
		var newSrc = curSrc.replace( /mode=[^&]+/, 'mode=' + mode );
		if( mode == 'direct' ) playedtotaltime = 0;
		// HLS mode: set src directly to m3u8 URL (bypass <source> element)
		if( mode == 'hls' ) {
			var player = document.getElementById( 'my-player' );
			player.src = newSrc;
			player.load();
			player.play();
		} else {
			$( "#my-player source" ).attr( 'src', newSrc );
			document.getElementById( 'my-player' ).load();
			document.getElementById( 'my-player' ).play();
		}
		subtrack_lastline = 0;
	});
	
	//Quality
	$( '#playerControlQuality' ).click( function(){
		if( $( this ).hasClass( 'playerControlQualityHD' ) ){
			$( this ).removeClass( 'playerControlQualityHD' );
			$( this ).attr( 'title', 'Quality SD' );
			$( this ).html( 'SD' );
		}else{
			$( this ).addClass( 'playerControlQualityHD' );
			$( this ).attr( 'title', 'Quality HD' );
			$( this ).html( 'HD' );
		}
		setQuality();
	});
	
	//sound init
	if( localStorage
	&& ( 'soundValue' in localStorage )
	&& parseInt( localStorage.getItem( "soundValue" ) ) >= 1
	&& parseInt( localStorage.getItem( "soundValue" ) ) <= 100
	){
        var soundvalue = localStorage.getItem( "soundValue" );
	}else{
		var soundvalue = 50;
		localStorage.setItem( "soundValue" , soundvalue );
	}
    $( "#my-player" ).prop( 'volume', ( soundvalue / 100 ) );
	$( "#slideVolume" ).val( soundvalue );
	
	//loading
	loading_show();
});

//CHECK VIDEO

var mousemovetimeout = null;

var DEBUG = false;
var retrytimer = false;
var playedtotaltime = <?php echo $playedtimebefore; ?>;
// Direct mode: no server offset, playedtotaltime must be 0
if( $( "#my-player source" ).length && $( "#my-player source" ).attr( "src" ).indexOf( "mode=direct" ) !== -1 ){ playedtotaltime = 0; }
var playererrors = 0;
var playererrors_max = 3;
var playerskiptime = <?php echo $PLAYERSKIPTIME; ?>;
var totaltime = parseInt( '<?php echo $time; ?>' );

//SLIDE UPDATE
function slideUpdate( nowtime ){
    var pos = parseInt( ( 100 * nowtime ) / totaltime );
    $( '.dSliderInner' ).css( 'width', pos + '%');
}

//TIME CHANGE

function playerTimeChanged( seconds, audiotrack, subtrack, quality ){
	audiotrack = typeof audiotrack !== 'undefined' ? audiotrack : audiotracknow;
	subtrack = typeof subtrack !== 'undefined' ? subtrack : subtracknow;
	quality = typeof quality !== 'undefined' ? quality : qualitynow;
	if( DEBUG ) console.log('changeTime ' + $( '#my-player' ).currentTime );
	// Direct/Remux mode: no server-side offset, playedtotaltime is always 0
	// Use native browser seek (browser sends HTTP Range request automatically)
	// Remux mode also serves a cached MP4 with full Range support
	// 检测方式：通过 URL 参数判断（modestr 数组可能不包含 remux）
	var _curSrc = $( "#my-player source" ).attr( 'src' ) || '';
	var _isNativeSeek = ( _curSrc.indexOf( 'mode=direct' ) !== -1 || _curSrc.indexOf( 'mode=remux' ) !== -1 );
	if( _isNativeSeek ){
		playedtotaltime = 0;
		document.getElementById( 'my-player' ).currentTime = seconds;
		return;
	}
	playedtotaltime = seconds;
	var url = $( "#my-player source" ).attr( 'src' );
	if( typeof url != 'undefined' ){
		url = url.substring( 0, url.indexOf( '&timeplayed=' ) );
		url += '&timeplayed=' + seconds + '&audiotrack=' + audiotrack + '&subtrack=' + subtrack + '&quality=' + quality;
		//playerTimeBarSelectPlayed( seconds );
		if( DEBUG ) console.log( 'attr: ' + $( "#my-player source" ).attr( 'src') );
		document.getElementById( 'my-player' ).load();
		$( "#my-player source" ).attr( 'src', url );
		document.getElementById( 'my-player' ).load();
		//reset subs line
		subtrack_lastline = 0;
	}
}

//PLAYER BARS

function secondsTimeSpanToHMS(seconds) {

    var sec_num = parseInt(seconds, 10);
    var hours   = Math.floor(sec_num / 3600);
    var minutes = Math.floor((sec_num - (hours * 3600)) / 60);
    var seconds = sec_num - (hours * 3600) - (minutes * 60);

    if (hours   < 10) {hours   = "0"+hours;}
    if (minutes < 10) {minutes = "0"+minutes;}
    if (seconds < 10) {seconds = "0"+seconds;}
    
    var result = '';
    if( hours > 0 ){
		result = hours+':'+minutes+':'+seconds;
    }else{
		result = minutes+':'+seconds;
    }
    
    return result;
}

//SOUND

function playerSoundChanged( value ){
    var value2 = value / 100;
	$( "#my-player" ).prop( 'volume', value2 );
	if( localStorage ){
		localStorage.setItem( "soundValue" , value );
	}
}

//AUDIO TRACKS

var audiotracknow = <?php echo $AUDIOTRACK; ?>;
function setAudioTrack( e, track ){
    $( '.playerControlAudioList .playerBoxBarControlsButton' ).removeClass( 'playerBoxBarControlsButtonSelected' );
    $( e ).addClass( 'playerBoxBarControlsButtonSelected' );
	audiotracknow = track;
	// 对于 direct/remux 模式：使用浏览器原生 AudioTrackList API 切换音轨
	// MP4 文件包含所有音轨，浏览器可以直接切换，无需重新请求服务器
	// 对于其他模式（mp4/hw/webm等）：需要服务器重新转码，使用 playerTimeChanged
	var player = document.getElementById( 'my-player' );
	var isNativeMode = false;
	// 通过 URL 参数检测当前模式（modestr 数组可能不包含 remux）
	var curSrc = $( "#my-player source" ).attr( 'src' ) || ( player ? player.src : '' );
	if( curSrc.indexOf( 'mode=direct' ) !== -1 || curSrc.indexOf( 'mode=remux' ) !== -1 ){
		isNativeMode = true;
	}
	if( isNativeMode && player && player.audioTracks && player.audioTracks.length > 1 ){
		// track 参数是 1-based（来自 ffprobe），AudioTrackList 是 0-based
		var targetIndex = track - 1;
		for( var i = 0; i < player.audioTracks.length; i++ ){
			player.audioTracks[i].enabled = ( i === targetIndex );
		}
		if( DEBUG ) console.log( 'Audio track switched via native API to index=' + targetIndex );
	} else {
		// 服务端转码模式：重新请求服务器（切换音轨需要重新编码）
		playerTimeChanged( playedtotaltime, audiotracknow, subtracknow, qualitynow );
	}
}

//SUBS TRACKS INVIDEO (imagesubs)

var subtracknow = -1;
function setSubTrack( e, track ){
	$( '#subsDropdownMenu a' ).removeClass( 'subs-selected' );
    $( e ).addClass( 'subs-selected' );
	subtracknow = track;
	//quit text subbed
	subtrack = false;
    subtrack_data = false;
    subtrack_lastline = false;
    $( '#subOverlay' ).html( '' );
	playerTimeChanged( playedtotaltime, audiotracknow, subtracknow, qualitynow );
	// Close dropdown
	var menu = document.getElementById( 'subsDropdownMenu' );
	if( menu ) menu.style.display = 'none';
}

//QUALITY

var qualitynow = 'sd';
function setQuality(){
	if( qualitynow == 'sd' ){
		qualitynow = 'hd';
	}else{
		qualitynow = 'sd';
	}
	
	playerTimeChanged( playedtotaltime, audiotracknow, subtracknow, qualitynow );
}

//FULLSCREEN

function toggleFullScreen() {
  var videoEl = document.getElementById('my-player');
  var isIOS = /iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
  
  if (isIOS && videoEl && videoEl.webkitEnterFullscreen) {
    // iOS: use native fullscreen (WebVTT track subtitles will show)
    videoEl.webkitEnterFullscreen();
    return;
  }
  
  if (!document.fullscreenElement &&
      !document.mozFullScreenElement && !document.webkitFullscreenElement && !document.msFullscreenElement ) {
    if (document.documentElement.requestFullscreen) {
      document.documentElement.requestFullscreen();
    } else if (document.documentElement.msRequestFullscreen) {
      document.documentElement.msRequestFullscreen();
    } else if (document.documentElement.mozRequestFullScreen) {
      document.documentElement.mozRequestFullScreen();
    } else if (document.documentElement.webkitRequestFullscreen) {
      document.documentElement.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT);
    }
  } else {
    if (document.exitFullscreen) {
      document.exitFullscreen();
    } else if (document.msExitFullscreen) {
      document.msExitFullscreen();
    } else if (document.mozCancelFullScreen) {
      document.mozCancelFullScreen();
    } else if (document.webkitExitFullscreen) {
      document.webkitExitFullscreen();
    }
  }
}

//VIDEO ERROR

function send_video_error(){
    //Stop Player and info
    $( "#my-player" ).remove();
    //send error
    var url = '?r=r&action=playervideoerror&idmedia=<?php echo $IDMEDIA; ?>';
    var data = [];
    show_msgbox( url, data );
    loading_hide();
}

//SUBS BASIC

var subtrack = false;
var subtrack_data = false;
//datasub = array( 'timestart', 'timeend', 'text' )
function updateSubtitleTrack( url ){
    var video = document.getElementById( 'my-player' );
    if( !video ) return;
    // Remove existing tracks
    var existing = video.querySelectorAll( 'track' );
    for( var i = 0; i < existing.length; i++ ){
        video.removeChild( existing[ i ] );
    }
    // Only add native track on iOS for fullscreen/PiP support
    var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    if( !isIOS ) return;
    // Add WebVTT track for native iOS fullscreen support
    var track = document.createElement( 'track' );
    track.kind = 'subtitles';
    track.src = url + '&format=vtt';
    track.srclang = 'zh';
    track.label = '字幕';
    track.default = true;
    video.appendChild( track );
}

function clearSubTrack( e ){
    subtrack = false;
    subtrack_data = false;
    subtrack_lastline = 0;
    $( '#subOverlay' ).html( '' );
    // Remove native track if exists
    var player = document.getElementById( 'my-player' );
    if( player ){
        var tracks = player.querySelectorAll( 'track' );
        for( var i = 0; i < tracks.length; i++ ){
            tracks[i].parentNode.removeChild( tracks[i] );
        }
    }
    // Close dropdown
    var menu = document.getElementById( 'subsDropdownMenu' );
    if( menu ) menu.style.display = 'none';
    // Update selected state
    $( '#subsDropdownMenu a' ).removeClass( 'subs-selected' );
    $( e ).addClass( 'subs-selected' );
}

function loadSubTrack( e, id ){
    //subsTracks
    $( '#subsDropdownMenu a' ).removeClass( 'subs-selected' );
    $( e ).addClass( 'subs-selected' );
    subtrack = id;
    var url = '?r=r&action=playsubs&idmedia=<?php echo $IDMEDIA; ?>&subtrack=' + id;
    updateSubtitleTrack( url );
    $.getJSON( url )
    .done( function( data ){
        if( DEBUG ) console.log( 'SUBS LOAD TRACK: ' + url );
        subtrack_data = data;
    });
    // Close dropdown
    var menu = document.getElementById( 'subsDropdownMenu' );
    if( menu ) menu.style.display = 'none';
}

function loadSubTrackExt( e, filename ){
    $( '#subsDropdownMenu a' ).removeClass( 'subs-selected' );
    $( e ).addClass( 'subs-selected' );
    subtrack = true;
    subtrack_data = false;
    subtrack_lastline = 0;
    $( '#subOverlay' ).html( '' );
    if( typeof DEBUG !== 'undefined' && DEBUG ) console.log( 'SUBS EXT CLICK: ' + filename );
    var url = '?r=r&action=playsubs&idmedia=<?php echo $IDMEDIA; ?>&extsubfile=' + encodeURIComponent( filename );
    updateSubtitleTrack( url );
    if( typeof DEBUG !== 'undefined' && DEBUG ) console.log( 'SUBS EXT URL: ' + url );
    $.getJSON( url )
    .done( function( data ){
        if( typeof DEBUG !== 'undefined' && DEBUG ){
            console.log( 'SUBS EXT RESPONSE: ', data );
            if( data ) console.log( 'SUBS EXT DATA LEN: ' + ( data.length || Object.keys(data).length ) );
        }
        subtrack_data = data;
    })
    .fail( function( jqXHR, textStatus, errorThrown ){
        console.log( 'SUBS EXT AJAX ERROR: ' + textStatus + ' - ' + errorThrown );
    });
    // Close dropdown
    var menu = document.getElementById( 'subsDropdownMenu' );
    if( menu ) menu.style.display = 'none';
}

var subtrack_lastline = 0;
function formatSubText( text ){
    var lines = text.split( '<br>' );
    var result = '';
    for( var i = 0; i < lines.length; i++ ){
        // Strip HTML tags from ASS→SRT conversion
        var line = lines[ i ].replace( /<[^>]*>/g, '' );
        line = line.replace( /&nbsp;/g, ' ' ).trim();
        if( line.length == 0 ) continue;
        // Detect if line contains CJK (Chinese/Japanese/Korean) characters
        if( /[\u4e00-\u9fff\u3400-\u4dbf\uf900-\ufaff]/.test( line ) ){
            result += '<div class="subLineCJK">' + line + '</div>';
        }else{
            result += '<div class="subLineEN">' + line + '</div>';
        }
    }
    return result;
}

function show_subs_timed( timenow ){
    var added = false;
    if( typeof DEBUG !== 'undefined' && DEBUG ) console.log( 'SUBS CHECK TEXT: ' + timenow );
    $.each( subtrack_data, function( key, data ){
        if( subtrack_lastline <= parseInt( key )
        && timenow >= parseFloat( data[ 'timestart' ] )
        && timenow <= parseFloat( data[ 'timeend' ] )
        ){
            subtrack_lastline = parseInt( key );
            var newHtml = formatSubText( data[ 'text' ] );
            if( newHtml != $( '#subOverlay' ).html() ){
                $( '#subOverlay' ).html( newHtml );
            }
            added = true;
            return false;
        }
    });
    if( added == false ){
        $( '#subOverlay' ).html( '' );
    }
}

// Mode dropdown toggle
function toggleModeDropdown( e ){
    e.stopPropagation();
    var menu = document.getElementById( 'modeDropdownMenu' );
    if( menu ){
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    }
    // Close subs dropdown if open
    var subsMenu = document.getElementById( 'subsDropdownMenu' );
    if( subsMenu ){
        subsMenu.style.display = 'none';
    }
}
// Subs dropdown toggle
function toggleSubsDropdown( e ){
    e.stopPropagation();
    var menu = document.getElementById( 'subsDropdownMenu' );
    if( menu ){
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    }
    // Close mode dropdown if open
    var modeMenu = document.getElementById( 'modeDropdownMenu' );
    if( modeMenu ){
        modeMenu.style.display = 'none';
    }
}
document.addEventListener( 'click', function(){
    var menu = document.getElementById( 'modeDropdownMenu' );
    if( menu ){
        menu.style.display = 'none';
    }
    var subsMenu = document.getElementById( 'subsDropdownMenu' );
    if( subsMenu ){
        subsMenu.style.display = 'none';
    }
});



</script>

<style type='text/css'>
html, body
{
    width: 100% !important;
    height: 100% !important;
    margin: 0px !important;
    padding: 0px !important;
    border: 0px !important;
    overflow: hidden;
}
.dBaseBox{
    width: 100% !important;
    height: 100% !important;
    margin: 0px !important;
    padding: 0px !important;
    border: 0px !important;
    background-color: black !important;
}

/* Mobile portrait: stack layout vertically instead of horizontal table */
@media screen and (orientation: portrait) and (max-width: 1024px) {
    .playerBoxControls {
        display: block !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch;
        max-height: 50vh;
    }
    .playerBoxBarInfo {
        display: none !important;
    }
    .playerBoxBarControls {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
    }
    .playerBoxBarControlsTitle {
        font-size: 110% !important;
    }
    .tRow {
        display: flex !important;
        flex-wrap: wrap !important;
        justify-content: center !important;
    }
    .playerBoxBarControlsButton {
        font-size: 130% !important;
        padding: 0.3em 0.4em !important;
    }
    .basecontrols .playerBoxBarControlsButton {
        font-size: 160% !important;
        padding: 0.2em 0.5em !important;
    }
    .text120 {
        font-size: 100% !important;
    }
    .text80 {
        font-size: 90% !important;
    }
    .videoinfo {
        font-size: 80% !important;
    }
    .tbTimer {
        font-size: 90% !important;
        padding: 5px !important;
    }
    .playerBoxBarControlsVolumeSlide {
        width: 80px !important;
    }
    .subs-dropdown .subs-dropdown-menu {
        max-height: 40vh !important;
    }
    .mode-dropdown .mode-dropdown-menu {
        max-height: 40vh !important;
        overflow-y: auto !important;
    }
}
@media only screen and (max-width: 480px) {
    .subOverlay {
        font-size: 0.75em !important;
    }
}

/* Mode dropdown styles */
.mode-dropdown {
    position: relative;
    display: inline-block;
}
.mode-dropdown .mode-dropdown-menu {
    display: none;
    position: absolute;
    bottom: 100%;
    left: 0;
    background-color: #333;
    min-width: 80px;
    box-shadow: 0px -4px 8px rgba(0,0,0,0.5);
    z-index: 1000;
    border-radius: 4px;
    overflow: hidden;
}
.mode-dropdown .mode-dropdown-menu a {
    color: #fff;
    padding: 8px 12px;
    text-decoration: none;
    display: block;
    font-size: 12px;
    white-space: nowrap;
}
.mode-dropdown .mode-dropdown-menu a:hover {
    background-color: #555;
}
/* Subs dropdown styles */
.subs-dropdown {
    position: relative;
    display: inline-block;
}
.subs-dropdown .subs-dropdown-menu {
    display: none;
    position: absolute;
    bottom: 100%;
    left: 0;
    background-color: #333;
    min-width: 120px;
    max-height: 60vh;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    box-shadow: 0px -4px 8px rgba(0,0,0,0.5);
    z-index: 1000;
    border-radius: 4px;
}
.subs-dropdown .subs-dropdown-menu a {
    color: #fff;
    padding: 8px 12px;
    text-decoration: none;
    display: block;
    font-size: 12px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 70vw;
}
.subs-dropdown .subs-dropdown-menu a:hover {
    background-color: #555;
}
.subs-dropdown .subs-dropdown-menu a.subs-selected {
    background-color: #797979;
}
</style>
	
	<video id="my-player" class="videoplayer"
	width="100%" height="100%"
	preload="auto"
	poster="<?php echo $urllandscape; ?>"
	autoplay
	playsinline
	webkit-playsinline
	>
        <?php
            $num = 1;
            $vtotal = count( $CODECORDER );
            $session = '&PHPSESSION=' . session_id();
            
            // Generate external player URL for URL Scheme
            $externalVideoUrl = getURLBase() . '?r=r&action=playtime&mode=remux&idmedia=' . $IDMEDIA . '&timeplayed=0&audiotrack=' . $AUDIOTRACK . $session;
            $externalVideoUrlEncoded = urlencode( $externalVideoUrl );
            $urlNPlayer = 'nplayer://play?url=' . $externalVideoUrlEncoded;
            $urlVLC = 'vlc://' . $externalVideoUrlEncoded;
            $urlInfuse = 'infuse://x-callback-url/play?url=' . $externalVideoUrlEncoded;
            $urlOPlayer = 'oplayer://' . $externalVideoUrlEncoded;
            $urlOPlayerHD = 'oplayerhd://' . $externalVideoUrlEncoded;
            
            foreach( $CODECORDER AS $urlident => $videoheader ){
                if( $num == $vtotal ){
                    $extra_vdata = " data-last='1'";
                }else{
                    $extra_vdata = "";
                }
                // HLS uses application/vnd.apple.mpegurl directly, not video/*
                $sourceType = ($urlident === 'hls') ? $videoheader : ("video/" . $videoheader);
                // HLS mode: timeplayed must be 0 to prevent -ss in FFmpeg command
                // HLS protocol handles seek natively, -ss would cause duplicate FFmpeg processes
                $hlsTimeplayed = ($urlident === 'hls') ? 0 : $playedtimebefore;
        ?>
        <source id='my-player-source' src="?r=r&action=playtime&mode=<?php echo $urlident; ?>&idmedia=<?php echo $IDMEDIA; ?>&timeplayed=<?php echo $hlsTimeplayed; ?>&audiotrack=<?php echo $AUDIOTRACK; ?><?php echo $session; ?>" type="<?php echo $sourceType; ?>" <?php echo $extra_vdata; ?> preload="auto" >
        <?php
                $num++;
            }
        ?>
        Your browser does not support the video tag.
	</video>
	
    <div id="subOverlay" class="subOverlay">
        
    </div>
	
	<div id='playerBoxC' class='playerBoxControls'>
        <div class='playerBoxBarInfo'>
            <img class='playerInfoImg' src='<?php echo $urllogo; ?>' title='<?php echo $title; ?>' />
        </div>
        <div class='playerBoxBarControls'>
            <div class='playerBoxBarControlsTitle'>
                <span><?php echo $title; ?> <?php echo $year; ?> &#x2605;<?php echo $rating; ?></span>
            </div>
            <div class='playerBoxBarControlsTimeBar'>
                <div class='tRow'>
                    <div class='tbTimer'>
                        <span class='playerControlTimeNowData'>00:00</span>/<?php echo secondsToTimeFormat( $time, TRUE ); ?>
                    </div>
                    <div class='tbSlider'>
                        <input class='hidden playerBoxBarControlsTimeBarSlide slider' id="slideTime" type="range" min="0" max="<?php echo $time; ?>" step="1" value="<?php echo $playedtimebefore; ?>" onchange="playerTimeChanged( this.value ); return false;" />
                        <div class='dSlider'>
                            <div class='dSliderInner'>
                                <span class='playerControlTimeNowData'>00:00</span>&nbsp;
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <hr />
            <div class='playerBoxBarControlsActions basecontrols'>
                <div class='tRow'>
                    <?php if( strlen( $backfileinfo ) > 0 ){ ?>
                    <div class='playerBoxBarControlsButton'><a href='<?php echo $backfileinfo; ?>' title='Next' id='aBackFile'>&#x23ee;</a></div>
                    <?php } ?>
                    <div id='playerControlPlayBack' class='playerBoxBarControlsButton'>&#9194;</div>
                    <div id='playerControlPause' class='playerBoxBarControlsButton'>&#10073;&#10073;</div>
                    <div id='playerControlPlay' class='playerBoxBarControlsButton'>&#x25B7;</div>
                    <div id='playerControlStop' class='playerBoxBarControlsButton'>&#9724;</div>
                    <div id='playerControlPlayFor' class='playerBoxBarControlsButton'>&#9193;</div>
                    <?php if( strlen( $nextfileinfo ) > 0 ){ ?>
                    <div class='playerBoxBarControlsButton'><a href='<?php echo $nextfileinfo; ?>' title='Next' id='aNextFile'>&#x23ed;</a></div>
                    <?php } ?>
                    <div id='playerControlQuality' class='playerBoxBarControlsButton' title='Quality' onclick='return setQuality();'>SD</div>
                    <div id='playerControlMode' class='playerBoxBarControlsButton mode-dropdown' title='点击切换播放模式'>
                        <span onclick='toggleModeDropdown(event)'><?php echo $currentModeLabel; ?></span>
                        <div class='mode-dropdown-menu' id='modeDropdownMenu'>
                            <a href='<?php echo $urlModeDirect; ?>'>&#x25B7;&nbsp;直连</a>
                            <a href='<?php echo $urlModeRemux; ?>'>&#x25B7;&nbsp;Remux</a>
                            <a href='<?php echo $urlModeHw; ?>'>&#x25B7;&nbsp;硬解</a>
                            <a href='<?php echo $urlModeMp4; ?>'>&#x25B7;&nbsp;MP4</a>
                            <a href='<?php echo $urlModeHls; ?>'>&#x25B7;&nbsp;HLS (iPad)</a>
                        </div>
                    </div>
                    <div id='playerControlFullScreenIco' class='playerBoxBarControlsButton' title='Full Screen' onclick="toggleFullScreen();">&#9633;</div>
                    <div id='playerControlVolume' class='playerBoxBarControlsButton' title='Volume'>
                        &#x266A;
                    </div>
                    <div id='playerControlVolume' class='playerBoxBarControlsButton' title='Volume'>
                        <input class='playerBoxBarControlsVolumeSlide slider' id="slideVolume" type="range" min="0" max="100" step="5" value="50" 
                            onchange="playerSoundChanged( this.value ); return false;" />
                    </div>
                    <div class='playerBoxBarControlsButton videoinfo'>
                        <?php echo $videoinfo[ 'width' ]; ?>x<?php echo $videoinfo[ 'height' ]; ?> 
                        <?php echo $videoinfo[ 'codec' ]; ?> <?php echo $videoinfo[ 'acodec' ]; ?>
                        <?php if( strlen( $forcedMode ) > 0 ){ echo '| ' . strtoupper( $forcedMode ); } ?>
                    </div>
                </div>
            </div>
            <hr />
            <div class='playerBoxBarControlsActions playerControlExternal'>
                <div class='tRow'>
                    <div class='playerBoxBarControlsButton text80' title='外部播放器打开'>
                        外部播放器:
                    </div>
                    <div class='playerBoxBarControlsButton text80' onclick="window.location.href='<?php echo $urlNPlayer; ?>';" title='NPlayer 播放（支持全格式软解）'>NPlayer</div>
                    <div class='playerBoxBarControlsButton text80' onclick="window.location.href='<?php echo $urlVLC; ?>';" title='VLC 播放'>VLC</div>
                    <div class='playerBoxBarControlsButton text80' onclick="window.location.href='<?php echo $urlInfuse; ?>';" title='Infuse 播放'>Infuse</div>
                    <div class='playerBoxBarControlsButton text80' onclick="window.location.href='<?php echo $urlOPlayer; ?>';" title='OPlayer 播放'>OPlayer</div>
                    <div class='playerBoxBarControlsButton text80' onclick="window.location.href='<?php echo $urlOPlayerHD; ?>';" title='OPlayer HD 播放'>OPlayerHD</div>
                </div>
            </div>
            <hr />
                <?php
                    if( is_array( $audiolist ) 
                    && count( $audiolist ) > 1
                    ){
                ?>
            <div class='playerBoxBarControlsActions playerControlAudioList'>
                <div class='tRow'>
                    <div class='playerBoxBarControlsButton text120'>
                        &#x266B; Audio:
                    </div>
                            <?php
                                //first normaly video
                                $num = 1;
                                foreach( $audiolist AS $al ){
                                    if( $AUDIOTRACK == $num ){
                                        $atselected = 'playerBoxBarControlsButtonSelected';
                                    }else{
                                        $atselected = '';
                                    }
                            ?>
                    <div class='playerBoxBarControlsButton text120 <?php echo $atselected; ?>' onclick='setAudioTrack( this, <?php echo $num; ?> );'><?php echo $al; ?></div>
                            <?php
                                    $num++;
                                }
                                
                            ?>
                    <?php
                        }
                    ?>
                    &nbsp;&nbsp;&nbsp;&nbsp;
                    <?php
                        if( (
                            is_array( $subslist )
                            && count( $subslist ) > 0
                            )
                            ||
                            (
                            is_array( $subslistv )
                            && count( $subslistv ) > 0
                            )
                            ||
                            (
                            is_array( $subslistext )
                            && count( $subslistext ) > 0
                            )
                        ){
                    ?>
                    <div class='playerBoxBarControlsButton text120 subs-dropdown' title='点击选择字幕'>
                        <span onclick='toggleSubsDropdown(event)'>&#x225F; 字幕</span>
                        <div class='subs-dropdown-menu' id='subsDropdownMenu'>
                            <a href='javascript:void(0)' onclick='clearSubTrack(this)'>&#x2716; 关闭字幕</a>
                            <?php
                                foreach( $subslist AS $l => $al ){
                            ?>
                            <a href='javascript:void(0)' onclick='loadSubTrack( this, <?php echo $l; ?> );' title='<?php echo htmlspecialchars( $al, ENT_QUOTES ); ?>'><?php echo htmlspecialchars( $al, ENT_QUOTES ); ?></a>
                            <?php
                                }
                            ?>
                            <?php
                                foreach( $subslistv AS $l => $al ){
                                    //hardsubs start from sub 0, fixed on ffprobe_get_data
                            ?>
                            <a href='javascript:void(0)' onclick='setSubTrack( this, <?php echo $l; ?> );' title='<?php echo htmlspecialchars( $al, ENT_QUOTES ); ?>'><?php echo htmlspecialchars( $al, ENT_QUOTES ); ?></a>
                            <?php
                                }
                            ?>
                            <?php
                                foreach( $subslistext AS $l => $al ){
                            ?>
                            <a href='javascript:void(0)' onclick='loadSubTrackExt( this, "<?php echo htmlspecialchars( $al, ENT_QUOTES ); ?>" );' title='<?php echo htmlspecialchars( $al, ENT_QUOTES ); ?>'>[EXT] <?php echo htmlspecialchars( $al, ENT_QUOTES ); ?></a>
                            <?php
                                }
                            ?>
                        </div>
                    </div>
                        <?php
                            }
                        ?>
                </div>
            </div>
        </div>
	</div>
	
<?php 
    }
?>
