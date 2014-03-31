<?php

/**
 * Used for debugging, will display a array in a preformated way, can add optional header
 *
 * @param array $array
 * @param string $header
 */
function displayArray( $array, $header = null, $returnString = false )
{
	$s = '<div style="position:relative;z-index:2;background-color:white;color:black">';
	if( isset( $header ) ) {
		$s .= "<h2>$header</h2>";
	}
	$s .= "<pre>";
	$s .= print_r( $array, true );
	$s .= "</pre>";
	$s .= '</div>' . PHP_EOL;
	if( $returnString ) {
		return $s;
	}
	else {
		echo $s;
	}
}

function el( $value, $header = '' )
{
	$path = dirname( __FILE__ );
	$logfile = $path . '/php_error_log';
	if (IS_PROD_EN_SITE || !is_writeable($logfile)) return;
	$msg = "\n" . '#### ' . $header . ' ####' . "\n" . print_r( $value, true ) . "\n";
	error_log( $msg, 3, $logfile );
}

function errorLog( $value, $line, $file )
{
	$file = end( explode( '/', $file ) );
	mail( 'msteudel@gmail.com', $file . ' - ' . $line, $value );
}

