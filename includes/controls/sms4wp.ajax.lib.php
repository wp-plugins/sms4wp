<?php
if (!defined('ABSPATH')) exit;

require_once( SMS4WP_INC_CONTROL_PATH . '/JSON.php' );

/**
 * sms4wp ajax
 */

//-- json encode --//
if( !function_exists('sms4wp_json_encode') ) {
    function sms4wp_json_encode( $data ) {
        $json = new Services_JSON();
        return( $json->encode($data) );
    }
}

//-- go to url --//
if( !function_exists('sms4wp_goto_url') ) {
    function sms4wp_goto_url( $url ) {
        echo '<script type="text/javascript">
            window.location = "'.$url.'";
        </script>';
    }
}


//-- 메시지 보내기 수신자 검색목록 --//
if( !function_exists('sms4wp_ajax_receivers') ) {
    function sms4wp_ajax_receivers() {
        global $wpdb;

	    $groups    = sms4wp_get_groups_all(); // 그룹정보
        $page_rows = intval( $_POST['page_rows'] );
	    $paged     = intval( $_POST['paged'] );
	    $s         = htmlspecialchars( $_POST['s'] );
	    $result    = '';

	    $query_where = " WHERE re_use = '1' ";

	    if ( $paged < 1 ) 
	        $paged = 1;
	    if ( !( isset($page_rows) && is_int($page_rows) && $page_rows > 0 ) ) // 한페이지에서 보여지는 아이템 숫자
	        $page_rows = 15;

	    if ( $s ) { // 검색어
	        $query_where .= " AND ( re_name like '%{$s}%' ";
	        $query_where .= " OR re_phone_number like '%{$s}%' ";
	        $query_where .= " OR re_memo like '%{$s}%' ) ";
	    }

	    $query_order = " ORDER BY ID desc ";
	    if ( $orderby && $order ) { 
	        $query_order = " ORDER BY {$orderby} {$order} ";
	    }

	    $total = $wpdb->get_row( "SELECT count(ID) AS cnt FROM `".SMS4WP_RECEIVERS_TABLE."` " . $query_where ); // 전체 수신자

	    $list['total'] = $total->cnt;

	    $total_page  = ceil($total->cnt / $page_rows);  // 전체 페이지 계산
	    $from_record = ($paged - 1) * $page_rows; // 시작 열을 구함

	    $rows = $wpdb->get_results( "SELECT * FROM `".SMS4WP_RECEIVERS_TABLE."` " . $query_where . $query_order . " limit " . $from_record . ", " . $page_rows );

	    if ( !( is_array($rows) || is_object($rows) ) )
	        return;

	    foreach ( $rows as $row ) { 
	        $group_name = $groups[$row->gr_id]['gr_name'];
	        if ( $group_name == '' )
	        	$group_name = 'None';

	        $result .= '<li re_id="'.$row->ID.'" class="add-receiver">'.$group_name.', '.$row->re_name.', '.$row->re_phone_number.'</li>';
	    }

	    die( json_encode( $result ) );
    }
    add_action( 'wp_ajax_sms4wp_ajax_receivers', 'sms4wp_ajax_receivers', 1 );
    add_action( 'wp_ajax_nopriv_sms4wp_ajax_receivers', 'sms4wp_ajax_receivers', 1 );
}

