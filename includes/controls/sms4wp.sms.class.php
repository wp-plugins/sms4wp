<?php
if ( !defined('ABSPATH') ) 
	exit;
// sms4wp에서 제공하는 함수

///////////////////////////////////////////////////////////////////////////////////////////
// 이 부분은 건드릴 필요가 없습니다.

function sms4wp_CheckPhoneNumber( $sender_phone ) {
	//$sender_phone=eregi_replace("[^0-9]","",$sender_phone);
	$sender_phone = preg_replace( "/[^0-9]/i", "", $sender_phone );
	if ( strlen($sender_phone) < 10 || strlen($sender_phone) > 11 ) 
		return "휴대폰 번호가 틀렸습니다";

	$pnc = substr( $sender_phone, 0, 3 );
	if ( preg_match("/[^0-9]/i", $pnc) || ( $pnc!='010' && $pnc!='011' && $pnc!='016' && $pnc!='017' && $pnc!='018' && $pnc!='019' ) ) 
		return "휴대폰 앞자리 번호가 잘못되었습니다";
}

function sms4wp_substitute( $message, $pattern = array() ) {
	if ( is_array($pattern) ) {
		foreach ( $pattern as $key => $value ) {
			$message = preg_replace($key, $value, $message);
		}
	}
	return $message;
}

class SMS4WP {
	private $backend_url_root;
    private $is_test;
    private $email;
    private $auth_token;
    private $signature_value;

    private $url;
    private $method;
    private $header = array();
    private $Sends  = array();
    private $Result = '';

	private $countgap = 1000; // 몇건씩 보낼지 설정
	private $sleepsec = 5;  // 천분의 몇초간 쉴지 설정

	public function __construct( $email, $auth_token, $signature ) {
		$this->backend_url_root = SMS4WP_SMS_URL;
		// $this->send_date        = date('c');
		$this->url              = $this->backend_url_root;
		$this->method           = 'POST';
		$this->is_test          = 'no';
		$this->email            = $email;
		$this->auth_token       = $auth_token;
		$this->signature_value  = $signature;
		$this->header           = array( sprintf( "Authorization: token %s", $this->auth_token ) );
	}

	public function Init() {
		$this->Sends = array();
		$this->Result = array();
	}

	public function Add( $args = array() ) {
        $default = array( 
        	'send_date'    => date( c, time() - (60 * 60 * 9) ), 
        	'message_type' => 'SMS', 
        	'bulk_file'    => '', 
        	'file'         => ''
        );

        $add_file1 = '';
        $add_file2 = '';

        if ( $args['file']['name'] ) {
			$original_name = dirname( $args['file']['tmp_name'] ) . '/' . $args['file']['name'];
			@rename( $args['file']['tmp_name'], $original_name );
			$add_file1 = '@' . $original_name; // MMS 첨부이미지 
		}

        $args = array_merge( $default, $args );
        $args = array_map( 'sms4wp_trim', $args );
        extract( $args );

        switch ( $message_type ) {
        	case 'SMS':
        		$this->backend_url_root = SMS4WP_SMS_URL;
				$this->url              = $this->backend_url_root;
        		break;
        	case 'LMS':
        		$this->backend_url_root = SMS4WP_LMS_URL;
				$this->url              = $this->backend_url_root;
        		break;
        	case 'MMS':
        		$this->backend_url_root = SMS4WP_MMS_URL;
				$this->url              = $this->backend_url_root;
        		break;
        }

		$receiver_phone = preg_replace( "/[^0-9]/i", "", $receiver_phone );
		$sender_phone   = preg_replace( "/[^0-9]/i", "", $sender_phone );

		// 받는 번호 검사 1
		$Error = sms4wp_CheckPhoneNumber( $receiver_phone );
		if ( $Error ) 
			return $Error;

		// 보내는 번호 검사 2
		if ( preg_match( "/[^0-9]/i", $sender_phone ) ) 
			return "회신 전화번호가 잘못되었습니다";

        $message_body = sms4wp_substitute( $message_body, $pattern ); // 내용 치환

		$data = array(
	        'signature_value' => $this->signature_value,
	        'sender_phone'    => $sender_phone,
	        'sender_name'     => $send_name,
	        'body'            => $message_body,
	        'subject'         => $message_subject,
	        'message_type'    => $message_type,
	        'receiver_phone'  => $receiver_phone, 
	        'receiver_name'   => $receiver_name,
	        'send_date'       => $send_date,
	        'send_timestamp'  => $send_timestamp, /* 예약일 */
	        /*'is_test'         => $is_test,*/
	        'file'            => $add_file1,
	        'bulk_file'       => $add_file2,
	        're_id'           => $re_id,
	    );


		array_push( $this->Sends, $data );
	}

	public function Send () {
		$ch = curl_init();

		if ( false !== $ch ) {
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
		    curl_setopt( $ch, CURLOPT_COOKIESESSION, TRUE );
		    curl_setopt( $ch, CURLOPT_FORBID_REUSE, TRUE );
		    curl_setopt( $ch, CURLOPT_FRESH_CONNECT, TRUE );
		    // curl_setopt($ch, CURLOPT_VERBOSE, TRUE);

		    curl_setopt( $ch, CURLOPT_HTTPHEADER, $this->header );

		    switch ( $this->method ) {
		        case 'GET':
		            curl_setopt( $ch, CURLOPT_HTTPGET, TRUE );
		        break;

		        case 'PUT':
		            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "PUT" );
		        break;

		        case 'DELETE':
		            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "DELETE" );
		        break;

		        case 'POST':
		            curl_setopt( $ch, CURLOPT_POST, TRUE );
		        break;

		        case 'HEAD':
		            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "HEAD" );
		        break;

		        case 'OPTIONS':
		            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "OPTIONS" );
		        break;

		        default:
		            return;
		        break;
		    }

		    // die( json_encode( $this->Sends[1]['subject'] . ' - test') );

		    curl_setopt( $ch, CURLOPT_URL, $this->url );

		    foreach ( $this->Sends as $key => $data ) {
			    curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
			    $result    = curl_exec( $ch );
			    $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

			    $this->Save_sms( $data, $result, $http_code );
			    array_push( $this->Result, $result );
		    }

		}

		curl_close( $ch );
	}

	public function Save_sms( $data, $result, $http_code ) {
		global $wpdb;

		extract( $data );

		$se_reservation_use = 0;
		if ( $send_timestamp )
			$se_reservation_use = 1;

		$fields = " re_id               = '{$re_id}',
					se_type             = '{$message_type}',
					se_send_number      = '{$sender_phone}',
					se_receiver_number  = '{$receiver_phone}',
					se_subject          = '{$subject}',
					se_message          = '{$body}',
					se_sms_file1        = '{$se_sms_file1}',
					se_sms_file2        = '{$se_sms_file2}',
					se_reservation_date = '{$send_timestamp}',
					se_reservation_use  = '{$se_reservation_use}',
					se_result           = '{$result}',
					se_result_code      = '{$http_code}',
					se_date             = '{$send_date}'
		";
		$result = $wpdb->query( "INSERT INTO `".SMS4WP_SEND_LIST_TABLE."` SET $fields " );
	}
}
?>
