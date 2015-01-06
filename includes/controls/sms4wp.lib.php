<?php
if (!defined('ABSPATH')) exit;

/*************************************************************************
**
**  sms4wp에 사용할 함수 모음
**
*************************************************************************/

/***
 * 설정관리 *
 ****/
//-- 기본설정데이터 --//
function sms4wp_get_configure( $option_name = '' ) {
    global $wpdb;

    $options = array();
    $option_name = trim($option_name);

    if ( $option_name ) { // 한개의 옵션값 요청
        $query_where = " AND op_name='{$option_name}' ";
    }

    $rows = $wpdb->get_results( "SELECT * FROM `".SMS4WP_OPTIONS_TABLE."` WHERE op_use='1'" . $query_where );
    if ( !( is_array($rows) || is_object($rows) ) )
        return;

    foreach ( $rows as $row ) { 
        $options[$row->op_name] = maybe_unserialize( $row->op_value );
    }

    if ( $option_name ) {
        return $options[$option_name];
    }
    else {
        return $options;
    }
}

//-- 기본설정데이터 저장하기 --//
function sms4wp_update_configure( $options ) {
    global $wpdb;

    if ( is_array( $options) ) {
        foreach ( $options as $option => $value ) {
            if ( substr( $option, 0, 6 ) == 'sms4wp' )
                sms4wp_update_configure_option( $option, $value );
        }
    }
}
function sms4wp_update_configure_option( $option, $value ) {
    global $wpdb;

    $option = trim($option);
    if ( empty($option) )
        return false;

    $date  = date('Y-m-d H:i:s');
    $option = stripslashes( $option );
    $value  = stripslashes( trim( $value ) );
    $value  = maybe_serialize( $value );

    $result = $wpdb->query( $wpdb->prepare( "INSERT INTO `".SMS4WP_OPTIONS_TABLE."` (`op_name`, `op_value`, `op_date`) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE `op_name` = VALUES(`op_name`), `op_value` = VALUES(`op_value`), `op_date` = VALUES(`op_date`)", $option, $value, $date ) );

    return true;
}


/***
 * 메시지 보내기 *
 ****/
//-- 보내는 메시지 --//
function sms4wp_send_message( $data = array() ) {
    global $wpdb;
    require_once( SMS4WP_INC_CONTROL_PATH . '/sms4wp.sms.class.php' );

    $data = array_map( 'trim', $data );

    if ( !wp_verify_nonce( $data['nonce'], 'sms4wp_ajax_message_nonce' ) )
        return;


    $sms4wp_config = sms4wp_get_configure();

    $SMS = new SMS4WP( $sms4wp_config['sms4wp_auth_email'], $sms4wp_config['sms4wp_auth_token'], $sms4wp_config['sms4wp_auth_signature'] );
    $SMS->Init();

    $args = array(
        'sender_phone'    => $data['sender_phone'], 
        'message_body'    => stripslashes( htmlspecialchars_decode( $data['message_body'] ) ), 
        'send_name'       => $data['send_name'], 
        'message_subject' => stripslashes( htmlspecialchars_decode( $data['message_subject'] ) ), 
        'receiver_phone'  => $data['receiver_phone'], 
        'receiver_name'   => $data['receiver_name'], 
        're_id'           => $reserve_id /* 수신자 관리 번호 */
    );
    $SMS->Add( $args );

    $SMS->Send();
    $SMS->Init();

    if ( $data['_wp_http_referer'] ) {
        echo '<script type="text/javascript">
            window.location = "'.$data['_wp_http_referer'].'";
        </script>';
        exit;
    }
}
//-- 메시지 DB저장 --//
function sms4wp_add_send_message( $args ) {
    global $wpdb;

    return;
}


/***
 * 수신자 관리 *
 ****/
