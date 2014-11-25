<?php
if ( !defined('ABSPATH') ) exit;

require_once( SMS4WP_INC_MODEL_PATH . '/sms4wp.lib.php' );

/**
 * sms4wp 패널 메뉴생성 및 플러그인 설치시 초기 테이블생성
 */


//-- admin panel menu structure --//
add_action('admin_menu', 'sms4wp_menu');
function sms4wp_menu() {
    add_menu_page('SMS4WP title', '문자메시지', none, 'sms4wp-service', 'sms4wp_view_configure', '', 100);

    add_submenu_page('sms4wp-service', 'SMS4WP SET-UP',    '설정',          'manage_options', 'sms4wp-configure', 'sms4wp_view_configure');
    add_submenu_page('sms4wp-service', 'SMS4WP SEND',      '메시지 보내기',   'manage_options', 'sms4wp-send',      'sms4wp_view_send');
    add_submenu_page('sms4wp-service', 'SMS4WP RECEIVERS', '수신자 관리',     'manage_options', 'sms4wp-receivers', 'sms4wp_view_receivers');
    add_submenu_page('sms4wp-service', 'SMS4WP GROUP',     '수신자 그룹관리',  'manage_options', 'sms4wp-group',     'sms4wp_view_group');
    add_submenu_page('sms4wp-service', 'SMS4WP SEND LIST', '전송 내역',       'manage_options', 'sms4wp-send-list', 'sms4wp_view_send_list');
    add_submenu_page('sms4wp-service', 'SMS4WP TEMPLATE',  '템플릿 관리',     'manage_options', 'sms4wp-template',   'sms4wp_view_template');
    add_submenu_page('sms4wp-service', 'SMS4WP BOOK FILE', '가져오기', 'manage_options', 'sms4wp-book-file',  'sms4wp_view_book_file');
    // add_submenu_page('sms4wp-service', 'SMS4WP BOOK FILE', '가져오기/내보내기', 'manage_options', 'sms4wp-book-file',  'sms4wp_view_book_file');
    // add_submenu_page('sms4wp-service', 'SMS4WP Plugin Options', '수신자 그룹관리', 'manage_options', 'edit-tags.php?taxonomy=sms4wp');
}

//-- plugin auto update --//
add_action('init', 'sms4wp_activate_au');
function sms4wp_activate_au() {
    require_once ( SMS4WP_INC_CORE_PATH . '/sms4wp.update.php' );
    
    $sms4wp_plugin_current_version = get_option( 'sms4wp-plugin-version' );
    $sms4wp_plugin_remote_path     = admin_url() . '/update.php';
    $sms4wp_plugin_slug            = plugin_basename(__FILE__);

    if ( !$sms4wp_plugin_current_version ) {
        add_option( 'sms4wp-plugin-version', '1.0', '', 'no' );
        $sms4wp_plugin_current_version = '1.0';
    }

    new sms4wp_auto_update ( $sms4wp_plugin_current_version, $sms4wp_plugin_remote_path, $sms4wp_plugin_slug );
}


