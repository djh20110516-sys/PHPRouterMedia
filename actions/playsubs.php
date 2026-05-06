<?php

	defined( 'ACCESS' ) or die( 'HTTP/1.0 401 Unauthorized.<br />' );
	//set_time_limit(0);
	
	//load identified subs and echo json datasub = array( 'timestart', 'timeend', 'text' ) converting supported formats
	//timestart and timeend in secods with decimals (4)
	//action
	//idmedia
	//subtrack
	
	
	if( array_key_exists( 'idmedia', $G_DATA ) ){
        $IDMEDIA = $G_DATA[ 'idmedia' ];
	}else{
        $IDMEDIA = FALSE;
	}
	
	if( array_key_exists( 'subtrack', $G_DATA ) ){
        $SUBTRACK = $G_DATA[ 'subtrack' ];
	}else{
        $SUBTRACK = FALSE;
	}
	
	if( array_key_exists( 'extsubfile', $G_DATA ) ){
        $EXTSUBFILE = $G_DATA[ 'extsubfile' ];
	}else{
        $EXTSUBFILE = FALSE;
	}
	
	if( array_key_exists( 'format', $G_DATA ) && strtolower( $G_DATA[ 'format' ] ) == 'vtt' ){
        $FORMAT = 'vtt';
	}else{
        $FORMAT = 'json';
	}
	
	
	//HELPERS
	
	function subs_format_time_vtt( $seconds ){
        $hours = floor( $seconds / 3600 );
        $minutes = floor( ( $seconds % 3600 ) / 60 );
        $secs = floor( $seconds % 60 );
        $ms = round( ( $seconds - floor( $seconds ) ) * 1000 );
        return sprintf( "%02d:%02d:%02d.%03d", $hours, $minutes, $secs, $ms );
	}
	
	//SRT
	
	function subs_import_srt( $data, $DEBUG = FALSE ){
        //Import str format
        /*
        1
        00:00:02,600 --> 00:00:08,869
        Al menos la mitad de esta historia
        está documentada como hecho histórico.
        
        2
        ...
        */
        $result = array( 0 => array( 'timestart' => 0, 'timeend' => 0, 'text' => '' ) );
        
        $x = 1;
        $nexttime = FALSE;
        $nexttext = FALSE;
        if( is_array( $data ) ){
            foreach( $data AS $row ){
                $row = trim( $row );
                if( $DEBUG ) echo "<br />-LINE: " . $row;
                if( $DEBUG ) echo "<br />-LINEPOS: " . $x;
                //Search $x pos
                if( ctype_digit( $row )
                && $x == (int)$row
                ){
                    if( $DEBUG ) echo "<br />SETX: " . $row;
                    $nexttime = TRUE;
                    $nexttext = FALSE;
                    $x++;
                }elseif( $nexttime ){
                    if( $DEBUG ) echo "<br />SETXNEXTTIME: " . $row;
                    //Get times and assign to result row
                    $timestart = subs_srt_convert_time( $row, 0, $DEBUG );
                    $timeend = subs_srt_convert_time( $row, 1, $DEBUG );
                    $result[ $x - 1 ][ 'timestart' ] = $timestart;
                    $result[ $x - 1 ][ 'timeend' ] = $timeend;
                    $nexttime = FALSE;
                    $nexttext = TRUE;
                }elseif( strlen( $row ) == 0 ){
                    if( $DEBUG ) echo "<br />SETXEXMPTY: " . $row;
                    $nexttime = FALSE;
                    $nexttext = FALSE;
                }elseif( $nexttext ){
                    if( $DEBUG ) echo "<br />SETXNEXTTEXT: " . $row;
                    //Get text and assign to result row
                    if( !array_key_exists( 'text', $result[ $x - 1 ] ) ){
                        $result[ $x - 1 ][ 'text' ] = $row;
                    }else{
                        $result[ $x - 1 ][ 'text' ] .= '<br>' . $row;
                    }
                }
                if( $DEBUG 
                && $x > 10
                ){
                    break;
                }
            }
        }
        
        return $result;
	}
	
	function subs_srt_convert_time( $row, $pos, $DEBUG = FALSE ){
        //Convert pos time to seconds with decimals
        //00:00:02,600 --> 00:00:08,869
        $result = 0.0000;
        
        if( $DEBUG ) echo "<br />GETTIMES: " . $row;
        if( ( $d = explode( ' --> ', $row ) ) != FALSE 
        && is_array( $d )
        && count( $d ) > 0
        && array_key_exists( $pos, $d )
        ){
            $t = $d[ $pos ];
            if( $DEBUG ) echo "<br />GETTIMESDATA: " . $t;
            //sometimes . or , localization
            if( inString( $t, ',' ) ){
                $t = str_ireplace( ',', '.', $t );
            }
            if( sscanf( $t, "%f:%f:%f", $hours, $minutes, $seconds) ){
                (float)$result = (float)( $hours * 3600 ) + (float)( $minutes * 60 ) + (float)$seconds;
                //adust to browser times -0.100
                (float)$result -= 0.100;
                if( $DEBUG ) echo "<br />GETTIMESDATARESULT: " . $result;
            }
        }
        
        return $result;
	}
	
	//BASE
	
	$DEBUG = FALSE;
	if( $IDMEDIA !== FALSE 
	&& ( $SUBTRACK !== FALSE || $EXTSUBFILE !== FALSE )
	&& ( $mi = sqlite_media_getdata( $IDMEDIA ) ) != FALSE 
	&& is_array( $mi )
	&& count( $mi ) > 0
	&& @file_exists( $mi[ 0 ][ 'file' ] )
	&& getFileMimeTypeVideo( $mi[ 0 ][ 'file' ] )
	){
        $FMEDIA = $mi[ 0 ][ 'file' ];
        
        if( $EXTSUBFILE !== FALSE ){
            // External subtitle file (srt, ass, etc.)
            $filesubs = dirname( $FMEDIA ) . DS . basename( $EXTSUBFILE );
            // Convert non-SRT formats with ffmpeg
            $ext = strtolower( pathinfo( $filesubs, PATHINFO_EXTENSION ) );
            if( $ext !== 'srt' ){
                if( !file_exists( PPATH_CACHE . DS . 'subs' ) ){
                    mkdir( PPATH_CACHE . DS . 'subs' );
                }
                $filesubs_converted = PPATH_CACHE . DS . 'subs' . DS . $mi[ 0 ][ 'idmedia' ] . '.' . md5( $EXTSUBFILE ) . '.srt';
                if( !file_exists( $filesubs_converted ) || filesize( $filesubs_converted ) == 0 ){
                    $cmd = O_FFMPEG . ' -i ' . escapeshellarg( $filesubs ) . ' ' . escapeshellarg( $filesubs_converted );
                    runExtCommand( $cmd );
                }
                if( file_exists( $filesubs_converted ) && filesize( $filesubs_converted ) > 0 ){
                    $filesubs = $filesubs_converted;
                }
            }
        }else{
            // Embedded subtitle track
            $filesubs = PPATH_CACHE . DS . 'subs' . DS . $mi[ 0 ][ 'idmedia' ] . '.' . $SUBTRACK . '.srt';
            if( !file_exists( PPATH_CACHE . DS . 'subs' ) ){
                mkdir( PPATH_CACHE . DS . 'subs' );
            }
            if( !file_exists( $filesubs ) 
            || filesize( $filesubs ) == 0
            ){
                ffmpeg_extract_subfile( $FMEDIA, $filesubs, $SUBTRACK, FALSE );
            }
        }
        
        if( $DEBUG ) echo "<br />FILESUB: " . $filesubs;
        
        $result = array();
        if( file_exists( $filesubs ) ){
            $data = array();
            if( ( $data = file_get_contents( $filesubs ) ) != FALSE ){
                // Detect encoding and convert to UTF-8 if needed
                // UTF-16 LE BOM: FF FE
                if( substr( $data, 0, 2 ) == "\xFF\xFE" ){
                    $data = mb_convert_encoding( $data, 'UTF-8', 'UTF-16LE' );
                }
                // UTF-16 BE BOM: FE FF
                elseif( substr( $data, 0, 2 ) == "\xFE\xFF" ){
                    $data = mb_convert_encoding( $data, 'UTF-8', 'UTF-16BE' );
                }
                // Remove UTF-8 BOM if present (also after conversion from UTF-16)
                if( substr( $data, 0, 3 ) == "\xEF\xBB\xBF" ){
                    $data = substr( $data, 3 );
                }
                // Normalize line endings to Unix style
                $data = str_replace( "\r\n", "\n", $data );
                $data = str_replace( "\r", "\n", $data );
                $data = explode( "\n", $data );
            }
            if( $DEBUG ) echo "<br />FILELINES: " . count( $data );
            
            //STR FORMAT
            if( is_array( $data )
            && endsWith( strtolower( $filesubs ), '.srt' ) 
            && ( $result = subs_import_srt( $data, $DEBUG ) ) != FALSE
            ){
                
            }else{
                $result = array();
            }
        }
        
        if( $DEBUG ) echo "<br />RESULT: " . nl2br( print_r( $result, TRUE ) );
        
        if( $FORMAT == 'vtt' ){
            if( !$DEBUG ) header( 'Content-Type: text/vtt; charset=utf-8' );
            echo "WEBVTT\n\n";
            foreach( $result AS $key => $data ){
                if( $key == 0 ) continue;
                $start = subs_format_time_vtt( (float)$data[ 'timestart' ] );
                $end = subs_format_time_vtt( (float)$data[ 'timeend' ] );
                $text = $data[ 'text' ];
                $text = preg_replace( '/<br\s*\/?>/i', "\n", $text );
                $text = preg_replace( '/<[^>]*>/', '', $text );
                echo $key . "\n";
                echo $start . " --> " . $end . "\n";
                echo $text . "\n\n";
            }
        }else{
            if( !$DEBUG ) header( 'Content-Type: application/json' );
            echo '' . json_encode( $result );
        }
    }
	
    exit();
?>

