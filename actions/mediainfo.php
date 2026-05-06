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
	
	if( $IDMEDIAINFO > 0
	&& ( $MEDIAINFO = sqlite_mediainfo_getdata( $IDMEDIAINFO ) ) != FALSE 
	&& is_array( $MEDIAINFO )
	&& count( $MEDIAINFO ) > 0
	){
        $MEDIAINFO = $MEDIAINFO[ 0 ];
        $HTMLRESULT = '';
	}elseif( $IDMEDIA > 0
	&& ( $m = sqlite_media_getdata( $IDMEDIA ) ) != FALSE 
	&& is_array( $m )
	&& count( $m ) > 0
	&& array_key_exists( 0, $m )
	&& array_key_exists( 'idmediainfo', $m[ 0 ] )
	&& $m[ 0 ][ 'idmediainfo' ] > 0
	&& ( $MEDIAINFO = sqlite_mediainfo_getdata( $m[ 0 ][ 'idmediainfo' ] ) ) != FALSE 
	&& is_array( $MEDIAINFO )
	&& count( $MEDIAINFO ) > 0
	){
        $MEDIAINFO = $MEDIAINFO[ 0 ];
        $IDMEDIAINFO = $MEDIAINFO[ 'idmediainfo' ];
        $HTMLRESULT = '';
	}else{
        $MEDIAINFO = FALSE;
        $HTMLRESULT = get_msg( 'DEF_NOTEXIST' );
	}
	
	if( strlen( $HTMLRESULT ) > 0 ){
        echo $HTMLRESULT;
	}else{
        //EXTRA VARS
        $urlposter = getURLImg( $IDMEDIA, $IDMEDIAINFO, 'poster' );
        $urlplayer = getURLPlayer( $IDMEDIA, $IDMEDIAINFO );
        $urlplayerhw = getURLPlayer( $IDMEDIA, $IDMEDIAINFO ) . '&mode=hw';
        $urlplayerremux = getURLPlayer( $IDMEDIA, $IDMEDIAINFO ) . '&mode=remux';
        $urlplayerhls = getURLPlayer( $IDMEDIA, $IDMEDIAINFO ) . '&mode=hls';
        $urlplayersafe = getURLPlayerSafe( $IDMEDIA, $IDMEDIAINFO );
        $urldowload = getURLDownload( $IDMEDIA, $IDMEDIAINFO );
        $urlchapters = getURLChapterList( $IDMEDIA, $IDMEDIAINFO );;
        $urllandscape = getURLImg( $IDMEDIA, $IDMEDIAINFO, 'landscape' );
        $duration = $MEDIAINFO[ 'runtime' ];
        if( (int)$MEDIAINFO[ 'season' ] > 0 ){
            $SHOW_CHAPTERS = TRUE;
        }else{
            $SHOW_CHAPTERS = FALSE;
        }
        $nextfileinfo = getURLNextInfo( FALSE, $IDMEDIAINFO );
        $ftitle = $MEDIAINFO[ 'title' ];
        $css_extra = '';
        $searchimages = getURLImgSearch( $IDMEDIAINFO );
        
        // Build external player streaming URL
        $extSession = '&PHPSESSION=' . session_id();
        $extStreamUrl = getURLBase() . '?r=r&action=playtime&mode=remux';
        if( $IDMEDIA > 0 ){
            $extStreamUrl .= '&idmedia=' . $IDMEDIA;
        }else{
            $extStreamUrl .= '&idmediainfo=' . $IDMEDIAINFO;
        }
        $extStreamUrl .= '&timeplayed=0' . $extSession;
        $extStreamUrlEncoded = urlencode( $extStreamUrl );
        $urlNPlayer = 'nplayer://play?url=' . $extStreamUrlEncoded;
        $urlVLC = 'vlc://' . $extStreamUrlEncoded;
        $urlInfuse = 'infuse://x-callback-url/play?url=' . $extStreamUrlEncoded;
        $urlOPlayer = 'oplayer://' . $extStreamUrlEncoded;
        $urlOPlayerHD = 'oplayerhd://' . $extStreamUrlEncoded;

// ============================================================
// 检测文件是否为 MKV（用于预缓存 REMUX 功能）
// 同时处理 idmedia 和 idmediainfo 两种访问方式
// ============================================================
$is_mkv = false;
$file_name = '';
$media_id = (int)$IDMEDIA;
$media_file = '';

// 方案1: 通过 $IDMEDIA 直接获取
if( $media_id > 0 ){
    if( ( $mi_check = sqlite_media_getdata( $media_id ) ) != FALSE
        && is_array( $mi_check )
        && count( $mi_check ) > 0
        && file_exists( $mi_check[ 0 ][ 'file' ] )
    ){
        $media_file = $mi_check[ 0 ][ 'file' ];
    }
}

// 方案2: 如果 $IDMEDIA 无效，通过 $IDMEDIAINFO 查找关联文件
if( empty( $media_file ) && $IDMEDIAINFO > 0 ){
    if( ( $medialist_check = sqlite_media_getdata_mediainfo( $IDMEDIAINFO, 1 ) ) != FALSE
        && is_array( $medialist_check )
        && count( $medialist_check ) > 0
    ){
        foreach( $medialist_check AS $row ){
            if( isset( $row[ 'file' ] ) && file_exists( $row[ 'file' ] ) ){
                $media_file = $row[ 'file' ];
                $media_id = (int)$row[ 'idmedia' ];
                break;
            }
        }
    }
}

// 检查文件扩展名（包含常见变体）
if( !empty( $media_file ) ){
    $file_ext = strtolower( pathinfo( $media_file, PATHINFO_EXTENSION ) );
    $is_mkv = ( $file_ext == 'mkv' || $file_ext == 'matroska' );
}

// 供JavaScript使用的media ID（确保不为0）
$precache_media_id = $media_id > 0 ? $media_id : (int)$IDMEDIA;

?>

<style type='text/css'>
.boxInfoOverlayBg{
    background-image: url("<?php echo $urllandscape ?>");
    /* position:fixed; */
}
</style>

<script>	
$(function () {
    //IMG LAZYLOAD
    $("img.lazy").Lazy();
    
    // 清除旧页面的remux轮询（页面间导航时）
    if( typeof remuxPollTimer !== 'undefined' && remuxPollTimer ){
        clearInterval( remuxPollTimer );
        remuxPollTimer = null;
    }
    if( typeof remuxMediaId !== 'undefined' ){
        remuxMediaId = 0;
    }
});
<?php 
	if( check_user_admin()
	){
?>
function infovideo_delete( element ){
	var url = '?r=r&action=mediadeletefile';
	var data = { 
		"idmedia": element
	};
	show_msg( url, data, 'result' );
}
<?php } ?>

function infovideo_playlater( idmedia, idmediainfo ){
	var url = '?r=r&action=mediaplaylater';
	var data = { 
		"idmedia": idmedia,
		"idmediainfo": idmediainfo,
	};
	show_msg( url, data, 'result' );
}

// ============================================================
// MKV → MP4 预缓存功能
// ============================================================
var remuxPollTimer = null;
var remuxMediaId = 0;

function startRemuxCache( idmedia ){
	remuxMediaId = idmedia;
	
	// 显示处理中状态
	document.getElementById('remux_status_idle').style.display = 'none';
	document.getElementById('remux_status_processing').style.display = 'block';
	document.getElementById('remux_status_done').style.display = 'none';
	document.getElementById('remux_status_failed').style.display = 'none';
	document.getElementById('remux_progress_text').innerText = '启动中...';
	document.getElementById('remux_progress_bar').style.width = '5%';
	
	// 发送请求触发后台转封装
	var url = '?r=r&action=playtime&mode=remux&idmedia=' + idmedia;
	var req = new XMLHttpRequest();
	req.open('GET', url, true);
	req.onload = function(){
		// 请求完成（无论结果如何，都开始轮询状态）
		if( remuxPollTimer ){
			clearInterval( remuxPollTimer );
		}
		checkRemuxStatus( idmedia );
		remuxPollTimer = setInterval( function(){ checkRemuxStatus( remuxMediaId ); }, 3000 );
	};
	req.onerror = function(){
		// 请求失败，仍然开始轮询
		if( remuxPollTimer ){
			clearInterval( remuxPollTimer );
		}
		checkRemuxStatus( idmedia );
		remuxPollTimer = setInterval( function(){ checkRemuxStatus( remuxMediaId ); }, 3000 );
	};
	req.send();
}

function checkRemuxStatus( idmedia ){
	var url = '?r=r&action=check_remux&idmedia=' + idmedia;
	var req = new XMLHttpRequest();
	req.open('GET', url, true);
	req.onload = function(){
		if( req.status == 200 ){
			try{
				var status = JSON.parse( req.responseText );
				updateRemuxUI( status );
			}catch(e){
				// JSON解析失败
			}
		}
	};
	req.send();
}

function updateRemuxUI( status ){
	// 先全部隐藏
	document.getElementById('remux_status_idle').style.display = 'none';
	document.getElementById('remux_status_processing').style.display = 'none';
	document.getElementById('remux_status_done').style.display = 'none';
	document.getElementById('remux_status_failed').style.display = 'none';
	
	if( status.status == 'done' ){
		// 完成
		if( remuxPollTimer ){
			clearInterval( remuxPollTimer );
			remuxPollTimer = null;
		}
		document.getElementById('remux_status_done').style.display = 'inline-block';
		document.getElementById('remux_done_size').innerText = status.size_fmt || '';
		if( status.elapsed > 0 ){
			var mins = Math.floor( status.elapsed / 60 );
			var secs = status.elapsed % 60;
			document.getElementById('remux_done_time').innerText = mins + '分' + secs + '秒';
		}
	}else if( status.status == 'processing' ){
		// 处理中
		document.getElementById('remux_status_processing').style.display = 'inline-block';
		
		var elapsed = status.elapsed || 0;
		var mins = Math.floor( elapsed / 60 );
		var secs = elapsed % 60;
		var sizeText = status.size_fmt ? ('已生成 ' + status.size_fmt) : '';
		document.getElementById('remux_progress_text').innerText = sizeText + ' (' + mins + '分' + secs + '秒)';
		
		// 使用进度条展示时间流逝（非精确进度）
		var maxWait = 15 * 60; // 最长等15分钟
		var pct = Math.min( 95, Math.round( (elapsed / maxWait) * 100 ) );
		document.getElementById('remux_progress_bar').style.width = pct + '%';
	}else if( status.status == 'stale' ){
		// 陈旧文件（FFmpeg被杀了），显示重试
		if( remuxPollTimer ){
			clearInterval( remuxPollTimer );
			remuxPollTimer = null;
		}
		document.getElementById('remux_status_failed').style.display = 'inline-block';
	}else{
		// idle - 未开始转换，显示开始按钮
		document.getElementById('remux_status_idle').style.display = 'inline-block';
	}
}

// 页面加载后检查该媒体是否已有缓存
$(function(){
	if( <?php echo $precache_media_id; ?> > 0 ){
		remuxMediaId = <?php echo $precache_media_id; ?>;
		checkRemuxStatus( remuxMediaId );
	}
});

// 删除 remux 缓存
function deleteRemuxCache( idmedia ){
	if( !confirm( '确定要删除缓存的 MP4 文件吗？删除后需要重新转封装。' ) ){
		return;
	}
	loading_show();
	$.getJSON( '?r=r&action=remux_delete&idmedia=' + idmedia )
	.done( function( data ){
		loading_hide();
		if( data.success ){
			msgbox( '缓存已删除' );
			// 切回空闲状态
			document.getElementById('remux_status_idle').style.display = 'inline-block';
			document.getElementById('remux_status_processing').style.display = 'none';
			document.getElementById('remux_status_done').style.display = 'none';
			document.getElementById('remux_status_failed').style.display = 'none';
		}else{
			msgbox( '删除失败: ' + ( data.message || '未知错误' ) );
		}
	})
	.fail( function(){
		loading_hide();
		msgbox( '请求失败，请重试' );
	});
}


</script>

<div class='boxInfo'>
    
    <br />
	<table class='tListInfo'>
        <tr class='tListInfoTRImgMS'>
            <td colspan='100'>
                <div class='tListInfoImgMS'>
                    <img class='listElementImgMS listElementImgInfoPoster <?php echo $css_extra; ?>' src='<?php echo $urlposter; ?>' 
                    title='<?php $f = 'title'; if( array_key_exists( $f, $MEDIAINFO ) && is_string( $MEDIAINFO[ $f ] ) ) echo $MEDIAINFO[ $f ]; ?>' 
                    />
				</div>
            </td>
        </tr>
		<tr>
			<td rowspan='12' class='tListInfoTDImg'>
                <div class='tListInfoImg'>
                    <img class='listElementImg listElementImgInfoPoster <?php echo $css_extra; ?>' src='<?php echo $urlposter; ?>' 
                    title='<?php $f = 'title'; if( array_key_exists( $f, $MEDIAINFO ) && is_string( $MEDIAINFO[ $f ] ) ) echo $MEDIAINFO[ $f ]; ?>' 
                    />
				</div>
			</td>
			<th class='tListInfoTitle'>
                <div>
                    &nbsp;&nbsp;
                    <?php $f = 'title'; if( array_key_exists( $f, $MEDIAINFO ) ) echo $MEDIAINFO[ $f ]; ?>
                    &nbsp;&nbsp;
                    <?php $f = 'season'; if( array_key_exists( $f, $MEDIAINFO ) && $MEDIAINFO[ $f ] > 0 ) echo sprintf( '%02d', $MEDIAINFO[ $f ] ) . 'x'; ?><?php $f = 'episode'; if( array_key_exists( $f, $MEDIAINFO ) && $MEDIAINFO[ $f ] > 0 ) echo sprintf( '%02d', $MEDIAINFO[ $f ] ); ?>
                    &nbsp;&nbsp;
                    <?php $f = 'titleepisode'; if( array_key_exists( $f, $MEDIAINFO ) ) echo $MEDIAINFO[ $f ]; ?>
                </div>
			</th>
		</tr>
		<tr>
			<td>
				<span>
				<?php 
					$f = 'year'; 
					if( array_key_exists( $f, $MEDIAINFO ) ){
						$g = $MEDIAINFO[ $f ];
						echo "<a href='?action=list&search=" . urlencode( $g ) . "' title='" . $g . "'>" . $g . "</a>";
					}
				?>
				</span>
				&nbsp;
				<span><?php echo $duration; ?> mins</span>
				&nbsp;
				&#x2605;
				<span><?php $f = 'rating'; if( array_key_exists( $f, $MEDIAINFO ) && is_string( $MEDIAINFO[ $f ] ) ) echo $MEDIAINFO[ $f ]; ?></span>
				&nbsp;
				<span><?php $f = 'mpaa'; if( array_key_exists( $f, $MEDIAINFO ) && is_string( $MEDIAINFO[ $f ] ) ) echo $MEDIAINFO[ $f ]; ?></span>
				&nbsp;
				<span>
				<?php echo get_msg( 'INFO_TIMEEND', FALSE ); ?>
				<?php echo date( 'H:i:s', strtotime( 'NOW + ' . $duration . ' minute' ) ); ?>
				</span>
			</td>
		</tr>
		<tr>
			<td>
				&nbsp;&nbsp;
				<span style='background-color: lightgreen !important;'>
					<a href='<?php echo $urlplayer; ?>'>&#x25B7;&nbsp;<?php echo get_msg( 'INFO_PLAY', FALSE ); ?></a>
				</span>
				<span style='background-color: goldenrod !important;'>
					<a href='<?php echo $urlplayerhw; ?>' title='rkmpp硬解播放'>&#x25B7;&nbsp;硬解</a>
				</span>
				&nbsp;
				<span style='background-color: mediumslateblue !important;'>
					<a href='<?php echo $urlplayerremux; ?>' title='Remux流复制播放(零CPU)'>&#x25B7;&nbsp;Remux</a>
				</span>
				&nbsp;
				<span style='background-color: deeppink !important;'>
					<a href='<?php echo $urlplayerhls; ?>' title='HLS流媒体播放(iPad Safari)'>&#x25B7;&nbsp;HLS</a>
				</span>
				<?php if( strlen( $nextfileinfo ) > 0 ){ ?>
				&nbsp;
				<span style='background-color: orange !important;'>
					<a href='<?php echo $nextfileinfo; ?>'><?php echo get_msg( 'INFO_NEXT', FALSE ); ?></a>
				</span>
				<?php } ?>
                <?php if( $is_mkv && $precache_media_id > 0 ){ ?>
                <span style="float:right;margin-right:55px;display:inline-block;vertical-align:middle;">
                <!-- 预缓存状态区域 -->
                <span id="remux_status_idle" style="background:#F3F3F3;color:black;border:1px solid #e8e8e8;cursor:pointer;display:none;padding:3px 10px;">
                    <a style="color:black;text-decoration:none;" onclick='startRemuxCache(<?php echo $precache_media_id; ?>)' title='后台转封装 MKV → MP4'>预缓存</a>
                </span>
                <span id="remux_status_processing" style="background-color: #F9A825 !important; display:none; padding:3px 10px; white-space:nowrap;">
                    <span>&#x23F3;&nbsp;转封装中</span>
                    <span id="remux_progress_text" style="font-size:85%;"></span>
                    <span style="display:inline-block;width:60px;height:8px;background:#555;border-radius:4px;overflow:hidden;vertical-align:middle;margin-left:4px;">
                        <span id="remux_progress_bar" style="display:block;width:5%;height:100%;background:#76FF03;border-radius:4px;transition:width 2s;"></span>
                    </span>
                </span>
                <span id="remux_status_done" style="display:none; padding:0; white-space:nowrap;">
                    <span style="background:#F3F3F3;color:black;border:1px solid #e8e8e8;padding:3px 10px;">
                        <a href='<?php echo $urlplayer; ?>' style="color:black;text-decoration:none;" title='直连播放已缓存的MP4'>播放缓存 <span id="remux_done_size"></span></a>
                    </span>
                    <span style="background:#F3F3F3;color:black;border:1px solid #e8e8e8;cursor:pointer;padding:3px 10px; margin-left:4px;">
                        <a style="color:black;text-decoration:none;" onclick='deleteRemuxCache(<?php echo $precache_media_id; ?>)' title='删除缓存的MP4文件'>删除缓存</a>
                    </span>
                </span>
                <span id="remux_status_failed" style="background-color: #c62828 !important; cursor: pointer; display:none; padding:3px 10px;">
                    <a style="color:white;text-decoration:none;" onclick='startRemuxCache(<?php echo $precache_media_id; ?>)' title='重试转封装'>&#x26A0;&nbsp;失败，重试</a>
                </span>
                </span>
                <?php } ?>
			</td>
		</tr>
		<tr>
			<td>
                &nbsp;&nbsp;
                <span style='background-color: lightgreen !important;'>
                    <a href='#' onclick='infovideo_playlater( <?php if( strlen( $IDMEDIA ) > 0 ){ echo $IDMEDIA; }else{ echo '0'; }; ?>, <?php echo $MEDIAINFO[ 'idmediainfo' ]; ?> )'>&#x25B7;&nbsp;<?php echo get_msg( 'INFO_PLAY_LATER', FALSE ); ?></a>
                </span>
                <?php
                    if( defined( 'O_VIDEO_PLAYSAFE' )
                    && O_VIDEO_PLAYSAFE
                    && strlen( $urlplayersafe ) > 0 
                    ){
                ?>
                &nbsp;
                <span style='background-color: DarkSalmon !important;'>
                    <a href='<?php echo $urlplayersafe; ?>'>&#x25B7;&nbsp;<?php echo get_msg( 'INFO_PLAY_SAFE', FALSE ); ?></a>
                </span>
                <?php
                    }
                ?>
                &nbsp;
                <span style='background-color: lightblue !important;'>
                    <a href='<?php echo $urldowload; ?>'>&#x21E3;&nbsp;<?php echo get_msg( 'INFO_DOWNLOAD', FALSE ); ?></a>
                </span>
                <?php if( $SHOW_CHAPTERS ){ ?>
                &nbsp;
                <span style='background-color: DarkKhaki !important;'>
                    <a href='<?php echo $urlchapters; ?>'><?php echo get_msg( 'INFO_CHAPTERLIST', FALSE ); ?></a>
                </span>
                <?php } ?>
                &nbsp;&nbsp;
                <span style='background-color: blue !important;'>
                    <a href='<?php echo $searchimages; ?>'><?php echo get_msg( 'MENU_IMGS_SEARCH', FALSE ); ?></a>
                </span>
			</td>
		</tr>
		<tr>
            <td>
                &nbsp;&nbsp;
                <span style='background-color: purple !important;'>
                    <a href='<?php echo $urlNPlayer; ?>' title='NPlayer 全格式软解'>&#x25B7;&nbsp;NPlayer</a>
                </span>
                <span style='background-color: darkorange !important;'>
                    <a href='<?php echo $urlVLC; ?>' title='VLC 播放'>&#x25B7;&nbsp;VLC</a>
                </span>
                <span style='background-color: darkcyan !important;'>
                    <a href='<?php echo $urlInfuse; ?>' title='Infuse 播放'>&#x25B7;&nbsp;Infuse</a>
                </span>
                <span style='background-color: dimgray !important;'>
                    <a href='<?php echo $urlOPlayer; ?>' title='OPlayer 播放'>&#x25B7;&nbsp;OPlayer</a>
                </span>
            </td>
        </tr>
		<tr>
            <td>
                <div>
				<?php 
                    if( check_user_admin()
					){
                        $listidmedia = array();
                        if( $IDMEDIAINFO > 0
                        && ( $medialist = sqlite_media_getdata_mediainfo( $IDMEDIAINFO, 3 ) ) != FALSE
                        && is_array( $medialist )
                        && count( $medialist ) > 0
                        ){
                            foreach( $medialist AS $row ){
                                if( !array_key_exists( $row[ 'idmedia' ], $listidmedia ) ){
                                    $listidmedia[ $row[ 'idmedia' ] ] = $row[ 'file' ];
                                }
                                if( count( $listidmedia ) >= 3 ){
                                    break;
                                }
                            }
                        }
                        
                        foreach( $listidmedia AS $ext_idmedia => $ext_title ){
                            //add filesize
                            if( file_exists( $ext_title ) ){
                                $fs = formatSizeUnits( filesize( $ext_title ) );
                            }else{
                                $fs = get_msg( 'DEF_FILENOTEXIST', FALSE );
                            }
                            $ext_title_b = $ext_title . ' (' . $fs . ')';
				?>
				<h2><?php echo get_msg( 'INFO_FILELIST', FALSE ); ?></h2>
				<span style='background-color: lowgray !important;font-size: 80%;'>
                    <?php echo get_msg( 'WEBSCRAP_FILEDOWNLOADED', FALSE ); ?><?php echo $ext_title_b; ?>
				</span>
				&nbsp;
				<span style='background-color: red !important;'>
					 <a class='cursorPointer' href='?action=identifye&idmedia=<?php echo $ext_idmedia; ?>'><?php echo get_msg( 'MENU_IDENTIFY', FALSE ); ?></a>
				</span>
				&nbsp;
				<span style='background-color: red !important;'>
                    <a class='cursorPointer' onclick='infovideo_delete( <?php echo $ext_idmedia; ?> );return false;'><?php echo get_msg( 'MENU_DELETE', FALSE ); ?></a>
				</span>
				<br /><br />
				<?php 
                        }
                    }
                ?>
                </div>
            </td>
		</tr>
		<tr>
			<td>
				<?php 
					$f = 'genre'; 
					if( array_key_exists( $f, $MEDIAINFO ) ){
                        if( ( $e_list = explode( ',', $MEDIAINFO[ $f ] ) ) != FALSE
                        && is_array( $e_list ) 
                        ){
							foreach( $e_list AS $g ){
								echo "&nbsp;<span><a href='?action=list&search=" . urlencode( $g ) . "' title='" . $g . "'>" . $g . "</a></span>&nbsp;&bull;";
							}
						}else{
							echo "&nbsp;<span><a href='?action=list&search=" . urlencode( $MEDIAINFO[ $f ] ) . "' title='" . $MEDIAINFO[ $f ] . "'>" . $MEDIAINFO[ $f ] . "</a></span>&nbsp;&bull;";
						}
					}
				?>
			</td>
		</tr>
		<tr>
			<td>
				<div><?php $f = 'tagline'; if( array_key_exists( $f, $MEDIAINFO ) && is_string( $MEDIAINFO[ $f ] ) ) echo $MEDIAINFO[ $f ]; ?></div>
			</td>
		</tr>
		<tr>
			<td>
				<div><?php $f = 'plot'; if( array_key_exists( $f, $MEDIAINFO ) && is_string( $MEDIAINFO[ $f ] ) ) echo $MEDIAINFO[ $f ]; ?></div>
			</td>
		</tr>
		<tr>
			<td>
				<?php
                    //TODO
					if( array_key_exists( 'fileinfo', $MEDIAINFO )
					&& array_key_exists( 'streamdetails', $MEDIAINFO[ 'fileinfo' ] ) 
					&& array_key_exists( 'video', $MEDIAINFO[ 'fileinfo' ][ 'streamdetails' ] ) 
					){
						$MEDIAINFO2 = $MEDIAINFO[ 'fileinfo' ][ 'streamdetails' ][ 'video' ];
				?>
				<span><?php $f = 'height'; if( array_key_exists( $f, $MEDIAINFO2 ) && is_string( $MEDIAINFO2[ $f ] ) && $MEDIAINFO2[ $f ] > 700 ) echo 'HD'; else echo 'SD'; ?></span>
				&nbsp;
				<span><?php $f = 'codec'; if( array_key_exists( $f, $MEDIAINFO2 ) && is_string( $MEDIAINFO2[ $f ] ) ){ echo $MEDIAINFO2[ $f ]; } ?></span>
				<?php
					}
				?>
			</td>
		</tr>
		<tr>
			<td>
				<?php 
					$f = 'imdbid'; 
					if( array_key_exists( $f, $MEDIAINFO ) 
					&& strlen( $MEDIAINFO[ $f ] ) > 0
					){
						echo "&nbsp;<span><a href='" . O_ANON_LINK . "http://www.imdb.com/title/" . $MEDIAINFO[ $f ] . "' title='" . $ftitle . "' target='_blank'>IMDb</a></span>&nbsp";
					}
					$f = 'imdb'; 
					if( array_key_exists( $f, $MEDIAINFO ) 
					&& strlen( $MEDIAINFO[ $f ] ) > 0
					){
						echo "&nbsp;<span><a href='" . O_ANON_LINK . "" . $MEDIAINFO[ $f ] . "' title='" . $ftitle . "' target='_blank'>IMDb</a></span>&nbsp";
					}
					$f = 'tmdbid'; 
					if( array_key_exists( $f, $MEDIAINFO ) 
					&& strlen( $MEDIAINFO[ $f ] ) > 0
					){
						echo "&nbsp;<span><a href='" . O_ANON_LINK . "https://www.themoviedb.org/movie/" . $MEDIAINFO[ $f ] . "' title='" . $ftitle . "' target='_blank'>TheMovieDb</a></span>&nbsp";
					}
					$f = 'tmdb'; 
					if( array_key_exists( $f, $MEDIAINFO ) 
					&& strlen( $MEDIAINFO[ $f ] ) > 0
					){
						echo "&nbsp;<span><a href='" . O_ANON_LINK . "" . $MEDIAINFO[ $f ] . "' title='" . $ftitle . "' target='_blank'>TheMovieDb</a></span>&nbsp";
					}
					$f = 'tvdbid'; 
					if( array_key_exists( $f, $MEDIAINFO ) 
					&& strlen( $MEDIAINFO[ $f ] ) > 0
					){
						echo "&nbsp;<span><a href='" . O_ANON_LINK . "https://thetvdb.com/index.php?tab=episode&id=" . $MEDIAINFO[ $f ] . "' title='" . $ftitle . "' target='_blank'>TheTVDB</a></span>&nbsp";
					}
					$f = 'tvdb'; 
					if( array_key_exists( $f, $MEDIAINFO ) 
					&& strlen( $MEDIAINFO[ $f ] ) > 0
					){
						echo "&nbsp;<span><a href='" . O_ANON_LINK . "" . $MEDIAINFO[ $f ] . "' title='" . $ftitle . "' target='_blank'>TheTVDB</a></span>&nbsp";
					}
				?>
			</td>
		</tr>
	</table>
	

    
	<?php
        //RELATED
		$f = 'genre'; 
		if( array_key_exists( $f, $MEDIAINFO ) 
		&& strlen( $MEDIAINFO[ $f ] ) > 0
		&& ( $genres = explode( ',', $MEDIAINFO[ $f ] ) ) != FALSE
		&& is_array( $genres )
		&& count( $genres ) > 0
		&& ( $genreslist = sqlite_media_getdata_related( $genres, O_LIST_MINI_QUANTITY, $MEDIAINFO[ 'idmediainfo' ] ) ) != FALSE
		){
	?>
		<?php echo get_html_list( $genreslist, get_msg( 'INFO_RELATED', FALSE ), FALSE ); ?>
	
	<?php
		}
	?>
	
		<?php
			$f = 'actor'; 
			if( array_key_exists( $f, $MEDIAINFO ) 
			){
				$x = 0;
				if( ( $data_a = explode( ',', $MEDIAINFO[ $f ] ) ) != FALSE 
				&& count( $data_a ) > 1
				){
        ?>
    <div class='boxList'>
        <h2><?php echo get_msg( 'INFO_ACTORS', FALSE ); ?></h2>
	
        <div class='tListInfoActors'>
            <?php
                        foreach( $data_a AS $actor ){
                            if( $x > 9 ){
                                $x = 0;
                                echo "<div class='tListInfoActorsSep'></div>";
                            }
                            $urlactor = getURLActor( $actor );
            ?>
                    <div class='tListInfoActorsE eColors0<?php echo $x; ?>' >
                        <a href='?action=list&search=<?php echo urlencode( $actor ); ?>' title='<?php echo urlencode( $actor ); ?>'>
                            <img class='lazy' data-src='<?php echo $urlactor; ?>' src='' />
                        </a>
                        <span><?php echo $actor; ?></span>
                    </div>
            <?php
                            $x++;
                        }
                    }elseif( strlen( $MEDIAINFO[ $f ] ) > 0 ){
                        $actor = $MEDIAINFO[ $f ];
                        $urlactor = getURLActor( $actor );
                    ?>
                    <div class='tListInfoActorsE eColors0<?php echo $x; ?>' >
                        <a href='?action=list&search=<?php echo urlencode( $actor ); ?>' title='<?php echo urlencode( $actor ); ?>'><img src='<?php echo $urlactor; ?>' />
                        <span><?php echo $actor; ?></span>
                    </div>
            <?php
                    }
                }
            ?>
        </div>
    </div>
    
</div>

<?php 
    }
?>