//-- 수신자 목록 --//
function sms4wp_list_receivers( $args ) {
    global $wpdb;

    $list = array();
    $args = array_map( 'trim', $args );
    $args = array_map( 'htmlspecialchars', $args );
    extract( $args );

    $page_rows = intval( $page_rows );

    if ( $paged < 1 ) 
        $paged = 1;
    if ( !( isset($page_rows) && is_int($page_rows) ) ) // 한페이지에서 보여지는 아이템 숫자
        $page_rows = 15;

    if ( $s ) { // 검색어
        $query_where = " AND ( re_name like '%{$s}%' ";
        $query_where .= " OR re_phone_number like '%{$s}%' ";
        $query_where .= " OR re_memo like '%{$s}%' ) ";
    }

    if ( $receiver_group ) { // 검색어
        $query_where .= " AND ( gr_id = '{$receiver_group}' ) ";
    }

    if ( isset( $sf ) && $sf && isset( $ss ) && $ss ) { // 검색필드
        $query_where .= " AND ( {$sf} = '{$ss}' ";
    }

    $query_order = " ORDER BY ID desc ";
    if ( $orderby && $order ) { 
        $query_order = " ORDER BY {$orderby} {$order} ";
    }
    
    $total    = $wpdb->get_row( "SELECT count(ID) AS cnt FROM `".SMS4WP_RECEIVERS_TABLE."` WHERE (1)" ); // 전체 수신자
    $totalCnt = $wpdb->get_row( "SELECT count(ID) AS cnt FROM `".SMS4WP_RECEIVERS_TABLE."` WHERE (1)" . $query_where ); // 전체 수신자
    $notcall  = $wpdb->get_row( "SELECT count(ID) AS cnt FROM `".SMS4WP_RECEIVERS_TABLE."` WHERE ( re_use = '0' )" . $query_where ); // 수신거부 

    $list['total'] = $total->cnt;
    $list['total_not_call'] = $notcall->cnt;

    $total_page  = ceil($totalCnt->cnt / $page_rows);  // 전체 페이지 계산
    $from_record = ($paged - 1) * $page_rows; // 시작 열을 구함

    $rows = $wpdb->get_results( "SELECT * FROM `".SMS4WP_RECEIVERS_TABLE."` WHERE (1)" . $query_where . $query_order . " limit " . $from_record . ", " . $page_rows );
 
    if ( !( is_array($rows) || is_object($rows) ) )
        return;

    $c = 0;
    foreach ( $rows as $receivers ) { 
        foreach ( $receivers as $key=>$value ) { 
            $list['receivers'][$c][$key] = maybe_unserialize( $value );
        }
        $c++;
    }

    return $list;
}
//-- 수신자 정보 --//
function sms4wp_get_receiver( $id, $field = '' ) {
    global $wpdb;

    $options     = array();
    $field_name = trim($field);
    $fields      = ' * ';

    $id = intval( $id );

    if ( $field_name ) { // 한개의 옵션값 요청
        $fields = " {$field_name} ";
    }

    $rows = $wpdb->get_results( "SELECT {$fields} FROM `".SMS4WP_RECEIVERS_TABLE."` WHERE ID = '{$id}' "  );
    if ( !( is_array($rows[0]) || is_object($rows[0]) ) )
        return;

    foreach ( $rows[0] as $key=>$value ) { 
        $options[$key] = maybe_unserialize( $value );
    }

    if ( $field_name ) {
        return $options[$field];
    }
    else {
        return $options;
    }
}
//-- 수신자 정보 업데이트 --//
function sms4wp_receiver_update( $post ) {
    global $wpdb;

    $post = array_map( 'sms4wp_trim', $post );
    $post = array_map( 'sms4wp_htmlspecialchars', $post );
    extract( $post );

    if ( ! wp_verify_nonce( $_wpnonce, 'sms4wp_update_receiver_nonce' ) )
        return;

    $receiver_phone_number = sms4wp_get_hp( $receiver_phone_number, 0 );

    $fields = " `gr_id`           = '{$receiver_group}',
                `re_user_id`      = '{$user_id}',
                `re_name`         = '{$receiver_name}',
                `re_phone_number` = '{$receiver_phone_number}',
                `re_use`          = '{$receiver_use}',
                `re_memo`         = '{$receiver_memo}' ";

    $id = $id ? intval( $id ) : 0;
    if ( $id ) {
        $result = $wpdb->query( "UPDATE `".SMS4WP_RECEIVERS_TABLE."` SET " . $fields . " WHERE ID = '{$id}'" );
    }
    else {
        $result = $wpdb->query( "INSERT INTO `".SMS4WP_RECEIVERS_TABLE."` SET " . $fields . ", `re_update`  = now() " );
        $id     = mysql_insert_id();
    }

    $url = $_POST['_wp_http_referer'] . '&action=edit&s=' . $s . '&sf=' . $sf . '&paged=' . $paged . '&id=' . $id;

    return $url;
}
//-- 수신자 정보 삭제 --//
function sms4wp_receiver_delete( $data ) {
    global $wpdb;

    $data = array_map( 'sms4wp_trim', $data );
    $data = array_map( 'sms4wp_htmlspecialchars', $data );
    extract( $data );


    if ( ! wp_verify_nonce( $_wpnonce, 'sms4wp_delete_receiver_nonce' ) )
        return;

    if ( is_array( $id ) ) {
        foreach ( $id as $value ) {
            $value  = intval( $value );
            $result = $wpdb->query( "DELETE FROM `".SMS4WP_RECEIVERS_TABLE."` WHERE ID = '{$value}'" );
        }
    }
    else {
        $id  = intval( $id );
        if ( !$id )
            return;

        $result = $wpdb->query( "DELETE FROM `".SMS4WP_RECEIVERS_TABLE."` WHERE ID = '{$id}'" );
    }

    $qs = sms4wp_query_string();
    if ( $_wp_http_referer )
        $url = $_wp_http_referer . $qs;
    else
        $url = 'admin.php?page=sms4wp-receivers' . $qs;

    return $url;
}
//-- SMS전송목록 --//
function sms4wp_sms_sends( $id ) {
    global $wpdb;

    $list = array();
    $id   = intval( trim( $id ) );

    if ( !$id )
        return false;

    $rows = $wpdb->get_results( "SELECT * FROM `".SMS4WP_SEND_LIST_TABLE."` WHERE re_id = '{$id}' ORDER BY ID DESC" );
    if ( !( is_array($rows) || is_object($rows) ) )
        return;

    $c = 0;
    foreach ( $rows as $sends ) { 
        foreach ( $sends as $key=>$value ) { 
            $list[$c][$key] = maybe_unserialize( $value );
        }
        $c++;
    }

    return $list;
}
//-- 수신자 SMS전송목록 삭제 --//
function sms4wp_sms_send_delete( $data ) {
    global $wpdb;

    $data = array_map( 'trim', $data );
    $data = array_map( 'htmlspecialchars', $data );
    extract( $data );

    if ( ! wp_verify_nonce( $nonce, 'sms4wp_delete_sends_nonce' ) )
        return;

    $id = intval( $id );
    if ( !$id )
        return;

    $result = $wpdb->query( "DELETE FROM `".SMS4WP_SEND_LIST_TABLE."` WHERE ID = '{$id}'" );
    $qs     = sms4wp_query_string();
    $url    = $_POST['_wp_http_referer'] . $qs;

    return $url;
}


