<?php

use PHPUnit\Framework\TestCase;

class AdminUITest extends TestCase {

	public function test_clear_after_notices_outputs_clear_div() : void {
		$_GET['page'] = 'clefa-forms';
		ob_start();
		CLEFA_Admin_UI::clear_after_notices();
		$html = ob_get_clean();
		unset( $_GET['page'] );
		$this->assertStringContainsString( 'class="clear clefa-admin-notice-clear"', $html );
	}

	public function test_clear_after_notices_skips_non_clefa_pages() : void {
		$_GET['page'] = 'plugins.php';
		ob_start();
		CLEFA_Admin_UI::clear_after_notices();
		$html = ob_get_clean();
		unset( $_GET['page'] );
		$this->assertSame( '', $html );
	}

	public function test_settings_messages_renders_settings_errors() : void {
		global $clefa_test_settings_errors;
		$clefa_test_settings_errors = array();
		add_settings_error( 'clefa_test', 'saved', 'Settings saved.', 'updated' );
		ob_start();
		CLEFA_Admin_UI::settings_messages( 'clefa_test' );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'Settings saved.', $html );
	}
}
