function _$(id) {
    return document.getElementById(id)
}
function pd_style() {
    this.name = '';
    this.n_id = 0;
    this.n_desc = '';
    this.m_id = 0;
    this.m_desc = '';
    this.w_id = 0;
    this.w_desc = '';
    this.tag = ''
}
function preload_pd_style() {
    if (_$('preload_value').value != '0') {
        url = window.location.href;
        fnd = '';
        qs = window.location.search.substring(1);
        qv = qs.split("&");
        for (i = 0; i < qv.length; i++) {
            ft = qv[i].split("=");
            if (ft[0] == 'preload') {
                fnd = '&preload=' + ft[1];
                url = url.replace(fnd, '')
            }
        }
        window.location = url + "&preload=" + _$('preload_value').value
    }
}
function pd_bind(obj) {
    if (obj.id == 'font_bold') {
        if (obj.checked) {
            PDRTJS_1.font_bold = 'bold';
            PDRTJS_settings.font_bold = 'bold';
        }
        else {
            PDRTJS_1.font_bold = 'normal';
            PDRTJS_settings.font_bold = 'normal';
        }
    } else if (obj.id == 'font_italic') {
        if (obj.checked) {
            PDRTJS_1.font_italic = 'italic';
            PDRTJS_settings.font_italic = 'italic';
        }
        else {
            PDRTJS_1.font_italic = 'normal';
            PDRTJS_settings.font_italic = 'normal';
        }
    } else if (obj.id == 'size_sml' || obj.id == 'size_med' || obj.id == 'size_lrg') {
        PDRTJS_1.size = obj.value;
        PDRTJS_settings.size = obj.value;
    } else if (obj.id == 'nero_style') {
        PDRTJS_1.star_color = obj.value;
        PDRTJS_settings.star_color = obj.value;
    } else if (obj.id == 'font_color') {
        PDRTJS_1.font_color = obj.value;
        PDRTJS_settings.font_color = obj.value;
    } else if (obj.id == 'polldaddy-rating-popup') {
        if (obj.checked) {
            PDRTJS_1.popup = 'on';
            PDRTJS_settings.popup = 'on';
            _$('pd_popup_holder_1').style.width = '350px';
        } else {
            PDRTJS_1.popup = 'off';
            PDRTJS_settings.popup = 'off';
            _$('pd_popup_holder_1').style.width = '175px';
        }
    }
    else {
        eval('PDRTJS_1.' + obj.id + ' = "' + obj.value.replace('"', '&quot;').replace("'", '&#39;') + '";');
        eval('PDRTJS_settings.' + obj.id + ' = "' + obj.value.replace('"', '&quot;') + '";');
    }
    PDRTJS_1.build();
}
function pd_change_type(type) {
    var dd_label = _$('editor_star_color_text');
    var size_label = _$('editor_star_size_text');
    var obj_star = _$('star_color');
    var obj_nero = _$('nero_style');
    if (type == 1) {
        PDRTJS_1.type = 'nero';
        PDRTJS_1.build();
        dd_label.innerHTML = adminRatingsL10n.nero_type;
        size_label.innerHTML = adminRatingsL10n.nero_size;
        obj_star.style.display = 'none';
        obj_nero.style.display = 'block';
        pd_bind(obj_nero);
    } else {
        PDRTJS_1.type = 'stars';
        PDRTJS_1.build();
        dd_label.innerHTML = adminRatingsL10n.star_colors;
        size_label.innerHTML = adminRatingsL10n.star_size;
        obj_star.style.display = 'block';
        obj_nero.style.display = 'none';
        pd_bind(obj_star);
    }
}
function show_options(id) {
    if (_$('pd_show_' + id).checked == true) {
        _$('span_' + id).style.display = 'inline-block';
    } else {
        _$('span_' + id).style.display = 'none';
    }
}
function show_settings() {
    var div = _$('poststuff');
    if (div.style.display == 'block') {
        div.style.display = 'none';
    } else {
        div.style.display = 'block';
    }
}