//-- 보내는 메시지 --//
if( !function_exists('sms4wp_ajax_msg_sends') ) {
	function sms4wp_ajax_msg_sends() {
	    global $wpdb;

	    $countgap = 1000; // 몇건씩 보낼지 설정
		$sleepsec = 5;  // 천분의 몇초간 쉴지 설정

		if ( !wp_verify_nonce( $_REQUEST['nonce'], "sms4wp_ajax_message_nonce")) {
			die("No naughty business please");
		}  

	    require_once( SMS4WP_INC_CONTROL_PATH . '/sms4wp.sms.class.php' );
	    require_once( SMS4WP_INC_CONTROL_PATH . '/sms4wp.lib.php' );

	    $sms4wp_config = sms4wp_get_configure();

	    $groups    = array();
	    $receivers = array();

		$data = array_map( 'sms4wp_trim', $_REQUEST );
	    $data = array_map( 'sms4wp_htmlspecialchars', $data );
	    $list = array(); // 수신번호, 수신자, 그룹 수신자

	    $send_timestamp = '';
	    if ( $data['reservation_date'] != '' && $data['reservation_time'] != '' ) {
	    	// UTC 기준 한국시간 +9h
		    $send_timestamp = date( c, strtotime($data['reservation_date'] . ' ' . $data['reservation_time']) - (60 * 60 * 9) );
	    }

	    // 받는사람 여러명인 경우 확인
    	$receivers_phone = $data['receiver_phone'];
	    if ( @eregi( ',', $receivers_phone ) && !is_array( $receivers_phone ) ) {
	    	$receivers_phone = explode( ',', $receivers_phone );
	    }
	    // 받는사람 입력
	    if ( is_array( $receivers_phone ) ) {
		    foreach ( $receivers_phone as $number ) { // 수신번호
		    	$number = preg_replace( "/[^0-9]/i", "", $number );
		    	$list[$number] = array( 're_id'=>'', 'gr_id'=>'' );
		    }
	    }
	    else {
	    	$number = preg_replace( "/[^0-9]/i", "", $receivers_phone );
	    	$list[$number] = array( 're_id'=>'', 'gr_id'=>'' );
		}

		// 수신자
		$receivers = explode( ',', $data['receivers'] );
		if ( is_array( $receivers ) ) {
		    foreach ( $receivers as $re_id ) { // 수신자
		    	$re = sms4wp_get_receiver( intval( trim($re_id) ) );

		    	$number = preg_replace( "/[^0-9]/i", "", $re['re_phone_number'] );

		    	if ( !is_array( $list[$number] ) ) // 중복 핸드폰이 없는 경우
			    	$list[$number] = array( 're_id'=>$re['ID'], 'gr_id'=>'' );
		    }
		}

		// 그룹
		$groups = explode( ',', $data['groups'] );
		if ( is_array( $groups ) ) {
		    foreach ( $groups as $gr_id ) { // 그룹소속 수신자
		    	$gr_receiver = sms4wp_get_group_receivers( intval( trim($gr_id) ) );

		    	for ( $c = 0; $c < count($gr_receiver); $c++ ) {
			    	$number = preg_replace( "/[^0-9]/i", "", $gr_receiver[$c]['re_phone_number'] );

		    		if ( !is_array( $list[$number] ) ) // 중복 핸드폰이 없는 경우
				    	$list[$number] = array( 're_id'=>$gr_receiver[$c]['ID'], 'gr_id'=>$gr_receiver[$c]['gr_id'] );
		    	}
		    }
		}

		flush();
        ob_flush();

	    $SMS = new SMS4WP( $sms4wp_config['sms4wp_auth_email'], $sms4wp_config['sms4wp_auth_token'], $sms4wp_config['sms4wp_auth_signature'] );

	    $c = 0;
	    foreach ( $list as $number => $receiver ) {
	    	if ( $countgap == $c ) {
	    		$c = 0;
	    	}
		    $SMS->Init();

		    $pattern         = array(); // 메시지 치환정보 array('patterns'=>'replacements');
		    $message_body    = $data['message_body'];
		    $message_subject = $data['message_subject'];

		    $args = array(
		        'sender_phone'    => $data['sender_phone'], 
		        'sender_name'     => $data['sender_name'], 
		        'message_type'    => $data['message_type'], 
		        'message_subject' => $data['message_subject'], 
		        'message_body'    => $message_body, 
		        'message_subject' => $message_subject, 
		        'pattern'    	  => $pattern, 
		        'receiver_phone'  => $number, 
		        'send_timestamp'  => $send_timestamp, 
		        'receiver_name'   => $data['receiver_name'], 
		        're_id'   		  => $receiver['re_id'], 
		        'gr_id'   		  => $receiver['gr_id'], 
		        'file'            => $_FILES['add_file1'],
		        'bulk_file'       => $_FILES['add_file2'],
		    );
		    $SMS->Add( $args );

		    $SMS->Send();
		    $c++;

		    flush();
	        ob_flush();
	        ob_end_flush();
	        usleep( $sleepsec );
	    }
	    $SMS->Init();

	    @unlink( $_FILES['add_file1']['tmp_name'] );
	    @unlink( $_FILES['add_file2']['tmp_name'] );

	    die( json_encode('success') );
	}
	add_action( 'wp_ajax_sms4wp_ajax_msg_sends', 'sms4wp_ajax_msg_sends', 1 );
    add_action( 'wp_ajax_nopriv_sms4wp_ajax_msg_sends', 'sms4wp_ajax_msg_sends', 1 );
}


