<?php

	defined( 'ACCESS' ) or die( 'HTTP/1.0 401 Unauthorized.<br />' );
	//admin
	check_mod_admin();
	
	//folder
	//quantity
	
	if( array_key_exists( 'folder', $G_DATA ) ){
        $FOLDER = $G_DATA[ 'folder' ];
	}else{
        $FOLDER = get_downloads_path();
	}
	
	if( array_key_exists( 'quantity', $G_DATA ) ){
        $QUANTITY = $G_DATA[ 'quantity' ];
	}else{
        $QUANTITY = 10;
	}
	
	//Save settings
	if( array_key_exists( 'save_settings', $G_DATA ) && $G_DATA[ 'save_settings' ] == '1' ){
        if( array_key_exists( 'downloads_path', $G_DATA ) && strlen( $G_DATA[ 'downloads_path' ] ) > 0 ){
            sqlite_config_set( 'ppath_downloads', $G_DATA[ 'downloads_path' ] );
            echo '<script>alert("保存成功！");</script>';
        }
	}
	
?>

<script type="text/javascript">
$(function () {
    
});
function import_folder(){
    var url = '<?php echo getURLBase(); ?>?' + $( '#fElementImport' ).serialize();
    loading_show();
    $.get( url )
    .done( function( data ){
        scrolltop();
        $( '#dResultImport' ).html( data );
        loading_hide();
    });
    
    return false;
}
function scan_downloads(){
    var url = '<?php echo getURLBase(); ?>?r=r&action=scan&quantity=100';
    loading_show();
    $.get( url )
    .done( function( data ){
        scrolltop();
        $( '#dResultScan' ).html( '扫描完成！新文件已加入数据库。' );
        $( '#dResultImport' ).html( data );
        loading_hide();
    });
    
    return false;
}
function save_downloads_path(){
    var path = $( '#downloads_path' ).val();
    if( !path || path.trim() == '' ){
        alert( '路径不能为空！' );
        return false;
    }
    var url = '<?php echo getURLBase(); ?>?r=r&action=importfolder&save_settings=1&downloads_path=' + encodeURIComponent( path );
    loading_show();
    $.get( url )
    .done( function( data ){
        loading_hide();
        $( '#dResultSettings' ).html( '路径已保存！' );
        setTimeout( function(){ $( '#dResultSettings' ).html( '' ); }, 3000 );
    });
    
    return false;
}
</script>

<br />

<!-- Downloads Path Settings -->
<table class='tList'>
    <tr>
        <th>下载目录设置</th>
        <th><?php echo get_msg( 'MENU_ACTION', FALSE ); ?></th>
    </tr>
    <tr>
        <td><input style='width:98%;' type='text' name='downloads_path' id='downloads_path' value='<?php echo get_downloads_path(); ?>' onkeypress="return event.keyCode != 13;" /></td>
        <td><input onclick='save_downloads_path();' type='button' value='保存设置' /></td>
    </tr>
    <tr>
        <td colspan='100' id='dResultSettings' style='color: green;'></td>
    </tr>
</table>

<br /><hr /><br />

<form id='fElementImport'>
    
    <input type='hidden' id='r' name='r' value='r' />
    <input type='hidden' id='action' name='action' value='importfoldera' />
    <table class='tList'>
        <tr>
            <th><?php echo get_msg( 'MENU_FOLDER', FALSE ); ?> (SELECTEDFOLDER/Movie Name/files.[.nfo,mkv,jpg]) (SELECTEDFOLDER/Serie/ChaptersFiles[.nfo,mkv,jpg])</th>
            <th><?php echo get_msg( 'MENU_QUANTITY', FALSE ); ?></th>
            <th><?php echo get_msg( 'MENU_ACTION', FALSE ); ?></th>
        </tr>
        <tr>
            <td><input style='width:98%;' type='text' name='folder' id='folder' value='<?php echo get_downloads_path(); ?>' onkeypress="return event.keyCode != 13;" /></td>
            <td><input style='width:98%;' type='number' name='quantity' id='quantity' value='<?php echo $QUANTITY; ?>' onkeypress="return event.keyCode != 13;" /></td>
            <td><input onclick='import_folder();' type='button' id='bImport' name='bImport' value='<?php echo get_msg( 'MENU_IMPORT', FALSE ); ?>' /></td>
        </tr>
        <tr>
            <td colspan='100' id='dResultImport'></td>
        </tr>
    </table>
	
	<br /><hr /><br />
	
	<table class='tList'>
        <tr>
            <th>下载目录快速扫描</th>
            <th><?php echo get_msg( 'MENU_ACTION', FALSE ); ?></th>
        </tr>
        <tr>
            <td><?php echo get_downloads_path(); ?>目录中的新文件将被扫描并加入数据库</td>
            <td><input onclick='scan_downloads();' type='button' value='扫描下载目录' /></td>
        </tr>
        <tr>
            <td colspan='100' id='dResultScan'></td>
        </tr>
    </table>
	
</form>
<?php
    
?>
