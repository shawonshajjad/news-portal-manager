<?php
/**
 * Plugin Name: NewsPortal Manager 
 * Description: Complete system for AJAX registration, Reporter workflows, and Live Analytics.
 * Version: 1.0
 * Author:  Shawon
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Enqueue Assets
add_action('wp_enqueue_scripts', 'ct_dashboard_enqueue_assets');
function ct_dashboard_enqueue_assets() {
    wp_enqueue_style('ct-dashboard-styles', plugin_dir_url(__FILE__) . 'assets/css/custom-styles.css');
    wp_enqueue_script('ct-dashboard-scripts', plugin_dir_url(__FILE__) . 'assets/js/custom-scripts.js', array('jquery'), null, true);
    
    // Passing AJAX URL to JS
    wp_localize_script('ct-dashboard-scripts', 'ct_ajax_obj', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('custom_reg_nonce')
    ));
}

/**
 * ==============================================================================
 * PART 1: SETUP & DISABLE DEFAULTS
 * ==============================================================================
 */
remove_action('register_new_user', 'wp_send_new_user_notifications');
remove_action('edit_user_created_user', 'wp_send_new_user_notifications');
add_filter( 'wp_new_user_notification_email', '__return_false' );
add_filter( 'send_password_change_email', '__return_false' );
add_filter( 'wp_password_change_notification_email', '__return_false' );

add_action('after_setup_theme', 'custom_remove_admin_bar');
function custom_remove_admin_bar() {
    if (!current_user_can('administrator') && !is_admin()) { show_admin_bar(false); }
}

/**
 * ==============================================================================
 * PART 2: REGISTRATION & LOGIN
 * ==============================================================================
 */
add_action('wp_ajax_nopriv_custom_theme_register', 'custom_theme_handle_ajax_register');
add_action('wp_ajax_custom_theme_register', 'custom_theme_handle_ajax_register');
function custom_theme_handle_ajax_register() {
    check_ajax_referer('custom_reg_nonce', 'security');
    $username = sanitize_user($_POST['user_login']);
    $email    = sanitize_email($_POST['user_email']);
    $password = $_POST['user_pass']; 
    if ( empty($username) || empty($email) || empty($password) ) { wp_send_json_error('All fields are required.'); }
    if ( username_exists($username) ) { wp_send_json_error('Username already taken.'); }
    if ( email_exists($email) ) { wp_send_json_error('Email already registered.'); }
    $user_id = wp_insert_user(array('user_login' => $username, 'user_email' => $email, 'user_pass'  => $password, 'role' => 'subscriber'));
    if ( is_wp_error($user_id) ) { wp_send_json_error($user_id->get_error_message()); } 
    else { wp_set_current_user($user_id); wp_set_auth_cookie($user_id); wp_send_json_success('Registration successful! Redirecting...'); }
    die();
}

/**
 * ==============================================================================
 * PART 4: DASHBOARD (FIXED LINKS & NOTIFICATION BOX)
 * ==============================================================================
 */
