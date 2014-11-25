
<link rel="stylesheet" href="<?php echo SMS4WP_INC_VIEW_CSS_URL; ?>/sms4wp.css" />

<div class="wrap">
<h2><?php esc_html_e( 'SMS4WP Config'); ?></h2>

<div class="local_desc01 local_desc">
    <p>
        <?php esc_html_e( 'SMS 기능을 사용하시려면 먼저 SMS4WP에 서비스 신청을 하셔야 합니다.'); ?><br>
        <a href="https://sms4wp.com/register/" target="_blank" class="button"><?php esc_html_e( 'SMS4WP 서비스 신청하기'); ?></a>
    </p>
</div>

<form id="frmConfig" method="post" action="admin.php?page=sms4wp-configure">
<input type="hidden" name="action" value="update" />
<input type="hidden" name="_wp_http_referer" value="admin.php?page=sms4wp-configure">

<table class="form-table">
<!-- <tr>
<th scope="row"><label for="sms4wp_mb_id"><?php _e('SMS4WP member ID') ?></label></th>
<td><input name="sms4wp_mb_id" type="text" id="sms4wp_mb_id" value="<?php echo $sms4wp_config['sms4wp_mb_id']; ?>" class="regular-text" /></td>
</tr> -->

<tr>
<th scope="row"><label for="sms4wp_auth_token"><?php _e('인증 토큰') ?></label></th>
<td><input name="sms4wp_auth_token" type="text" id="sms4wp_auth_token" value="<?php echo $sms4wp_config['sms4wp_auth_token']; ?>" class="regular-text" /></td>
</tr>

<!-- <tr>
<th scope="row"><label for="sms4wp_auth_email"><?php //_e('Authentication E-Amil ') ?></label></th>
<td><input name="sms4wp_auth_email" type="text" id="sms4wp_auth_email" value="<?php //echo $sms4wp_config['sms4wp_auth_email']; ?>" class="regular-text" /></td>
</tr> -->

<tr>
<th scope="row"><label for="sms4wp_auth_signature"><?php _e('인증 시그니쳐') ?></label></th>
<td><input name="sms4wp_auth_signature" type="text" id="sms4wp_auth_signature" value="<?php echo $sms4wp_config['sms4wp_auth_signature']; ?>" class="regular-text" />
</td>
</tr>

<tr>
<th scope="row"><label for="sms4wp_charge"><?php _e('문자 잔여건수') ?></label></th>
<td>
	<input name="message_type" type="radio" class="message_type" value="SMS" checked /><span class="msg_type_sms sms_selected">SMS</span> &nbsp; 
	<input name="message_type" type="radio" class="message_type" value="LMS" /><span class="msg_type_lms">LMS</span> &nbsp; 
	<input name="message_type" type="radio" class="message_type" value="MMS" /><span class="msg_type_mms">MMS</span>
	<span class="message_count"><?php echo $sms_charge; ?></span>
	<br /><a href="https://sms4wp.com/price/" class="button" target="_blank"><?php _e('충전하기') ?></a>
</td>
</tr>
<tr>
<th scope="row"><label for="sms4wp_reply_number"><?php _e('회신번호') ?> </label></th>
<td><input name="sms4wp_reply_number" type="text" id="sms4wp_reply_number" value="<?php echo $sms4wp_config['sms4wp_reply_number']; ?>" />
	<!-- <a href="#" class="button"><?php _e('휴대폰 인증') ?></a> -->
</td>
</tr>

</tr>

</table>

<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save Changes') ?>"></p>

</form>

</div>
<script>
(function($){
	$('.message_type').click(function (e) {
		var msg_type = $(this).val();

		var sms_point = "<?php echo number_format( $sms4wp_data['sms_point'] ); ?>";
		var lms_point = "<?php echo number_format( $sms4wp_data['lms_point'] ); ?>";
		var mms_point = "<?php echo number_format( $sms4wp_data['mms_point'] ); ?>";

		if ( msg_type == "MMS" ) {
			if ( mms_point != "0" ) {
				$('.msg_type_mms').addClass('sms_selected');
				$('.msg_type_sms').removeClass('sms_selected');
				$('.msg_type_lms').removeClass('sms_selected');

				$(".message_count").text( "MMS: " + mms_point + "건");
			}
			else {
				$(".message_count").text( "<?php echo $sms4wp_data['sms_error_msg']; ?>");
			}
		}
		else if ( msg_type == "LMS" ) {
			if ( lms_point != "0" ) {
				$('.msg_type_mms').removeClass('sms_selected');
				$('.msg_type_sms').removeClass('sms_selected');
				$('.msg_type_lms').addClass('sms_selected');

				$(".message_count").text( "LMS: " + lms_point + "건" );
			}
			else {
				$(".message_count").text( "<?php echo $sms4wp_data['sms_error_msg']; ?>");
			}
		}
		else {
			if ( sms_point != "0" ) {
				$('.msg_type_mms').removeClass('sms_selected');
				$('.msg_type_sms').addClass('sms_selected');
				$('.msg_type_lms').removeClass('sms_selected');

				$(".message_count").text( "SMS: " + sms_point + "건" );
			}
			else {
				$(".message_count").text( "<?php echo $sms4wp_data['sms_error_msg']; ?>");
			}
		}
	});	
})(jQuery);
</script>