//-- 템플릿 목록 --//
if( !function_exists('sms4wp_ajax_templates') ) {
    function sms4wp_ajax_templates() {
        global $wpdb;

        $page_rows = intval( $_POST['page_rows'] );
	    $paged     = intval( $_POST['paged'] );
	    $result    = '';

	    $query_where = " WHERE (1) ";

	    if ( $paged < 1 ) 
	        $paged = 1;
	    if ( !( isset($page_rows) && is_int($page_rows) && $page_rows > 0 ) ) // 한페이지에서 보여지는 아이템 숫자
	        $page_rows = 15;

	    $query_order = " ORDER BY ID desc ";
	    if ( $orderby && $order ) { 
	        $query_order = " ORDER BY {$orderby} {$order} ";
	    }

	    $total   = $wpdb->get_row( "SELECT count(ID) AS cnt FROM `".SMS4WP_TEMPLATE_TABLE."` " . $query_where ); // 전체 템플릿

	    $list['total'] = $total->cnt;

	    $total_page  = ceil($total->cnt / $page_rows);  // 전체 페이지 계산
	    $from_record = ($paged - 1) * $page_rows; // 시작 열을 구함

	    $rows = $wpdb->get_results( "SELECT * FROM `".SMS4WP_TEMPLATE_TABLE."` " . $query_where . $query_order . " limit " . $from_record . ", " . $page_rows );
	    // print_r( $rows );
	    if ( !( is_array($rows) || is_object($rows) ) )
	        return;

	    foreach ( $rows as $row ) { 
	        $result .= '
				<li class="add-template">
					<span>' . $row->te_subject . '</span>
					<span class="message">' . $row->te_message . '</span>
				</li>
	        ';
	    }

	    die( json_encode( $result ) );
    }
    add_action( 'wp_ajax_sms4wp_ajax_templates', 'sms4wp_ajax_templates', 1 );
    add_action( 'wp_ajax_nopriv_sms4wp_ajax_templates', 'sms4wp_ajax_templates', 1 );
}



//-- 템플릿 저장하기 --//
if( !function_exists('sms4wp_ajax_save_template') ) {
    function sms4wp_ajax_save_template() {
        global $wpdb;

        if ( !wp_verify_nonce( $_REQUEST['nonce'], "sms4wp_ajax_message_nonce")) {
			die( json_encode("No naughty business please") );
		}  

        $message = htmlspecialchars( trim( $_POST['message'] ) );
        $subject = htmlspecialchars( trim( $_POST['subject'] ) );

        if ( $subject == '' )
        	$subject = 'none';

	    $rows = $wpdb->get_results( "INSERT INTO `".SMS4WP_TEMPLATE_TABLE."` SET te_subject = '" . $subject ."', te_message = '" . $message ."', te_date = now() " );
	    die( '100' );
    }
    add_action( 'wp_ajax_sms4wp_ajax_save_template', 'sms4wp_ajax_save_template', 1 );
    add_action( 'wp_ajax_nopriv_sms4wp_ajax_save_template', 'sms4wp_ajax_save_template', 1 );
}


//-- api key 인증 확인 --//
function sms4wp_ajax_api_certification() {
	global $wpdb;
}


