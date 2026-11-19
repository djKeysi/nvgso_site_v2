<?php
/**
 * Небольшие правки админки.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_head',
	function () {
		echo '<style id="nvgso-admin-ui">#wf-onboarding-banner{display:none!important}</style>';
	}
);
