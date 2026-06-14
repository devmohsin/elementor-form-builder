<?php



use PHPUnit\Framework\TestCase;



class FormEscaperTest extends TestCase {



	public function test_esc_html_context() : void {

		$this->assertSame( '&lt;script&gt;', CLEFA_Form_Escaper::esc( '<script>', 'html' ) );

	}



	public function test_esc_attr_context() : void {

		$this->assertSame( 'a&amp;b', CLEFA_Form_Escaper::esc( 'a&b', 'attr' ) );

	}



	public function test_esc_url_context() : void {

		$this->assertSame( 'https://example.com', CLEFA_Form_Escaper::esc( 'https://example.com', 'url' ) );

	}



	public function test_esc_textarea_context() : void {

		$this->assertStringContainsString( 'line1', CLEFA_Form_Escaper::esc( "line1\nline2", 'textarea' ) );

	}



	public function test_esc_json_context() : void {

		$json = CLEFA_Form_Escaper::esc( array( 'a' => 1 ), 'json' );

		$this->assertSame( '{"a":1}', $json );

	}



	public function test_esc_raw_context() : void {

		$this->assertSame( '<b>ok</b>', CLEFA_Form_Escaper::esc( '<b>ok</b>', 'raw' ) );

	}



	public function test_esc_kses_allows_safe_html() : void {

		$result = CLEFA_Form_Escaper::esc( '<p>Hi</p><script>x</script>', 'kses' );

		$this->assertStringContainsString( '<p>Hi</p>', $result );

		$this->assertStringNotContainsString( '<script>', $result );

	}



	public function test_context_for_field_type_map() : void {

		$this->assertSame( 'url', CLEFA_Form_Escaper::context_for_field( 'url' ) );

		$this->assertSame( 'textarea', CLEFA_Form_Escaper::context_for_field( 'textarea' ) );

		$this->assertSame( 'kses', CLEFA_Form_Escaper::context_for_field( 'html' ) );

		$this->assertSame( 'html', CLEFA_Form_Escaper::context_for_field( 'text' ) );

	}



	public function test_esc_field_uses_type_context() : void {

		$this->assertSame( 'https://x.com', CLEFA_Form_Escaper::esc_field( 'https://x.com', 'url' ) );

	}



	public function test_contexts_constant_lists_all() : void {

		$this->assertContains( 'html', CLEFA_Form_Escaper::CONTEXTS );

		$this->assertContains( 'json', CLEFA_Form_Escaper::CONTEXTS );

	}

}


