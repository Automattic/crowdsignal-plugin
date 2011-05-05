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
	
	abbr{
		font-size:11px;
		color: #b8b8b8;
		line-height: 20px;
		vertical-align: text-top;
	}
	
	
	hr{
		border: 1px #EFEFEF solid;
		
	}
	
	#polldaddy-error.error a{
		color: #21759b;
	}
	
	h2#polldaddy-header, h2#poll-list-header{
		padding-left:38px;
		background:url('<?php echo $this->base_url; ?>img/pd-wp-icon-<?php echo $color; ?>-lrg.png') no-repeat 0px 13px;
		margin-bottom: 14px; 
	}
	
	h2 a.button{
		font-family: Lucida Grande, Arial, Sans;
	}
	
	#pd-wrap .postbox .inside{
		padding:13px;
	}
	
	#pd-wrap .postbox .inside h4{
		margin:0px;
		font-size:1.2em;
	}
	
	#pd-wrap table td label {
		font-size:15px;
	}
	
	#pd-wrap table td label.small{
		font-size:11px;
		line-height:14px;
		margin-top:4px;
		padding-left:18px;
		display: block;
	}
	
	.pd-box-header{
		padding-bottom: 10px;
		margin-bottom: 10px;
		border-bottom: 1px #ebebeb solid;
	}
	
	td#signup-button{
		border-left:1px #ebebeb solid;
		width:23%;
		vertical-align: top;
	}
	
	#signup-button h5{
		font-size:14px;
		font-weight: normal;
		margin:0px;
		margin-top:7px;
	}
	
	#signup-button input{
		display: block;
		margin-top:10px;
		width:90px;
	}
	
	td a.row-title{
		/*font-size: 14px;*/	
	}
	
	ul#answers li span.handle{
		background-color: #FFF;
	}
	
	.poll-votes.num.column-vote{
		font-size:24px !important;
		font-family: Georgia;
		font-weight: normal;
		text-align:right;
		color: #666;
		padding: 10px;
	}
	
	.votes-label{
		font-size:11px;
		color: #999;
		display: block;
		margin-top:2px;
	}
	
	.minor-publishing{
		background: #FFF;
		border-bottom: 1px #DDD solid;
	}
	
	ul#answer-options{
		list-style: none;
	}
	
	ul#answer-options li{
		padding: 0px 0px 5px 10px;
		border-bottom: 1px #EEE solid;
		margin: 0px 0px 5px 0px;
	}
	
	ul#answer-options li label{
		font-size:12px;
		color: #666;
		padding-left: 5px;
	}
	
	ul#answer-options li:first-of-type{
		margin-top:15px;
	}
	
	ul#answer-options li:last-of-type{
		border-bottom: none;
		margin-bottom: 15px;
	}
	
	ul.pd-tabs{
		list-style: none;
		overflow: hidden;
		margin-bottom: 0px;
	}
	
	ul.pd-tabs li{
		float: left;
		padding: 5px;
	}
	
	ul.pd-tabs li.selected{
		background: #f1f1f1;
		border: 1px #dfdfdf solid;
		border-radius: 3px 3px 0px 0px;
		border-bottom: 0px;
		margin-right: 5px;	
	}
	
	ul.pd-tabs li a{
		text-decoration: none;
	}
	
	ul.pd-tabs li.selected a{
		color: #000;
	}
	
	.pd-tab-panel{
		height: 300px;
		border: 1px #dfdfdf solid;
		margin-top:-7px;
		display: none;
	}
	
	.pd-tab-panel.show{
		display: block;
	}
	
	#answers input{
		outline: none;
	}
	
	#no-polls{
		font-size:14px;
		text-align: center;
	}
	
</style>