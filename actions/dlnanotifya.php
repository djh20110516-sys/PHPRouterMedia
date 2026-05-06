<?php

	defined( 'ACCESS' ) or die( 'HTTP/1.0 401 Unauthorized.<br />' );
	//admin
	check_mod_admin();
	
	$result = array( 'success' => false, 'dlna_active' => false, 'message' => '' );
	
	// Get current state
	if( defined( 'DLNA_ACTIVE' ) ){
		$result[ 'dlna_active' ] = DLNA_ACTIVE;
	}
	
	// Toggle DLNA_ACTIVE in config.php
	if( array_key_exists( 'dlna_toggle', $_GET ) ){
		$new_state = ( $_GET[ 'dlna_toggle' ] == '1' );
		
		$configfile = PPATH_BASE . DS . 'config.php';
		$configfilebackup = PPATH_BASE . DS . 'config.php.backup';
		
		if( !is_writable( $configfile ) ){
			$result[ 'message' ] = 'Config file not writable.';
		}else{
			$configdata = file_get_contents( $configfile );
			if( $configdata === FALSE ){
				$result[ 'message' ] = 'Failed to read config file.';
			}else{
				// Replace DLNA_ACTIVE define
				if( $new_state ){
					$configdata = preg_replace(
						"/define\s*\(\s*'DLNA_ACTIVE'\s*,\s*FALSE\s*\)\s*;/i",
						"define( 'DLNA_ACTIVE', TRUE );",
						$configdata
					);
				}else{
					$configdata = preg_replace(
						"/define\s*\(\s*'DLNA_ACTIVE'\s*,\s*TRUE\s*\)\s*;/i",
						"define( 'DLNA_ACTIVE', FALSE );",
						$configdata
					);
				}
				
				// Write backup
				@unlink( $configfilebackup );
				@file_put_contents( $configfilebackup, file_get_contents( $configfile ) );
				
				// Write new config
				if( file_put_contents( $configfile, $configdata ) !== FALSE ){
					// Verify syntax
					$tmpfile = PPATH_TEMP . DS . getRandomString( 10 ) . '.php';
					if( file_put_contents( $tmpfile, $configdata ) !== FALSE ){
						$syntax_ok = false;
						$php_bin = defined( 'O_PHP' ) ? O_PHP : 'php';
						exec( $php_bin . ' -l "' . $tmpfile . '" 2>/dev/null', $syntax_out, $syntax_ret );
						if( $syntax_ret === 0 ){
							$syntax_ok = true;
						}
						@unlink( $tmpfile );
						
						if( $syntax_ok ){
							$result[ 'success' ] = true;
							$result[ 'dlna_active' ] = $new_state;
							$result[ 'message' ] = $new_state ? 'DLNA 已启用' : 'DLNA 已禁用';
							
							// --- Send SSDP notifications + control daemon ---
							require_once( PPATH_CORE . DS . 'functions.dlna.php' );
							
							if( $new_state ){
								// Enable: send NOTIFY alive, start daemon if not running
								
								// 1. Broadcast NOTIFY alive
								$uuidStr = dlna_get_uuidStr();
								$locationUrl = rtrim( DLNA_WEB_BASEFOLDER_HTTP, '/' ) . '/dlna/rootDesc.php';
								ssdp_send_notify( $uuidStr, $locationUrl, 'ssdp:alive' );
								
								// 2. Check if daemon is running, start if not
								$pid_file = '/tmp/phpmediaserver-ssdpd.pid';
								$daemon_active = false;
								if( file_exists( $pid_file ) ){
									$pid = (int)trim( file_get_contents( $pid_file ) );
									if( $pid > 0 && file_exists( '/proc/' . $pid ) ){
										$daemon_active = true;
									}
								}
								if( !$daemon_active ){
									// Fallback pgrep check
									$p_out = array();
									exec( 'pgrep -f "ssdpd.php" 2>/dev/null', $p_out );
									if( is_array( $p_out ) && count( $p_out ) > 0 ){
										$daemon_active = true;
									}
								}
								
								if( !$daemon_active ){
									// Try to start via systemd
									$s_out = array();
									$s_ret = 0;
									exec( 'sudo /bin/systemctl start phpmediaserver-ssdpd 2>/dev/null', $s_out, $s_ret );
									if( $s_ret === 0 ){
										$result[ 'message' ] .= '，守护进程已启动';
									}else{
										$result[ 'message' ] .= '。守护进程未运行，无法自动启动（如需手动启动: sudo systemctl start phpmediaserver-ssdpd）';
									}
								}else{
									$result[ 'message' ] .= '，守护进程已在运行';
								}
							}else{
								// Disable: send NOTIFY byebye, stop daemon
								
								// 1. Broadcast NOTIFY byebye
								$uuidStr = dlna_get_uuidStr();
								$locationUrl = rtrim( DLNA_WEB_BASEFOLDER_HTTP, '/' ) . '/dlna/rootDesc.php';
								ssdp_send_notify( $uuidStr, $locationUrl, 'ssdp:byebye' );
								
								// 2. Stop daemon
								$s_out = array();
								$s_ret = 0;
								exec( 'sudo /bin/systemctl stop phpmediaserver-ssdpd 2>/dev/null', $s_out, $s_ret );
								if( $s_ret === 0 ){
									$result[ 'message' ] .= '，守护进程已停止';
								}else{
									$result[ 'message' ] .= '。守护进程停止失败，可手动执行: sudo systemctl stop phpmediaserver-ssdpd';
								}
							}
							
						}else{
							// Recover from backup
							if( file_exists( $configfilebackup ) ){
								file_put_contents( $configfile, file_get_contents( $configfilebackup ) );
							}
							$result[ 'message' ] = 'Syntax error in config, recovered from backup.';
						}
					}
				}else{
					$result[ 'message' ] = 'Failed to write config file.';
				}
			}
		}
	}
	
	header( 'Content-Type: application/json' );
	echo json_encode( $result );
	
	/**
	 * Send SSDP NOTIFY broadcast (alive or byebye)
	 */
	function ssdp_send_notify( $uuidStr, $locationUrl, $nts ){
		$host = '239.255.255.250';
		$port = 1900;
		
		$notifies = array(
			array( 'upnp:rootdevice', 'uuid:' . $uuidStr . '::upnp:rootdevice' ),
			array( 'uuid:' . $uuidStr, 'uuid:' . $uuidStr ),
			array( 'urn:schemas-upnp-org:device:MediaServer:1', 'uuid:' . $uuidStr . '::urn:schemas-upnp-org:device:MediaServer:1' ),
			array( 'urn:schemas-upnp-org:service:ContentDirectory:1', 'uuid:' . $uuidStr . '::urn:schemas-upnp-org:service:ContentDirectory:1' ),
			array( 'urn:schemas-upnp-org:service:ConnectionManager:1', 'uuid:' . $uuidStr . '::urn:schemas-upnp-org:service:ConnectionManager:1' ),
		);
		
		foreach( $notifies as $n ){
			$nt = $n[0];
			$usn = $n[1];
			
			$msg = "NOTIFY * HTTP/1.1\r\n";
			$msg .= "HOST: $host:$port\r\n";
			$msg .= "CACHE-CONTROL: max-age=600\r\n";
			$msg .= "LOCATION: $locationUrl\r\n";
			$msg .= "NT: $nt\r\n";
			$msg .= "NTS: $nts\r\n";
			$msg .= "SERVER: Linux UPnP/1.0 DLNADOC/1.50 PHPMediaServer/0\r\n";
			$msg .= "X-User-Agent: redsonic\r\n";
			$msg .= "USN: $usn\r\n";
			$msg .= "Content-Length: 0\r\n";
			$msg .= "\r\n";
			
			$socket = @socket_create( AF_INET, SOCK_DGRAM, SOL_UDP );
			if( $socket !== FALSE ){
				@socket_set_option( $socket, SOL_SOCKET, SO_BROADCAST, 1 );
				@socket_sendto( $socket, $msg, strlen( $msg ), 0, $host, $port );
				@socket_close( $socket );
			}
			usleep( 3000 ); // 3ms delay between packets
		}
	}

?>