/***
 * 그룹관리 *
 ****/
//-- 그룹목록 --//
function sms4wp_list_groups( $args ) {
    global $wpdb;

    $list = array();
    $args = array_map( 'trim', $args );
    $args = array_map( 'htmlspecialchars', $args );
    extract( $args );

    $page_rows = intval( $page_rows );

    if ( $paged < 1 ) 
        $paged = 1;
    if ( !( isset($page_rows) && is_int($page_rows) ) ) // 한페이지에서 보여지는 아이템 숫자
        $page_rows = 15;

    if ( $s ) { // 검색어
        $query_where = " AND ( gr_name like '%{$s}%' ";
        $query_where .= " OR gr_memo like '%{$s}%' ) ";
    }

    $query_order = " ORDER BY gr_order asc ";
    if ( $orderby && $order ) { 
        $query_order = " ORDER BY {$orderby} {$order} ";
    }
    
    $total = $wpdb->get_row( "SELECT count(ID) AS cnt FROM `".SMS4WP_GROUP_TABLE."` WHERE (1)" . $query_where ); // 전체 수신자

    $list['total'] = $total->cnt;

    $total_page  = ceil($total->cnt / $page_rows);  // 전체 페이지 계산
    $from_record = ($paged - 1) * $page_rows; // 시작 열을 구함

    $rows = $wpdb->get_results( "SELECT * FROM `".SMS4WP_GROUP_TABLE."` WHERE (1)" . $query_where . $query_order . " limit " . $from_record . ", " . $page_rows );
    if ( !( is_array($rows) || is_object($rows) ) )
        return;

    $c = 0;
    foreach ( $rows as $group ) { 
        foreach ( $group as $key=>$value ) { 
            $list['group'][$c][$key] = maybe_unserialize( $value );
        }

        $receivers = $wpdb->get_row( "SELECT count(ID) AS cnt FROM `".SMS4WP_RECEIVERS_TABLE."` WHERE gr_id = '" . $list['group'][$c]['ID'] . "' " ); // 그룹 수신자 카운트
        $list['group'][$c]['receivers'] = $receivers->cnt;

        $c++;
    }

    return $list;
}
//-- 그룹정보 --//
function sms4wp_get_group( $id, $field = '' ) {
    global $wpdb;

    $group      = array();
    $field_name = trim($field);
    $fields     = ' * ';

    $id = intval( $id );

    if ( $field_name ) { // 한개의 옵션값 요청
        $fields = " {$field_name} ";
    }

    $rows = $wpdb->get_results( "SELECT {$fields} FROM `".SMS4WP_GROUP_TABLE."` WHERE ID = '{$id}' "  );
    if ( !( is_array($rows[0]) || is_object($rows[0]) ) )
        return;

    foreach ( $rows[0] as $key=>$value ) { 
        $group[$key] = maybe_unserialize( $value );
    }

    if ( $field_name ) {
        return $group[$field_name];
    }
    else {
        return $group;
    }
}
//-- 그룹 저장(수정) --//
function sms4wp_group_update( $post ) {
    global $wpdb;

    $group = array();
    $post  = array_map( 'trim', $post );
    $post  = array_map( 'htmlspecialchars', $post );
    extract( $post );

    if ( ! wp_verify_nonce( $_wpnonce, 'sms4wp_update_group_nonce' ) )
        return;

    $id = $id ? intval( $id ) : 0;

    if ( $id ) 
        $group = sms4wp_get_group( $id );

    $gr_depth = 1;
    if ( $group['gr_parent'] != $gr_parent ) {
        if ( $gr_parent > 0 ) {
            $parent   = sms4wp_get_group( $gr_parent );
            $gr_depth = $parent['gr_depth'];

            $gr_depth++;

            $order = $wpdb->get_row( "SELECT MAX(gr_order) max_order FROM `".SMS4WP_GROUP_TABLE."` WHERE LENGTH(gr_order) = ". strlen($parent['gr_order'])+4 ."  ", ARRAY_A );
            if ( $order['max_order'] > 0 )
                $gr_order = $order['max_order'] + 1;
            else 
                $gr_order = $parent['gr_order'] . 1000;
        }
        else {
            $order = $wpdb->get_row( "SELECT MAX(gr_order) max_order FROM `".SMS4WP_GROUP_TABLE."` WHERE LENGTH(gr_order) = 4  ", ARRAY_A );
            if ( $order['max_order'] > 0 )
                $gr_order = $order['max_order'] + 1;
            else 
                $gr_order = 1000;
        }
    }

    $gr_use = $gr_use != '' ? intval( $gr_use ): 1;

    $fields = " `gr_name`   = '{$gr_name}',
                `gr_count`  = '{$gr_count}',
                `gr_parent` = '{$gr_parent}',
                `gr_depth`  = '{$gr_depth}',
                `gr_order`  = '{$gr_order}',
                `gr_use`    = '{$gr_use}',
                `gr_memo`   = '{$gr_memo}' ";

    if ( $id ) {
        $result = $wpdb->query( "UPDATE `".SMS4WP_GROUP_TABLE."` SET " . $fields . " WHERE ID = '{$id}'" );
    }
    else {
        $result = $wpdb->query( "INSERT INTO `".SMS4WP_GROUP_TABLE."` SET " . $fields . ", `gr_update`  = now() " );
        $id     = mysql_insert_id();
    }

    $url = $_POST['_wp_http_referer'];
    if ( $action == 'update' )
        $url .= '&action=edit&id=' . $id;

    return $url;
}
//-- 그룹 삭제 --//
function sms4wp_group_delete( $data ) {
    global $wpdb;

    $data = array_map( 'sms4wp_trim', $data );
    $data = array_map( 'sms4wp_htmlspecialchars', $data );
    extract( $data );

    if ( ! wp_verify_nonce( $_wpnonce, 'sms4wp_delete_group_nonce' ) )
        return;

    if ( is_array( $id ) ) {
        foreach ( $id as $value ) {
            $value  = intval( $value );
            $result = $wpdb->query( "DELETE FROM `".SMS4WP_GROUP_TABLE."` WHERE ID = '{$value}'" );
        }
    }
    else {
        $id  = intval( $id );
        if ( !$id )
            return;

        $result = $wpdb->query( "DELETE FROM `".SMS4WP_GROUP_TABLE."` WHERE ID = '{$id}'" );
    }

    $qs  = sms4wp_query_string();
    if ( $_wp_http_referer )
        $url = $_wp_http_referer . $qs;
    else
        $url = 'admin.php?page=sms4wp-group' . $qs;

    return $url;
}
//-- 그룹전체 리스트 --//
function sms4wp_get_groups_all( $args = array() ) {
    global $wpdb;

    $list = array();
    $args = array_map( 'trim', $args );
    $args = array_map( 'htmlspecialchars', $args );
    extract( $args );

    $query_order = " ORDER BY ID desc ";
    if ( $orderby && $order ) { 
        $query_order = " ORDER BY {$orderby} {$order} ";
    }

    $query_where = " AND gr_depth = '1' ";

    $rows = $wpdb->get_results( "SELECT * FROM `".SMS4WP_GROUP_TABLE."` WHERE gr_use = 1 " . $query_where . $query_order );

    if ( !( is_array($rows) || is_object($rows) ) )
        return;

    foreach ( $rows as $group ) { 
        $gr_id = $group->ID;
        foreach ( $group as $key=>$value ) { 
            $list[$gr_id][$key] = maybe_unserialize( $value );
        }
    }

    return $list;
}
//-- 그룹select --//
function sms4wp_get_groups_select( $args = array() ) {
    global $wpdb;

    $select = '';
    $rows   = array();
    $rows   = sms4wp_get_groups_all( $args );

    if ( !( is_array($rows) || is_object($rows) ) )
        return;

    foreach ( $rows as $group ) { 
        // $gr_name = '';

        $gr_name = $group['gr_name'];

        $select .= '<option value="' . $group['ID'] . '">' . $group['gr_name'] . '</option>' . PHP_EOL;
        $c++;
    }

    return $select;
}
//-- 그룹내 수신자 목록 --//
function sms4wp_get_group_receivers( $gr_id ) {
    global $wpdb;

    $list  = array();
    $gr_id = intval( trim( $gr_id ) );

    if ( !$gr_id )
        return;

    $rows = $wpdb->get_results( "SELECT * FROM `".SMS4WP_RECEIVERS_TABLE."` WHERE gr_id = '{$gr_id}'" );
    
    if ( !( is_array($rows) || is_object($rows) ) )
        return;

    $c = 0;
    foreach ( $rows as $receiver ) { 
        foreach ( $receiver as $key=>$value ) { 
            $list[$c][$key] = maybe_unserialize( $value );
        }
        $c++;
    }

    return $list;
}



