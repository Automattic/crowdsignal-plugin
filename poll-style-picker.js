function $(id) {
    return document.getElementById(id);
}

function pd_style() {
	this.name = '';				// style name
	this.n_id = 0;				// narrow style id
	this.n_desc = '';			// narrow style description
	this.m_id = 0;				// medium style id
	this.m_desc = '';			// medium style description
	this.w_id = 0;				// wide style id
	this.w_desc = '';			// wide style description
	this.tag = '';				// tag name for styles
}

var styles_array = new Array();

var s = new pd_style;
s.name = 'Aluminum';
s.n_id = 101;
s.m_id = 102;
s.w_id = 103;
s.tag = 'st-alum-light';
styles_array.push(s);

var s = new pd_style;
s.name = 'Plain White';
s.n_id = 104;
s.m_id = 105;
s.w_id = 106;
s.tag = 'st-plain-light';
styles_array.push(s);

var s = new pd_style;
s.name = 'Plain Black';
s.n_id = 107;
s.m_id = 108;
s.w_id = 109;
s.tag = 'st-plain-dark';
styles_array.push(s);

var s = new pd_style;
s.name = 'Paper';
s.n_id = 110;
s.m_id = 111;
s.w_id = 112;
s.tag = 'st-paper';
styles_array.push(s);

var s = new pd_style;
s.name = 'Skull Dark';
s.n_id = 113;
s.m_id = 114;
s.w_id = 115;
s.tag = 'st-skull-dark';
styles_array.push(s);

var s = new pd_style;
s.name = 'Skull Light';
s.n_id = 116;
s.m_id = 117;
s.w_id = 118;
s.tag = 'st-skull-light';
styles_array.push(s);

var s = new pd_style;
s.name = 'Micro';
s.m_id = 157;
s.m_desc = 'Width 150px. The micro style is useful when space is tight.';
s.tag = 'st-micro';
styles_array.push(s);

var s = new pd_style;
s.name = 'Plastic White';
s.n_id = 119;
s.m_id = 120;
s.w_id = 121;
s.tag = 'st-plastic-white';
styles_array.push(s);

var s = new pd_style;
s.name = 'Plastic Grey';
s.n_id = 122;
s.m_id = 123;
s.w_id = 124;
s.tag = 'st-plastic-grey';
styles_array.push(s);

var s = new pd_style;
s.name = 'Plastic Black';
s.n_id = 125;
s.m_id = 126;
s.w_id = 127;
s.tag = 'st-plastic-black';
styles_array.push(s);

var s = new pd_style;
s.name = 'Manga';
s.n_id = 128;
s.m_id = 129;
s.w_id = 130;
s.tag = 'st-manga';
styles_array.push(s);

var s = new pd_style;
s.name = 'Tech Dark';
s.n_id = 131;
s.m_id = 132;
s.w_id = 133;
s.tag = 'st-tech-dark';
styles_array.push(s);

var s = new pd_style;
s.name = 'Tech Grey';
s.n_id = 134;
s.m_id = 135;
s.w_id = 136;
s.tag = 'st-tech-grey';
styles_array.push(s);

var s = new pd_style;
s.name = 'Tech Light';
s.n_id = 137;
s.m_id = 138;
s.w_id = 139;
s.tag = 'st-tech-light';
styles_array.push(s);

var s = new pd_style;
s.name = 'Working Male';
s.n_id = 140;
s.m_id = 141;
s.w_id = 142;
s.tag = 'st-working-male';
styles_array.push(s);

var s = new pd_style;
s.name = 'Working Female';
s.n_id = 143;
s.m_id = 144;
s.w_id = 145;
s.tag = 'st-working-female';
styles_array.push(s);

var s = new pd_style;
s.name = 'Thinking Male';
s.n_id = 146;
s.m_id = 147;
s.w_id = 148;
s.tag = 'st-thinking-male';
styles_array.push(s);

var s = new pd_style;
s.name = 'Thinking Female';
s.n_id = 149;
s.m_id = 150;
s.w_id = 151;
s.tag = 'st-thinking-female';
styles_array.push(s);

var s = new pd_style;
s.name = 'Sunset';
s.n_id = 152;
s.m_id = 153;
s.w_id = 154;
s.tag = 'st-sunset';
styles_array.push(s);

