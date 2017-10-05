<?php
require('../../config.php');


/* Default globals */
global $CFG, $PAGE, $USER, $SITE, $COURSE;

?>

<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
	<title><?php echo $OUTPUT->page_title(); ?></title>
	<link rel="shortcut icon" href="<?php echo $OUTPUT->favicon() ?>"/>
	<?php echo $OUTPUT->standard_head_html() ?>
	<meta name="viewport" content="width=device-width, initial-scale=1.0, minimal-ui">
</head>

<body <?php echo $OUTPUT->body_attributes(); ?>>

	<div class="wrapper"> <!-- main page wrapper -->

		<?php
		echo $OUTPUT->standard_top_of_body_html();

// Include header navigation
		require_once(\theme_remui\controller\theme_controller::get_partial_element('header'));

// Include main sidebar.
		require_once(\theme_remui\controller\theme_controller::get_partial_element('pre-aside'));
		?>

		<!-- Content Wrapper. Contains page content -->
		<div class="content-wrapper">

			<!-- Content Header (Page header) -->
			<section class="content-header">
				<div class="heading"><?php echo $OUTPUT->page_heading(); ?></div>

				<div class="action-buttons">
					<?php echo $OUTPUT->page_heading_button(); ?>
					<?php echo $OUTPUT->course_header(); ?>
				</div>
			</section>

			<section class="content-breadcrumb">
				<ol class="breadcrumb">
					<?php echo $OUTPUT->navbar(); ?>
				</ol>
			</section>

			<!-- Main content -->
			<section class="content">
			</section>
		</div>


	</div>
</body>
</html>