/***
 * 전송내역 *
 ****/
//-- 전체전송내역목록 --//
function sms4wp_list_sends( $args ) {
    global $wpdb;

    $list = array();
    $args = array_map( 'trim', $args );
    $args = array_map( 'htmlspecialchars', $args );
    extract( $args );

    $page_rows = intval( $page_rows );

    if ( $paged < 1 ) 
        $paged = 1;
    if ( !( isset($page_rows) && is_int($page_rows) ) ) // 한페이지에서 보여지는 아이템 숫자
        $page_rows = 15;

    if ( $s ) { // 검색어
        $query_where = " AND ( gr_name like '%{$s}%' ";
        $query_where .= " OR gr_memo like '%{$s}%' ) ";
    }

    if ( isset( $sf ) && isset( $ss ) ) { // 검색필드
        switch ( $sf ) {
            case 'se_result_code':
                if ( $ss == '200' )
                    $query_where .= " AND {$sf} = '{$ss}' ";
                else
                    $query_where .= " AND {$sf} <> '200' ";
                break;
            
            default:
                $query_where .= " AND {$sf} = '{$ss}' ";
                break;
        }
    }

    $query_order = " ORDER BY ID desc ";
    if ( $orderby && $order ) { 
        $query_order = " ORDER BY {$orderby} {$order} ";
    }
    
    $total      = $wpdb->get_row( "SELECT count(ID) AS cnt FROM `".SMS4WP_SEND_LIST_TABLE."` WHERE (1)" ); // 전체 발송
    $totalCnt   = $wpdb->get_row( "SELECT count(ID) AS cnt FROM `".SMS4WP_SEND_LIST_TABLE."` WHERE (1)" . $query_where ); // 검색전체 발송
    $successCnt = $wpdb->get_row( "SELECT count(ID) AS cnt FROM `".SMS4WP_SEND_LIST_TABLE."` WHERE se_result_code = '200' " ); // 발송성공 
    $failedCnt  = $wpdb->get_row( "SELECT count(ID) AS cnt FROM `".SMS4WP_SEND_LIST_TABLE."` WHERE se_result_code <> '200' " ); // 발송실패

    $list['total']      = $total->cnt;
    $list['successCnt'] = $successCnt->cnt;
    $list['failedCnt']  = $failedCnt->cnt;

    $total_page  = ceil($totalCnt->cnt / $page_rows);  // 전체 페이지 계산
    $from_record = ($paged - 1) * $page_rows; // 시작 열을 구함

    $rows = $wpdb->get_results( "SELECT * FROM `".SMS4WP_SEND_LIST_TABLE."` WHERE (1)" . $query_where . $query_order . " limit " . $from_record . ", " . $page_rows );

    if ( !( is_array($rows) || is_object($rows) ) )
        return;

    $c = 0;
    foreach ( $rows as $send ) { 
        foreach ( $send as $key=>$value ) { 
            $list['send'][$c][$key] = maybe_unserialize( $value );
        }

        $c++;
    }

    return $list;
}
//-- 전체전송목록 삭제 --//
function sms4wp_list_sends_delete( $data ) {
    global $wpdb;

    $data = array_map( 'sms4wp_trim', $data );
    $data = array_map( 'sms4wp_htmlspecialchars', $data );
    extract( $data );

    if ( ! wp_verify_nonce( $_wpnonce, 'sms4wp_delete_send_list_nonce' ) )
        return;

    if ( is_array( $id ) ) {
        foreach ( $id as $value ) {
            $value  = intval( $value );
            $result = $wpdb->query( "DELETE FROM `".SMS4WP_SEND_LIST_TABLE."` WHERE ID = '{$value}'" );
        }
    }
    else {
        $id  = intval( $id );
        if ( !$id )
            return;

        $result = $wpdb->query( "DELETE FROM `".SMS4WP_SEND_LIST_TABLE."` WHERE ID = '{$id}'" );
    }

    $qs  = sms4wp_query_string();
    if ( $_wp_http_referer )
        $url = $_wp_http_referer . $qs;
    else
        $url = 'admin.php?page=sms4wp-send-list' . $qs;

    return $url;
}