add_shortcode('my_custom_dashboard', 'custom_theme_dashboard_content');
function custom_theme_dashboard_content() {
    if (!is_user_logged_in()) { return '<div class="td-login-alert">Please login to view your dashboard.</div>'; }
    $user_id = get_current_user_id();
    $user = get_userdata($user_id);
    $user_roles = $user->roles;
    $is_admin = in_array('administrator', $user_roles);
    $is_reporter = in_array('reporter', $user_roles);

    // --- ANALYTICS ---
    $daily_stats = get_option('ct_daily_visits', array());
    $vis_week = 0; $vis_month = 0;
    foreach($daily_stats as $date => $count) {
        $ts = strtotime($date);
        if($ts >= strtotime('-7 days')) $vis_week += $count;
        if($ts >= strtotime('-30 days')) $vis_month += $count;
    }
    $my_total_views = 0; $my_highest_view = 0; $my_best_post_title = 'N/A';
    $stats_query = new WP_Query(array('author' => $user_id, 'post_type' => 'post', 'posts_per_page' => -1, 'post_status' => array('publish', 'pending')));
    if($stats_query->have_posts()) {
        foreach($stats_query->posts as $p) {
            $v = (int)get_post_meta($p->ID, 'ct_post_views', true);
            $my_total_views += $v;
            if($v > $my_highest_view) { $my_highest_view = $v; $my_best_post_title = $p->post_title; }
        }
    }

    // --- NOTIFICATION HISTORY (MIRRORING AJAX LOGIC) ---
    $dash_notifs = array();

    // 1. ADMIN
    if($is_admin) {
        $posts = get_posts(array('post_status'=>'pending', 'numberposts'=>8, 'orderby'=>'date', 'order'=>'DESC'));
        foreach($posts as $p) $dash_notifs[] = array('time'=>$p->post_date, 'icon'=>'📝', 'msg'=>'New Pending Post: <b>'.wp_trim_words($p->post_title,4).'</b>', 'url'=>site_url('/profile/'));
        
        $comments = get_comments(array('number'=>8, 'orderby'=>'comment_date', 'order'=>'DESC'));
        foreach($comments as $c){ 
            if($c->user_id != $user_id) $dash_notifs[] = array('time'=>$c->comment_date, 'icon'=>'💬', 'msg'=>'<b>'.$c->comment_author.'</b> commented: "'.wp_trim_words($c->comment_content,5).'"', 'url'=>get_comment_link($c->comment_ID));
        }
    }
    // 2. REPORTER
    if($is_reporter) {
        $posts = get_posts(array('author'=>$user_id, 'post_status'=>'publish', 'numberposts'=>8, 'orderby'=>'date', 'order'=>'DESC'));
        foreach($posts as $p) $dash_notifs[] = array('time'=>$p->post_date, 'icon'=>'✅', 'msg'=>'Your post <b>'.wp_trim_words($p->post_title,4).'</b> is Live!', 'url'=>get_permalink($p->ID));
        
        $my_c_ids = wp_list_pluck(get_comments(array('user_id'=>$user_id)), 'comment_ID');
        if($my_c_ids) {
            $replies = get_comments(array('parent__in'=>$my_c_ids, 'status'=>'approve', 'number'=>8));
            foreach($replies as $r) { if($r->user_id!=$user_id) $dash_notifs[] = array('time'=>$r->comment_date, 'icon'=>'↩️', 'msg'=>'<b>'.$r->comment_author.'</b> replied to you.', 'url'=>get_comment_link($r->comment_ID)); }
        }
    }
    // 3. SUBSCRIBER
    if(in_array('subscriber', (array)$user->roles)) {
        $app_cmts = get_comments(array('user_id'=>$user_id, 'status'=>'approve', 'number'=>8, 'meta_key'=>'_custom_approval_time', 'orderby'=>'comment_ID', 'order'=>'DESC'));
        foreach($app_cmts as $ac) {
            $t = get_comment_meta($ac->comment_ID, '_custom_approval_time', true);
            if($t) $dash_notifs[] = array('time'=>$t, 'icon'=>'👍', 'msg'=>'Your comment was Approved!', 'url'=>get_comment_link($ac->comment_ID));
        }
        $new_posts = get_posts(array('post_status'=>'publish', 'numberposts'=>8, 'orderby'=>'date', 'order'=>'DESC'));
        foreach($new_posts as $np) {
            $dash_notifs[] = array('time'=>$np->post_date, 'icon'=>'📰', 'msg'=>'New Post: <b>'.wp_trim_words($np->post_title,4).'</b>', 'url'=>get_permalink($np->ID));
        }
    }

    usort($dash_notifs, function($a, $b) { return strtotime($b['time']) - strtotime($a['time']); });
    $dash_notifs = array_slice($dash_notifs, 0, 10);

    $msg_html = '';
    if (isset($_GET['post_sub']) && $_GET['post_sub'] == 'success') { $msg_html = '<div class="ct-alert-success">✅ আপনার নিউজ সফলভাবে জমা হয়েছে!</div>'; }

    ob_start();
    ?>
    <div class="ct-dashboard-container">
        <div class="ct-dash-inner">
            <?php echo $msg_html; ?>
            <div class="ct-head">
                <?php echo get_avatar($user_id, 80); ?>
                <div>
                    <h3><?php echo $user->display_name; ?> 
                        <?php if($is_admin) echo '<span class="badge badge-admin">ADMIN</span>'; elseif($is_reporter) echo '<span class="badge badge-reporter">REPORTER</span>'; ?>
                    </h3>
                    <span style="color:#888; font-size:14px;"><?php echo $user->user_email; ?></span>
                </div>
                <a href="<?php echo wp_logout_url(home_url()); ?>" class="ct-logout">Log Out</a>
            </div>

            <?php if ($is_reporter || $is_admin) : ?>
                <div class="ct-stats-grid">
                    <div class="ct-stat-card st-green"><span class="ct-stat-num"><?php echo number_format($vis_week); ?></span><span class="ct-stat-label">Site Visitors (Week)</span></div>
                    <div class="ct-stat-card st-purple"><span class="ct-stat-num"><?php echo number_format($vis_month); ?></span><span class="ct-stat-label">Site Visitors (Month)</span></div>
                    <div class="ct-stat-card st-blue"><span class="ct-stat-num"><?php echo number_format($my_total_views); ?></span><span class="ct-stat-label">My Total Views</span></div>
                    <div class="ct-stat-card st-orange"><span class="ct-stat-num"><?php echo number_format($my_highest_view); ?></span><span class="ct-stat-label" title="<?php echo esc_attr($my_best_post_title); ?>">Highest Post View</span></div>
                </div>

                <?php if ($is_admin) : ?>
                    <div class="ct-queue-box">
                        <div class="ct-queue-head queue-news"><span>⚠️ Pending News Approval</span></div>
                        <table class="ct-table">
                            <thead><tr><th>Title</th><th>Reporter</th><th>Date</th><th style="min-width:240px;">Action</th></tr></thead>
                            <tbody>
                            <?php $p_posts = new WP_Query(array('post_status'=>'pending','post_type'=>'post','posts_per_page'=>-1)); 
                            if($p_posts->have_posts()): while($p_posts->have_posts()): $p_posts->the_post(); $pid=get_the_ID(); ?>
                                <tr id="post-row-<?php echo $pid; ?>">
                                    <td>
                                        <a href="<?php the_permalink(); ?>" target="_blank" style="color:#333;text-decoration:none;font-weight:600;"><?php the_title(); ?></a>
                                    </td>
                                    <td><?php the_author(); ?></td><td><?php echo get_the_date('d M'); ?></td>
                                    <td>
                                        <a href="<?php echo get_preview_post_link($pid); ?>" target="_blank" class="ct-action-btn btn-view">View</a>
                                        <a href="<?php echo admin_url('post.php?post='.$pid.'&action=edit'); ?>" target="_blank" class="ct-action-btn btn-edit">Edit</a>
                                        <button class="ct-action-btn btn-yes" onclick="ctApprovePost(<?php echo $pid; ?>)">Publish</button>
                                        <button class="ct-action-btn btn-no" onclick="ctTrashPost(<?php echo $pid; ?>)">Delete</button>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?><tr><td colspan="4" style="text-align:center;padding:15px;color:#777;">No pending posts.</td></tr><?php endif; wp_reset_postdata(); ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="ct-queue-box">
                        <div class="ct-queue-head queue-comments"><span>💬 Pending Comments</span></div>
                        <table class="ct-table">
                            <thead><tr><th>Author</th><th>Comment</th><th>Post</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php $p_comments = get_comments(array('status'=>'hold')); if($p_comments): foreach($p_comments as $pc): ?>
                                <tr id="comment-row-<?php echo $pc->comment_ID; ?>">
                                    <td><strong><?php echo $pc->comment_author; ?></strong></td><td><?php echo wp_trim_words($pc->comment_content,15); ?></td>
                                    <td><a href="<?php echo get_permalink($pc->comment_post_ID); ?>" target="_blank" style="text-decoration:none; color:#3498db;"><?php echo get_the_title($pc->comment_post_ID); ?></a></td>
                                    <td><button class="ct-action-btn btn-yes" onclick="ctApproveComment(<?php echo $pc->comment_ID; ?>)">Approve</button><button class="ct-action-btn btn-no" onclick="ctDeleteComment(<?php echo $pc->comment_ID; ?>)">Delete</button></td>
                                </tr>
                            <?php endforeach; else: ?><tr><td colspan="4" style="text-align:center;padding:15px;color:#777;">No pending comments.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <script>
                        function ctApprovePost(id) { if(confirm('Publish?')) jQuery.post('<?php echo admin_url("admin-ajax.php"); ?>', { action: 'ct_frontend_publish_post', post_id: id }, function(res){ if(res.success) jQuery('#post-row-'+id).fadeOut(); }); }
                        function ctTrashPost(id) { if(confirm('Trash?')) jQuery.post('<?php echo admin_url("admin-ajax.php"); ?>', { action: 'ct_frontend_trash_post', post_id: id }, function(res){ if(res.success) jQuery('#post-row-'+id).fadeOut(); }); }
                        function ctApproveComment(id) { if(confirm('Approve?')) jQuery.post('<?php echo admin_url("admin-ajax.php"); ?>', { action: 'ct_approve_comment', comment_id: id }, function(res){ if(res.success) jQuery('#comment-row-'+id).fadeOut(); }); }
                        function ctDeleteComment(id) { if(confirm('Delete?')) jQuery.post('<?php echo admin_url("admin-ajax.php"); ?>', { action: 'ct_delete_comment', comment_id: id }, function(res){ if(res.success) jQuery('#comment-row-'+id).fadeOut(); }); }
                    </script>
                <?php endif; ?>

                <div class="ct-form-section">
                    <div class="ct-section-title">📝 নতুন খবর পোস্ট করুন</div>
                    <form method="post" action="" enctype="multipart/form-data">
                        <?php wp_nonce_field('ct_reporter_post_nonce', 'ct_security'); ?>
                        <div class="ct-form-grid"><div class="ct-form-group"><label>Title *</label><input type="text" name="news_title" class="ct-form-input" required></div><div class="ct-form-group"><label>Category *</label><?php wp_dropdown_categories(array('show_option_none'=>'Select','class'=>'ct-form-select','name'=>'news_cat','hide_empty'=>0,'required'=>true)); ?></div></div>
                        <div class="ct-form-grid"><div class="ct-form-group"><label>Tags</label><input type="text" name="news_tags" class="ct-form-input" placeholder="Tag1, Tag2"></div><div class="ct-form-group"><label>Featured Image</label><input type="file" name="news_image" accept="image/*" class="ct-form-input" style="padding:9px;"></div></div>
                        <div class="ct-form-group ct-form-full"><label>Details *</label><?php wp_editor('', 'news_content', array('media_buttons'=>true,'textarea_rows'=>15,'teeny'=>false)); ?></div>
                        <button type="submit" name="ct_submit_news" class="ct-submit-btn">Submit Post</button>
                    </form>
                </div>

                <div class="ct-lists-container">
                    <div>
                        <div class="ct-section-title">📂 পোস্ট হিস্ট্রি (Post History)</div>
                        <table class="ct-table" style="border:1px solid #eee;">
                            <thead><tr><th>Title</th><th>Date</th><th>Views</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php $hist = new WP_Query(array('author'=>$user_id,'post_type'=>'post','post_status'=>array('publish','pending','draft'),'posts_per_page'=>10)); 
                            if($hist->have_posts()): while($hist->have_posts()): $hist->the_post(); $v=(int)get_post_meta(get_the_ID(),'ct_post_views',true); ?>
                                <tr>
                                    <td>
                                        <a href="<?php the_permalink(); ?>" target="_blank" style="color:#333;text-decoration:none;font-weight:600;"><?php the_title(); ?></a>
                                    </td>
                                    <td><?php echo get_the_date('d M'); ?></td><td><?php echo $v; ?></td><td><?php echo ucfirst(get_post_status()); ?></td>
                                </tr>
                            <?php endwhile; else: ?><tr><td colspan="4">No posts.</td></tr><?php endif; wp_reset_postdata(); ?>
                            </tbody>
                        </table>
                    </div>
                    <div>
                        <div class="ct-section-title" style="color:#d35400;">🔔 Notification History</div>
                        <div style="background:#fff; border:1px solid #eee; border-radius:8px; overflow-y:auto; max-height:400px;">
                            <?php if(!empty($dash_notifs)): ?>
                                <ul style="list-style:none; padding:0; margin:0;">
                                <?php foreach($dash_notifs as $n): ?>
                                    <li style="padding:12px; border-bottom:1px solid #f9f9f9; display:flex; gap:10px; align-items:flex-start;">
                                        <span style="font-size:18px;"><?php echo $n['icon']; ?></span>
                                        <div style="font-size:13px; line-height:1.4;">
                                            <a href="<?php echo $n['url']; ?>" style="color:#333; text-decoration:none; display:block;">
                                                <?php echo $n['msg']; ?>
                                            </a>
                                            <div style="font-size:11px; color:#999; margin-top:3px;"><?php echo human_time_diff(strtotime($n['time']), current_time('timestamp')); ?> ago</div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                                </ul>
                            <?php else: ?><p style="padding:20px; text-align:center; color:#777;">No new notifications.</p><?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else : ?>
                <div class="ct-lists-container">
                    <div>
                        <div class="ct-section-title" style="color:#3498db;">💬 আপনার মন্তব্য সমূহ (Your Comments)</div>
                        <div style="overflow-x:auto;">
                            <table class="ct-table" style="border:1px solid #eee;">
                                <thead><tr><th style="width:15%;">Date</th><th style="width:40%;">Comment</th><th style="width:30%;">Post</th><th style="width:15%;">Status</th></tr></thead>
                                <tbody>
                                <?php $my_cmts = get_comments(array('user_id'=>$user_id,'status'=>'all')); 
                                    if($my_cmts): foreach($my_cmts as $cmt): $st_label=($cmt->comment_approved=='1')?'Approved':'Pending'; $st_style=($cmt->comment_approved=='1')?'background:#d4edda; color:#155724;':'background:#fff3cd; color:#856404;'; ?>
                                    <tr>
                                        <td style="color:#777; font-size:12px;"><?php echo get_comment_date('d M', $cmt->comment_ID); ?></td>
                                        <td style="font-style:italic;">"<?php echo wp_trim_words($cmt->comment_content, 15); ?>"</td>
                                        <td><a href="<?php echo get_permalink($cmt->comment_post_ID); ?>" target="_blank" style="text-decoration:none; color:#3498db; font-weight:bold;">📄 <?php echo get_the_title($cmt->comment_post_ID); ?></a></td>
                                        <td><span style="padding:4px 8px; border-radius:4px; font-size:11px; font-weight:bold; <?php echo $st_style; ?>"><?php echo $st_label; ?></span></td>
                                    </tr>
                                <?php endforeach; else: ?><tr><td colspan="4" style="text-align:center;padding:30px;color:#777;">আপনি এখনো কোনো কমেন্ট করেননি।</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div>
                        <div class="ct-section-title" style="color:#2ecc71;">📰 নতুন খবর (Latest News)</div>
                        <div style="background:#fff; border:1px solid #eee; border-radius:8px; overflow:hidden;">
                            <ul style="list-style:none; padding:0; margin:0;">
                            <?php $new_posts = get_posts(array('post_status'=>'publish', 'numberposts'=>8)); 
                                if($new_posts): foreach($new_posts as $np): ?>
                                <li style="padding:12px; border-bottom:1px solid #f9f9f9; display:flex; align-items:center; transition:0.2s;">
                                    <span style="margin-right:10px; font-size:16px;">📰</span>
                                    <a href="<?php echo get_permalink($np->ID); ?>" target="_blank" style="text-decoration:none; color:#333; font-weight:500; font-size:14px; display:block;">
                                        <?php echo $np->post_title; ?>
                                        <span style="display:block; font-size:11px; color:#999; margin-top:2px;"><?php echo get_the_time('d M, Y', $np->ID); ?></span>
                                    </a>
                                </li>
                            <?php endforeach; else: ?><li style="padding:15px; text-align:center;">No news yet.</li><?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * ==============================================================================
 * PART 5 & 6: PROFILE REDIRECT & TRACKERS
 * ==============================================================================
 */
add_action('template_redirect', function(){ if ( is_user_logged_in() && is_author() ) { $u = wp_get_current_user(); if ( $u->ID == get_queried_object_id() ) { wp_redirect( site_url('/profile/') ); exit; } } });

add_action('transition_comment_status', function($new_status, $old_status, $comment){ if($new_status == 'approved' && $old_status != 'approved'){ update_comment_meta($comment->comment_ID, '_custom_approval_time', current_time('mysql')); } }, 10, 3);
add_action('wp_head', function(){
    if(is_single()) { global $post; $v = get_post_meta($post->ID, 'ct_post_views', true); $v = $v ? $v + 1 : 1; update_post_meta($post->ID, 'ct_post_views', $v); }
    if(!is_admin() && !isset($_COOKIE['ct_visit_tracked'])) { $d = date('Y-m-d'); $s = get_option('ct_daily_visits', array()); if(isset($s[$d])) $s[$d]++; else $s[$d] = 1; update_option('ct_daily_visits', $s); setcookie('ct_visit_tracked', '1', time()+86400, '/'); }
});

/**
 * ==============================================================================
 * PART 7: NOTIFICATION SYSTEM (AJAX + LOGIC FOR ALL ROLES)
 * ==============================================================================
 */
add_action('wp_ajax_custom_get_notifs', 'custom_theme_fetch_notifications');
function custom_theme_fetch_notifications() {
    if(!is_user_logged_in()) wp_die();
    $user_id = get_current_user_id();
    $last_checked = get_user_meta($user_id, 'ct_notif_read_time', true);
    if(!$last_checked) $last_checked = '2000-01-01 00:00:00';
    $notif_map = array();

    // -- LOGIC MIRRORING THE DASHBOARD PHP --
    // 1. ADMIN
    if(current_user_can('administrator')) {
        $posts = get_posts(array('post_status'=>'pending', 'numberposts'=>5, 'orderby'=>'date', 'order'=>'DESC'));
        foreach($posts as $p) $notif_map['ap_'.$p->ID] = array('type'=>'admin_post', 'time'=>$p->post_date, 'obj'=>$p);
        
        $comments = get_comments(array('number'=>8, 'orderby'=>'comment_date', 'order'=>'DESC'));
        foreach($comments as $c){ if($c->user_id != $user_id) $notif_map['ac_'.$c->comment_ID] = array('type'=>'admin_comment', 'time'=>$c->comment_date, 'obj'=>$c); }
    }
    // 2. REPORTER
    if(in_array('reporter', (array)wp_get_current_user()->roles)) {
        $posts = get_posts(array('author'=>$user_id, 'post_status'=>'publish', 'numberposts'=>5, 'orderby'=>'date', 'order'=>'DESC'));
        foreach($posts as $p) $notif_map['rp_'.$p->ID] = array('type'=>'rep_approved', 'time'=>$p->post_date, 'obj'=>$p);
        
        $my_c_ids = wp_list_pluck(get_comments(array('user_id'=>$user_id)), 'comment_ID');
        if($my_c_ids) {
            $replies = get_comments(array('parent__in'=>$my_c_ids, 'status'=>'approve', 'number'=>5));
            foreach($replies as $r) { if($r->user_id!=$user_id) $notif_map['rr_'.$r->comment_ID] = array('type'=>'reply', 'time'=>$r->comment_date, 'obj'=>$r); }
        }
    }
    // 3. SUBSCRIBER
    if(in_array('subscriber', (array)wp_get_current_user()->roles)) {
        $app_cmts = get_comments(array('user_id'=>$user_id, 'status'=>'approve', 'number'=>5, 'meta_key'=>'_custom_approval_time', 'orderby'=>'comment_ID', 'order'=>'DESC'));
        foreach($app_cmts as $ac) { $t = get_comment_meta($ac->comment_ID, '_custom_approval_time', true); if($t) $notif_map['uc_'.$ac->comment_ID] = array('type'=>'user_approved', 'time'=>$t, 'obj'=>$ac); }
        
        $new_posts = get_posts(array('post_status'=>'publish', 'numberposts'=>5, 'orderby'=>'date', 'order'=>'DESC'));
        foreach($new_posts as $np) { $notif_map['up_'.$np->ID] = array('type'=>'new_post', 'time'=>$np->post_date, 'obj'=>$np); }
    }

    $notifications = array_values($notif_map);
    usort($notifications, function($a, $b) { return strtotime($b['time']) - strtotime($a['time']); });
    $notifications = array_slice($notifications, 0, 8);
    
    $unread_count = 0; 
    foreach($notifications as $item){ if (strtotime($item['time']) > strtotime($last_checked)) $unread_count++; }

    $html = ''; 
    foreach($notifications as $item){
        $o = $item['obj']; $is_new = (strtotime($item['time']) > strtotime($last_checked)); $bg = $is_new ? '#e7f3ff' : '#fff'; $time_diff = human_time_diff(strtotime($item['time']), current_time('timestamp'));
        $avatar = ''; $text = ''; $url = '#';

        if($item['type'] == 'admin_post') { $avatar='📝'; $url=site_url('/profile/'); $text='New Pending Post: <b>'.wp_trim_words($o->post_title,4).'</b>'; }
        elseif($item['type'] == 'admin_comment') { $avatar=get_avatar($o->user_id,40); $url=get_comment_link($o->comment_ID); $text='<b>'.$o->comment_author.'</b> commented: "'.wp_trim_words($o->comment_content,5).'"'; }
        elseif($item['type'] == 'rep_approved') { $avatar='✅'; $url=get_permalink($o->ID); $text='Your post <b>'.wp_trim_words($o->post_title,4).'</b> is Live!'; }
        elseif($item['type'] == 'reply') { $avatar='↩️'; $url=get_comment_link($o->comment_ID); $text='<b>'.$o->comment_author.'</b> replied to you.'; }
        elseif($item['type'] == 'user_approved') { $avatar='👍'; $url=get_comment_link($o->comment_ID); $text='Your comment was Approved!'; }
        elseif($item['type'] == 'new_post') { $avatar='📰'; $url=get_permalink($o->ID); $text='New Post: <b>'.wp_trim_words($o->post_title,4).'</b>'; }

        $html .= '<li style="background:'.$bg.';padding:10px;border-bottom:1px solid #f0f0f0;display:flex;gap:10px;align-items:center;"><div style="flex-shrink:0;font-size:18px;">'.$avatar.'</div><div style="flex-grow:1;line-height:1.3;"><a href="'.$url.'" style="display:block;font-size:13px;color:#050505;text-decoration:none;">'.$text.'</a><span style="font-size:11px;color:#65676b;">'.$time_diff.' ago</span></div></li>';
    }
    if(empty($html)) $html = '<li style="padding:15px;text-align:center;color:#65676b;">No new notifications</li>';
    echo json_encode(array('html' => $html, 'count' => $unread_count)); wp_die();
}

add_action('wp_ajax_custom_mark_read', function(){ if(is_user_logged_in()){ update_user_meta(get_current_user_id(), 'ct_notif_read_time', current_time('mysql')); } wp_die(); });

/**
 * ==============================================================================
 * PART 8 & 9: ACTIONS & ROLE
 * ==============================================================================
 */
add_action('wp_ajax_ct_approve_comment', function(){ if(!current_user_can('moderate_comments')) wp_die(); wp_set_comment_status($_POST['comment_id'], 'approve'); update_comment_meta($_POST['comment_id'], '_custom_approval_time', current_time('mysql')); wp_send_json_success(); });
add_action('wp_ajax_ct_delete_comment', function(){ if(!current_user_can('moderate_comments')) wp_die(); wp_delete_comment($_POST['comment_id'], true); wp_send_json_success(); });
add_action('wp_ajax_ct_frontend_publish_post', function(){ if(!current_user_can('edit_others_posts')) wp_send_json_error(); wp_update_post(array('ID'=>intval($_POST['post_id']),'post_status'=>'publish')); wp_send_json_success(); });
add_action('wp_ajax_ct_frontend_trash_post', function(){ if(!current_user_can('edit_others_posts')) wp_send_json_error(); wp_trash_post(intval($_POST['post_id'])); wp_send_json_success(); });

add_action('init', function(){ if(!get_role('reporter')){ add_role('reporter','Reporter',array('read'=>true,'edit_posts'=>true,'upload_files'=>true)); } });
add_action('admin_init', function(){ if(defined('DOING_AJAX'))return; $u=wp_get_current_user(); if(in_array('reporter',(array)$u->roles)&&!current_user_can('administrator')){ wp_redirect(home_url('/')); exit; } });
add_action('init', 'custom_theme_handle_frontend_post');
function custom_theme_handle_frontend_post() {
    if (isset($_POST['ct_submit_news']) && isset($_POST['ct_security'])) {
        if (!wp_verify_nonce($_POST['ct_security'], 'ct_reporter_post_nonce')) wp_die('Security fail');
        if (!is_user_logged_in()) wp_die('Login first');
        $post_data = array('post_title'=>sanitize_text_field($_POST['news_title']), 'post_content'=>wp_kses_post($_POST['news_content']), 'post_status'=>'pending', 'post_author'=>get_current_user_id(), 'post_category'=>array(intval($_POST['news_cat'])));
        $pid = wp_insert_post($post_data);
        if ($pid) {
            if(!empty($_POST['news_tags'])) wp_set_post_tags($pid, sanitize_text_field($_POST['news_tags']));
            if(!empty($_FILES['news_image']['name'])){ require_once(ABSPATH.'wp-admin/includes/image.php'); require_once(ABSPATH.'wp-admin/includes/file.php'); require_once(ABSPATH.'wp-admin/includes/media.php'); $aid=media_handle_upload('news_image',$pid); if(!is_wp_error($aid)) set_post_thumbnail($pid,$aid); }
            wp_redirect(remove_query_arg('post_sub').'?post_sub=success'); exit;
        }
    }
}