var s = new pd_style;
s.name = 'Music';
s.m_id = 155;
s.w_id = 156;
s.tag = 'st-music';
styles_array.push(s);


var style_id = 0;


function pd_build_styles( current_pos )
{
	var style = styles_array[ current_pos ];
	$('st_name').innerHTML = style.name;
	
	var style_sizes = '';

	if ( style_id == 0 )
	{
		if ( style.m_id > 0 )
		{
			style_id = style.m_id;
		}
		else if ( style.w_id > 0 )
		{
			style_id = style.w_id;
		}
		else if ( style.n_id > 0 )
		{
			style_id = style.w_id;
		}
	}
	
	if ( style.w_id > 0 )
	{
		if ( style_id == style.w_id )
		{
			style_sizes += 'Wide | ';
			
			if ( style.w_desc == '' )
			{
				$('st_description').innerHTML = 'Width: 630px, the wide style is good for blog posts.';
			}
			else
			{
				$('st_description').innerHTML = style.w_desc;
			}
		}
		else
		{
			style_sizes += '<a href="javascript:pd_change_style(' + style.w_id + ');">Wide</a> | ';
		}
	}
	if ( style.m_id > 0 )
	{
		if ( style_id == style.m_id )
		{
			style_sizes += 'Medium';

			if ( style.n_id > 0 ){
				style_sizes += ' | ';
			}

			if ( style.m_desc == '' )
			{
				$('st_description').innerHTML = 'Width: 300px, the medium style is good for general use.';
			}
			else
			{
				$('st_description').innerHTML = style.m_desc;
			}

		}
		else
		{
			style_sizes += '<a href="javascript:pd_change_style(' + style.m_id + ');">Medium</a>';
			if ( style.n_id > 0 ){
				style_sizes += ' | ';
			}
		}
	}
	if ( style.n_id > 0 )
	{
		if ( style_id == style.n_id )
		{
			style_sizes += 'Narrow ';

			if ( style.n_desc == '' )
			{
				$('st_description').innerHTML = 'Width: 150px, the narrow style is good for sidebars etc.';
			}
			else
			{
				$('st_description').innerHTML = style.n_desc;
			}

		}
		else
		{
			style_sizes += '<a href="javascript:pd_change_style(' + style.n_id + ');">Narrow</a> ';
		}
	}	
	
	$('st_sizes').innerHTML = style_sizes;
	$('st_number').innerHTML = (current_pos + 1) +' of '+ styles_array.length;
	$('st_image').style.background = 'url(http://i.polldaddy.com/polls/' + style.tag + '.png) no-repeat top left';

    $('regular').checked = true;
	$('styleID').value = style_id;
}

function pd_pick_style( id )
{
	found = false;
	for ( x=0; x<=styles_array.length - 1; x++ )
	{
		if ( styles_array[x].n_id == id || styles_array[x].m_id == id || styles_array[x].w_id == id )
		{
			current_pos = x;
			pd_change_style( id );
			found = true;
			break;
		}
	}
	
	if (!found)
	{
		current_pos = 0;
		pd_build_styles( current_pos );
	}
}

function pd_change_style( id ){
	style_id = id;
	
	if ( style_id < 1000 )	// Regular
    {
        $('regular').checked = true;
		pd_build_styles( current_pos );
    }
    else	// custom
    {
        $('custom').checked = true;
    }

    $('styleID').value = style_id;
}

function pd_move( dir )
{	
	if ( dir == 'next' )
	{
		if ( styles_array.length <= ( current_pos + 1 ) )
		{
			current_pos = 0;
		}
		else
		{
			current_pos = current_pos + 1;
		}
	}
	else if ( dir == 'prev' )
	{
		if ( current_pos == 0 )
		{
			current_pos = styles_array.length - 1;
		}
		else
		{
			current_pos = current_pos - 1;
		}
	}
	style_id = 0;
	pd_build_styles( current_pos );
}

function st_results( obj, cmd )
{
	if ( cmd == 'show' )
	{
		obj.style.backgroundPosition = 'top right';
	}
	else if ( cmd == 'hide' )
	{
		obj.style.backgroundPosition = 'top left';
	}
}