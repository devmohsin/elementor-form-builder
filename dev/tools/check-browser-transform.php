<?php
define( 'ABSPATH', __DIR__ . '/../../' );
define( 'CLEFA_PLUGIN_PATH', dirname( __DIR__, 2 ) . '/' );
define( 'CLEFA_DEV_PATH', dirname( __DIR__ ) . '/' );
define( 'CLEFA_ASSET_URL', 'http://example.com/assets/' );
define( 'CLEFA_PLUGIN_VERSION', '1.0.0' );

require CLEFA_PLUGIN_PATH . 'includes/Dev/Js_Test_Loader.php';

$files = CLEFA_Js_Test_Loader::get_unit_test_files();
$tmpdir = sys_get_temp_dir() . '/clefa-js-check';
if ( ! is_dir( $tmpdir ) ) {
	mkdir( $tmpdir );
}

foreach ( $files as $file ) {
	$path   = CLEFA_DEV_PATH . 'tests/js/' . $file;
	$out    = CLEFA_Js_Test_Loader::transform_for_browser( (string) file_get_contents( $path ) );
	$outpath = $tmpdir . '/' . $file;
	file_put_contents( $outpath, $out );
	$count = CLEFA_Js_Test_Loader::count_js_tests_in_file( $file );
	echo $file . ' tests=' . $count . ' bytes=' . strlen( $out ) . PHP_EOL;
}

echo 'TMP: ' . $tmpdir . PHP_EOL;
