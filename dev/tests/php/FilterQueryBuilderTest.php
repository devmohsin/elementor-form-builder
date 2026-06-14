<?php



use PHPUnit\Framework\TestCase;



class FilterQueryBuilderTest extends TestCase {



	private function state( array $filter, $page = 1 ) : CLEFA_Filter_State {

		$_GET = array(

			'clefa_filter' => $filter,

			'clefa_page'   => (string) $page,

		);

		return CLEFA_Filter_State::from_get();

	}



	protected function tearDown() : void {

		$_GET = array();

		parent::tearDown();

	}



	public function test_build_taxonomy_filter() : void {

		$widget = array(

			'filter_post_type' => 'post',

			'posts_per_page'   => 5,

			'filter_sections'  => array(

				array(

					'section_id'  => 'cat',

					'filter_type' => 'checkbox',

					'source_type' => 'taxonomy',

					'source_key'  => 'category',

				),

			),

		);

		$args = CLEFA_Filter_Query_Builder::build( $widget, $this->state( array( 'cat' => array( 'news', 'blog' ) ) ) );

		$this->assertSame( 'post', $args['post_type'] );

		$this->assertSame( 5, $args['posts_per_page'] );

		$this->assertCount( 2, $args['tax_query'] );

		$this->assertSame( 'category', $args['tax_query'][0]['taxonomy'] );

		$this->assertSame( array( 'news', 'blog' ), $args['tax_query'][0]['terms'] );

	}



	public function test_build_meta_range_filter() : void {

		$widget = array(

			'filter_sections' => array(

				array(

					'section_id'  => 'price',

					'filter_type' => 'range_dual',

					'source_type' => 'post_meta',

					'source_key'  => 'custom_price',

				),

			),

		);

		$args = CLEFA_Filter_Query_Builder::build(

			$widget,

			$this->state( array( 'price' => array( 'min' => '10', 'max' => '100' ) ) )

		);

		$this->assertSame( 'custom_price', $args['meta_query'][0]['key'] );

		$this->assertSame( 'BETWEEN', $args['meta_query'][0]['compare'] );

	}



	public function test_build_skips_inactive_sections() : void {

		$widget = array(

			'filter_sections' => array(

				array(

					'section_id'  => 'cat',

					'filter_type' => 'checkbox',

					'source_type' => 'taxonomy',

					'source_key'  => 'category',

				),

			),

		);

		$args = CLEFA_Filter_Query_Builder::build( $widget, $this->state( array( 'cat' => '' ) ) );

		$this->assertArrayNotHasKey( 'tax_query', $args );

	}



	public function test_build_search_section() : void {

		$widget = array(

			'filter_sections' => array(

				array(

					'section_id'  => 'q',

					'filter_type' => 'search',

					'source_type' => 'search',

					'source_key'  => 's',

				),

			),

		);

		$args = CLEFA_Filter_Query_Builder::build( $widget, $this->state( array( 'q' => 'hello world' ) ) );

		$this->assertSame( 'hello world', $args['s'] );

	}



	public function test_orderby_map_price_sets_meta_key() : void {

		$_GET = array(

			'clefa_filter' => array(),

			'clefa_orderby' => 'price',

		);

		$state = CLEFA_Filter_State::from_get();

		$args  = CLEFA_Filter_Query_Builder::build( array( 'filter_sections' => array() ), $state );

		$this->assertSame( '_price', $args['meta_key'] );

		$this->assertSame( 'meta_value_num', $args['orderby'] );

	}

}


