<?php
/**
 * Error page template.
 *
 * @package UpdatePulse_Server
 *
 * @var string $title   Browser page title.
 * @var string $heading Error heading.
 * @var string $message Error message.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} ?>
<html>
	<head>
		<title><?php echo esc_html( $title ); ?></title>
	</head>
	<body>
		<h1><?php echo esc_html( $heading ); ?></h1>
		<p><?php echo esc_html( $message ); ?></p>
	</body>
</html>