//-- 핸드폰 인증 확인 --//
function sms4wp_ajax_cellphone_certification() {
	global $wpdb;
}


//-- 템플릿 가져오기 --//
function sms4wp_ajax_import_template() {
	global $wpdb;
}


//-- 받는사람 추가 (수신자, 그룹) --//
function sms4wp_ajax_import_addressee_group() {
	global $wpdb;
}


//-- 수신자 파일 다운로드 --//
if( !function_exists('sms4wp_ajax_book_file_download') ) {
	function sms4wp_ajax_book_file_download() {
		global $wpdb;

		if ( !wp_verify_nonce( $_REQUEST['nonce'], "sms4wp_book-file-download")) {
			die( json_encode("No naughty business please") );
		} 

		$data = array_map( 'sms4wp_trim', $_REQUEST );
	    $data = array_map( 'sms4wp_htmlspecialchars', $data );
	    extract( $data );

		if ( $gr_parent != 'all' && $gr_parent < 1 )
		    die( '801' ); // 다운로드 할 휴대폰번호 그룹을 선택해주세요.

		$sql_group = ""; 
		if ( $gr_parent != 'all' )  
			$sql_group = " and gr_id = '{$gr_parent}' ";

		$sql_hp = " and re_phone_number <> '' ";

		$sql = " SELECT COUNT(*) AS cnt FROM " . SMS4WP_RECEIVERS_TABLE . " WHERE (1) {$sql_group} {$sql_hp} ORDER BY re_name ";
		$total = $wpdb->get_row( $sql );

		if ( !$total->cnt ) 
			die( '802' ); // 데이터가 없습니다.

		$rows = $wpdb->get_results( " SELECT * FROM " . SMS4WP_RECEIVERS_TABLE . " WHERE (1) {$sql_group} {$sql_hp} ORDER BY re_name " );

		/*================================================================================
		php_writeexcel http://www.bettina-attack.de/jonny/view.php/projects/php_writeexcel/
		=================================================================================*/

		include_once( SMS4WP_INC_MODEL_PATH . '/Excel/php_writeexcel/class.writeexcel_workbook.inc.php' );
		include_once( SMS4WP_INC_MODEL_PATH . '/Excel/php_writeexcel/class.writeexcel_worksheet.inc.php' );

		$upload_dir = wp_upload_dir();
		$fname      = tempnam( $upload_dir['basedir'], "/receivers.xls" );

		$workbook  = new writeexcel_workbook( $fname );
		$worksheet = $workbook->addworksheet();

		$num2_format =& $workbook->addformat( array(num_format => '\0#') );

		// Put Excel data
		$data = array('이름', '전화번호');
		$data = array_map('sms4wp_iconv_euckr', $data);

		$col = 0;
		foreach( $data as $cell ) {
		    $worksheet->write( 0, $col++, $cell );
		}

		$c = 0;
		foreach ( $rows as $key=>$row ) {
			$c++;

		    $cellPhone = sms4wp_get_hp( sms4wp_iconv_euckr( $row->re_phone_number ) );
		    $re_name   = sms4wp_iconv_euckr( $row->re_name );
		    if ( !$cellPhone ) 
		    	continue;

		    $worksheet->write( $c, 0, $re_name );
		    $worksheet->write( $c, 1, $cellPhone, $num2_format );
		}


		$workbook->close();

		$filename = "수신자번호목록-" . date( "ymd", time() ) . ".xls";
		if( sms4wp_is_ie() ) 
			$filename = sms4wp_utf2euc( $filename );


		header( "Content-Type: application/x-msexcel; name=" . $filename );
		header( "Content-Disposition: inline; filename=" . $filename );

		flush();
		$fp = @fopen( $fname, "rb" );
		
		if ( !fpassthru( $fp ) ) {
		   fclose( $fp );
		}
		flush();
		@unlink( $fname );

		die( json_encode('success') );
		exit;
	}
	add_action( 'wp_ajax_sms4wp_ajax_book_file_download', 'sms4wp_ajax_book_file_download', 1 );
	add_action( 'wp_ajax_nopriv_sms4wp_ajax_book_file_download', 'sms4wp_ajax_book_file_download', 1 );
}


