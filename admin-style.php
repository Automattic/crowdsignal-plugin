<style type="text/css" media="screen" type="text/css">

	#toplevel_page_polls .wp-menu-image,
	#toplevel_page_ratings .wp-menu-image{
		background:url('<?php echo $this->base_url; ?>img/pd-wp-icon-<?php echo $color; ?>.png') 7px 7px no-repeat !important;

	}
	
	#toplevel_page_polls:hover .wp-menu-image,
	#toplevel_page_ratings:hover .wp-menu-image,
	#toplevel_page_polls.wp-has-current-submenu .wp-menu-image,
	#toplevel_page_ratings.wp-has-current-submenu .wp-menu-image {
		background:url('<?php echo $this->base_url; ?>img/pd-wp-icon-hover.png') 7px 7px no-repeat !important;
	}

	#toplevel_page_polls img, #toplevel_page_ratings img{
		display: none;
	}
	
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
		background:url('<?php echo $this->base_url; ?>img/pd-wp-icon-<?php echo $color; ?>-lrg.png') no-repeat 0px 13px;
		margin-bottom: 14px; 
	}
	
	<?php if( isset( $_GET['iframe']) ):?>
	h2#polldaddy-header, h2#poll-list-header{
		background-position: 0px 0px;
		margin-top: 20px;
	}
	
	.pd-tabs li a{
		font-size:11px !important;
	}
	
	<?php endif; ?>
	
	
	
</style>