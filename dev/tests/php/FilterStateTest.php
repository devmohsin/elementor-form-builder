<?php

use PHPUnit\Framework\TestCase;

class FilterStateTest extends TestCase {

	protected function tearDown() : void {
		$_GET = array();
		parent::tearDown();
	}

	public function test_from_get_sanitizes_values() : void {
		$_GET = array(
			'clefa_filter' => array(
				'color' => ' red ',
				'size'  => array( 'min' => '10', 'max' => '20' ),
			),
			'clefa_page'    => '2',
			'clefa_orderby' => 'title',
			'clefa_order'   => 'ASC',
		);

		$state = CLEFA_Filter_State::from_get();
		$this->assertSame( 'red', $state->get( 'color' ) );
		$this->assertSame( array( 'min' => '10', 'max' => '20' ), $state->get( 'size' ) );
		$this->assertSame( 2, $state->get_page() );
		$this->assertSame( 'title', $state->get_orderby() );
		$this->assertSame( 'ASC', $state->get_order() );
	}

	public function test_is_active_false_for_empty_values() : void {
		$_GET = array(
			'clefa_filter' => array(
				'empty'   => '',
				'blank'   => array(),
				'zeros'   => array( 'min' => '', 'max' => '' ),
				'active'  => 'yes',
			),
		);
		$state = CLEFA_Filter_State::from_get();
		$this->assertFalse( $state->is_active( 'empty' ) );
		$this->assertFalse( $state->is_active( 'blank' ) );
		$this->assertFalse( $state->is_active( 'zeros' ) );
		$this->assertTrue( $state->is_active( 'active' ) );
	}

	public function test_get_active_count() : void {
		$_GET = array(
			'clefa_filter' => array(
				'a' => '1',
				'b' => '',
				'c' => 'x',
			),
		);
		$this->assertSame( 2, CLEFA_Filter_State::from_get()->get_active_count() );
	}

	public function test_to_query_string_includes_non_defaults() : void {
		$_GET = array(
			'clefa_filter' => array( 'cat' => 'news' ),
			'clefa_page'   => '3',
		);
		$qs = CLEFA_Filter_State::from_get()->to_query_string();
		$this->assertStringContainsString( 'clefa_filter', $qs );
		$this->assertStringContainsString( 'clefa_page=3', $qs );
	}

	public function test_from_request() : void {
		$request = new WP_REST_Request( array(
			'filter'  => array( 'tag' => 'featured' ),
			'page'    => 1,
			'orderby' => 'date',
			'order'   => 'DESC',
		) );
		$state = CLEFA_Filter_State::from_request( $request );
		$this->assertSame( 'featured', $state->get( 'tag' ) );
		$this->assertSame( 1, $state->get_page() );
	}
}
