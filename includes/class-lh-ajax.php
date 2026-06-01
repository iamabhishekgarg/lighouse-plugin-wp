<?php
class LH_Ajax
{
    const PARENT_RELATIONSHIPS = ['Parent', 'Spouse', 'Adult Child', 'Other'];

    static function init()
    {
    $nopriv = ['lhp_register', 'lhp_login', 'lhp_forgot_password', 'lhp_reset_password', 'lhp_register_via_invite', 'lhp_register_via_ep_link', 'lhp_verify_ep_otp'];
    $priv = [
      'lhp_logout',
      'lhp_get_records',
      'lhp_save_record',
      'lhp_delete_record',
    'lhp_get_record',
    'lhp_get_profile',
    'lhp_save_profile',
      'lhp_admin_get_users',
    'lhp_admin_get_records',
    'lhp_admin_create_user',
    'lhp_admin_delete_user',
    'lhp_admin_toggle_user',
    'lhp_admin_stats',
    'lhp_save_note',
    'lhp_get_notes',
    'lhp_upload_file',
    'lhp_delete_file',
    'lhp_get_clients',
    'lhp_save_client',
    'lhp_delete_client',
    'lhp_get_client_records',
    'lhp_get_my_records',
    'lhp_get_delegated_users',
    'lhp_save_delegated_user',
    'lhp_remove_delegated_user',
    'lhp_activate_delegated_access',
    'lhp_upload_death_doc',
    'lhp_activate_access_person',
    'lhp_get_my_delegated_records',
    'lhp_create_parent_record',
    'lhp_get_invite_link',
    'lhp_set_invite_password',
    'lhp_save_ep_profile',
    'lhp_get_ep_profile',
    'lhp_generate_client_link',
    'lhp_resend_invite',
    'lhp_get_ep_links',
    'lhp_upload_logo',
    'lhp_delete_share_link',
    'lhp_get_ep_token',
    'lhp_save_owner2',
    'lhp_get_owner2',
    'lhp_set_password',
    'lhp_owner_delete_profile',
    'lhp_refresh_nonce'
    ];

    foreach ($nopriv as $a) {
    add_action("wp_ajax_{$a}", [__CLASS__, $a]);
    add_action("wp_ajax_nopriv_{$a}", [__CLASS__, $a]);
    }
    foreach ($priv as $a) {
    add_action("wp_ajax_{$a}", [__CLASS__, $a]);
    }
    }

    static function verify()
    {
    if (!check_ajax_referer('flp_nonce', 'nonce', false)) {
    wp_send_json_error('Security check failed.');
    }
    }

    /* ── NONCE REFRESH ─────────────────────────────────── */
    static function lhp_refresh_nonce()
    {
    if (!is_user_logged_in()) wp_send_json_error('Not logged in.');
    wp_send_json_success(['nonce' => wp_create_nonce('flp_nonce')]);
    }

