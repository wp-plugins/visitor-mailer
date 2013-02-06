<?php

if(isset($_POST['submit'])) {
	$options = get_option( 'statmailer_options' );
	if ($options['send_email']) {
		statmailer_log('Go to schedule email function');
		statmailer_schedule_email();
	}	
}

?>