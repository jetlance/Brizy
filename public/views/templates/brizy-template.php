<?php if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}
?>
<!DOCTYPE html>
<html>
<head>
	<?php wp_head(); ?>
</head>
<body class="brz">
<?php the_content() ?>
<?php wp_footer(); ?>
</body>
</html>
