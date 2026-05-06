<?php

	defined( 'ACCESS' ) or die( 'HTTP/1.0 401 Unauthorized.<br />' );
	//admin
	check_mod_admin();
	
	$QUANTITY = 100;
	if( array_key_exists( 'quantity', $G_DATA ) 
	&& is_numeric( $G_DATA[ 'quantity' ] )
	&& (int)$G_DATA[ 'quantity' ] > 0
	){
        $QUANTITY = (int)$G_DATA[ 'quantity' ];
	}
	
	media_scan_downloads( $QUANTITY, TRUE, TRUE );
	
?>