    /* ── Clean title: strips HTML entities WordPress adds via wptexturize ── */
    private static function clean_title($post_id)
    {
    return html_entity_decode(\get_the_title($post_id), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /* ── REGISTER ──────────────────────────────────────── */
    static function lhp_register()
    {
    self::verify();
    $role = sanitize_key($_POST['role'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $name = sanitize_text_field($_POST['full_name'] ?? '');

    if (!in_array($role, ['lighthouse_planner', 'lighthouse_parent']))
    wp_send_json_error('Invalid role.');
    if (!is_email($email))
    wp_send_json_error('Invalid email address.');
    if (email_exists($email))
    wp_send_json_error('An account with this email already exists.');
    if (strlen($pass) < 8)
    wp_send_json_error('Password must be at least 8 characters.');

    $user_id = wp_insert_user([
    'user_login' => $email,
    'user_email' => $email,
    'user_pass' => $pass,
    'display_name' => $name,
    'role' => $role,
    ]);

    if (is_wp_error($user_id))
    wp_send_json_error($user_id->get_error_message());

    if ($role === 'lighthouse_planner') {
    $planner_phone = sanitize_text_field($_POST['phone'] ?? '');
    if (!self::validate_phone($planner_phone))
      wp_send_json_error('Phone number must be at least 10 digits.');
    update_user_meta($user_id, '_flp_firm_name', sanitize_text_field($_POST['firm_name'] ?? ''));
    update_user_meta($user_id, '_flp_phone', $planner_phone);
    update_user_meta($user_id, '_flp_internal_ref', sanitize_text_field($_POST['internal_ref'] ?? ''));
    } else {
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    if (!self::validate_phone($phone))
      wp_send_json_error('Mobile Phone must be exactly 10 digits.');
    update_user_meta($user_id, '_flp_phone', $phone);

    // If an invite token is present, link to the planner's existing record
    $invite_token = sanitize_text_field($_POST['invite_token'] ?? '');
    $linked = false;
    if ($invite_token) {
      $records = get_posts([
        'post_type'      => 'lh_record',
        'posts_per_page' => 1,
        'meta_query'     => [['key' => '_flp_invite_token', 'value' => $invite_token, 'compare' => '=']],
      ]);
      if ($records) {
        $record_id = $records[0]->ID;
        update_post_meta($record_id, '_flp_owner_id', $user_id);
        update_user_meta($user_id, '_flp_record_id', $record_id);
        delete_post_meta($record_id, '_flp_invite_token');
        $linked = true;
      }
    }

    if (!$linked) {
      // Auto-create draft record for parent
      $record_id = wp_insert_post([
        'post_type'   => 'lh_record',
        'post_title'  => $name . "'s Lighthouse",
        'post_status' => 'publish',
        'post_author' => $user_id,
      ]);
      update_post_meta($record_id, '_flp_owner_id', $user_id);
      update_post_meta($record_id, '_flp_planner_id', 0);
      update_post_meta($record_id, '_flp_status', 'draft');
      update_post_meta($record_id, '_flp_completion', 0);
      update_user_meta($user_id, '_flp_record_id', $record_id);
    }
    }

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    $redirect = $role === 'lighthouse_planner'
    ? home_url('/planner-dashboard')
    : home_url('/dashboard');

    wp_send_json_success(['redirect' => $redirect]);
    }

    /* ── REGISTER VIA INVITE LINK ──────────────────────── */
    static function lhp_register_via_invite()
    {
    self::verify();
    $token = sanitize_text_field($_POST['token'] ?? '');
    $name  = sanitize_text_field($_POST['full_name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (!$token) wp_send_json_error('Invalid invitation.');
    if (!$name)  wp_send_json_error('Full name is required.');
    if (!is_email($email)) wp_send_json_error('Invalid email address.');
    if (email_exists($email)) wp_send_json_error('An account with this email already exists. Please log in.');
    if (strlen($pass) < 8) wp_send_json_error('Password must be at least 8 characters.');

    // Find EP who owns this token + check if already claimed
    $ep_id       = 0;
    $token_lower = strtolower($token);
    $users = get_users(['meta_key' => '_flp_share_links', 'number' => -1, 'fields' => ['ID']]);
    foreach ($users as $u) {
    $links = get_user_meta($u->ID, '_flp_share_links', true) ?: [];
    foreach ($links as $l) {
      if (strtolower($l['token'] ?? '') === $token_lower) {
        if (!empty($l['client_id']) && (int) $l['client_id'] > 0)
          wp_send_json_error('This invitation has already been used. Please sign in or contact your planner for a new link.');
        $ep_id = $u->ID;
        break 2;
      }
    }
    }
    if (!$ep_id) wp_send_json_error('Invalid or expired invitation.');

    // Generate 6-digit OTP and store pending registration in transient (15 min)
    // Key prefixed with flow type to prevent collision with ep_welcome flow
    $otp = sprintf('%06d', random_int(0, 999999));
    $key = 'flp_otp_invite_' . md5(strtolower($email));
    set_transient($key, [
        'otp'          => $otp,
        'flow'         => 'invite',
        'ep_id'        => $ep_id,
        'invite_token' => $token,
        'name'         => $name,
        'email'        => $email,
        'phone'        => $phone,
        'password'     => $pass,
        'attempts'     => 0,
        'expires_at'   => time() + 15 * MINUTE_IN_SECONDS,
    ], 15 * MINUTE_IN_SECONDS);

    // Email OTP
    $site = get_bloginfo('name');
    wp_mail(
        $email,
        "Your {$site} verification code",
        "Hello {$name},\r\n\r\n" .
        "Your email verification code is:\r\n\r\n" .
        "    {$otp}\r\n\r\n" .
        "This code expires in 15 minutes.\r\n\r\n" .
        "If you did not request this, please ignore this email.\r\n\r\n" .
        "— The {$site} Team"
    );

    $at     = strpos($email, '@');
    $local  = substr($email, 0, $at);
    $domain = substr($email, $at);
    $masked = substr($local, 0, min(2, strlen($local))) . str_repeat('*', max(0, strlen($local) - 2)) . $domain;

    wp_send_json_success(['step' => 'verify_otp', 'masked_email' => $masked]);
    }

    /* ── LOGIN ─────────────────────────────────────────── */
    static function lhp_login()
    {
    self::verify();
    $email = sanitize_email($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    $user = wp_authenticate($email, $pass);
    if (is_wp_error($user))
    wp_send_json_error('Invalid email or password.');

    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);

    $role = LH_Auth::current_role($user);
    $redirect = $role === 'lighthouse_planner'
    ? home_url('/planner-dashboard')
    : home_url('/dashboard');

    wp_send_json_success(['redirect' => $redirect]);
    }

    /* ── FORGOT PASSWORD ──────────────────────────────── */
    static function lhp_forgot_password()
    {
    self::verify();
    $email = sanitize_email($_POST['email'] ?? '');

    if (!is_email($email)) {
    wp_send_json_error('Invalid email address.');
    }

    $user = get_user_by('email', $email);

    // Always return success to prevent email enumeration
    if (!$user) {
    wp_send_json_success(['message' => "If an account exists with that email, you'll receive a reset link shortly."]);
    return;
    }

    $key = get_password_reset_key($user);
    if (is_wp_error($key)) {
    wp_send_json_error('Unable to generate reset link. Please try again.');
    return;
    }

    $reset_url = home_url('/login?action=reset&key=' . rawurlencode($key) . '&login=' . rawurlencode($user->user_login));

    $site_name = get_bloginfo('name');
    $subject   = "Reset your {$site_name} password";
    $message   = "Hi {$user->display_name},\r\n\r\n";
    $message  .= "Someone requested a password reset for your Family Lighthouse account.\r\n\r\n";
    $message  .= "Click the link below to reset your password:\r\n";
    $message  .= "{$reset_url}\r\n\r\n";
    $message  .= "This link will expire in 24 hours.\r\n\r\n";
    $message  .= "If you did not request a password reset, you can safely ignore this email.\r\n\r\n";
    $message  .= "— The Family Lighthouse Team";

    wp_mail($user->user_email, $subject, $message);

    wp_send_json_success(['message' => "If an account exists with that email, you'll receive a reset link shortly."]);
    }

    /* ── RESET PASSWORD ────────────────────────────────── */
    static function lhp_reset_password()
    {
    self::verify();
    $key   = sanitize_text_field($_POST['key']       ?? '');
    $login = sanitize_text_field($_POST['login']     ?? '');
    $pass  = $_POST['password']  ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (!$key || !$login) {
        wp_send_json_error('Invalid reset link. Please request a new one.');
    }
    if (strlen($pass) < 8) {
        wp_send_json_error('Password must be at least 8 characters.');
    }
    if ($pass !== $pass2) {
        wp_send_json_error('Passwords do not match.');
    }

    $user = check_password_reset_key($key, $login);
    if (is_wp_error($user)) {
        wp_send_json_error('This reset link has expired or is invalid. Please request a new one.');
    }

    reset_password($user, $pass);
    wp_send_json_success([
        'message'  => 'Password updated! Redirecting to sign in…',
        'redirect' => home_url('/login'),
    ]);
    }

    /* ── LOGOUT ────────────────────────────────────────── */
    static function lhp_logout()
    {
    wp_logout();
    wp_send_json_success(['redirect' => home_url('/login')]);
    }

    /* ── GET ALL RECORDS (planner) ────────────────────── */
    static function lhp_get_records()
    {
    self::verify();
    $role = LH_Auth::current_role();
    if (!in_array($role, ['administrator', 'lhp_super_admin', 'lighthouse_planner']))
    wp_send_json_error('Access denied.');

    $uid = get_current_user_id();
    $args = [
    'post_type' => 'lh_record',
    'posts_per_page' => -1,
    'orderby' => 'date',
    'order' => 'DESC',
    ];
    if ($role === 'lighthouse_planner') {
    $args['meta_query'] = [['key' => '_flp_planner_id', 'value' => $uid]];
    }

    $records = get_posts($args);
    $data = [];
    foreach ($records as $r) {
    $subject = get_post_meta($r->ID, '_flp_subject', true);
    $owner_id = (int) get_post_meta($r->ID, '_flp_owner_id', true);
    $owner = $owner_id ? get_userdata($owner_id) : null;
    $data[] = [
      'id' => $r->ID,
      'title' => self::clean_title($r->ID),
      'subject_name' => is_array($subject) ? ($subject['full_name'] ?? '') : '',
      'subject_dob' => is_array($subject) ? ($subject['dob'] ?? '') : '',
      'status' => get_post_meta($r->ID, '_flp_status', true) ?: 'draft',
      'completion' => (int) get_post_meta($r->ID, '_flp_completion', true),
      'created' => get_the_date('M j, Y', $r->ID),
      'owner_email' => $owner ? $owner->user_email : '',
      'owner_id' => $owner_id,
      'has_owner' => (bool) $owner_id,
    ];
    }
    wp_send_json_success($data);
    }

    /* ── GET SINGLE RECORD ─────────────────────────────── */
    static function lhp_get_record()
    {
    self::verify();
    $post_id = intval($_POST['record_id'] ?? 0);
    if (!$post_id || !LH_Auth::verify_record_access($post_id))
    wp_send_json_error('Access denied.');
    wp_send_json_success(self::build_record_data($post_id));
    }

    static function build_record_data($post_id)
    {
    $data = [
    'id' => $post_id,
    'title' => self::clean_title($post_id),
    'status' => get_post_meta($post_id, '_flp_status', true) ?: 'draft',
    'completion' => (int) get_post_meta($post_id, '_flp_completion', true),
    'subject' => get_post_meta($post_id, '_flp_subject', true) ?: [],
    'children' => get_post_meta($post_id, '_flp_children', true) ?: [],
    'access_people' => get_post_meta($post_id, '_flp_access_people', true) ?: [],
    'access_grant' => get_post_meta($post_id, '_flp_access_grant', true) ?: '',
    'personal_items' => get_post_meta($post_id, '_flp_personal_items', true) ?: [],
    'burial' => get_post_meta($post_id, '_flp_burial', true) ?: [],
    'bank_accounts' => get_post_meta($post_id, '_flp_bank_accounts', true) ?: [],
    'life_insurance' => get_post_meta($post_id, '_flp_life_insurance', true) ?: [],
    'letters' => get_post_meta($post_id, '_flp_letters', true) ?: [],
    'docs' => array_map(function($doc) {
      if (!empty($doc['id'])) {
        $fresh = wp_get_attachment_url((int)$doc['id']);
        if ($fresh) $doc['url'] = $fresh;
      }
      return $doc;
    }, get_post_meta($post_id, '_flp_docs', true) ?: []),
    'owner2' => get_post_meta($post_id, '_flp_owner2', true) ?: [],
    'owner2_id' => (int) get_post_meta($post_id, '_flp_owner2_id', true),
    'owner2_name' => self::get_owner2_name($post_id),
    'delegated_users' => get_post_meta($post_id, '_flp_delegated_users', true) ?: [],
    'social_media' => get_post_meta($post_id, '_flp_social_media', true) ?: '',
    'additional_wishes' => get_post_meta($post_id, '_flp_additional_wishes', true) ?: '',
    'planner_id' => (int) get_post_meta($post_id, '_flp_planner_id', true),
    'owner_id' => (int) get_post_meta($post_id, '_flp_owner_id', true),
    ];
    $owner_id = (int) get_post_meta($post_id, '_flp_owner_id', true);
    if ($owner_id) {
    $o_user = get_userdata($owner_id);
    if ($o_user) {
      $data['owner_name'] = $o_user->display_name;
      $data['owner_email'] = $o_user->user_email;
      $data['owner_phone'] = get_user_meta($owner_id, '_flp_phone', true);
    }
    }
    if (empty($data['owner_name']))  $data['owner_name']  = '';
    if (empty($data['owner_email'])) $data['owner_email'] = '';
    if (empty($data['owner_phone'])) $data['owner_phone'] = '';
    return $data;
    }

    /* ── SAVE RECORD (create or update) ────────────────── */
    static function lhp_save_record()
    {
    self::verify();
    $post_id = intval($_POST['record_id'] ?? 0);
    $is_new = !$post_id;
    $role = LH_Auth::current_role();
    $uid = get_current_user_id();

    if (!$is_new && !LH_Auth::verify_record_access($post_id))
    wp_send_json_error('Access denied.');

    if (in_array($role, ['lighthouse_planner']))
    wp_send_json_error('Records cannot be created directly — send an invite link from the Clients tab.');

    if ($is_new) {
    if (!in_array($role, ['lighthouse_parent', 'administrator', 'lhp_super_admin']))
      wp_send_json_error('Access denied.');

    $post_id = wp_insert_post([
      'post_type' => 'lh_record',
      'post_title' => 'New Lighthouse Record',
      'post_status' => 'publish',
      'post_author' => $uid,
    ]);
    if (is_wp_error($post_id))
      wp_send_json_error('Failed to create record.');

    // planner_id is the EP who owns this client relationship, not the parent creating their own record
    $planner_id = ($role === 'lighthouse_parent') ? (int) get_user_meta($uid, '_flp_planner_id', true) : $uid;
    update_post_meta($post_id, '_flp_planner_id', $planner_id);
    update_post_meta($post_id, '_flp_owner_id', ($role === 'lighthouse_parent') ? $uid : 0);
    update_post_meta($post_id, '_flp_status', 'draft');
    update_post_meta($post_id, '_flp_completion', 0);
    if (!empty($_POST['client_id'])) {
      update_post_meta($post_id, '_flp_client_id', intval($_POST['client_id']));
    }
    }

    // Pre-decode and validate subject before the save loop
    $decoded_sections = [];
    if (isset($_POST['subject'])) {
    $subj = json_decode(stripslashes($_POST['subject']), true);
    if (empty($subj['relationship_to_owner']))
      wp_send_json_error('Please select your relationship.');
    if (empty(trim($subj['full_name'] ?? '')))
      wp_send_json_error('Full legal name is required.');
    $subj_email = trim($subj['email'] ?? '');
    if ($subj_email && !is_email($subj_email))
      wp_send_json_error('Invalid email format for subject.');
    $decoded_sections['subject'] = $subj;
    }

    // JSON sections — reuse already-decoded values to avoid double json_decode
    $json_sections = [
    'subject', 'children', 'access_people', 'personal_items',
    'burial', 'bank_accounts', 'life_insurance', 'letters', 'docs', 'owner2'
    ];
    foreach ($json_sections as $sec) {
    if (!isset($_POST[$sec])) continue;
    $val = $decoded_sections[$sec] ?? json_decode(stripslashes($_POST[$sec]), true);
    if ($val !== null) {
      // Validate emails in children and access_people
      if (in_array($sec, ['children', 'access_people']) && is_array($val)) {
        foreach ($val as $row) {
          $row_email = trim($row['email'] ?? '');
          if ($row_email && $row_email !== '__optout__' && !is_email($row_email))
            wp_send_json_error('Invalid email in ' . str_replace('_', ' ', $sec) . '.');
        }
      }
      update_post_meta($post_id, "_flp_{$sec}", $val);
      // Ensure every access_person has a stable id, then send emails
      if ($sec === 'access_people' && is_array($val)) {
        $existing_people = get_post_meta($post_id, '_flp_access_people', true) ?: [];
        $existing_map = [];
        foreach ($existing_people as $ep) {
          if (!empty($ep['email'])) $existing_map[$ep['email']] = $ep;
        }
        foreach ($val as &$row) {
          $row_email = trim($row['email'] ?? '');
          // Preserve existing id/user_id/status if email matches
          if ($row_email && isset($existing_map[$row_email])) {
            $row['id']      = $row['id'] ?? ($existing_map[$row_email]['id'] ?? uniqid('ap_'));
            $row['user_id'] = $row['user_id'] ?? ($existing_map[$row_email]['user_id'] ?? 0);
            $row['status']  = $row['status'] ?? ($existing_map[$row_email]['status'] ?? 'pending');
          } else {
            if (empty($row['id'])) $row['id'] = uniqid('ap_');
          }
        }
        unset($row);
        update_post_meta($post_id, "_flp_{$sec}", $val);
        self::send_delegate_emails($val, $post_id);
      }
    }
    }

    // Scalar fields
    if (isset($_POST['access_grant']))
    update_post_meta($post_id, '_flp_access_grant', sanitize_text_field($_POST['access_grant']));
    if (isset($_POST['social_media']))
    update_post_meta($post_id, '_flp_social_media', sanitize_textarea_field($_POST['social_media']));
    if (isset($_POST['additional_wishes']))
    update_post_meta($post_id, '_flp_additional_wishes', sanitize_textarea_field($_POST['additional_wishes']));

    // Update title: from explicit title param OR from subject name
    $new_title = sanitize_text_field($_POST['record_title'] ?? '');
    if (!$new_title) {
    $subject = get_post_meta($post_id, '_flp_subject', true);
    $new_title = is_array($subject) && !empty($subject['full_name'])
      ? sanitize_text_field($subject['full_name']) . "'s Lighthouse"
      : '';
    }
    if ($new_title) {
    wp_update_post(['ID' => $post_id, 'post_title' => $new_title]);
    }

    // Recalculate completion
    $pct = self::calc_completion($post_id);
    update_post_meta($post_id, '_flp_completion', $pct);
    update_post_meta($post_id, '_flp_status', $pct >= 100 ? 'complete' : 'draft');

    wp_send_json_success(['record_id' => $post_id, 'completion' => $pct]);
    }

    /* ── DELETE RECORD ─────────────────────────────────── */
    static function lhp_delete_record()
    {
    self::verify();
    $post_id = intval($_POST['record_id'] ?? 0);
    $role = LH_Auth::current_role();
    if (!in_array($role, ['administrator', 'lhp_super_admin', 'lighthouse_parent']))
    wp_send_json_error('Access denied.');
    if (!LH_Auth::verify_record_access($post_id))
    wp_send_json_error('Access denied.');
    wp_delete_post($post_id, true);
    wp_send_json_success('Record deleted.');
    }

    /* ── GET PROFILE ───────────────────────────────────── */
    static function lhp_get_profile()
    {
    self::verify();
    $uid = get_current_user_id();
    $user = get_userdata($uid);
    $record_id = intval($_POST['record_id'] ?? 0);
    $is_primary = $record_id ? ((int) get_post_meta($record_id, '_flp_owner_id', true) === $uid) : true;

    // When owner2 is logged in, also return owner1's details for display
    $owner1_data = null;
    if (!$is_primary && $record_id) {
        $owner1_id = (int) get_post_meta($record_id, '_flp_owner_id', true);
        if ($owner1_id) {
            $o1 = get_userdata($owner1_id);
            if ($o1) {
                $owner1_data = [
                    'name'  => $o1->display_name,
                    'email' => $o1->user_email,
                    'phone' => get_user_meta($owner1_id, '_flp_phone', true) ?: '',
                ];
            }
        }
    }

    wp_send_json_success([
    'name' => $user->display_name,
    'email' => $user->user_email,
    'phone' => get_user_meta($uid, '_flp_phone', true),
    'firm_name' => get_user_meta($uid, '_flp_firm_name', true),
    'internal_ref' => get_user_meta($uid, '_flp_internal_ref', true),
    'relationship' => get_user_meta($uid, '_flp_relationship', true),
    'role' => LH_Auth::current_role(),
    'is_primary_owner' => $is_primary,
    'owner1' => $owner1_data,
    ]);
    }

    /* ── SAVE PROFILE ──────────────────────────────────── */
    static function lhp_save_profile()
    {
    self::verify();
    $uid = get_current_user_id();
    $new_name = sanitize_text_field($_POST['name'] ?? '');
    wp_update_user(['ID' => $uid, 'display_name' => $new_name]);
    $fields = [
    'phone' => '_flp_phone',
    'firm_name' => '_flp_firm_name',
    'internal_ref' => '_flp_internal_ref'
    ];
    foreach ($fields as $post_key => $meta_key) {
    if (isset($_POST[$post_key]))
      update_user_meta($uid, $meta_key, sanitize_text_field($_POST[$post_key]));
    }
    // Update lighthouse title on any record this user owns as primary owner
    if ($new_name) {
      $records = get_posts([
        'post_type'      => 'lh_record',
        'posts_per_page' => -1,
        'meta_key'       => '_flp_owner_id',
        'meta_value'     => $uid,
      ]);
      foreach ($records as $r) {
        $subject = get_post_meta($r->ID, '_flp_subject', true);
        $subject_name = is_array($subject) ? ($subject['full_name'] ?? '') : '';
        // Only update title when subject is the owner themselves (relationship = self)
        $relationship = is_array($subject) ? ($subject['relationship_to_owner'] ?? '') : '';
        if ($relationship === 'self' && $subject_name) {
          // Update subject name too so it stays in sync
          $subject['full_name'] = $new_name;
          update_post_meta($r->ID, '_flp_subject', $subject);
          wp_update_post(['ID' => $r->ID, 'post_title' => $new_name . "'s Lighthouse"]);
        } elseif (!$subject_name) {
          // No subject yet — update title based on owner name
          wp_update_post(['ID' => $r->ID, 'post_title' => $new_name . "'s Lighthouse"]);
        }
      }
    }
    wp_send_json_success('Profile saved.');
    }

    /* ── INVITE PARENT ─────────────────────────────────── */
    static function lhp_invite_parent()
    {
    self::verify();
    $role = LH_Auth::current_role();
    if (in_array($role, ['lighthouse_planner']))
    wp_send_json_error('Send an invite link from the Clients tab instead.');
    if (!in_array($role, ['administrator', 'lhp_super_admin']))
    wp_send_json_error('Access denied.');

    $post_id = intval($_POST['record_id'] ?? 0);
    $email = sanitize_email($_POST['email'] ?? '');
    $name = sanitize_text_field($_POST['name'] ?? '');
    $uid = get_current_user_id();

    if (!$post_id || !LH_Auth::verify_record_access($post_id))
    wp_send_json_error('Access denied.');
    if (!is_email($email))
    wp_send_json_error('Invalid email address.');

    $existing = get_user_by('email', $email);
    if ($existing) {
    update_post_meta($post_id, '_flp_owner_id', $existing->ID);
    update_user_meta($existing->ID, '_flp_record_id', $post_id);
    wp_send_json_success('Linked to existing account. No email sent.');
    return;
    }

    $temp_pass = wp_generate_password(12, false);
    $parent_id = wp_insert_user([
    'user_login' => $email,
    'user_email' => $email,
    'user_pass' => $temp_pass,
    'display_name' => $name,
    'role' => 'lighthouse_parent',
    ]);
    if (is_wp_error($parent_id))
    wp_send_json_error('Could not create parent account.');

    update_post_meta($post_id, '_flp_owner_id', $parent_id);
    update_user_meta($parent_id, '_flp_record_id', $post_id);

    $login_url = home_url('/login');
    $planner = get_userdata($uid);
    $subject = 'Your Family Lighthouse Invitation';
    $message = "Hello {$name},\n\nYour planner, {$planner->display_name}, has set up a Family Lighthouse record for you.\n\nLogin here: {$login_url}\n\nEmail: {$email}\nTemporary Password: {$temp_pass}\n\nPlease log in and change your password as soon as possible.\n\nThis is not a will or legal document. — Family Lighthouse";
    wp_mail($email, $subject, $message);

    wp_send_json_success("Invitation sent to {$email}.");
    }

    /* ── HELPERS ───────────────────────────────────────── */
    static function calc_completion($post_id)
    {
    $weights = [
    '_flp_subject' => 20,
    '_flp_children' => 15,
    '_flp_access_people' => 10,
    '_flp_access_grant' => 5,
    '_flp_personal_items' => 10,
    '_flp_burial' => 10,
    '_flp_bank_accounts' => 10,
    '_flp_life_insurance' => 5,
    '_flp_letters' => 10,
    '_flp_delegated_users' => 5,
    ];
    $total = 0;
    foreach ($weights as $key => $w) {
    $val = get_post_meta($post_id, $key, true);
    if (empty($val)) continue;
    // For array-based sections, require at least one row with a non-empty required field
    if (is_array($val)) {
      $has_content = false;
      foreach ($val as $row) {
        if (is_array($row) && array_filter(array_values($row))) { $has_content = true; break; }
      }
      if (!$has_content) continue;
    }
    $total += $w;
    }
    return min(100, $total);
    }

    /* ── SUPER ADMIN: GET ALL USERS ────────────────────── */
    static function lhp_admin_get_users()
    {
    self::verify();
    if (!in_array(LH_Auth::current_role(), ['lhp_super_admin', 'lighthouse_law_firm', 'administrator']))
    wp_send_json_error('Access denied.');

    $roles = isset($_POST['role']) && $_POST['role'] ? [sanitize_key($_POST['role'])] : ['lighthouse_planner', 'lighthouse_parent', 'lighthouse_law_firm'];
    $search = sanitize_text_field($_POST['search'] ?? '');

    $args = ['role__in' => $roles, 'number' => 200, 'orderby' => 'registered', 'order' => 'DESC'];
    if ($search)
    $args['search'] = '*' . $search . '*';

    $users = get_users($args);
    $data = [];
    foreach ($users as $u) {
    $role = LH_Auth::current_role($u);
    $record_count = 0;
    if ($role === 'lighthouse_planner') {
      $record_count = count(get_posts([
        'post_type' => 'lh_record',
        'posts_per_page' => -1,
        'meta_query' => [['key' => '_flp_planner_id', 'value' => $u->ID]]
      ]));
    } elseif ($role === 'lighthouse_parent') {
      $record_count = get_user_meta($u->ID, '_flp_record_id', true) ? 1 : 0;
    }
    $data[] = [
      'id' => $u->ID,
      'name' => $u->display_name,
      'email' => $u->user_email,
      'role' => $role,
      'role_label' => self::role_label($role),
      'firm' => get_user_meta($u->ID, '_flp_firm_name', true),
      'phone' => get_user_meta($u->ID, '_flp_phone', true),
      'registered' => wp_date('M j, Y', strtotime($u->user_registered)),
      'record_count' => $record_count,
      'status' => get_user_meta($u->ID, '_flp_status', true) ?: 'active',
    ];
    }
    wp_send_json_success($data);
    }

    /* ── SUPER ADMIN: GET ALL RECORDS ──────────────────── */
    static function lhp_admin_get_records()
    {
    self::verify();
    if (!in_array(LH_Auth::current_role(), ['lhp_super_admin', 'lighthouse_law_firm', 'administrator']))
    wp_send_json_error('Access denied.');

    $records = get_posts(['post_type' => 'lh_record', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC']);
    $data = [];
    foreach ($records as $r) {
    $subject = get_post_meta($r->ID, '_flp_subject', true);
    $planner_id = (int) get_post_meta($r->ID, '_flp_planner_id', true);
    $owner_id = (int) get_post_meta($r->ID, '_flp_owner_id', true);
    $planner = $planner_id ? get_userdata($planner_id) : null;
    $owner = $owner_id ? get_userdata($owner_id) : null;
    $data[] = [
      'id' => $r->ID,
      'title' => self::clean_title($r->ID),
      'subject_name' => is_array($subject) ? ($subject['full_name'] ?? '') : '',
      'subject_dob' => is_array($subject) ? ($subject['dob'] ?? '') : '',
      'status' => get_post_meta($r->ID, '_flp_status', true) ?: 'draft',
      'completion' => (int) get_post_meta($r->ID, '_flp_completion', true),
      'created' => get_the_date('M j, Y', $r->ID),
      'planner_name' => $planner ? $planner->display_name : '—',
      'owner_name' => $owner ? $owner->display_name : '—',
      'owner_email' => $owner ? $owner->user_email : '—',
    ];
    }
    wp_send_json_success($data);
    }

    /* ── SUPER ADMIN: CREATE USER ──────────────────────── */
    static function lhp_admin_create_user()
    {
    self::verify();
    if (!in_array(LH_Auth::current_role(), ['lhp_super_admin', 'administrator']))
    wp_send_json_error('Access denied.');

    $role = sanitize_key($_POST['role'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $name = sanitize_text_field($_POST['name'] ?? '');
    $valid_roles = ['lighthouse_planner', 'lighthouse_parent', 'lighthouse_law_firm', 'lhp_super_admin'];

    if (!in_array($role, $valid_roles))
    wp_send_json_error('Invalid role.');
    if (!is_email($email))
    wp_send_json_error('Invalid email address.');
    if (email_exists($email))
    wp_send_json_error('Email already exists.');

    $pass = wp_generate_password(12, false);
    $uid = wp_insert_user(['user_login' => $email, 'user_email' => $email, 'user_pass' => $pass, 'display_name' => $name, 'role' => $role]);
    if (is_wp_error($uid))
    wp_send_json_error($uid->get_error_message());

    if (isset($_POST['firm_name']))
    update_user_meta($uid, '_flp_firm_name', sanitize_text_field($_POST['firm_name']));
    if (isset($_POST['phone']))
    update_user_meta($uid, '_flp_phone', sanitize_text_field($_POST['phone']));

    // If parent: auto-create draft record
    if ($role === 'lighthouse_parent') {
    $record_id = wp_insert_post(['post_type' => 'lh_record', 'post_title' => $name . "'s Lighthouse", 'post_status' => 'publish', 'post_author' => $uid]);
    update_post_meta($record_id, '_flp_owner_id', $uid);
    update_post_meta($record_id, '_flp_status', 'draft');
    update_post_meta($record_id, '_flp_completion', 0);
    update_user_meta($uid, '_flp_record_id', $record_id);
    }

    // Send welcome email
    $login_url = home_url('/login');
    wp_mail($email, 'Your Family Lighthouse Account', "Hello {$name},\n\nYour account has been created.\n\nLogin: {$login_url}\nEmail: {$email}\nPassword: {$pass}\n\nPlease log in and change your password.\n\n— Family Lighthouse");

    self::log_activity('user_created', "User {$name} ({$role}) created.", 0, $uid);
    wp_send_json_success(['user_id' => $uid, 'temp_pass' => $pass]);
    }

    /* ── SUPER ADMIN: DELETE USER ──────────────────────── */
    static function lhp_admin_delete_user()
    {
    self::verify();
    if (!in_array(LH_Auth::current_role(), ['lhp_super_admin', 'administrator']))
    wp_send_json_error('Access denied.');
    $uid = intval($_POST['user_id'] ?? 0);
    if (!$uid)
    wp_send_json_error('Invalid user.');
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($uid);
    self::log_activity('user_deleted', "User ID {$uid} deleted.");
    wp_send_json_success('User deleted.');
    }

    /* ── SUPER ADMIN: TOGGLE USER STATUS ───────────────── */
    static function lhp_admin_toggle_user()
    {
    self::verify();
    if (!in_array(LH_Auth::current_role(), ['lhp_super_admin', 'administrator']))
    wp_send_json_error('Access denied.');
    $uid = intval($_POST['user_id'] ?? 0);
    if (!$uid) wp_send_json_error('Invalid user ID.');
    $status = sanitize_key($_POST['status'] ?? 'active');
    if (!in_array($status, ['active', 'suspended', 'disabled']))
    wp_send_json_error('Invalid status value.');
    update_user_meta($uid, '_flp_status', $status);
    self::log_activity('user_status', "User ID {$uid} set to {$status}.");
    wp_send_json_success("User status updated to {$status}.");
    }

    /* ── SUPER ADMIN: PLATFORM STATS ───────────────────── */
    static function lhp_admin_stats()
    {
    self::verify();
    if (!in_array(LH_Auth::current_role(), ['lhp_super_admin', 'lighthouse_law_firm', 'administrator']))
    wp_send_json_error('Access denied.');

    $total_records = wp_count_posts('lh_record')->publish;
    $total_planners = count(get_users(['role' => 'lighthouse_planner', 'count_total' => true, 'fields' => 'ID']));
    $total_parents = count(get_users(['role' => 'lighthouse_parent', 'count_total' => true, 'fields' => 'ID']));
    $total_firms = count(get_users(['role' => 'lighthouse_law_firm', 'count_total' => true, 'fields' => 'ID']));

    $records = get_posts(['post_type' => 'lh_record', 'posts_per_page' => -1, 'fields' => 'ids']);
    $complete = 0;
    $avg = 0;
    foreach ($records as $rid) {
    $s = get_post_meta($rid, '_flp_status', true);
    $c = (int) get_post_meta($rid, '_flp_completion', true);
    if ($s === 'complete')
      $complete++;
    $avg += $c;
    }
    $avg_completion = count($records) ? round($avg / count($records)) : 0;

    wp_send_json_success([
    'total_records' => $total_records,
    'complete_records' => $complete,
    'draft_records' => $total_records - $complete,
    'total_planners' => $total_planners,
    'total_parents' => $total_parents,
    'total_firms' => $total_firms,
    'avg_completion' => $avg_completion,
    'activity_log' => self::get_activity_log(intval($_POST['activity_limit'] ?? 10)),
    ]);
    }

    /* ── NOTES: SAVE NOTE ON RECORD ─────────────────────── */
    static function lhp_save_note()
    {
    self::verify();
    $post_id = intval($_POST['record_id'] ?? 0);
    if (!$post_id || !LH_Auth::verify_record_access($post_id))
    wp_send_json_error('Access denied.');

    $text = sanitize_textarea_field($_POST['note'] ?? '');
    if (!$text)
    wp_send_json_error('Note cannot be empty.');

    $user = wp_get_current_user();
    $notes = get_post_meta($post_id, '_flp_notes', true) ?: [];
    $notes[] = [
    'id' => uniqid(),
    'author' => $user->display_name,
    'role' => LH_Auth::current_role(),
    'text' => $text,
    'date' => wp_date('M j, Y g:i a'),
    'ts' => time(),
    ];
    update_post_meta($post_id, '_flp_notes', $notes);
    self::log_activity('note_added', "Note added to record #{$post_id}.", $post_id);
    wp_send_json_success($notes);
    }

    /* ── NOTES: GET NOTES ───────────────────────────────── */
    static function lhp_get_notes()
    {
    self::verify();
    $post_id = intval($_POST['record_id'] ?? 0);
    if (!$post_id || !LH_Auth::verify_record_access($post_id))
    wp_send_json_error('Access denied.');
    $notes = get_post_meta($post_id, '_flp_notes', true) ?: [];
    wp_send_json_success(array_reverse($notes));
    }

    /* ── FILE UPLOAD ────────────────────────────────────── */
    static function lhp_upload_file()
    {
    self::verify();
    $post_id = intval($_POST['record_id'] ?? 0);
    if (!$post_id || !LH_Auth::verify_record_access($post_id))
    wp_send_json_error('Access denied.');

    if (empty($_FILES['file']))
    wp_send_json_error('No file received.');

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $allowed_mime = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    $file_info = wp_check_filetype_and_ext($_FILES['file']['tmp_name'], $_FILES['file']['name'], $allowed_mime);
    if (!$file_info['type'])
    wp_send_json_error('File type not allowed. Allowed: JPG, PNG, PDF, DOC, DOCX.');

    if ($_FILES['file']['size'] > 10 * 1024 * 1024)
    wp_send_json_error('File too large. Maximum size is 10MB.');

    // Skip thumbnail generation — these are document attachments, not gallery images
    add_filter('intermediate_image_sizes_advanced', '__return_empty_array', 99);
    $attachment_id = media_handle_upload('file', $post_id);
    remove_filter('intermediate_image_sizes_advanced', '__return_empty_array', 99);
    if (is_wp_error($attachment_id))
    wp_send_json_error($attachment_id->get_error_message());

    $docs = get_post_meta($post_id, '_flp_docs', true) ?: [];
    $docs[] = [
    'id' => $attachment_id,
    'name' => sanitize_text_field($_FILES['file']['name']),
    'url' => wp_get_attachment_url($attachment_id),
    'type' => $_FILES['file']['type'],
    'size' => size_format($_FILES['file']['size']),
    'uploaded' => wp_date('M j, Y'),
    'uploader' => wp_get_current_user()->display_name,
    '_section' => sanitize_key($_POST['section'] ?? 'documents'),
    ];
    update_post_meta($post_id, '_flp_docs', $docs);
    self::log_activity('file_uploaded', "File uploaded to record #{$post_id}.", $post_id);
    wp_send_json_success(['docs' => $docs, 'attachment_id' => $attachment_id]);
    }

    /* ── FILE DELETE ────────────────────────────────────── */
    static function lhp_delete_file()
    {
    self::verify();
    $post_id = intval($_POST['record_id'] ?? 0);
    $att_id = intval($_POST['attachment_id'] ?? 0);
    if (!$post_id || !LH_Auth::verify_record_access($post_id))
    wp_send_json_error('Access denied.');

    $docs = get_post_meta($post_id, '_flp_docs', true) ?: [];
    $docs = array_filter($docs, function ($d) use ($att_id) {
    return (int) $d['id'] !== $att_id;
    });
    $docs = array_values($docs);
    update_post_meta($post_id, '_flp_docs', $docs);
    wp_delete_attachment($att_id, true);
    wp_send_json_success($docs);
    }

    /* ── ACTIVITY LOG HELPERS ───────────────────────────── */
    static function log_activity($type, $message, $record_id = 0, $target_user = 0)
    {
    $log = get_option('_flp_activity_log', []);
    $log[] = [
    'type' => $type,
    'message' => $message,
    'record_id' => $record_id,
    'target_user' => $target_user,
    'actor_id' => get_current_user_id(),
    'actor_name' => is_user_logged_in() ? wp_get_current_user()->display_name : 'System',
    'timestamp' => time(),
    'date' => wp_date('M j, Y g:i a'),
    ];
    // Keep last 500 entries
    if (count($log) > 500)
    $log = array_slice($log, -500);
    update_option('_flp_activity_log', $log);
    }

    static function get_activity_log($limit = 50)
    {
    $log = get_option('_flp_activity_log', []);
    return array_slice(array_reverse($log), 0, $limit);
    }

    static function role_label($role)
    {
    $map = [
    'lhp_super_admin' => 'Super Admin',
    'lighthouse_law_firm' => 'Law Firm',
    'lighthouse_planner' => 'Estate Planner',
    'lighthouse_parent' => 'Parent / Family',
    'administrator' => 'Administrator',
    ];
    return $map[$role] ?? ucfirst($role);
    }
    /* ══════════════════════════════════════════════════════
     CLIENT MANAGEMENT
    ══════════════════════════════════════════════════════ */

    static function lhp_get_clients()
    {
    self::verify();
    $role = LH_Auth::current_role();
    $uid = get_current_user_id();
    if (!in_array($role, ['lighthouse_planner', 'lhp_super_admin', 'lighthouse_law_firm', 'administrator']))
    wp_send_json_error('Access denied.');

    $args = [
    'post_type' => 'lh_client',
    'posts_per_page' => -1,
    'orderby' => 'date',
    'order' => 'DESC',
    ];
    if ($role === 'lighthouse_planner')
    $args['meta_query'] = [['key' => '_flp_planner_id', 'value' => $uid]];

    $clients = get_posts($args);
    $data = [];
    foreach ($clients as $c) {
    $records = get_posts([
      'post_type' => 'lh_record',
      'posts_per_page' => -1,
      'fields' => 'ids',
      'meta_query' => [['key' => '_flp_client_id', 'value' => $c->ID]],
    ]);
    $owner_id = (int) get_post_meta($c->ID, '_flp_owner_id', true);
    $owner = $owner_id ? get_userdata($owner_id) : null;
    $data[] = [
      'id' => $c->ID,
      'name' => self::clean_title($c->ID),
      'email' => get_post_meta($c->ID, '_flp_email', true),
      'phone' => get_post_meta($c->ID, '_flp_phone', true),
      'relationship' => get_post_meta($c->ID, '_flp_relationship', true),
      'notes' => get_post_meta($c->ID, '_flp_notes', true),
      'record_count' => count($records),
      'record_ids' => $records,
      'owner_id' => $owner_id,
      'owner_email' => $owner ? $owner->user_email : '',
      'planner_id' => (int) get_post_meta($c->ID, '_flp_planner_id', true),
      'created' => get_the_date('M j, Y', $c->ID),
    ];
    }
    wp_send_json_success($data);
    }

    static function lhp_save_client()
    {
    self::verify();
    $role = LH_Auth::current_role();
    $uid = get_current_user_id();
    $client_id = intval($_POST['client_id'] ?? 0);
    if (!in_array($role, ['lighthouse_planner', 'lhp_super_admin', 'administrator']))
    wp_send_json_error('Access denied.');

    $name = sanitize_text_field($_POST['name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    if (!$name)
    wp_send_json_error('Full name is required.');
    if ($email && !is_email($email))
    wp_send_json_error('Invalid email address.');
    if ($phone && !self::validate_phone($phone)) {
    wp_send_json_error('Phone number must be at least 10 digits.');
    }

    $rel = sanitize_text_field($_POST['relationship'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');

    if ($client_id) {
    // Update
    wp_update_post(['ID' => $client_id, 'post_title' => $name]);
    } else {
    // Create
    $client_id = wp_insert_post([
      'post_type' => 'lh_client',
      'post_title' => $name,
      'post_status' => 'publish',
      'post_author' => $uid,
    ]);
    if (is_wp_error($client_id))
      wp_send_json_error('Failed to create client.');
    update_post_meta($client_id, '_flp_planner_id', $uid);
    }

    update_post_meta($client_id, '_flp_email', $email);
    update_post_meta($client_id, '_flp_phone', $phone);
    update_post_meta($client_id, '_flp_relationship', $rel);
    update_post_meta($client_id, '_flp_notes', $notes);

    self::log_activity('client_saved', "Client '{$name}' saved.", $client_id);
    wp_send_json_success(['client_id' => $client_id]);
    }

    static function lhp_delete_client()
    {
    self::verify();
    $cid = intval($_POST['client_id'] ?? 0);
    $role = LH_Auth::current_role();
    if (!in_array($role, ['lighthouse_planner', 'lhp_super_admin', 'administrator']))
    wp_send_json_error('Access denied.');
    // Delete all records for this client too
    $recs = get_posts(['post_type' => 'lh_record', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_query' => [['key' => '_flp_client_id', 'value' => $cid]]]);
    foreach ($recs as $rid)
    wp_delete_post($rid, true);
    wp_delete_post($cid, true);
    wp_send_json_success('Client deleted.');
    }

    static function lhp_get_client_records()
    {
    self::verify();
    $cid = intval($_POST['client_id'] ?? 0);
    $role = LH_Auth::current_role();
    $uid = get_current_user_id();
    if (!$cid)
    wp_send_json_error('Invalid client.');
    // Verify planner owns this client
    if ($role === 'lighthouse_planner') {
    $planner = (int) get_post_meta($cid, '_flp_planner_id', true);
    if ($planner !== $uid)
      wp_send_json_error('Access denied.');
    }
    $records = get_posts([
    'post_type' => 'lh_record',
    'posts_per_page' => -1,
    'orderby' => 'date',
    'order' => 'DESC',
    'meta_query' => [['key' => '_flp_client_id', 'value' => $cid]],
    ]);
    $data = [];
    foreach ($records as $r) {
    $subject = get_post_meta($r->ID, '_flp_subject', true);
    $data[] = [
      'id' => $r->ID,
      'title' => self::clean_title($r->ID),
      'subject_name' => is_array($subject) ? ($subject['full_name'] ?? '') : '',
      'subject_dob' => is_array($subject) ? ($subject['dob'] ?? '') : '',
      'status' => get_post_meta($r->ID, '_flp_status', true) ?: 'draft',
      'completion' => (int) get_post_meta($r->ID, '_flp_completion', true),
      'created' => get_the_date('M j, Y', $r->ID),
    ];
    }
    wp_send_json_success($data);
    }

    /* ══════════════════════════════════════════════════════
     MULTIPLE RECORDS — parent creates additional records
    ══════════════════════════════════════════════════════ */

    static function lhp_get_my_records()
    {
    self::verify();
    $uid = get_current_user_id();
    $role = LH_Auth::current_role();
    // Parents get records where they are owner
    // Planners get records they manage (already have lhp_get_records)
    if (!in_array($role, ['lighthouse_parent', 'lighthouse_planner', 'administrator', 'lhp_super_admin']))
    wp_send_json_error('Access denied.');

    if ($role === 'lighthouse_parent') {
    $meta_query = [
      'relation' => 'OR',
      ['key' => '_flp_owner_id',  'value' => $uid],
      ['key' => '_flp_owner2_id', 'value' => $uid],
    ];
    } else {
    $meta_key   = $role === 'lighthouse_planner' ? '_flp_planner_id' : '_flp_owner_id';
    $meta_query = [['key' => $meta_key, 'value' => $uid]];
    }
    $records = get_posts([
    'post_type' => 'lh_record',
    'posts_per_page' => -1,
    'orderby' => 'date',
    'order' => 'DESC',
    'meta_query' => $meta_query,
    ]);
    $data = [];
    foreach ($records as $r) {
    $subject = get_post_meta($r->ID, '_flp_subject', true) ?: [];
    $data[] = [
      'id' => $r->ID,
      'title' => self::clean_title($r->ID),
      'subject_name' => is_array($subject) ? ($subject['full_name'] ?? '') : '',
      'subject_dob' => is_array($subject) ? ($subject['dob'] ?? '') : '',
      'status' => get_post_meta($r->ID, '_flp_status', true) ?: 'draft',
      'completion' => (int) get_post_meta($r->ID, '_flp_completion', true),
      'created' => get_the_date('M j, Y', $r->ID),
    ];
    }
    wp_send_json_success($data);
    }

    /* ══════════════════════════════════════════════════════
     DELEGATED ACCESS MANAGEMENT
    ══════════════════════════════════════════════════════ */

    static function lhp_get_delegated_users()
    {
    self::verify();
    $post_id = intval($_POST['record_id'] ?? 0);
    if (!$post_id || !LH_Auth::verify_record_access($post_id))
    wp_send_json_error('Access denied.');

    $delegated = get_post_meta($post_id, '_flp_delegated_users', true) ?: [];
    // Hydrate with user info
    foreach ($delegated as &$d) {
    $u = isset($d['user_id']) ? get_userdata((int) $d['user_id']) : null;
    $d['user_name'] = $u ? $u->display_name : ($d['name'] ?? '');
    $d['user_email'] = $u ? $u->user_email : ($d['email'] ?? '');
    $d['has_account'] = (bool) $u;
    }
    wp_send_json_success($delegated);
    }

    static function lhp_save_delegated_user()
    {
    self::verify();
    $post_id = intval($_POST['record_id'] ?? 0);
    if (!$post_id || !LH_Auth::verify_record_access($post_id))
    wp_send_json_error('Access denied.');

    $entry_id = sanitize_key($_POST['entry_id'] ?? '');
    $name = sanitize_text_field($_POST['name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $rel = sanitize_text_field($_POST['relationship'] ?? '');
    $cond = sanitize_key($_POST['condition_type'] ?? 'immediate');
    $cdate = sanitize_text_field($_POST['condition_date'] ?? '');
    $cevent = sanitize_text_field($_POST['condition_event'] ?? '');

    if (!$name || !is_email($email))
    wp_send_json_error('Name and valid email required.');

    // Find or create user account for delegated user
    $existing = get_user_by('email', $email);
    $user_id = 0;
    $temp_pass = '';
    if ($existing) {
    $user_id = $existing->ID;
    // Add delegated role without overriding existing role
    if (!in_array('lighthouse_delegated', (array) $existing->roles)) {
      $existing->add_role('lighthouse_delegated');
    }
    } else {
    // Create delegated user account
    $temp_pass = wp_generate_password(10, false);
    $user_id = wp_insert_user([
      'user_login' => $email,
      'user_email' => $email,
      'user_pass' => $temp_pass,
      'display_name' => $name,
      'role' => 'lighthouse_delegated',
    ]);
    if (is_wp_error($user_id))
      $user_id = 0;
    }

    $status = $cond === 'immediate' ? 'active' : 'pending';
    // If condition is a past date, activate
    if ($cond === 'date' && $cdate && strtotime($cdate) <= time())
    $status = 'active';

    $entry = [
    'id' => $entry_id ?: uniqid('del_'),
    'user_id' => $user_id,
    'name' => $name,
    'email' => $email,
    'relationship' => $rel,
    'condition_type' => $cond,
    'condition_date' => $cdate,
    'condition_event' => $cevent,
    'status' => $status,
    'added_date' => wp_date('M j, Y'),
    'added_by' => wp_get_current_user()->display_name,
    ];

    $delegated = get_post_meta($post_id, '_flp_delegated_users', true) ?: [];

    if ($entry_id) {
    // Update existing
    foreach ($delegated as &$d) {
      if (($d['id'] ?? '') === $entry_id) {
        $d = $entry;
        break;
      }
    }
    } else {
    $delegated[] = $entry;
    }
    update_post_meta($post_id, '_flp_delegated_users', $delegated);

    // Track this record on the delegated user
    if ($user_id) {
    $their_records = get_user_meta($user_id, '_flp_delegated_record_ids', true) ?: [];
    if (!in_array($post_id, $their_records)) {
      $their_records[] = $post_id;
      update_user_meta($user_id, '_flp_delegated_record_ids', $their_records);
    }
    }

    // Send invite email
    if ($temp_pass && $user_id) {
    $login_url = home_url('/login');
    $owner = wp_get_current_user();
    $cond_text = $cond === 'immediate' ? 'You have immediate access.'
      : ($cond === 'date' ? "Your access will activate on: {$cdate}"
        : "Your access will be activated manually by the owner.");
    wp_mail(
      $email,
      "You've been granted Family Lighthouse access",
      "Hello {$name},\n\n{$owner->display_name} has shared their Family Lighthouse record with you.\n\n{$cond_text}\n\nLogin: {$login_url}\nEmail: {$email}\nTemp Password: {$temp_pass}\n\n— Family Lighthouse"
    );
    }

    self::log_activity('delegated_added', "Delegated user {$name} added to record #{$post_id}.", $post_id, $user_id);
    wp_send_json_success(['entry' => $entry, 'temp_pass' => $temp_pass ?: '(existing account)']);
    }

    static function lhp_remove_delegated_user()
    {
    self::verify();
    $post_id = intval($_POST['record_id'] ?? 0);
    $entry_id = sanitize_key($_POST['entry_id'] ?? '');
    if (!$post_id || !LH_Auth::verify_record_access($post_id))
    wp_send_json_error('Access denied.');

    $delegated = get_post_meta($post_id, '_flp_delegated_users', true) ?: [];
    $removed = null;
    $delegated = array_filter($delegated, function ($d) use ($entry_id, &$removed) {
    if (($d['id'] ?? '') === $entry_id) {
      $removed = $d;
      return false;
    }
    return true;
    });
    $delegated = array_values($delegated);
    update_post_meta($post_id, '_flp_delegated_users', $delegated);

    // Remove from user's record list
    if ($removed && !empty($removed['user_id'])) {
    $uid = (int) $removed['user_id'];
    $their = get_user_meta($uid, '_flp_delegated_record_ids', true) ?: [];
    $their = array_values(array_filter($their, function ($rid) use ($post_id) {
      return (int) $rid !== $post_id;
    }));
    update_user_meta($uid, '_flp_delegated_record_ids', $their);
    }
    self::log_activity('delegated_removed', "Delegated user removed from record #{$post_id}.", $post_id);
    wp_send_json_success('Access revoked.');
    }

    static function lhp_activate_delegated_access()
    {
    self::verify();
    $post_id  = intval($_POST['record_id'] ?? 0);
    $entry_id = sanitize_key($_POST['entry_id'] ?? '');
    $doc_id   = intval($_POST['doc_id'] ?? 0);
    if (!$post_id || !LH_Auth::verify_record_access($post_id))
    wp_send_json_error('Access denied.');

    $delegated = get_post_meta($post_id, '_flp_delegated_users', true) ?: [];
    foreach ($delegated as &$d) {
    if (($d['id'] ?? '') === $entry_id) {
      $d['status'] = 'active';
      $d['activated_at'] = current_time('mysql');
      $d['activated_by'] = get_current_user_id();
      if ($doc_id) {
        $d['death_doc_id']   = $doc_id;
        $d['death_doc_name'] = basename(get_attached_file($doc_id) ?: '');
      }
      break;
    }
    }
    update_post_meta($post_id, '_flp_delegated_users', $delegated);
    self::log_activity('access_activated', "Delegated access activated on record #{$post_id}." . ($doc_id ? " Verification doc #{$doc_id} attached." : ''), $post_id);
    wp_send_json_success('Access activated.');
    }

    static function lhp_activate_access_person()
    {
    self::verify();
    $post_id  = intval($_POST['record_id'] ?? 0);
    $entry_id = sanitize_key($_POST['entry_id'] ?? '');
    $doc_id   = intval($_POST['doc_id'] ?? 0);
    if (!$post_id || !LH_Auth::verify_record_access($post_id))
    wp_send_json_error('Access denied.');

    $people = get_post_meta($post_id, '_flp_access_people', true) ?: [];
    $found  = false;
    foreach ($people as &$p) {
    if (($p['id'] ?? '') !== $entry_id) continue;
    $p['status']       = 'active';
    $p['activated_at'] = current_time('mysql');
    $p['activated_by'] = get_current_user_id();
    if ($doc_id) {
      $p['death_doc_id']   = $doc_id;
      $p['death_doc_name'] = basename(get_attached_file($doc_id) ?: '');
    }
    $found = true;
    break;
    }
    if (!$found) wp_send_json_error('Entry not found.');

    update_post_meta($post_id, '_flp_access_people', $people);
    self::log_activity('access_person_activated', "Access person activated on record #{$post_id}." . ($doc_id ? " Verification doc #{$doc_id} attached." : ''), $post_id);
    wp_send_json_success('Access activated.');
    }

    static function lhp_upload_death_doc()
    {
    self::verify();
    $post_id  = intval($_POST['record_id'] ?? 0);
    $entry_id = sanitize_key($_POST['entry_id'] ?? '');
    if (!$post_id || !LH_Auth::verify_record_access($post_id))
    wp_send_json_error('Access denied.');

    if (empty($_FILES['file']['tmp_name']))
    wp_send_json_error('No file received.');

    $allowed = ['application/pdf','image/jpeg','image/jpg','image/png'];
    $mime = mime_content_type($_FILES['file']['tmp_name']);
    if (!in_array($mime, $allowed))
    wp_send_json_error('Invalid file type. PDF, JPG, PNG only.');

    if ($_FILES['file']['size'] > 10 * 1024 * 1024)
    wp_send_json_error('File exceeds 10 MB limit.');

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attachment_id = media_handle_upload('file', $post_id);
    if (is_wp_error($attachment_id))
    wp_send_json_error($attachment_id->get_error_message());

    update_post_meta($attachment_id, '_lhp_death_verification', 1);
    update_post_meta($attachment_id, '_lhp_death_doc_entry', $entry_id);
    if (!empty($_POST['notes']))
    update_post_meta($attachment_id, '_lhp_death_doc_notes', sanitize_textarea_field($_POST['notes']));

    wp_send_json_success([
    'attachment_id' => $attachment_id,
    'file_name'     => basename(get_attached_file($attachment_id)),
    'url'           => wp_get_attachment_url($attachment_id),
    ]);
    }

    static function lhp_get_my_delegated_records()
    {
    self::verify();
    $uid = get_current_user_id();
    $user = get_userdata($uid);
    // Allow users who have the delegated role OR have delegated record IDs (e.g. lighthouse_parent who was also delegated)
    if (!in_array('lighthouse_delegated', (array) $user->roles) && !get_user_meta($uid, '_flp_delegated_record_ids', true))
    wp_send_json_error('Access denied.');
    $rec_ids = get_user_meta($uid, '_flp_delegated_record_ids', true) ?: [];
    $data = [];
    foreach ($rec_ids as $rid) {
    $has_access = LH_Auth::verify_record_access($rid, $uid);

    // Find this user's entry — check both delegated_users and access_people
    $my_entry = null;
    $my_status = 'pending';

    $delegated = get_post_meta($rid, '_flp_delegated_users', true) ?: [];
    foreach ($delegated as $d) {
      if ((int) ($d['user_id'] ?? 0) === $uid) { $my_entry = $d; $my_status = $d['status'] ?? 'pending'; break; }
    }
    if (!$my_entry) {
      $access_people = get_post_meta($rid, '_flp_access_people', true) ?: [];
      foreach ($access_people as $ap) {
        if ((int) ($ap['user_id'] ?? 0) === $uid) { $my_entry = $ap; $my_status = $ap['status'] ?? 'pending'; break; }
      }
    }

    // Include record if user has access OR has a pending entry (so they know it exists)
    if (!$has_access && $my_status !== 'pending') continue;
    if (!$has_access && !$my_entry) continue;

    $subject = get_post_meta($rid, '_flp_subject', true) ?: [];
    $owner_id = (int) get_post_meta($rid, '_flp_owner_id', true);
    $planner_id = (int) get_post_meta($rid, '_flp_planner_id', true);
    $owner = $owner_id ? get_userdata($owner_id) : null;
    $planner = $planner_id ? get_userdata($planner_id) : null;
    $data[] = [
      'id' => $rid,
      'title' => self::clean_title($rid),
      'subject_name' => is_array($subject) ? ($subject['full_name'] ?? '') : '',
      'subject_dob' => is_array($subject) ? ($subject['dob'] ?? '') : '',
      'status' => get_post_meta($rid, '_flp_status', true) ?: 'draft',
      'completion' => (int) get_post_meta($rid, '_flp_completion', true),
      'owner_name' => $owner ? $owner->display_name : '—',
      'planner_name' => $planner ? $planner->display_name : '—',
      'my_condition' => $my_entry ? ($my_entry['condition_type'] ?? ($my_entry['privilege'] ?? 'view_after_death')) : 'view_after_death',
      'my_status' => $my_status,
      'relationship' => $my_entry ? ($my_entry['relationship'] ?? '') : '',
      'access_since' => $my_entry ? ($my_entry['added_date'] ?? '') : '',
    ];
    }
    wp_send_json_success($data);
    }

    static function lhp_create_parent_record()
    {
    self::verify();
    $role = LH_Auth::current_role();
    $uid = get_current_user_id();
    if (!in_array($role, ['lighthouse_parent', 'lighthouse_planner', 'administrator', 'lhp_super_admin']))
    wp_send_json_error('Access denied.');

    // One Lighthouse per user — transient lock prevents concurrent duplicate creation
    if (in_array($role, ['lighthouse_parent'])) {
    $lock_key = 'flp_create_lock_' . $uid;
    if (get_transient($lock_key))
      wp_send_json_error('A record is already being created. Please wait a moment and try again.');
    set_transient($lock_key, 1, 10);
    $existing = get_user_meta($uid, '_flp_record_id', true);
    if ($existing) {
      $check = get_post($existing);
      if ($check && $check->post_status === 'publish') {
        delete_transient($lock_key);
        wp_send_json_error('You already have a Family Lighthouse record. Only one per user is allowed.');
      }
    }
    $existing_records = get_posts(['post_type' => 'lh_record', 'posts_per_page' => 1, 'meta_query' => [['key' => '_flp_owner_id', 'value' => $uid]]]);
    if (!empty($existing_records)) {
      delete_transient($lock_key);
      wp_send_json_error('You already have a Family Lighthouse record. Only one per user is allowed.');
    }
    }

    $title = sanitize_text_field($_POST['title'] ?? '');
    if (!$title)
    wp_send_json_error('Title is required.');

    $record_id = wp_insert_post([
    'post_type' => 'lh_record',
    'post_title' => $title,
    'post_status' => 'publish',
    'post_author' => $uid,
    ]);
    if (is_wp_error($record_id))
    wp_send_json_error('Failed to create record.');

    update_post_meta($record_id, '_flp_owner_id', $role === 'lighthouse_parent' ? $uid : 0);
    update_post_meta($record_id, '_flp_planner_id', $role === 'lighthouse_planner' ? $uid : 0);
    update_post_meta($record_id, '_flp_status', 'draft');
    update_post_meta($record_id, '_flp_completion', 0);

    // Update user's primary record if first one
    if ($role === 'lighthouse_parent') {
    $existing_rid = get_user_meta($uid, '_flp_record_id', true);
    if (!$existing_rid)
      update_user_meta($uid, '_flp_record_id', $record_id);
    delete_transient('flp_create_lock_' . $uid);
    }
    self::log_activity('record_created', "Record '{$title}' created.", $record_id);
    wp_send_json_success(['record_id' => $record_id, 'title' => $title]);
    }

    /* ── GET INVITE LINK (magic-link flow) ───────────── */
    static function lhp_get_invite_link()
    {
    self::verify();
    if (!in_array(LH_Auth::current_role(), ['lighthouse_planner', 'lhp_super_admin', 'administrator']))
    wp_send_json_error('Access denied.');

    $post_id = intval($_POST['record_id'] ?? 0);
    if (!$post_id || !LH_Auth::verify_record_access($post_id))
    wp_send_json_error('Access denied.');

    // Pick the first delegated user entry that has a real WP account
    $delegated = get_post_meta($post_id, '_flp_delegated_users', true) ?: [];
    $entry     = null;
    foreach ($delegated as $d) {
    if (!empty($d['user_id']) && !empty($d['email'])) {
      $entry = $d;
      break;
    }
    }

    if (!$entry)
    wp_send_json_error('No delegated access user on this record. Add one in Step 6 (Delegated Access) first.');

    $user_id  = (int) $entry['user_id'];
    $del_user = get_user_by('id', $user_id);
    if (!$del_user)
    wp_send_json_error('Delegated user account not found.');

    $inviter      = wp_get_current_user();
    $inviter_name = $inviter->display_name ?: $inviter->user_email;

    // Stamp invite metadata
    update_user_meta($user_id, '_flp_invited_by_name', $inviter_name);
    update_user_meta($user_id, '_flp_needs_password', 1);

    // One-time magic token (7 days)
    $token = wp_generate_password(40, false);
    update_user_meta($user_id, '_flp_magic_token', $token);
    update_user_meta($user_id, '_flp_magic_expiry', time() + 7 * DAY_IN_SECONDS);

    wp_send_json_success([
    'url' => add_query_arg(['magic' => $token], home_url('/delegated-dashboard')),
    ]);
    }

    /* ── SET INVITE PASSWORD ─────────────────────────── */
    static function lhp_set_invite_password()
    {
    self::verify();
    if (!in_array(LH_Auth::current_role(), ['lighthouse_parent', 'lighthouse_delegated']))
    wp_send_json_error('Access denied.');

    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';
    if (strlen($pass) < 8)
    wp_send_json_error('Password must be at least 8 characters.');
    if ($pass !== $pass2)
    wp_send_json_error('Passwords do not match.');

    $uid = get_current_user_id();
    wp_set_password($pass, $uid);
    delete_user_meta($uid, '_flp_needs_password');

    // wp_set_password logs out the user — re-issue cookie
    wp_set_auth_cookie($uid, true);

    wp_send_json_success('Password set.');
    }

    /* ══════════════════════════════════════════════════════
     ESTATE PLANNER PROFILE
    ══════════════════════════════════════════════════════ */

    static function lhp_save_ep_profile()
    {
    self::verify();
    $uid = get_current_user_id();
    if (LH_Auth::current_role() !== 'lighthouse_planner')
    wp_send_json_error('Access denied.');
    wp_update_user(['ID' => $uid, 'display_name' => sanitize_text_field($_POST['name'] ?? '')]);
    update_user_meta($uid, '_flp_firm_name', sanitize_text_field($_POST['firm_name'] ?? ''));
    update_user_meta($uid, '_flp_phone', sanitize_text_field($_POST['phone'] ?? ''));
    update_user_meta($uid, '_flp_bio', sanitize_textarea_field($_POST['bio'] ?? ''));
    $logo_id = intval($_POST['logo_id'] ?? 0);
    if ($logo_id) update_user_meta($uid, '_flp_logo_id', $logo_id);
    wp_send_json_success('Profile saved.');
    }

    static function lhp_get_ep_profile()
    {
    self::verify();
    $uid = get_current_user_id();
    if (LH_Auth::current_role() !== 'lighthouse_planner')
    wp_send_json_error('Access denied.');
    $user = get_userdata($uid);
    wp_send_json_success([
    'name' => $user->display_name,
    'email' => $user->user_email,
    'firm_name' => get_user_meta($uid, '_flp_firm_name', true),
    'phone' => get_user_meta($uid, '_flp_phone', true),
    'bio' => get_user_meta($uid, '_flp_bio', true),
    'logo_id'  => (int) get_user_meta($uid, '_flp_logo_id', true),
    'logo_url' => ($lid = (int) get_user_meta($uid, '_flp_logo_id', true)) ? wp_get_attachment_url($lid) : '',
    ]);
    }

    /* ══════════════════════════════════════════════════════
     CLIENT LINK GENERATION
    ══════════════════════════════════════════════════════ */

    static function lhp_generate_client_link()
    {
    self::verify();
    $uid = get_current_user_id();
    if (LH_Auth::current_role() !== 'lighthouse_planner')
    wp_send_json_error('Access denied.');

    $email = sanitize_email($_POST['email'] ?? '');
    $name = sanitize_text_field($_POST['name'] ?? '');
    if (!is_email($email))
    wp_send_json_error('Invalid email address.');

    $links = get_user_meta($uid, '_flp_share_links', true) ?: [];
    // Block duplicate unclaimed links for the same email
    foreach ($links as $l) {
    if (isset($l['client_email']) && strtolower($l['client_email']) === strtolower($email) && empty($l['client_id']))
      wp_send_json_error('An unused invitation already exists for this email address. Delete the existing link first.');
    }
    $token = strtolower(wp_generate_password(16, false));
    $ep_slug = self::ep_slug_for($uid);
    $link = home_url('/' . $ep_slug . '/invite/' . $token);

    $entry = [
    'token' => $token,
    'client_email' => $email,
    'client_name' => $name,
    'created' => time(),
    'client_id' => 0,
    'link' => $link,
    ];
    $links[] = $entry;
    update_user_meta($uid, '_flp_share_links', $links);

    // Send email
    $ep_name = get_userdata($uid)->display_name;
    $site_name = get_bloginfo('name');
    $subject = "You're invited to create your Family Lighthouse";
    $message = "Hello {$name},\r\n\r\nYour estate planner, {$ep_name}, has invited you to create your personal Family Lighthouse.\r\n\r\n";
    $message .= "A Family Lighthouse is a secure place to store end-of-life wishes, documents, and instructions.\r\n\r\n";
    $message .= "Click here to get started:\r\n{$link}\r\n\r\n";
    $message .= "— The {$site_name} Team";
    wp_mail($email, $subject, $message);

    wp_send_json_success([
    'token' => $token,
    'link' => $link,
    ]);
    }

    static function lhp_resend_invite()
    {
    self::verify();
    $uid = get_current_user_id();
    if (LH_Auth::current_role() !== 'lighthouse_planner')
    wp_send_json_error('Access denied.');

    $token = sanitize_key($_POST['token'] ?? '');
    if (!$token)
    wp_send_json_error('Invalid token.');

    $links = get_user_meta($uid, '_flp_share_links', true) ?: [];
    $entry = null;
    foreach ($links as $l) {
    if (($l['token'] ?? '') === $token) { $entry = $l; break; }
    }
    if (!$entry)
    wp_send_json_error('Invite not found.');
    if (!empty($entry['client_id']))
    wp_send_json_error('Client has already registered — resend is not needed.');

    $ep_slug  = self::ep_slug_for($uid);
    $ep_name  = get_userdata($uid)->display_name;
    $site_name = get_bloginfo('name');
    $link = (strpos($entry['link'] ?? '', '/invite/') !== false)
        ? $entry['link']
        : home_url('/' . $ep_slug . '/invite/' . $token);

    $email    = $entry['client_email'];
    $name     = $entry['client_name'] ?? '';
    $subject  = "Reminder: You're invited to create your Family Lighthouse";
    $message  = "Hello {$name},\r\n\r\nThis is a reminder that your estate planner, {$ep_name}, has invited you to create your personal Family Lighthouse.\r\n\r\n";
    $message .= "A Family Lighthouse is a secure place to store end-of-life wishes, documents, and instructions.\r\n\r\n";
    $message .= "Click here to get started:\r\n{$link}\r\n\r\n";
    $message .= "— The {$site_name} Team";

    if (!wp_mail($email, $subject, $message))
    wp_send_json_error('Failed to send email. Please try again.');

    wp_send_json_success('Invite resent.');
    }

    static function lhp_get_ep_links()
    {
    self::verify();
    $uid = get_current_user_id();
    if (LH_Auth::current_role() !== 'lighthouse_planner')
    wp_send_json_error('Access denied.');

    $ep_slug = self::ep_slug_for($uid);
    $links = get_user_meta($uid, '_flp_share_links', true) ?: [];
    $data = [];
    foreach ($links as $l) {
    $cid = (int) ($l['client_id'] ?? 0);
    $has_record = false;
    if ($cid) {
      $rec_id = (int) get_user_meta($cid, '_flp_record_id', true);
      $has_record = $rec_id > 0;
    }
    // Use new URL format; fall back to legacy for links generated before upgrade
    $stored = $l['link'] ?? '';
    $invite_url = (strpos($stored, '/invite/') !== false)
        ? $stored
        : home_url('/' . $ep_slug . '/invite/' . $l['token']);
    $data[] = [
      'token' => $l['token'],
      'client_email' => $l['client_email'],
      'client_name' => $l['client_name'] ?? '',
      'client_id' => $cid,
      'has_record' => $has_record,
      'short_link' => $invite_url,
      'link' => $invite_url,
      'created' => wp_date('M j, Y', $l['created']),
    ];
    }
    wp_send_json_success(['links' => array_reverse($data)]);
    }

    static function lhp_upload_logo()
    {
    self::verify();
    $uid = get_current_user_id();
    if (LH_Auth::current_role() !== 'lighthouse_planner')
    wp_send_json_error('Access denied.');

    if (empty($_FILES['file']))
    wp_send_json_error('No file received.');

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    // SVG excluded — contains embeddable JS (stored XSS risk). Use PNG/JPG/WEBP instead.
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    $file_info = wp_check_filetype_and_ext(
        $_FILES['file']['tmp_name'],
        $_FILES['file']['name'],
        ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp']
    );
    if (!$file_info['type'] || !in_array($file_info['type'], $allowed_types))
    wp_send_json_error('File type not allowed. Allowed: JPG, PNG, WEBP.');

    if ($_FILES['file']['size'] > 2 * 1024 * 1024)
    wp_send_json_error('File too large. Maximum size is 2MB.');

    $attachment_id = media_handle_upload('file', 0);
    if (is_wp_error($attachment_id))
    wp_send_json_error($attachment_id->get_error_message());

    update_user_meta($uid, '_flp_logo_id', $attachment_id);

    wp_send_json_success([
    'attachment_id' => $attachment_id,
    'url' => wp_get_attachment_url($attachment_id),
    ]);
    }

    /* ══════════════════════════════════════════════════════
     DELETE SHARE LINK
    ══════════════════════════════════════════════════════ */

    static function lhp_delete_share_link()
    {
    self::verify();
    $uid = get_current_user_id();
    if (LH_Auth::current_role() !== 'lighthouse_planner')
    wp_send_json_error('Access denied.');

    $token = sanitize_text_field($_POST['token'] ?? '');
    if (!$token) wp_send_json_error('Invalid token.');

    $links = get_user_meta($uid, '_flp_share_links', true) ?: [];

    // Find the link being deleted so we can clean up the associated client account.
    $deleted_link = null;
    foreach ($links as $l) {
      if (strtolower($l['token'] ?? '') === strtolower($token)) {
        $deleted_link = $l;
        break;
      }
    }

    $links = array_filter($links, function ($l) use ($token) {
    return strtolower($l['token'] ?? '') !== strtolower($token);
    });
    $links = array_values($links);
    update_user_meta($uid, '_flp_share_links', $links);

    // Delete the associated WP user account if one was created for this invite.
    if ($deleted_link) {
      $client_id = intval($deleted_link['client_id'] ?? 0);
      if ($client_id > 0) {
        // Delete any lh_record posts owned by this client.
        $client_records = get_posts([
          'post_type'      => 'lh_record',
          'posts_per_page' => -1,
          'meta_query'     => [['key' => '_flp_owner_id', 'value' => $client_id]],
        ]);
        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ($client_records as $cr) {
          wp_delete_post($cr->ID, true);
        }
        wp_delete_user($client_id);
      }
    }

    wp_send_json_success('Link deleted.');
    }

    /* ══════════════════════════════════════════════════════
     OWNER 2 MANAGEMENT
    ══════════════════════════════════════════════════════ */

    static function lhp_save_owner2()
    {
    self::verify();
    $record_id = intval($_POST['record_id'] ?? 0);
    $uid = get_current_user_id();
    $role = LH_Auth::current_role();
    if (!in_array($role, ['lighthouse_parent', 'administrator', 'lhp_super_admin']))
    wp_send_json_error('Access denied.');
    if (!LH_Auth::verify_record_access($record_id))
    wp_send_json_error('Access denied.');

    $email = sanitize_email($_POST['email'] ?? '');
    $name  = sanitize_text_field($_POST['name'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');

    if (!$email || !is_email($email)) wp_send_json_error('Invalid email address.');

    $owner2_id = (int) get_post_meta($record_id, '_flp_owner2_id', true);
    $existing  = get_user_by('email', $email);

    if ($existing) {
    // Block if target user already owns their own lighthouse
    $owns_record = get_posts(['post_type' => 'lh_record', 'posts_per_page' => 1,
        'meta_query' => [['key' => '_flp_owner_id', 'value' => $existing->ID]]]);
    if (!empty($owns_record))
        wp_send_json_error('This person already has their own Lighthouse account and cannot be added as a co-owner.');
    // Block if target is already co-owner on a different record
    $other_co = get_posts(['post_type' => 'lh_record', 'posts_per_page' => 1,
        'meta_query' => [['key' => '_flp_owner2_id', 'value' => $existing->ID]]]);
    if (!empty($other_co) && (int)$other_co[0]->ID !== $record_id)
        wp_send_json_error('This person is already a co-owner on another Lighthouse.');
    $owner2_id = $existing->ID;
    // Ensure correct role
    if (!in_array('lighthouse_parent', $existing->roles)) {
      $existing->set_role('lighthouse_parent');
    }
    } else {
    $pass = wp_generate_password(10, false);
    $owner2_id = wp_insert_user([
      'user_login'   => $email,
      'user_email'   => $email,
      'user_pass'    => $pass,
      'display_name' => $name,
      'role'         => 'lighthouse_parent',
    ]);
    if (is_wp_error($owner2_id))
      wp_send_json_error($owner2_id->get_error_message());

    if ($phone) update_user_meta($owner2_id, '_flp_phone', $phone);

    // Send welcome email
    $login_url = home_url('/login');
    $owner     = get_userdata($uid);
    wp_mail($email, 'You\'ve been added as a Family Lighthouse co-owner',
      "Hello {$name},\r\n\r\n{$owner->display_name} has added you as a co-owner of their Family Lighthouse record.\r\n\r\n" .
      "Login: {$login_url}\r\nEmail: {$email}\r\nPassword: {$pass}\r\n\r\n" .
      "Please log in and change your password.\r\n\r\n— Family Lighthouse");
    }

    // Update display name in case it changed
    if ($owner2_id && $name) {
    wp_update_user(['ID' => $owner2_id, 'display_name' => $name]);
    }
    if ($owner2_id && $phone) {
    update_user_meta($owner2_id, '_flp_phone', $phone);
    }

    update_post_meta($record_id, '_flp_owner2_id', $owner2_id);

    // Also update the owner2 JSON for backward compat with display
    $o2_user = get_userdata($owner2_id);
    update_post_meta($record_id, '_flp_owner2', [
    'full_name' => $o2_user ? $o2_user->display_name : $name,
    'email'     => $email,
    'phone'     => $phone,
    ]);

    wp_send_json_success([
    'owner2_id'  => $owner2_id,
    'full_name'  => $o2_user ? $o2_user->display_name : $name,
    'email'      => $email,
    'phone'      => $phone,
    ]);
    }

    static function lhp_get_owner2()
    {
    self::verify();
    $record_id = intval($_POST['record_id'] ?? 0);
    if (!LH_Auth::verify_record_access($record_id))
    wp_send_json_error('Access denied.');

    $owner2_id = (int) get_post_meta($record_id, '_flp_owner2_id', true);
    if (!$owner2_id) {
    // Check legacy meta
    $legacy = get_post_meta($record_id, '_flp_owner2', true);
    if ($legacy && !empty($legacy['full_name'])) {
      wp_send_json_success([
        'owner2_id' => 0,
        'full_name' => $legacy['full_name'],
        'email'     => $legacy['email'] ?? '',
        'phone'     => $legacy['phone'] ?? '',
        'legacy'    => true,
      ]);
    }
    wp_send_json_success(null);
    }

    $user = get_userdata($owner2_id);
    wp_send_json_success([
    'owner2_id' => $owner2_id,
    'full_name' => $user ? $user->display_name : '',
    'email'     => $user ? $user->user_email : '',
    'phone'     => get_user_meta($owner2_id, '_flp_phone', true),
    ]);
    }

    static function lhp_set_password()
    {
    self::verify();
    $uid = get_current_user_id();
    $pass = $_POST['password'] ?? '';
    if (strlen($pass) < 8) wp_send_json_error('Password must be at least 8 characters.');
    wp_set_password($pass, $uid);
    // Re-authenticate after wp_set_password logs out
    wp_set_current_user($uid);
    wp_set_auth_cookie($uid, true);
    wp_send_json_success('Password updated.');
    }

    private static function get_owner2_name($post_id)
    {
    $owner2_id = (int) get_post_meta($post_id, '_flp_owner2_id', true);
    if ($owner2_id) {
    $u = get_userdata($owner2_id);
    if ($u) return $u->display_name;
    }
    $legacy = get_post_meta($post_id, '_flp_owner2', true);
    return $legacy['full_name'] ?? '';
    }

    private static function send_delegate_emails($people, $post_id)
    {
    $subject_meta = get_post_meta($post_id, '_flp_subject', true);
    $subject_name = is_array($subject_meta) ? ($subject_meta['full_name'] ?? '') : '';
    $owner_id   = (int) get_post_meta($post_id, '_flp_owner_id', true);
    $owner_user = $owner_id ? get_userdata($owner_id) : false;
    $owner_name = $owner_user ? $owner_user->display_name : 'The Family Lighthouse owner';
    $login_url  = home_url('/lhp-login');

    $priv_labels = [
    'unlock_all'     => 'May unlock Family Lighthouse for all beneficiaries',
    'view_anytime'   => 'May view interior at any time',
    'view_after_death' => 'May view interior after the owner passes away',
    ];

    $updated_people = [];
    foreach ($people as $p) {
    $email     = trim($p['email'] ?? '');
    $name      = $p['full_name'] ?? '';
    $privilege = $p['privilege'] ?? 'view_after_death';

    if (!is_email($email)) { $updated_people[] = $p; continue; }

    $priv_text  = $priv_labels[$privilege] ?? $privilege;
    $is_pending = ($privilege === 'view_after_death');

    // Find or create WP account
    $existing  = get_user_by('email', $email);
    $temp_pass = '';
    if ($existing) {
      $uid = $existing->ID;
      if (!in_array('lighthouse_delegated', (array) $existing->roles))
        $existing->add_role('lighthouse_delegated');
    } else {
      $temp_pass = wp_generate_password(10, false);
      $uid = wp_insert_user([
        'user_login'   => sanitize_user($email, true),
        'user_email'   => $email,
        'user_pass'    => $temp_pass,
        'display_name' => $name,
        'role'         => 'lighthouse_delegated',
      ]);
      if (is_wp_error($uid)) { $uid = 0; }
    }

    // Track record on this user
    if ($uid) {
      $rec_ids = get_user_meta($uid, '_flp_delegated_record_ids', true) ?: [];
      if (!in_array($post_id, $rec_ids)) {
        $rec_ids[] = $post_id;
        update_user_meta($uid, '_flp_delegated_record_ids', $rec_ids);
      }
    }

    // Store user_id and status back on the access_people entry
    $p['user_id'] = $uid;
    $p['status']  = $is_pending ? 'pending' : 'active';
    $updated_people[] = $p;

    // Email
    $mail_subject = "You've been granted Family Lighthouse access";
    $message  = "Hello {$name},\r\n\r\n";
    $message .= "{$owner_name} has added you as an access contact";
    if ($subject_name) $message .= " for their Family Lighthouse record ({$subject_name})";
    $message .= ".\r\n\r\nYour privilege: {$priv_text}\r\n\r\n";

    if ($temp_pass) {
      $message .= "Your login credentials:\r\n";
      $message .= "  Email:    {$email}\r\n";
      $message .= "  Password: {$temp_pass}\r\n\r\n";
      $message .= "Login here: {$login_url}\r\n";
      $message .= "Please change your password after first login.\r\n\r\n";
    } else {
      // Existing user — generate a one-time reset key so they can get in without knowing their password
      $reset_key = get_password_reset_key(get_userdata($uid));
      if (!is_wp_error($reset_key)) {
        $reset_url = network_site_url("wp-login.php?action=rp&key={$reset_key}&login=" . rawurlencode(get_userdata($uid)->user_login), 'login');
        $message .= "Login here: {$login_url}\r\n";
        $message .= "  Email: {$email}\r\n\r\n";
        $message .= "Forgot your password or first time here? Reset it:\r\n{$reset_url}\r\n\r\n";
      } else {
        $message .= "Login here: {$login_url}\r\n";
        $message .= "  Email: {$email}\r\n\r\n";
      }
    }

    if ($is_pending) {
      $message .= "IMPORTANT: Your access is currently pending.\r\n";
      $message .= "You can log in but your Lighthouse view will unlock only after\r\n";
      $message .= "the owner's estate planner or family confirms activation.\r\n";
      $message .= "You do NOT need to do anything — you will be notified when access is live.\r\n\r\n";
    }
    $message .= "— Family Lighthouse";

    wp_mail($email, $mail_subject, $message);
    }

    // Persist updated user_id + status back to meta
    if (!empty($updated_people))
    update_post_meta($post_id, '_flp_access_people', $updated_people);
    }

    /* ── OWNER SELF / CO-OWNER DELETE PROFILE ──────────── */
    static function lhp_owner_delete_profile()
    {
        self::verify();
        $uid = get_current_user_id();
        $role = LH_Auth::current_role();
        if (!in_array($role, ['lighthouse_parent', 'administrator', 'lhp_super_admin']))
            wp_send_json_error('Access denied.');

        $target_id = intval($_POST['user_id'] ?? 0);
        if (!$target_id) $target_id = $uid;

        $is_self = ($target_id === $uid);

        // If deleting someone else, verify current user is owner1 of a record with this owner2
        if (!$is_self) {
            $records = new WP_Query([
                'post_type' => 'lh_record',
                'meta_query' => [
                    ['key' => '_flp_owner_id', 'value' => $uid],
                    ['key' => '_flp_owner2_id', 'value' => $target_id],
                ],
                'fields' => 'ids',
            ]);
            if (empty($records->posts))
                wp_send_json_error('Access denied. You are not the primary owner of a record with this co-owner.');
        }

        $target_user = get_userdata($target_id);
        if (!$target_user)
            wp_send_json_error('User not found.');

        $target_name = $target_user->display_name;

        require_once ABSPATH . 'wp-admin/includes/user.php';

        // Remove owner2 from all records first
        $o2_records = new WP_Query([
            'post_type' => 'lh_record',
            'meta_key' => '_flp_owner2_id',
            'meta_value' => $target_id,
            'fields' => 'ids',
        ]);
        foreach ($o2_records->posts as $rid) {
            delete_post_meta($rid, '_flp_owner2_id');
            delete_post_meta($rid, '_flp_owner2');
        }

        // Reassign records to admin if the target owns any records
        $admin_id = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
        $reassign = !empty($admin_id) ? (int) $admin_id[0] : 0;
        wp_delete_user($target_id, $reassign);

        self::log_activity('user_deleted', "Profile deleted: {$target_name} (ID {$target_id})" . ($is_self ? ' [self]' : ' by ' . wp_get_current_user()->display_name));

        if ($is_self) {
            wp_logout();
            wp_send_json_success(['redirect' => home_url('/login?deleted=1')]);
        } else {
            wp_send_json_success('Co-owner deleted.');
        }
    }

    private static function validate_phone($phone)
    {
    return preg_match('/^\d{10}$/', $phone) === 1;
    }

    // Get (or generate) the URL-safe slug for an estate planner.
    // Derived from firm name → display name. Stored as _lhp_ep_token.
    private static function ep_slug_for($uid)
    {
    $firm = get_user_meta($uid, '_flp_firm_name', true);
    $name = get_userdata($uid)->display_name ?? '';
    $base = trim($firm ?: $name);
    $slug = $base ? trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($base)), '-') : '';
    if (empty($slug)) $slug = 'planner-' . $uid;

    // Ensure unique across other planners
    $original = $slug; $i = 2;
    while (true) {
        $conflict = get_users([
            'meta_key'   => '_flp_ep_token',
            'meta_value' => $slug,
            'number'     => 1,
            'fields'     => ['ID'],
            'exclude'    => [(int) $uid],
        ]);
        if (!$conflict) break;
        $slug = $original . '-' . $i++;
    }
    update_user_meta($uid, '_flp_ep_token', $slug);
    return $slug;
    }

    /* ══════════════════════════════════════════════════════
       ESTATE PLANNER PERMANENT WELCOME LINK
    ══════════════════════════════════════════════════════ */

    static function lhp_get_ep_token()
    {
    self::verify();
    $uid = get_current_user_id();
    if (LH_Auth::current_role() !== 'lighthouse_planner')
        wp_send_json_error('Access denied.');

    $slug = self::ep_slug_for($uid);
    wp_send_json_success([
        'token' => $slug,
        'url'   => home_url('/' . $slug . '/welcome/'),
        'count' => (int) get_user_meta($uid, '_flp_ep_reg_count', true),
    ]);
    }

    static function lhp_register_via_ep_link()
    {
    self::verify();
    $token = sanitize_text_field($_POST['ep_token'] ?? '');
    $name  = sanitize_text_field($_POST['full_name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (!$token) wp_send_json_error('Invalid invitation link.');

    $users = get_users([
        'meta_key'   => '_flp_ep_token',
        'meta_value' => $token,
        'number'     => 1,
        'fields'     => ['ID'],
    ]);
    if (!$users) wp_send_json_error('Invalid or expired invitation link.');
    $ep_id = (int) $users[0]->ID;

    if (!$name)            wp_send_json_error('Full name is required.');
    if (!is_email($email)) wp_send_json_error('Invalid email address.');
    if (email_exists($email)) wp_send_json_error('An account with this email already exists. Please sign in.');
    if (strlen($pass) < 8) wp_send_json_error('Password must be at least 8 characters.');

    // Generate 6-digit OTP and store pending registration in transient (15 min)
    // Key prefixed with flow type to prevent collision with invite flow
    $otp = sprintf('%06d', random_int(0, 999999));
    $key = 'flp_otp_ep_' . md5(strtolower($email));
    set_transient($key, [
        'otp'        => $otp,
        'flow'       => 'ep_welcome',
        'ep_id'      => $ep_id,
        'name'       => $name,
        'email'      => $email,
        'phone'      => $phone,
        'password'   => $pass,
        'attempts'   => 0,
        'expires_at' => time() + 15 * MINUTE_IN_SECONDS,
    ], 15 * MINUTE_IN_SECONDS);

    // Email OTP to client
    $site = get_bloginfo('name');
    wp_mail(
        $email,
        "Your {$site} verification code",
        "Hello {$name},\r\n\r\n" .
        "Your email verification code is:\r\n\r\n" .
        "    {$otp}\r\n\r\n" .
        "This code expires in 15 minutes.\r\n\r\n" .
        "If you did not request this, please ignore this email.\r\n\r\n" .
        "— The {$site} Team"
    );

    // Mask email for safe display in UI (e.g. jo***@example.com)
    $at     = strpos($email, '@');
    $local  = substr($email, 0, $at);
    $domain = substr($email, $at);
    $masked = substr($local, 0, min(2, strlen($local))) . str_repeat('*', max(0, strlen($local) - 2)) . $domain;

    wp_send_json_success(['step' => 'verify_otp', 'masked_email' => $masked]);
    }

    static function lhp_verify_ep_otp()
    {
    self::verify();
    $email = sanitize_email($_POST['email'] ?? '');
    $otp   = sanitize_text_field($_POST['otp'] ?? '');
    $flow  = sanitize_key($_POST['flow'] ?? 'ep_welcome');

    if (!is_email($email) || !$otp) wp_send_json_error('Invalid request.');

    // Use flow-specific key to avoid collision between invite and welcome flows
    $key  = ($flow === 'invite' ? 'flp_otp_invite_' : 'flp_otp_ep_') . md5(strtolower($email));
    $data = get_transient($key);

    if (!$data) wp_send_json_error('Verification code has expired. Please go back and register again.');

    $attempts = (int) ($data['attempts'] ?? 0);
    if ($attempts >= 5) {
        delete_transient($key);
        wp_send_json_error('Too many incorrect attempts. Please go back and register again.');
    }

    if ($data['otp'] !== $otp) {
        $data['attempts'] = $attempts + 1;
        // Preserve original expiry — don't reset TTL on wrong attempt
        $remaining = max(60, (int) ($data['expires_at'] ?? time() + 60) - time());
        set_transient($key, $data, $remaining);
        $left = 5 - ($attempts + 1);
        wp_send_json_error('Incorrect code. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' remaining.');
    }

    // Valid — check email before deleting transient so user can recover if already exists
    if (email_exists($email)) wp_send_json_error('An account with this email already exists. Please sign in.');

    delete_transient($key);

    $flow  = $data['flow'] ?? 'ep_welcome';
    $ep_id = (int) $data['ep_id'];
    $name  = $data['name'];
    $phone = $data['phone'];
    $pass  = $data['password'];

    $user_id = wp_insert_user([
        'user_login'   => $email,
        'user_email'   => $email,
        'user_pass'    => $pass,
        'display_name' => $name,
        'role'         => 'lighthouse_parent',
    ]);
    if (is_wp_error($user_id)) wp_send_json_error($user_id->get_error_message());

    if ($phone) update_user_meta($user_id, '_flp_phone', $phone);

    $record_id = wp_insert_post([
        'post_type'   => 'lh_record',
        'post_title'  => $name . "'s Lighthouse",
        'post_status' => 'publish',
        'post_author' => $user_id,
    ]);
    if (is_wp_error($record_id)) {
        // Account created but record failed — clean up and report
        wp_delete_user($user_id);
        wp_send_json_error('Failed to create your Family Lighthouse. Please try again.');
    }
    update_post_meta($record_id, '_flp_owner_id',   $user_id);
    update_post_meta($record_id, '_flp_planner_id', $ep_id);
    update_post_meta($record_id, '_flp_status',     'draft');
    update_post_meta($record_id, '_flp_completion', 0);
    update_user_meta($user_id, '_flp_record_id', $record_id);

    // Invite flow: mark link as claimed (single-use enforcement)
    if ($flow === 'invite') {
        $invite_token = strtolower($data['invite_token'] ?? '');
        $links = get_user_meta($ep_id, '_flp_share_links', true) ?: [];
        foreach ($links as &$l) {
            if (strtolower($l['token'] ?? '') === $invite_token) {
                $l['client_id'] = $user_id;
                break;
            }
        }
        update_user_meta($ep_id, '_flp_share_links', $links);
    }

    // Billing tracking — both flows
    global $wpdb;
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->usermeta} SET meta_value = meta_value + 1 WHERE user_id = %d AND meta_key = '_flp_ep_reg_count'",
        $ep_id
    ));
    if (!$updated) {
        add_user_meta($ep_id, '_flp_ep_reg_count', 1, true);
    }
    $regs   = get_user_meta($ep_id, '_flp_ep_registrations', true) ?: [];
    $regs[] = ['user_id' => $user_id, 'name' => $name, 'email' => $email, 'date' => time(), 'flow' => $flow];
    update_user_meta($ep_id, '_flp_ep_registrations', $regs);

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    wp_send_json_success(['redirect' => home_url('/dashboard')]);
    }
}