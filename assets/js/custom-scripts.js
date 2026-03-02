jQuery(document).ready(function($){
    
    // --- PART 3: JS INJECTION (LOGIN FORM) ---
    setTimeout(function(){
        var form = $('#td-register-div');
        var originalBtn = form.find('#register_button');
        if(originalBtn.length > 0 && $('#custom_reg_btn').length === 0) {
            var passHtml = '<div class="td-login-inputs"><input class="td-login-input" type="password" name="custom_pass" id="custom_pass" placeholder="Password" required style="width:100%; margin-bottom:15px;"></div>';
            var newBtn = $('<button type="button" id="custom_reg_btn" class="wpb_button btn td-login-button">REGISTER</button>');
            var msgBox = $('<div id="custom_reg_msg" style="margin-bottom:10px; font-weight:bold;"></div>');
            originalBtn.hide(); originalBtn.attr('id', 'old_register_button_disabled'); 
            originalBtn.before(passHtml); originalBtn.before(msgBox); originalBtn.after(newBtn);
            
            newBtn.on('click', function(e){
                e.preventDefault(); 
                var u = form.find('input[name="register_user"]').val(); 
                var e_mail = form.find('input[name="register_email"]').val(); 
                var p = $('#custom_pass').val(); 
                var btn = $(this);
                if(!u || !e_mail || !p) { $('#custom_reg_msg').css('color', 'red').text('Please fill all fields.'); return; }
                btn.text('Processing...'); btn.prop('disabled', true);
                
                $.ajax({ 
                    type: 'POST', 
                    url: ct_ajax_obj.ajax_url, 
                    data: { 
                        action: 'custom_theme_register', 
                        user_login: u, 
                        user_email: e_mail, 
                        user_pass: p, 
                        security: ct_ajax_obj.nonce 
                    },
                    success: function(response) { 
                        if(response.success) { 
                            $('#custom_reg_msg').css('color', 'green').text(response.data); 
                            setTimeout(function(){ location.reload(); }, 1000); 
                        } else { 
                            $('#custom_reg_msg').css('color', 'red').text(response.data); 
                            btn.text('REGISTER'); btn.prop('disabled', false); 
                        } 
                    },
                    error: function() { $('#custom_reg_msg').css('color', 'red').text('Server error.'); btn.text('REGISTER'); btn.prop('disabled', false); }
                });
            });
        }
    }, 1500); 

    // --- PART 5: PROFILE LINKS UPDATE ---
    var l = window.location.origin + '/profile/';
    function c_links(){
        var a = $('.td-login-info a, .tdb-head-usr-name a, .td-author-name a');
        if(a.length) a.attr('href', l);
    }
    c_links();
    setInterval(c_links, 2000);
    $('.td-login-info').on('mouseenter', c_links);

    // --- PART 7: NOTIFICATION UI ---
    var bell = '<div class="ct-notif-wrapper" id="ctNotifBell"><span class="ct-bell-icon">🔔</span><span class="ct-notif-badge" id="ctNotifBadge">0</span></div>';
    var drop = '<div class="ct-notif-dropdown" id="ctNotifDropdown"><div class="ct-notif-header">Notifications</div><ul class="ct-notif-list" id="ctNotifList"><li>Loading...</li></ul></div>';
    
    function addBell(){ 
        if($('#ctNotifBell').length > 0) return; 
        $('body').append(drop); 
        if($('.tdb-head-usr-name').length){ $('.tdb-head-usr-name').before(bell); }
        else if($('.td-login-info').length){ $('.td-login-info').prepend(bell); }
        else if($('.td-guest-user').length){ $('.td-guest-user').prepend(bell); }
        else if($('.td-header-sp-top-menu').length){ $('.td-header-sp-top-menu').first().append(bell); } 
    }
    
    addBell(); 
    setInterval(function(){ if($('#ctNotifBell').length == 0) addBell(); }, 2000);
    
    function checkN(){ 
        $.post(ct_ajax_obj.ajax_url, {action:'custom_get_notifs'}, function(r){ 
            try {
                var d = JSON.parse(r); 
                $('#ctNotifList').html(d.html); 
                if(d.count > 0) $('#ctNotifBadge').text(d.count).fadeIn();
                else $('#ctNotifBadge').fadeOut();
            } catch(e){} 
        }); 
    }
    
    checkN(); 
    setInterval(checkN, 5000);
    
    $(document).on('click','#ctNotifBell',function(e){ 
        e.stopPropagation(); 
        var b = $(this); 
        var d = $('#ctNotifDropdown'); 
        var o = b.offset(); 
        d.css({'top':(o.top+50)+'px','left':(o.left-290)+'px'}); 
        d.fadeToggle(100); 
        if($('#ctNotifBadge').is(':visible')){ 
            $.post(ct_ajax_obj.ajax_url, {action:'custom_mark_read'}, function(){ $('#ctNotifBadge').fadeOut(); }); 
        } 
    });
    
    $(document).click(function(){ $('#ctNotifDropdown').fadeOut(100); });
});