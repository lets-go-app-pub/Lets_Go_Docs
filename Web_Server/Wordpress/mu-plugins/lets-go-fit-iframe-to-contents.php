<?php

/**
 * Plugin Name: Add To Email List
 * Description: Adds an email to the passed email list for SendGrid.
 * Version: 1.0.0
 */

//Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SendEmailToSendGridMailList {
	function __construct() {
		add_shortcode( 'setup_iframe_to_fit_contents', array( $this, 'runShortcode' ) );
	}

	function returnInputEmailForm($url): string {
		ob_start();
		?>
            <iframe style="width: 100%; height: 750px; border: 0;" src="<?php echo $url ?>"></iframe>
<!--        <iframe style="height: 100vh; width: 100%; border: 0;" src="--><?php //echo $url ?><!--"></iframe>-->
		<?php
		return ob_get_clean();
	}

	function runShortcode( $attr ): string {
		$options = shortcode_atts(
			array(
				'url' => '#'
			),
			$attr
		);

		return $this->returnInputEmailForm($options['url']);
	}
}

$sendEmailToSendGridMailList = new SendEmailToSendGridMailList();