//-- sms4wp registers a plugin function --//
function sms4wp_install() {
    global $wpdb;
    global $charset_collate;

    if ( !empty( $wpdb->charset ) ) {
        $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
    }

    if ( !empty( $wpdb->collate ) ) {
        $charset_collate .= " COLLATE $wpdb->collate";
    }

    // sms options table
    if( $wpdb->get_var("show tables like `".SMS4WP_OPTIONS_TABLE."`") != SMS4WP_OPTIONS_TABLE ) {
        $sqls[] = "
            CREATE TABLE `".SMS4WP_OPTIONS_TABLE."` (
                `ID` bigint(20) NOT NULL AUTO_INCREMENT,
                `op_name` varchar(255) NOT NULL default '',
                `op_value` longtext NOT NULL,
                `op_date` datetime NOT NULL default '0000-00-00 00:00:00',
                `op_use` tinyint(4) NOT NULL default '1', /* 0:미사용, 1:사용, */
                PRIMARY KEY  (`ID`),
                UNIQUE KEY `op_name` (`op_name`)
            ) {$charset_collate};
        ";
    }

    // sms send list table
    if( $wpdb->get_var("show tables like `".SMS4WP_SEND_LIST_TABLE."`") != SMS4WP_SEND_LIST_TABLE ) {
        $sqls[] = "
            CREATE TABLE `".SMS4WP_SEND_LIST_TABLE."` (
                `ID` bigint(20) NOT NULL AUTO_INCREMENT,
                `re_id` bigint(11) NOT NULL default '0',
                `se_type` varchar(10) NOT NULL default '', /* SMS, LMS, MMS */
                `se_send_number` varchar(100) NOT NULL default '', /* 보내는 번호 */
                `se_receiver_number` varchar(100) NOT NULL default '', /* 받는 번호 */
                `se_receiver_name` varchar(100) NOT NULL default '', /* 받는 번호 */
                `se_subject` varchar(255) NOT NULL default '',
                `se_message` text NOT NULL,
                `se_sms_file1` varchar(255) NOT NULL default '',
                `se_sms_file2` varchar(255) NOT NULL default '', 
                `se_reservation_date` datetime NOT NULL default '0000-00-00 00:00:00', /* 예약일 */
                `se_reservation_use` tinyint(4) NOT NULL default '0', /* 0:즉시발송, 1:예약문자, */
                `se_result` tinyint(4) NOT NULL default '0', /* 0:실패, 1:성공, */
                `se_result_code` text NOT NULL,
                `se_date` datetime NOT NULL default '0000-00-00 00:00:00', /* 발송일 */
                PRIMARY KEY  (`ID`),
                KEY `re_id` (`re_id`)
            );
        ";
    }

    // sms receivers table
    if( $wpdb->get_var("show tables like `".SMS4WP_RECEIVERS_TABLE."`") != SMS4WP_RECEIVERS_TABLE ) {
        $sqls[] = "
            CREATE TABLE `".SMS4WP_RECEIVERS_TABLE."` (
                `ID` bigint(20) NOT NULL AUTO_INCREMENT,
                `gr_id` bigint(11) NOT NULL default '0',
                `re_user_id` varchar(255) NOT NULL default '',
                `re_name` varchar(255) NOT NULL default '',
                `re_phone_number` varchar(100) NOT NULL default '',
                `re_use` tinyint(4) NOT NULL default '1', /* 0:수신거부, 1:수신허용, */
                `re_memo` text NOT NULL,
                `re_update` datetime NOT NULL default '0000-00-00 00:00:00',
                PRIMARY KEY  (`ID`)
            );
        ";
    }


    // sms group table
    if( $wpdb->get_var("show tables like `".SMS4WP_GROUP_TABLE."`") != SMS4WP_GROUP_TABLE ) {
        $sqls[] = "
            CREATE TABLE `".SMS4WP_GROUP_TABLE."` (
                `ID` bigint(20) NOT NULL AUTO_INCREMENT,
                `gr_count` int(11) NOT NULL default '0',
                `gr_name` varchar(255) NOT NULL default '',
                `gr_parent` bigint(20) NOT NULL default '0',
                `gr_depth` tinyint(4) NOT NULL default '1',
                `gr_order` int(11) NOT NULL default '0',
                `gr_use` tinyint(4) NOT NULL default '1',
                `gr_memo` text NOT NULL,
                `gr_update` datetime NOT NULL default '0000-00-00 00:00:00',
                PRIMARY KEY  (`ID`)
            );
        ";
    }
    // sms template table
    if( $wpdb->get_var("show tables like `".SMS4WP_TEMPLATE_TABLE."`") != SMS4WP_TEMPLATE_TABLE ) {
        $sqls[] = "
            CREATE TABLE `".SMS4WP_TEMPLATE_TABLE."` (
                `ID` bigint(20) NOT NULL AUTO_INCREMENT,
                `te_type` varchar(10) NOT NULL default '',
                `te_group` varchar(255) NOT NULL default '',
                `te_subject` varchar(255) NOT NULL default '',
                `te_message` text NOT NULL,
                `te_file1` varchar(255) NOT NULL default '',
                `te_file2` varchar(255) NOT NULL default '',
                `te_date` datetime NOT NULL default '0000-00-00 00:00:00',
                PRIMARY KEY  (`ID`)
            );
        ";
    }
 
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    foreach($sqls as $sql) {
        dbDelta($sql);
    }
}

?>