/***
 * 템플릿관리 *
 ****/
//-- 템플릿목록 --//
function sms4wp_list_templates( $args ) {
    global $wpdb;

    $list = array();
    $args = array_map( 'trim', $args );
    $args = array_map( 'htmlspecialchars', $args );
    extract( $args );

    $page_rows = intval( $page_rows );

    if ( $paged < 1 ) 
        $paged = 1;
    if ( !( isset($page_rows) && is_int($page_rows) ) ) // 한페이지에서 보여지는 아이템 숫자
        $page_rows = 15;

    if ( $s ) { // 검색어
        $query_where = " AND ( te_subject like '%{$s}%' ";
        $query_where .= " OR te_message like '%{$s}%' ) ";
    }

    $query_order = " ORDER BY ID desc ";
    if ( $orderby && $order ) { 
        $query_order = " ORDER BY {$orderby} {$order} ";
    }
    
    $total = $wpdb->get_row( "SELECT count(ID) AS cnt FROM `".SMS4WP_TEMPLATE_TABLE."` WHERE (1)" . $query_where ); // 전체 템플릿

    $list['total'] = $total->cnt;

    $total_page  = ceil($total->cnt / $page_rows);  // 전체 페이지 계산
    $from_record = ($paged - 1) * $page_rows; // 시작 열을 구함

    $rows = $wpdb->get_results( "SELECT * FROM `".SMS4WP_TEMPLATE_TABLE."` WHERE (1)" . $query_where . $query_order . " limit " . $from_record . ", " . $page_rows );

    if ( !( is_array($rows) || is_object($rows) ) )
        return;

    $c = 0;
    foreach ( $rows as $template ) { 
        // echo $row->op_name.':'. $row->op_value . '<br />';
        foreach ( $template as $key=>$value ) { 
            $list['template'][$c][$key] = maybe_unserialize( $value );
        }

        $c++;
    }

    return $list;
}
//-- 템플릿정보 --//
function sms4wp_get_template( $id, $field = '' ) {
    global $wpdb;

    $template   = array();
    $field_name = trim($field);
    $fields     = ' * ';

    $id = intval( $id );

    if ( $field_name ) { // 한개의 옵션값 요청
        $fields = " {$field_name} ";
    }

    $rows = $wpdb->get_results( "SELECT {$fields} FROM `".SMS4WP_TEMPLATE_TABLE."` WHERE ID = '{$id}' "  );
    if ( !( is_array($rows[0]) || is_object($rows[0]) ) )
        return;

    foreach ( $rows[0] as $key=>$value ) { 
        // echo $key.':'. $value . '<br />';
        $template[$key] = maybe_unserialize( $value );
    }

    if ( $field_name ) {
        return $template[$field_name];
    }
    else {
        return $template;
    }
}
//-- 템플릿 저장(수정) --//
function sms4wp_template_update( $post ) {
    global $wpdb;

    $template = array();
    $post     = array_map( 'trim', $post );
    $post     = array_map( 'htmlspecialchars', $post );
    extract( $post );

    $id = $id ? intval( $id ) : 0;

    $fields = " `te_type`    = '{$te_type}',
                `te_group`   = '{$te_group}',
                `te_subject` = '{$te_subject}',
                `te_message`    = '{$te_message}',
                `te_file1`   = '{$te_file1}',
                `te_file2`   = '{$te_file2}' ";

    if ( $id ) {
        $result = $wpdb->query( "UPDATE `".SMS4WP_TEMPLATE_TABLE."` SET " . $fields . " WHERE ID = '{$id}'" );
    }
    else {
        $result = $wpdb->query( "INSERT INTO `".SMS4WP_TEMPLATE_TABLE."` SET " . $fields . ", `te_date`  = now() " );
        $id     = mysql_insert_id();
    }

    $url = $_POST['_wp_http_referer'] . '&action=edit&id=' . $id;

    return $url;
}
//-- 템플릿 삭제 --//
function sms4wp_template_delete( $data ) {
    global $wpdb;

    $data = array_map( 'sms4wp_trim', $data );
    $data = array_map( 'sms4wp_htmlspecialchars', $data );
    extract( $data );
    if ( ! wp_verify_nonce( $_wpnonce, 'sms4wp_delete_template_nonce' ) )
        return;

    if ( is_array( $id ) ) {
        foreach ( $id as $value ) {
            $value  = intval( $value );
            $result = $wpdb->query( "DELETE FROM `".SMS4WP_TEMPLATE_TABLE."` WHERE ID = '{$value}'" );
        }
    }
    else {
        $id  = intval( $id );
        if ( !$id )
            return;

        $result = $wpdb->query( "DELETE FROM `".SMS4WP_TEMPLATE_TABLE."` WHERE ID = '{$id}'" );
    }

    $qs = sms4wp_query_string();
    if ( $_wp_http_referer )
        $url = $_wp_http_referer . $qs;
    else
        $url = 'admin.php?page=sms4wp-template' . $qs;

    return $url;
}
//-- 템플릿 그룹 추가 --//
function sms4wp_template_groups() {
    global $wpdb;

}


/***
 * 가져오기/내보내기 *
 ****/
//-- 수신자 내보내기 --//
function sms4wp_book_file_download( $gr_id, $nonce ) {
    global $wpdb;

    $group = sms4wp_get_group( $gr_id );
    if ( !$group['ID'] )
        return;

    $rows = $wpdb->get_results( "SELECT * FROM `".SMS4WP_RECEIVERS_TABLE."` WHERE gr_id = '{$group['gr_id']}' " );

    if ( !( is_array($rows) || is_object($rows) ) )
        return;

    $c = 0;
    foreach ( $rows as $receivers ) { 
        foreach ( $receivers as $key=>$value ) { 
        }
        $c++;
    }

    return $list;
}
//-- 수신자 가져오기 --//
function sms4wp_book_file_upload( $gr_id, $nonce ) {
    global $wpdb;

    $group = sms4wp_get_group( $gr_id );
    if ( !$group['ID'] )
        return;
}

?>
