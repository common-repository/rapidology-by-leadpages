<?php
function rad_marketing_page(){
	$imageurl = RAD_PLUGIN_IMAGE_DIR;
	$html = <<<BOH
	<div class="marketing_header">
		<h1>NEED MORE HELP BUILDING OUT YOUR EMAIL LIST?</h1>
		<p>We have the resources to get the most out of your marketing efforts.</p>
	</div>
	<div class="marketing_wrapper">
	<div class="rapidology_marketing_main">
			<div id="video_course">
				<div class="box_header">
					<p>Download Our Free 9-Video Course</p>
				</div>
				<div class="rapidology_marketing_content">
					<img src="$imageurl/email-list-building-course.png" name="9_video_course" />
					<div>
						<p>The Ultimate Business Owner’s Guide to List-Building</p>
						<a href = "https://lp.leadpages.net/email-list-building-rapidology/" target="_blank">Download</a>
					</div>
				</div>
			</div>

			<div id="live_training">
				<div class="box_header">
					<p>Check Out Our Free Live Training</p>
				</div>
				<div class="rapidology_marketing_content">
					<img src="$imageurl/free-live-training.png" name="live-training" />
					<div>
						<p>How to Grow Your List Without Spending All Your Time On Marketing</p>
						<a href = "https://lp.leadpages.net/webinar-rapidology/" target="_blank">Download</a>
					</div>
				</div>
			</div>

			<div id="free_images">
				<div class="box_header">
					<p>Download 10 Free Images Designed to Convert</p>
				</div>
				<div class="rapidology_marketing_content">
					<img src="$imageurl/10-free-images.png" name="free_images" />
					<div>
						<p>Grow Your List Faster By Testing These 10 Images On Your Popups</p>
						<a href = "https://lp.leadpages.net/10-images-rapidology/" target="_blank">Download</a>
					</div>
				</div>
			</div>

			<div id="facebook_course">
				<div class="box_header">
					<p>See Our Free 11-Video Facebook Course</p>
				</div>
				<div class="rapidology_marketing_content">
					<img src="$imageurl/facebook-ad-course.png" name="facebook_course" />
					<div>
						<p>Use Facebook to Grow Your List with the Facebook Advertising System</p>
						<a href = "https://lp.leadpages.net/facebook-advertising-rapidology/" target="_blank">Download</a>
					</div>
				</div>
			</div>
	</div>
BOH;

	return $html;
}

include_once('marketing_sidebar.php');

$main = rad_marketing_page();
$sidebar = rapidology_marketing_sidebar();
echo $main;
echo $sidebar;