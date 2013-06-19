<?php

if( $color == 'blue' ){
	$yoffset = 7;
} else {
	$yoffset = -35;
}

 ?>

<style type="text/css" media="screen">

#toplevel_page_polls img, #toplevel_page_ratings img, #toplevel_page_feedback img{
	display: none;
}
	
<?php if ( $this->has_feedback_menu ) : ?>
.toplevel_page_feedback div.wp-menu-image {
	background: url('<?php echo $this->base_url; ?>img/grunion-menu.png') no-repeat 7px 7px !important;
	background-size: 15px 16px !important;
}

.toplevel_page_feedback:hover div.wp-menu-image,
.toplevel_page_feedback.wp-has-current-submenu div.wp-menu-image,
.toplevel_page_feedback.current div.wp-menu-image {
	background: url('<?php echo $this->base_url; ?>img/grunion-menu-hover.png') no-repeat 7px 7px !important;
	background-size: 15px 16px !important;
}	
<?php else: ?>
.toplevel_page_feedback div.wp-menu-image{
	background:url('<?php echo $this->base_url; ?>img/pd-wp-icons.png') 7px <?php echo $yoffset; ?>px no-repeat !important;
}

.toplevel_page_feedback:hover div.wp-menu-image,
.toplevel_page_feedback.wp-has-current-submenu div.wp-menu-image,
.toplevel_page_feedback.current div.wp-menu-image {
	background:url('<?php echo $this->base_url; ?>img/pd-wp-icons.png') 7px -77px no-repeat !important;
}

<?php endif;?>

#polldaddy-error.error{
	border-radius:6px;
	margin-left:5px;
	margin-right:2%;
	background-color:#FFC;
	background:url('<?php echo $this->base_url; ?>img/error-<?php echo $color; ?>.png') no-repeat 3px 3px, -moz-linear-gradient(top, #FFF, #FFC);
	background:url('<?php echo $this->base_url; ?>img/error-<?php echo $color; ?>.png') no-repeat 3px 3px, -webkit-linear-gradient(top, #FFF, #FFC);
	margin-top:14px;
	border:1px #cccccc solid;
	padding:3px 5px 3px 40px;
}


h2#polldaddy-header, h2#poll-list-header{
	padding-left:38px;
	background:url('<?php echo $this->base_url; ?>img/pd-wp-icon-<?php echo $color; ?>-lrg.png') no-repeat 0 9px;
	margin-bottom: 14px; 
}

@media only screen and (-moz-min-device-pixel-ratio: 1.5), only screen and (-o-min-device-pixel-ratio: 3/2), only screen and (-webkit-min-device-pixel-ratio: 1.5), only screen and (min-device-pixel-ratio: 1.5) {

<?php if ( $this->has_feedback_menu ) : ?>
	#adminmenu .menu-icon-feedback:hover div.wp-menu-image,
	#adminmenu .menu-icon-feedback.wp-has-current-submenu div.wp-menu-image,
	#adminmenu .menu-icon-feedback.current div.wp-menu-image {
		background: url('<?php echo $this->base_url; ?>img/grunion-menu-hover-2x.png') no-repeat 7px 7px !important;
		background-size: 15px 16px !important;
	}

	#adminmenu .menu-icon-feedback div.wp-menu-image {
		background: url('<?php echo $this->base_url; ?>img/grunion-menu-2x.png') no-repeat 7px 7px !important;
		background-size: 15px 16px !important;
	}
	
<?php endif; ?>
	
	h2#polldaddy-header, h2#poll-list-header{
		background:url('<?php echo $this->base_url; ?>img/pd-wp-icon-<?php echo $color; ?>-lrg@2x.png') no-repeat 0 9px;
		background-size: 31px 31px;
	}
}	

<?php if( isset( $_GET['iframe']) ):?>
h2#polldaddy-header, h2#poll-list-header{
	background-position: 0 0;
	margin-top: 20px;
}

.pd-tabs li a{
	font-size:11px !important;
}
<?php endif; ?>	
	
</style>