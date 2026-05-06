<?php
    
	defined( 'ACCESS' ) or die( 'HTTP/1.0 401 Unauthorized.<br />' );
	
	//action
	//search
	//page
	
	if( array_key_exists( 'search', $G_DATA ) ){
        $G_SEARCH = $G_DATA[ 'search' ];       
	}else{
        $G_SEARCH = '';
	}
	
	if( array_key_exists( 'page', $G_DATA ) 
	&& is_numeric( $G_DATA[ 'page' ] )
	&& (int)$G_DATA[ 'page' ] >= 0
	){
        $G_PAGE = (int)$G_DATA[ 'page' ];
	}else{
        $G_PAGE = FALSE;
	}
	
	if( strlen( $G_SEARCH ) == 0 
	&& $G_PAGE === FALSE
	){
        
        //Poster Wall - All Media (main content)
        if( ( $edata = sqlite_media_getdata_filtered( $G_SEARCH, O_LIST_QUANTITY, 0 ) ) != FALSE 
        && count( $edata ) > 0
        ){
            $TITLE = '媒体';
            echo '<div class="boxListHome">' . get_html_list( $edata, $TITLE ) . '</div>';
        }
        
    }else{
        if( $G_PAGE === FALSE ){
            $G_PAGE = 0;
        }
        //add downloads user
        if( PPATH_WEBSCRAP_SEARCH != FALSE
        && $G_PAGE == 0
        && defined( 'O_MENU_GENRES' )
        && is_array( O_MENU_GENRES )
        && !array_key_exists( $G_SEARCH, O_MENU_GENRES )
        ){
            echo "" . get_html_list_newdownloads_base( $G_SEARCH );
        }
        //check search genre
        if( defined( 'O_MENU_GENRES' )
        && is_array( O_MENU_GENRES )
        && array_key_exists( $G_SEARCH, O_MENU_GENRES )
        ){
            $onlygenre = TRUE;
        }else{
            $onlygenre = FALSE;
        }
        
        $TITLE = get_msg( 'LIST_SEARCH_RESULT', FALSE );
        if( ( $edata_pages = sqlite_media_getdata_filtered_grouped_pages_total( $G_SEARCH, O_LIST_BIG_QUANTITY, FALSE, $onlygenre ) ) != FALSE 
        && ( $edata = sqlite_media_getdata_filtered( $G_SEARCH, O_LIST_BIG_QUANTITY, $G_PAGE, $onlygenre ) ) != FALSE 
        ){
            $TITLE = get_msg( 'LIST_TITLE_LAST', FALSE );
            $edata_pages = (int)( $edata_pages / O_LIST_BIG_QUANTITY );
            echo get_html_list( $edata, $TITLE, $G_PAGE, $edata_pages );
        }else{
            echo '<h2>' . get_msg( 'DEF_EMPTYLIST', FALSE ) . '</h2>';
        }
    }
	
?>