//-- 수신자 파일 업로드 --//
if( !function_exists('sms4wp_ajax_book_file_upload') ) {
	function sms4wp_ajax_book_file_upload() {
		global $wpdb;

	    if ( !wp_verify_nonce( $_REQUEST['nonce'], "sms4wp_book-file-upload")) {
			die( json_encode("No naughty business please") );
		} 

		$data = array_map( 'sms4wp_trim', $_REQUEST );
	    $data = array_map( 'sms4wp_htmlspecialchars', $data );
	    extract( $data );

		if ( !$_FILES['book_file']['size'] ) 
		    die( '801' ); // 파일을 선택해주세요.

		$file     = $_FILES['book_file']['tmp_name'];
		$filename = $_FILES['book_file']['name'];

		$info = pathinfo( $filename );
		$ext  = $info['extension'];

		switch ( $ext ) {
		    case 'csv' :
		        $ext_file = file( $file );
		        $num_rows = count( $ext_file ) + 1;
		        $csv      = array();

		        foreach ( $ext_file as $item ) {
		            $item = explode( ',', $item );
		            array_push( $csv, $item );

		            if ( count($item) < 2 ) 
		                die( '802' ); // 올바른 파일이 아닙니다.
		        }
		        break;
		    default :
		        die( '803' ); // xls파일과 csv파일만 허용합니다.
		}

		$success = 0;
		$arr_hp  = array();

		for ( $c = 0; $c <= $num_rows; $c++ ) {
		    switch ($ext) {
		        case 'csv' :
		            $name = $csv[$c][0];
		            if ( mb_detect_encoding( $name, 'UTF-8', true ) === false ) { 
					    $name = utf8_encode( $name ); 
				    }

		            $name      = addslashes( trim($name) );
		            $cellPhone = sms4wp_get_hp( $csv[$c][1], 0 );
	            break;
		    }

	        if ( !$cellPhone ) // 전화번호 없는 경우
	        	continue;

		    if ( strlen($name) && $cellPhone ) {
		        if ( !in_array( $cellPhone, $arr_hp ) ) { // 중복번호 없는 경우
		            array_push( $arr_hp, $cellPhone );

		            // 수신자 중복번호 확인
		            $qry = " SELECT COUNT(*) AS cnt FROM " . SMS4WP_RECEIVERS_TABLE . " WHERE re_phone_number = '{$cellPhone}' "; 
		            $res = $wpdb->get_row( $qry );
		            if ( !$res->cnt && $cellPhone ) {
		            	$qry = " INSERT INTO " . SMS4WP_RECEIVERS_TABLE . " 
		            					SET gr_id           = '{$gr_parent}', 
		            						re_name         = '" . addslashes( $name ) . "', 
		            						re_phone_number = '{$cellPhone}', 
		            						re_update       = now() ";
		                $wpdb->query( $qry );
		                $success++;
		            }
		        }
		    }
		}

		unlink( $_FILES['book_file']['tmp_name'] );

		if ( $success ) {
		    $qry = " SELECT COUNT(*) AS cnt FROM " . SMS4WP_RECEIVERS_TABLE . " WHERE gr_id='$gr_id' ";
		    $total = $wpdb->get_row( $qry );

		    $qry = " UPDATE " . SMS4WP_GROUP_TABLE . " SET bg_count = '".$total->cnt."' WHERE gr_id = '$gr_id' ";
		    $wpdb->query( $qry );
		}

		die( json_encode('success') );
	}
	add_action( 'wp_ajax_sms4wp_ajax_book_file_upload', 'sms4wp_ajax_book_file_upload', 1 );
	add_action( 'wp_ajax_nopriv_sms4wp_ajax_book_file_upload', 'sms4wp_ajax_book_file_upload', 1 );
}

?>
