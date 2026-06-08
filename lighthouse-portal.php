<?php
/**
 * Plugin Name:  Lighthouse Portal
 * Plugin URI:   https://lighthouse.reckoningitsol.com
 * Description:  Role-based frontend portal for Estate Planners and Parent/Family Members.
 * Version:      1.9.3
 * Author:       Reckoning IT Solutions
 * Text Domain:  lighthouse-portal
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'LHP_VERSION',  '1.9.3' );
define( 'LHP_DIR',      plugin_dir_path( __FILE__ ) );
define( 'LHP_URL',      plugin_dir_url( __FILE__ ) );
define( 'LHP_FILE',     __FILE__ );

require_once LHP_DIR . 'includes/class-lh-setup.php';
require_once LHP_DIR . 'includes/class-lh-roles.php';
require_once LHP_DIR . 'includes/class-lh-cpt.php';
require_once LHP_DIR . 'includes/class-lh-auth.php';
require_once LHP_DIR . 'includes/class-lh-ajax.php';
require_once LHP_DIR . 'includes/class-lh-shortcodes.php';
require_once LHP_DIR . 'includes/class-lh-demo.php';
require_once LHP_DIR . 'includes/class-flp-migration.php';

// Extend nonce lifetime to 48 hours so long browser sessions don't expire mid-use
add_filter( 'nonce_life', function() { return 48 * HOUR_IN_SECONDS; } );

register_activation_hook( LHP_FILE,   [ 'LH_Setup', 'activate'   ] );
register_deactivation_hook( LHP_FILE, [ 'LH_Setup', 'deactivate' ] );

add_action( 'init', [ 'LH_Roles',      'register' ] );
add_action( 'init', [ 'LH_CPT',        'register' ] );
add_action( 'init', [ 'LH_Auth',       'init'     ] );
add_action( 'init', [ 'LH_Shortcodes', 'init'     ] );
add_action( 'init', [ 'LH_Demo',       'init'     ] );
add_action( 'init', 'lhp_rewrite_rules' );
add_action( 'init', 'lhp_check_rewrite_rules' );
add_action( 'init', 'lhp_migrate_pages' );
add_action( 'init', [ 'FLP_Migration', 'init' ], 20 );
LH_Ajax::init();

function lhp_check_rewrite_rules() {
    $stored = get_option( 'flp_version' );
    if ( $stored !== LHP_VERSION ) {
        lhp_rewrite_rules();
        flush_rewrite_rules();
        update_option( 'flp_version', LHP_VERSION );
    }
}

function lhp_migrate_pages() {
    $done = get_option( 'flp_page_migrated' );
    if ( ! $done ) {
        LH_Setup::migrate_attorney_to_planner();
        // Fix register page if it has wrong shortcode
        $form_page = get_page_by_path( 'register' );
        if ( $form_page && strpos( $form_page->post_content, 'lighthouse_intake_form' ) !== false ) {
            wp_update_post([
                'ID'           => $form_page->ID,
                'post_content' => '[lhp_register]',
            ]);
        }
        update_option( 'flp_page_migrated', 1 );
    }
    // Always ensure correct page template on portal pages
    LH_Setup::fix_page_templates();
}

// Block non-admins from wp-login.php; redirect reset-password links to portal
add_action( 'login_init', function () {
    $action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : 'login';

    // Always redirect WP password-reset links to our custom portal page
    if ( $action === 'rp' && isset( $_GET['key'] ) ) {
        wp_safe_redirect( home_url(
            '/login?action=reset&key='   . rawurlencode( $_GET['key'] )
            . '&login=' . rawurlencode( $_GET['login'] ?? '' )
        ) );
        exit;
    }

    // Logout works for everyone
    if ( $action === 'logout' ) return;

    // Logged-in non-admins should never see wp-login.php
    if ( is_user_logged_in() ) {
        $user        = wp_get_current_user();
        $admin_roles = [ 'administrator', 'lhp_super_admin' ];
        if ( empty( array_intersect( $admin_roles, (array) $user->roles ) ) ) {
            wp_safe_redirect( lhp_page_url( 'login' ) );
            exit;
        }
    }
} );

// Redirect old /attorney-dashboard (v1.3) to /planner-dashboard
add_action( 'template_redirect', 'lhp_redirect_old_attorney_url' );
function lhp_redirect_old_attorney_url() {
    if ( is_404() && strpos( $_SERVER['REQUEST_URI'] ?? '', '/attorney-dashboard' ) !== false ) {
        wp_safe_redirect( home_url( '/planner-dashboard' ), 301 );
        exit;
    }
}

/* ── Rewrite rules ── */
function lhp_rewrite_rules() {
    add_rewrite_tag( '%lhp_invite_token%', '([a-zA-Z0-9]+)' );
    // New format: /{planner-name}/invite/{token}
    add_rewrite_rule( '^([a-zA-Z0-9-]+)/invite/([a-zA-Z0-9]+)/?$', 'index.php?lhp_invite_token=$matches[2]', 'top' );
    // Legacy format (backward compat for already-sent links)
    add_rewrite_rule( '^lhp-invite/([a-zA-Z0-9]+)/?$', 'index.php?lhp_invite_token=$matches[1]', 'top' );
    // Planner permanent welcome link: /{planner-name}/welcome/
    add_rewrite_tag( '%lhp_ep_token%', '([a-zA-Z0-9-]+)' );
    add_rewrite_rule( '^([a-zA-Z0-9-]+)/welcome/?$', 'index.php?lhp_ep_token=$matches[1]', 'top' );
}
add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'lhp_invite_token';
    $vars[] = 'lhp_ep_token';
    return $vars;
} );

/**
 * Invite route helper.
 */
function lhp_is_invite_route() {
    return (bool) get_query_var( 'lhp_invite_token' );
}

/**
 * EP welcome route helper.
 */
function lhp_is_ep_route() {
    return (bool) get_query_var( 'lhp_ep_token' );
}

/**
 * Elementor fallback config for invite route.
 * Some themes enqueue Elementor frontend assets on this route, but
 * elementorFrontendConfig may be missing because this is rewrite-based.
 */
add_action( 'wp_head', 'lhp_invite_elementor_frontend_fallback', 0 );
function lhp_invite_elementor_frontend_fallback() {
    if ( is_admin() || ! lhp_is_invite_route() ) return;
    $assets_url = defined('ELEMENTOR_URL') ? trailingslashit(ELEMENTOR_URL) . 'assets/' : '';
    ?>
<script id="lhp-elementor-frontend-fallback">
window.elementorFrontendConfig = window.elementorFrontendConfig || {
  environmentMode: { edit: false, wpPreview: false, isScriptDebug: false },
  i18n: {},
  is_rtl: false,
  breakpoints: { xs: 0, sm: 480, md: 768, lg: 1025, xl: 1440, xxl: 1600 },
  responsive: { breakpoints: { mobile: { label: 'Mobile', value: 767 }, tablet: { label: 'Tablet', value: 1024 } } },
  version: '',
  is_static: false,
  experimentalFeatures: {},
  urls: { assets: '<?php echo esc_js( $assets_url ); ?>' },
  settings: { page: [], editorPreferences: [] },
  kit: {},
  post: { id: 0, title: '', excerpt: '', featuredImage: false },
  user: { roles: [] }
};
</script>
    <?php
}

/* ── Template redirect for /lhp-invite/{token} ── */
add_action( 'template_redirect', 'lhp_handle_invite_page' );
function lhp_handle_invite_page() {
    $token = strtolower( get_query_var( 'lhp_invite_token' ) );
    if ( ! $token ) return;

    // Find which estate planner owns this token
    $ep_id = 0;
    $client_email = '';
    $is_claimed   = false;
    $users = get_users( [ 'meta_key' => '_flp_share_links', 'number' => -1, 'fields' => [ 'ID' ] ] );
    foreach ( $users as $u ) {
        $links = get_user_meta( $u->ID, '_flp_share_links', true ) ?: [];
        foreach ( $links as $l ) {
            if ( strtolower( $l['token'] ?? '' ) === $token ) {
                $ep_id        = $u->ID;
                $client_email = $l['client_email'] ?? '';
                $is_claimed   = ! empty( $l['client_id'] ) && (int) $l['client_id'] > 0;
                break 2;
            }
        }
    }
    if ( ! $ep_id ) {
        wp_die( 'Invalid or expired invitation link.', 'Invalid Link', [ 'response' => 410 ] );
    }
    if ( $is_claimed ) {
        wp_die( 'This invitation has already been used. Please sign in to your Family Lighthouse account, or contact your estate planner for a new link.', 'Invitation Already Used', [ 'response' => 410, 'back_link' => false ] );
    }

    // If already logged in as any portal role, show a message then redirect
    if ( is_user_logged_in() ) {
        $role = LH_Auth::current_role();
        $dashboards = [
            'lighthouse_planner'  => '/planner-dashboard',
            'lighthouse_parent'   => '/dashboard',
            'lighthouse_delegated' => '/delegated-dashboard',
            'lhp_super_admin'     => '/fml-admin',
            'lighthouse_law_firm' => '/fml-admin',
        ];
        if ( isset( $dashboards[ $role ] ) ) {
            wp_die(
                'You are already signed in. <a href="' . esc_url( home_url( $dashboards[ $role ] ) ) . '">Go to your dashboard</a>.',
                'Already Signed In',
                [ 'response' => 200, 'back_link' => false ]
            );
            exit;
        }
    }

    $ep      = get_userdata( $ep_id );
    $ep_name = $ep ? $ep->display_name : '';
    $ep_firm = get_user_meta( $ep_id, '_flp_firm_name', true );
    $logo_id = (int) get_user_meta( $ep_id, '_flp_logo_id', true );
    $logo    = $logo_id ? wp_get_attachment_url( $logo_id ) : '';
    $bio     = get_user_meta( $ep_id, '_flp_bio', true );

    add_filter( 'body_class', function( $classes ) {
        $classes[] = 'lhp-portal-page';
        $classes[] = 'lhp-invite-page';
        $classes[] = 'elementor-default';
        $classes[] = 'elementor-template-full-width';
        $classes[] = 'elementor-kit-8';
        $classes[] = 'page';
        $classes[] = 'page-id-0';
        return $classes;
    } );
    add_filter( 'pre_get_document_title', function() {
        return 'You\'re Invited — Family Lighthouse';
    }, 999 );
    // Hide OceanWP theme page header on invite route
    add_filter( 'ocean_display_page_header', '__return_false' );
    add_action( 'wp_head', function() {
        echo '<style id="lhp-invite-hide-chrome">' . "\n";
        echo 'body.lhp-invite-page header, body.lhp-invite-page #masthead, body.lhp-invite-page #site-header,' . "\n";
        echo 'body.lhp-invite-page .site-header, body.lhp-invite-page .elementor-location-header,' . "\n";
        echo 'body.lhp-invite-page [data-elementor-type="header"], body.lhp-invite-page .main-navigation,' . "\n";
        echo 'body.lhp-invite-page .top-bar, body.lhp-invite-page #top-bar { display: none !important; }' . "\n";
        echo 'body.lhp-invite-page footer, body.lhp-invite-page #footer, body.lhp-invite-page #colophon,' . "\n";
        echo 'body.lhp-invite-page .site-footer, body.lhp-invite-page .elementor-location-footer,' . "\n";
        echo 'body.lhp-invite-page [data-elementor-type="footer"] { display: none !important; }' . "\n";
        echo 'body.lhp-invite-page, body.lhp-invite-page html,' . "\n";
        echo 'body.lhp-invite-page #outer-wrap, body.lhp-invite-page #wrap,' . "\n";
        echo 'body.lhp-invite-page .site-content, body.lhp-invite-page #content' . "\n";
        echo '{ padding-top: 0 !important; margin-top: 0 !important; background: #f1f5f9 !important; }' . "\n";
        echo 'body.lhp-invite-page .lhp-brand-logo img { max-width:100%; max-height:48px; width:auto; height:auto; object-fit:contain; border-radius:0; }' . "\n";
        echo 'body.lhp-invite-page .lhp-auth-form-header h2 { font-size:42px; }' . "\n";
        echo 'body.lhp-invite-page .lhp-auth-form-header p { font-size:18px; font-weight:700; }' . "\n";
        echo 'body.lhp-invite-page .lhp-brand-features li i.bi { display:inline-flex; align-items:center; justify-content:center; width:20px; font-size:18px; color:#a5b4fc; flex-shrink:0; }' . "\n";
        echo '</style>' . "\n";
    }, 9999 );
    get_header();
    ?>
<div class="lhp-root">
<div class="lhp-auth-center-wrap">
<div class="lhp-auth-split">

  <div class="lhp-auth-brand-panel">
    <div class="lhp-auth-brand-inner">
      <div class="lhp-brand-logo">
        <?php if ($logo): ?>
          <div style="max-width:160px;height:64px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.12);border-radius:10px;padding:8px 14px">
            <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($ep_firm ?: $ep_name); ?>" style="max-width:100%;max-height:48px;width:auto;height:auto;object-fit:contain;display:block">
          </div>
        <?php else: ?>
          <i class="bi bi-building" style="font-size:1.75rem;margin-bottom:10px"></i>
        <?php endif; ?>
        <span><?php echo esc_html( $ep_firm ?: $ep_name ?: 'Family Lighthouse' ); ?></span>
      </div>
      <h1 class="lhp-brand-headline">You're Invited!</h1>
      <p class="lhp-brand-sub"><?php echo esc_html($ep_name); ?><?php echo $ep_firm ? ' &middot; ' . esc_html($ep_firm) : ''; ?> has invited you to create your personal Family Lighthouse.</p>
      <?php if ($bio): ?>
        <div class="mt-3" style="background:rgba(255,255,255,.08);border-radius:10px;padding:.85rem 1rem;font-size:14px;line-height:1.6;border-left:3px solid rgba(255,255,255,.2);font-style:normal;color:#fff;font-weight:500"><?php echo esc_html($bio); ?></div>
      <?php endif; ?>
      <ul class="lhp-brand-features mt-4">
        <li><i class="bi bi-shield-check-fill" aria-hidden="true"></i> Bank-grade secure storage</li>
        <li><i class="bi bi-file-earmark-heart-fill" aria-hidden="true"></i> Store letters, wishes &amp; keepsakes</li>
        <li><i class="bi bi-lock-fill" aria-hidden="true"></i> You control who sees what</li>
      </ul>
    </div>
  </div>

  <div class="lhp-auth-form-panel">
    <div class="lhp-auth-form-inner">

      <div class="lhp-auth-form-header">
        <h2>Create Your Family Lighthouse</h2>
        <p>Set up your secure family portal</p>
      </div>

      <div class="alert alert-danger d-none align-items-center gap-2" id="invite-error" role="alert">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
        <span class="lhp-alert-msg"></span>
      </div>
      <div class="alert alert-success d-none" id="invite-success"></div>

      <!-- OTP verification pane (hidden until registration succeeds) -->
      <div id="invite-otp-pane" style="display:none">
        <div class="lhp-auth-form-header">
          <h2 style="display:flex;align-items:center;gap:.5rem"><i class="bi bi-envelope-check" style="color:var(--lhp-primary,#4f46e5)"></i> Verify Your Email</h2>
          <p id="invite-otp-sub">Enter the 6-digit code we sent to your email</p>
        </div>
        <div class="alert alert-danger d-none align-items-center gap-2" id="invite-otp-error" role="alert">
          <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
          <span class="lhp-alert-msg"></span>
        </div>
        <form id="invite-otp-form" novalidate>
          <input type="hidden" id="invite-pending-email" value="">
          <div class="mb-4 text-center">
            <label class="form-label fw-semibold d-block mb-3" for="invite-otp-code">Verification Code <span class="text-danger">*</span></label>
            <input type="text" class="form-control text-center mx-auto" id="invite-otp-code"
                   placeholder="000000" maxlength="6" inputmode="numeric" pattern="\d{6}"
                   autocomplete="one-time-code"
                   style="font-size:2rem;letter-spacing:.5rem;font-weight:700;max-width:220px">
            <small class="text-muted d-block mt-2"><i class="bi bi-clock me-1"></i>Expires in 15 minutes</small>
          </div>
          <div class="d-grid mt-2">
            <button class="btn lhp-btn-primary-solid btn-lg" id="invite-otp-btn" type="submit">
              <span class="btn-label"><i class="bi bi-check-circle me-2"></i>Verify &amp; Create My Lighthouse</span>
              <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Verifying…</span>
            </button>
          </div>
          <p class="text-center mt-3 mb-0">
            <button type="button" id="invite-otp-back" class="btn btn-link text-muted p-0" style="font-size:13px;text-decoration:none"><i class="bi bi-arrow-left me-1"></i>Back to registration</button>
          </p>
        </form>
      </div>

      <form id="lhp-invite-form" novalidate>
        <input type="hidden" id="invite-token" value="<?php echo esc_attr(strtolower($token)); ?>">

        <div class="mb-3">
          <label class="form-label fw-semibold" for="invite-name">Full Name <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" class="form-control" id="invite-name" placeholder="Your full name" required autocomplete="name">
          </div>
          <div class="invalid-feedback" id="invite-name-err"></div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold" for="invite-email">Email <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" id="invite-email" value="<?php echo esc_attr($client_email); ?>" placeholder="your@email.com" required autocomplete="email">
          </div>
          <div class="invalid-feedback" id="invite-email-err"></div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold" for="invite-phone">Phone</label>
          <div class="input-group" style="flex-wrap:nowrap">
            <span class="input-group-text" style="padding:0">
              <select id="invite-country" class="lhp-country-select">
                <option value="+1">US +1</option>
                <option value="+44">UK +44</option>
                <option value="+91">IN +91</option>
                <option value="+61">AU +61</option>
                <option value="+1-CA">CA +1</option>
                <option value="+353">IE +353</option>
                <option value="+64">NZ +64</option>
              </select>
            </span>
            <input type="tel" class="form-control" id="invite-phone" placeholder="Phone number" autocomplete="tel">
          </div>
          <div class="invalid-feedback" id="invite-phone-err"></div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold" for="invite-pass">Create Password <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control" id="invite-pass" placeholder="At least 8 characters" minlength="8" autocomplete="new-password" required>
            <button class="btn btn-outline-secondary lhp-toggle-pass" type="button" data-target="invite-pass" tabindex="-1" aria-label="Show/hide password"><i class="bi bi-eye" aria-hidden="true"></i></button>
          </div>
          <div class="invalid-feedback" id="invite-pass-err"></div>
          <div class="lhp-pass-strength mt-1" id="invite-pass-strength" style="display:none">
            <div class="lhp-pass-strength-bar"><div class="lhp-pass-strength-fill" id="invite-strength-fill"></div></div>
            <small id="invite-strength-label" class="text-muted" aria-live="polite"></small>
          </div>
        </div>

        <p class="lhp-legal-note text-center mb-3" style="font-size:12px">
          <i class="bi bi-shield-lock text-muted" aria-hidden="true"></i>
          This Lighthouse is not a will and is not a legal document.
        </p>
        <div class="d-grid">
          <button class="btn lhp-btn-primary-solid btn-lg" id="invite-submit-btn" type="submit">
            <span class="btn-label"><i class="bi bi-check-circle me-2"></i>Create My Lighthouse</span>
            <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Creating…</span>
          </button>
        </div>

        <div class="d-flex align-items-center justify-content-center gap-3 mt-4 pt-3" style="border-top:1px solid #e2e8f0">
          <small class="text-muted d-flex align-items-center gap-1"><i class="bi bi-shield-lock-fill" aria-hidden="true"></i> 256-bit Encrypted</small>
          <small class="text-muted d-flex align-items-center gap-1"><i class="bi bi-incognito" aria-hidden="true"></i> Private</small>
          <small class="text-muted d-flex align-items-center gap-1"><i class="bi bi-cloud-check-fill" aria-hidden="true"></i> Always yours</small>
        </div>
      </form>

    </div>
  </div>

</div>
</div>
</div>
<script>
document.querySelectorAll('.lhp-toggle-pass').forEach(function(btn){
  btn.addEventListener('click', function(){
    var inp = document.getElementById(this.getAttribute('data-target'));
    if(!inp) return;
    var show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    this.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
  });
});
(function(){
  var token = document.getElementById('invite-token').value;
  var form = document.getElementById('lhp-invite-form');
  var btn = document.getElementById('invite-submit-btn');
  var loadingEl = btn.querySelector('.btn-loading');
  var labelEl = btn.querySelector('.btn-label');

  var fields = {
    name: { el: document.getElementById('invite-name'), err: document.getElementById('invite-name-err') },
    email: { el: document.getElementById('invite-email'), err: document.getElementById('invite-email-err') },
    phone: { el: document.getElementById('invite-phone'), err: document.getElementById('invite-phone-err') },
    pass: { el: document.getElementById('invite-pass'), err: document.getElementById('invite-pass-err') },
  };

  // Password strength
  var passStrengthEl = document.getElementById('invite-pass-strength');
  var strengthFill = document.getElementById('invite-strength-fill');
  var strengthLabel = document.getElementById('invite-strength-label');
  fields.pass.el.addEventListener('input', function() {
    var v = this.value;
    if (!v) { passStrengthEl.style.display = 'none'; return; }
    passStrengthEl.style.display = 'block';
    var score = 0;
    if (v.length >= 8) score++; if (v.length >= 12) score++;
    if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
    if (/\d/.test(v)) score++;
    if (/[^a-zA-Z0-9]/.test(v)) score++;
    var levels = ['Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
    var colors = ['#ef4444','#f59e0b','#10b981','#059669','#047857'];
    var i = Math.min(score, 4);
    strengthFill.style.width = ((score + 1) * 20) + '%';
    strengthFill.style.background = colors[i];
    strengthLabel.textContent = levels[i];
  });

  function clearErrors() {
    Object.keys(fields).forEach(function(k) {
      fields[k].el.classList.remove('is-invalid');
      fields[k].err.textContent = '';
      fields[k].err.classList.remove('d-block');
    });
    document.getElementById('invite-error').classList.add('d-none');
  }

  function markInvalid(key, msg) {
    fields[key].el.classList.add('is-invalid');
    fields[key].err.textContent = msg;
    fields[key].err.classList.add('d-block');
  }

  function showAlert(msg) {
    var el = document.getElementById('invite-error');
    el.querySelector('.lhp-alert-msg').textContent = msg;
    el.classList.remove('d-none'); el.classList.add('d-flex');
  }

  form.addEventListener('submit', function(e) {
    e.preventDefault(); clearErrors();
    var name = fields.name.el.value.trim();
    var email = fields.email.el.value.trim();
    var phoneRaw = fields.phone.el.value.trim();
    var country = document.getElementById('invite-country').value;
    var phone = (country + ' ' + phoneRaw).trim();
    var pass = fields.pass.el.value;
    var hasErr = false;

    if (!name) { markInvalid('name', 'Full name is required.'); hasErr = true; }
    if (!email) { markInvalid('email', 'Email address is required.'); hasErr = true; }
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) { markInvalid('email', 'Please enter a valid email address.'); hasErr = true; }
    if (phoneRaw && !/^[\d\s\-+()]{7,}$/.test(phoneRaw)) { markInvalid('phone', 'Please enter a valid phone number.'); hasErr = true; }
    if (!pass) { markInvalid('pass', 'Password is required.'); hasErr = true; }
    else if (pass.length < 8) { markInvalid('pass', 'Password must be at least 8 characters.'); hasErr = true; }
    if (hasErr) return;

    btn.classList.add('lhp-btn-saving'); labelEl.classList.add('d-none'); loadingEl.classList.remove('d-none');
    var fd = new FormData();
    fd.append('action', 'lhp_register_via_invite');
    fd.append('nonce', '<?php echo wp_create_nonce('flp_nonce'); ?>');
    fd.append('token', token);
    fd.append('full_name', name);
    fd.append('email', email);
    fd.append('phone', phone);
    fd.append('password', pass);
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method:'POST', body:fd })
      .then(function(r){ return r.json(); })
      .then(function(r){
        btn.classList.remove('lhp-btn-saving'); labelEl.classList.remove('d-none'); loadingEl.classList.add('d-none');
        if (r.success) {
          if (r.data.step === 'verify_otp') {
            var masked = r.data.masked_email || email;
            document.getElementById('invite-otp-sub').textContent = 'Enter the 6-digit code we sent to ' + masked;
            document.getElementById('invite-pending-email').value = email;
            document.getElementById('invite-otp-code').value = '';
            document.getElementById('invite-otp-error').classList.add('d-none');
            form.style.display = 'none';
            document.getElementById('invite-otp-pane').style.display = '';
            setTimeout(function(){ document.getElementById('invite-otp-code').focus(); }, 100);
          } else {
            document.getElementById('invite-success').innerHTML = '<div style="text-align:center;padding:2rem 1rem"><div style="width:80px;height:80px;border-radius:50%;background:#d1fae5;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;animation:pulse 1s ease-in-out"><i class="bi bi-check-circle-fill text-success" style="font-size:2.5rem"></i></div><h4 class="fw-bold mb-2" style="font-size:1.35rem">Welcome!</h4><p class="text-muted mb-0" style="font-size:.9rem">Your Family Lighthouse is ready. Redirecting you now…</p><div class="mt-3"><div class="spinner-border spinner-border-sm text-primary" role="status"></div></div></div><style>@keyframes pulse{0%{transform:scale(0.8);opacity:0}50%{transform:scale(1.05)}100%{transform:scale(1);opacity:1}}</style>';
            document.getElementById('invite-success').classList.remove('d-none');
            form.style.display = 'none';
            setTimeout(function(){ window.location.href = r.data.redirect; }, 1500);
          }
        } else {
          var msg = r.data || 'Registration failed.';
          if (msg.toLowerCase().indexOf('email') !== -1 && msg.toLowerCase().indexOf('exist') !== -1) {
            markInvalid('email', msg);
          }
          showAlert(msg);
        }
      })
      .catch(function(){
        btn.classList.remove('lhp-btn-saving'); labelEl.classList.remove('d-none'); loadingEl.classList.add('d-none');
        showAlert('Network error. Please try again.');
      });
  });

  // OTP form
  document.getElementById('invite-otp-form').addEventListener('submit', function(e){
    e.preventDefault();
    var otpBtn = document.getElementById('invite-otp-btn');
    var otpErrEl = document.getElementById('invite-otp-error');
    otpErrEl.classList.add('d-none');
    var otpVal = document.getElementById('invite-otp-code').value.trim();
    var pendingEmail = document.getElementById('invite-pending-email').value;
    if (!otpVal || otpVal.length !== 6 || !/^\d{6}$/.test(otpVal)) {
      otpErrEl.querySelector('.lhp-alert-msg').textContent = 'Please enter the 6-digit code.';
      otpErrEl.classList.remove('d-none'); otpErrEl.classList.add('d-flex');
      return;
    }
    otpBtn.classList.add('lhp-btn-saving');
    otpBtn.querySelector('.btn-label').classList.add('d-none');
    otpBtn.querySelector('.btn-loading').classList.remove('d-none');
    var fd2 = new FormData();
    fd2.append('action', 'lhp_verify_ep_otp');
    fd2.append('nonce', '<?php echo wp_create_nonce('flp_nonce'); ?>');
    fd2.append('email', pendingEmail);
    fd2.append('otp', otpVal);
    fd2.append('flow', 'invite');
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method:'POST', body:fd2 })
      .then(function(r){ return r.json(); })
      .then(function(r){
        if (r.success) {
          document.getElementById('invite-otp-pane').innerHTML = '<div style="text-align:center;padding:2rem 1rem"><div style="width:80px;height:80px;border-radius:50%;background:#d1fae5;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem"><i class="bi bi-check-circle-fill text-success" style="font-size:2.5rem"></i></div><h4 class="fw-bold mb-2">Email Verified!</h4><p class="text-muted mb-0">Your Family Lighthouse is ready. Redirecting…</p><div class="mt-3"><div class="spinner-border spinner-border-sm text-primary"></div></div></div>';
          setTimeout(function(){ window.location.href = r.data.redirect; }, 1500);
        } else {
          otpBtn.classList.remove('lhp-btn-saving');
          otpBtn.querySelector('.btn-label').classList.remove('d-none');
          otpBtn.querySelector('.btn-loading').classList.add('d-none');
          otpErrEl.querySelector('.lhp-alert-msg').textContent = r.data || 'Verification failed.';
          otpErrEl.classList.remove('d-none'); otpErrEl.classList.add('d-flex');
        }
      })
      .catch(function(){
        otpBtn.classList.remove('lhp-btn-saving');
        otpBtn.querySelector('.btn-label').classList.remove('d-none');
        otpBtn.querySelector('.btn-loading').classList.add('d-none');
        otpErrEl.querySelector('.lhp-alert-msg').textContent = 'Network error. Please try again.';
        otpErrEl.classList.remove('d-none'); otpErrEl.classList.add('d-flex');
      });
  });

  // OTP back button
  document.getElementById('invite-otp-back').addEventListener('click', function(){
    document.getElementById('invite-otp-pane').style.display = 'none';
    document.getElementById('invite-otp-error').classList.add('d-none');
    form.style.display = '';
  });
})();
    </script>
<?php get_footer(); ?>
<?php
    exit;
}

/* ── Elementor fallback for /ep/{token} route ── */
add_action( 'wp_head', 'lhp_ep_elementor_frontend_fallback', 0 );
function lhp_ep_elementor_frontend_fallback() {
    if ( is_admin() || ! lhp_is_ep_route() ) return;
    $assets_url = defined('ELEMENTOR_URL') ? trailingslashit(ELEMENTOR_URL) . 'assets/' : '';
    ?>
<script id="lhp-elementor-ep-fallback">
window.elementorFrontendConfig = window.elementorFrontendConfig || {
  environmentMode: { edit: false, wpPreview: false, isScriptDebug: false },
  i18n: {},
  is_rtl: false,
  breakpoints: { xs: 0, sm: 480, md: 768, lg: 1025, xl: 1440, xxl: 1600 },
  responsive: { breakpoints: { mobile: { label: 'Mobile', value: 767 }, tablet: { label: 'Tablet', value: 1024 } } },
  version: '',
  is_static: false,
  experimentalFeatures: {},
  urls: { assets: '<?php echo esc_js( $assets_url ); ?>' },
  settings: { page: [], editorPreferences: [] },
  kit: {},
  post: { id: 0, title: '', excerpt: '', featuredImage: false },
  user: { roles: [] }
};
</script>
    <?php
}

/* ── Template redirect for /ep/{token} — planner co-branded welcome page ── */
add_action( 'template_redirect', 'lhp_handle_ep_page' );
function lhp_handle_ep_page() {
    $token = strtolower( get_query_var( 'lhp_ep_token' ) );
    if ( ! $token ) return;

    // Find planner with this EP token
    $ep_id = 0;
    $users = get_users( [ 'meta_key' => '_flp_ep_token', 'meta_value' => $token, 'number' => 1, 'fields' => [ 'ID' ] ] );
    if ( $users ) $ep_id = (int) $users[0]->ID;
    if ( ! $ep_id ) {
        wp_die( 'This invitation link is not valid. Please contact your estate planner for a new link.', 'Invalid Link', [ 'response' => 410 ] );
    }

    // Redirect already-logged-in users to their dashboard
    if ( is_user_logged_in() ) {
        $role = LH_Auth::current_role();
        $dashboards = [
            'lighthouse_planner'   => '/planner-dashboard',
            'lighthouse_parent'    => '/dashboard',
            'lighthouse_delegated' => '/delegated-dashboard',
            'lhp_super_admin'      => '/fml-admin',
            'lighthouse_law_firm'  => '/fml-admin',
        ];
        if ( isset( $dashboards[ $role ] ) ) {
            wp_safe_redirect( home_url( $dashboards[ $role ] ) );
            exit;
        }
    }

    $ep         = get_userdata( $ep_id );
    $ep_name    = $ep ? $ep->display_name : '';
    $ep_firm    = get_user_meta( $ep_id, '_flp_firm_name', true );
    $logo_id    = (int) get_user_meta( $ep_id, '_flp_logo_id', true );
    $logo       = $logo_id ? wp_get_attachment_url( $logo_id ) : '';
    $bio        = get_user_meta( $ep_id, '_flp_bio', true );
    $brand_name = $ep_firm ?: $ep_name;

    add_filter( 'body_class', function( $classes ) {
        $classes[] = 'lhp-portal-page';
        $classes[] = 'lhp-ep-welcome-page';
        $classes[] = 'elementor-default';
        $classes[] = 'elementor-template-full-width';
        $classes[] = 'elementor-kit-8';
        $classes[] = 'page';
        $classes[] = 'page-id-0';
        return $classes;
    } );
    add_filter( 'pre_get_document_title', function() use ( $brand_name ) {
        return 'Welcome — ' . esc_html( $brand_name ?: 'Family Lighthouse' );
    }, 999 );
    add_filter( 'ocean_display_page_header', '__return_false' );
    add_action( 'wp_head', function() {
        echo '<style id="lhp-ep-hide-chrome">
body.lhp-ep-welcome-page header,body.lhp-ep-welcome-page #masthead,body.lhp-ep-welcome-page #site-header,
body.lhp-ep-welcome-page .site-header,body.lhp-ep-welcome-page .elementor-location-header,
body.lhp-ep-welcome-page [data-elementor-type="header"],body.lhp-ep-welcome-page .main-navigation,
body.lhp-ep-welcome-page .top-bar,body.lhp-ep-welcome-page #top-bar{display:none!important}
body.lhp-ep-welcome-page footer,body.lhp-ep-welcome-page #footer,body.lhp-ep-welcome-page #colophon,
body.lhp-ep-welcome-page .site-footer,body.lhp-ep-welcome-page .elementor-location-footer,
body.lhp-ep-welcome-page [data-elementor-type="footer"]{display:none!important}
body.lhp-ep-welcome-page,body.lhp-ep-welcome-page #outer-wrap,body.lhp-ep-welcome-page #wrap,
body.lhp-ep-welcome-page .site-content,body.lhp-ep-welcome-page #content
{padding-top:0!important;margin-top:0!important;background:#f1f5f9!important}
/* Tab buttons: override global .lhp-root button{color:white!important} */
body.lhp-ep-welcome-page .lhp-root button.lhp-ep-tab{color:#475569!important;background:transparent!important;box-shadow:none!important}
body.lhp-ep-welcome-page .lhp-root button.lhp-ep-tab.lhp-ep-tab-active{color:#fff!important;background:var(--lhp-primary,#4f46e5)!important;box-shadow:0 2px 8px rgba(79,70,229,.3)!important}
</style>' . "\n";
    }, 9999 );

    get_header();
    ?>
<div class="lhp-root">
<div class="lhp-auth-center-wrap">
<div class="lhp-auth-split">

  <!-- ═══ LEFT: Co-branded brand panel ═══ -->
  <div class="lhp-auth-brand-panel">
    <div class="lhp-auth-brand-inner">

      <!-- OurLighthouse identity -->
      <div class="lhp-brand-logo">
        <i class="bi bi-house-heart-fill" style="font-size:2.25rem;color:#fff;filter:drop-shadow(0 2px 6px rgba(0,0,0,.25))"></i>
        <span>Family Lighthouse</span>
      </div>

      <!-- "In Partnership With" divider -->
      <div style="display:flex;align-items:center;gap:10px;margin:20px 0 16px">
        <div style="flex:1;height:1px;background:rgba(255,255,255,.35)"></div>
        <span style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.8);font-weight:700;white-space:nowrap">In Partnership With</span>
        <div style="flex:1;height:1px;background:rgba(255,255,255,.35)"></div>
      </div>

      <!-- Planner co-brand -->
      <div class="lhp-brand-logo" style="margin-top:0">
        <?php if ( $logo ) : ?>
          <div style="width:72px;height:72px;border-radius:12px;background:#fff;display:flex;align-items:center;justify-content:center;padding:6px;box-shadow:0 4px 16px rgba(0,0,0,.18);flex-shrink:0">
            <img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $brand_name ); ?>" style="max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain">
          </div>
        <?php else : ?>
          <div style="width:60px;height:60px;border-radius:12px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0"><i class="bi bi-building"></i></div>
        <?php endif; ?>
        <span style="font-size:1.1rem;font-weight:700"><?php echo esc_html( $brand_name ); ?></span>
      </div>

      <h1 class="lhp-brand-headline" style="margin-top:1.5rem">Welcome!</h1>
      <p class="lhp-brand-sub">
        Your gateway to a secure, organised Lighthouse — presented by
        <strong><?php echo esc_html( $brand_name ); ?></strong> and Family Lighthouse.
      </p>

      <?php if ( $bio ) : ?>
        <div style="background:rgba(255,255,255,.1);border-radius:10px;padding:.85rem 1rem;font-size:14px;line-height:1.6;border-left:3px solid rgba(255,255,255,.3);color:#fff;margin-top:1rem">
          <?php echo esc_html( $bio ); ?>
        </div>
      <?php endif; ?>

      <ul class="lhp-brand-features mt-4">
        <li><i class="bi bi-shield-check-fill" aria-hidden="true"></i> Bank-grade secure storage</li>
        <li><i class="bi bi-file-earmark-heart-fill" aria-hidden="true"></i> Store letters, wishes &amp; keepsakes</li>
        <li><i class="bi bi-lock-fill" aria-hidden="true"></i> You control who sees what</li>
        <li><i class="bi bi-people-fill" aria-hidden="true"></i> Share with trusted family members</li>
      </ul>
    </div>
  </div>

  <!-- ═══ RIGHT: Form panel ═══ -->
  <div class="lhp-auth-form-panel">
    <div class="lhp-auth-form-inner">

      <!-- Tab bar -->
      <div id="ep-tab-bar" style="display:flex;gap:0;background:#f1f5f9;border-radius:10px;padding:4px;margin-bottom:1.5rem">
        <button id="ep-tab-register" type="button" class="lhp-ep-tab lhp-ep-tab-active" style="flex:1;padding:10px 8px;border:none;font-weight:600;font-size:14px;cursor:pointer;transition:all .2s">Create Account</button>
        <button id="ep-tab-login"    type="button" class="lhp-ep-tab" style="flex:1;padding:10px 8px;border:none;font-weight:600;font-size:14px;cursor:pointer;transition:all .2s">Sign In</button>
      </div>

      <!-- ── REGISTER PANE ── -->
      <div id="ep-register-pane">
        <div class="lhp-auth-form-header">
          <h2>Create Your Family Lighthouse</h2>
          <p>Set up your secure family portal</p>
        </div>
        <div class="alert alert-danger d-none align-items-center gap-2" id="ep-reg-error" role="alert">
          <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
          <span class="lhp-alert-msg"></span>
        </div>
        <div class="alert alert-success d-none" id="ep-reg-success"></div>
        <form id="ep-register-form" novalidate>
          <input type="hidden" id="ep-token" value="<?php echo esc_attr( $token ); ?>">
          <div class="mb-3">
            <label class="form-label fw-semibold" for="ep-reg-name">Full Name <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-person" aria-hidden="true"></i></span>
              <input type="text" class="form-control" id="ep-reg-name" placeholder="Your full name" autocomplete="name">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="ep-reg-email">Email Address <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-envelope" aria-hidden="true"></i></span>
              <input type="email" class="form-control" id="ep-reg-email" placeholder="your@email.com" autocomplete="email">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="ep-reg-phone">Phone <span class="text-muted fw-normal small">(optional)</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-telephone" aria-hidden="true"></i></span>
              <input type="tel" class="form-control" id="ep-reg-phone" placeholder="Phone number" autocomplete="tel">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="ep-reg-pass">Create Password <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-lock"></i></span>
              <input type="password" class="form-control" id="ep-reg-pass" placeholder="At least 8 characters" autocomplete="new-password">
              <button class="btn btn-outline-secondary lhp-toggle-pass" type="button" data-target="ep-reg-pass" tabindex="-1" aria-label="Show/hide password"><i class="bi bi-eye" aria-hidden="true"></i></button>
            </div>
            <div class="lhp-pass-strength mt-1" id="ep-pass-strength" style="display:none">
              <div class="lhp-pass-strength-bar"><div class="lhp-pass-strength-fill" id="ep-strength-fill"></div></div>
              <small id="ep-strength-label" class="text-muted" aria-live="polite"></small>
            </div>
          </div>
          <p class="lhp-legal-note text-center mb-3" style="font-size:12px">
            <i class="bi bi-shield-lock text-muted" aria-hidden="true"></i>
            This Lighthouse is not a will and is not a legal document.
          </p>
          <div class="d-grid">
            <button class="btn lhp-btn-primary-solid btn-lg" id="ep-register-btn" type="submit">
              <span class="btn-label"><i class="bi bi-check-circle me-2"></i>Create My Lighthouse</span>
              <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Creating…</span>
            </button>
          </div>
          <div class="d-flex align-items-center justify-content-center gap-3 mt-4 pt-3" style="border-top:1px solid #e2e8f0">
            <small class="text-muted d-flex align-items-center gap-1"><i class="bi bi-shield-lock-fill" aria-hidden="true"></i> 256-bit Encrypted</small>
            <small class="text-muted d-flex align-items-center gap-1"><i class="bi bi-incognito" aria-hidden="true"></i> Private</small>
            <small class="text-muted d-flex align-items-center gap-1"><i class="bi bi-cloud-check-fill" aria-hidden="true"></i> Always yours</small>
          </div>
        </form>
      </div>

      <!-- ── OTP PANE ── -->
      <div id="ep-otp-pane" style="display:none">
        <div class="lhp-auth-form-header">
          <h2 style="display:flex;align-items:center;gap:.5rem"><i class="bi bi-envelope-check" style="color:var(--lhp-primary,#4f46e5)"></i> Verify Your Email</h2>
          <p id="ep-otp-sub">Enter the 6-digit code we sent to your email</p>
        </div>
        <div class="alert alert-danger d-none align-items-center gap-2" id="ep-otp-error" role="alert">
          <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
          <span class="lhp-alert-msg"></span>
        </div>
        <form id="ep-otp-form" novalidate>
          <input type="hidden" id="ep-pending-email" value="">
          <div class="mb-4 text-center">
            <label class="form-label fw-semibold d-block mb-3" for="ep-otp-code">Verification Code <span class="text-danger">*</span></label>
            <input type="text" class="form-control text-center mx-auto" id="ep-otp-code"
                   placeholder="000000" maxlength="6" inputmode="numeric" pattern="\d{6}"
                   autocomplete="one-time-code"
                   style="font-size:2rem;letter-spacing:.5rem;font-weight:700;max-width:220px">
            <small class="text-muted d-block mt-2"><i class="bi bi-clock me-1"></i>Expires in 15 minutes</small>
          </div>
          <div class="d-grid mt-2">
            <button class="btn lhp-btn-primary-solid btn-lg" id="ep-otp-btn" type="submit">
              <span class="btn-label"><i class="bi bi-check-circle me-2"></i>Verify &amp; Create My Lighthouse</span>
              <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Verifying…</span>
            </button>
          </div>
          <p class="text-center mt-3 mb-0">
            <button type="button" id="ep-otp-back" class="btn btn-link text-muted p-0" style="font-size:13px;text-decoration:none"><i class="bi bi-arrow-left me-1"></i>Back to registration</button>
          </p>
        </form>
      </div>

      <!-- ── LOGIN PANE ── -->
      <div id="ep-login-pane" style="display:none">
        <div class="lhp-auth-form-header">
          <h2>Welcome Back</h2>
          <p>Sign in to your Family Lighthouse</p>
        </div>
        <div class="alert alert-danger d-none align-items-center gap-2" id="ep-login-error" role="alert">
          <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
          <span class="lhp-alert-msg"></span>
        </div>
        <form id="ep-login-form" novalidate>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="ep-login-email">Email Address</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-envelope" aria-hidden="true"></i></span>
              <input type="email" class="form-control" id="ep-login-email" placeholder="your@email.com" autocomplete="email" autofocus>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="ep-login-pass">Password</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-lock" aria-hidden="true"></i></span>
              <input type="password" class="form-control" id="ep-login-pass" placeholder="Your password" autocomplete="current-password">
              <button class="btn btn-outline-secondary lhp-toggle-pass" type="button" data-target="ep-login-pass" tabindex="-1" aria-label="Show/hide password"><i class="bi bi-eye" aria-hidden="true"></i></button>
            </div>
          </div>
          <div class="d-grid mt-4">
            <button class="btn lhp-btn-primary-solid btn-lg" id="ep-login-btn" type="submit">
              <span class="btn-label"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</span>
              <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Signing in…</span>
            </button>
          </div>
          <p class="text-center text-muted mt-3" style="font-size:13px">
            <a href="<?php echo esc_url( lhp_page_url('login') ); ?>#forgot" class="lhp-link">Forgot password?</a>
          </p>
        </form>
      </div>

    </div>
  </div>

</div>
</div>
</div>
<script>
(function(){
  // Toggle password visibility
  document.querySelectorAll('.lhp-toggle-pass').forEach(function(btn){
    btn.addEventListener('click',function(){
      var inp=document.getElementById(this.getAttribute('data-target'));
      if(!inp)return;
      var show=inp.type==='password';
      inp.type=show?'text':'password';
      this.innerHTML=show?'<i class="bi bi-eye-slash"></i>':'<i class="bi bi-eye"></i>';
    });
  });

  // Tab switching (register / login / otp)
  var tabBar=document.getElementById('ep-tab-bar');
  var tabReg=document.getElementById('ep-tab-register');
  var tabLog=document.getElementById('ep-tab-login');
  var paneReg=document.getElementById('ep-register-pane');
  var paneLog=document.getElementById('ep-login-pane');
  var paneOtp=document.getElementById('ep-otp-pane');
  function showTab(t){
    var isReg=t==='register';var isLog=t==='login';var isOtp=t==='otp';
    tabBar.style.display=isOtp?'none':'';
    tabReg.classList.toggle('lhp-ep-tab-active',isReg);
    tabLog.classList.toggle('lhp-ep-tab-active',isLog);
    paneReg.style.display=isReg?'':'none';
    paneLog.style.display=isLog?'':'none';
    paneOtp.style.display=isOtp?'':'none';
  }
  tabReg.addEventListener('click',function(){showTab('register');});
  tabLog.addEventListener('click',function(){showTab('login');});

  var ajaxUrl='<?php echo admin_url('admin-ajax.php'); ?>';
  var nonce='<?php echo wp_create_nonce('flp_nonce'); ?>';
  var epToken=document.getElementById('ep-token').value;

  // Password strength meter
  var psBars=document.getElementById('ep-pass-strength');
  var psFill=document.getElementById('ep-strength-fill');
  var psLbl=document.getElementById('ep-strength-label');
  document.getElementById('ep-reg-pass').addEventListener('input',function(){
    var v=this.value;
    if(!v){psBars.style.display='none';return;}
    psBars.style.display='block';
    var s=0;
    if(v.length>=8)s++;if(v.length>=12)s++;
    if(/[a-z]/.test(v)&&/[A-Z]/.test(v))s++;
    if(/\d/.test(v))s++;
    if(/[^a-zA-Z0-9]/.test(v))s++;
    var lvl=['Weak','Fair','Good','Strong','Very Strong'];
    var clr=['#ef4444','#f59e0b','#10b981','#059669','#047857'];
    var i=Math.min(s,4);
    psFill.style.width=((s+1)*20)+'%';
    psFill.style.background=clr[i];
    psLbl.textContent=lvl[i];
  });

  function btnSaving(btn,saving){
    btn.querySelector('.btn-label').classList.toggle('d-none',saving);
    btn.querySelector('.btn-loading').classList.toggle('d-none',!saving);
    btn.classList.toggle('lhp-btn-saving',saving);
  }
  function showErr(el,msg){
    el.querySelector('.lhp-alert-msg').textContent=msg;
    el.classList.remove('d-none');el.classList.add('d-flex');
  }

  // Register form
  document.getElementById('ep-register-form').addEventListener('submit',function(e){
    e.preventDefault();
    var btn=document.getElementById('ep-register-btn');
    var errEl=document.getElementById('ep-reg-error');
    errEl.classList.add('d-none');
    var name=document.getElementById('ep-reg-name').value.trim();
    var email=document.getElementById('ep-reg-email').value.trim();
    var phone=document.getElementById('ep-reg-phone').value.trim();
    var pass=document.getElementById('ep-reg-pass').value;
    if(!name){showErr(errEl,'Full name is required.');return;}
    if(!email||!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)){showErr(errEl,'Please enter a valid email address.');return;}
    if(pass.length<8){showErr(errEl,'Password must be at least 8 characters.');return;}
    btnSaving(btn,true);
    var fd=new FormData();
    fd.append('action','lhp_register_via_ep_link');
    fd.append('nonce',nonce);
    fd.append('ep_token',epToken);
    fd.append('full_name',name);
    fd.append('email',email);
    fd.append('phone',phone);
    fd.append('password',pass);
    fetch(ajaxUrl,{method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(r){
        btnSaving(btn,false);
        if(r.success){
          if(r.data.step==='verify_otp'){
            // Show OTP pane
            var masked=r.data.masked_email||email;
            document.getElementById('ep-otp-sub').textContent='Enter the 6-digit code we sent to '+masked;
            document.getElementById('ep-pending-email').value=email;
            document.getElementById('ep-otp-code').value='';
            document.getElementById('ep-otp-error').classList.add('d-none');
            showTab('otp');
            setTimeout(function(){document.getElementById('ep-otp-code').focus();},100);
          }else{
            // Fallback direct success
            document.getElementById('ep-reg-success').innerHTML='<div style="text-align:center;padding:2rem 1rem"><div style="width:80px;height:80px;border-radius:50%;background:#d1fae5;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem"><i class="bi bi-check-circle-fill text-success" style="font-size:2.5rem"></i></div><h4 class="fw-bold mb-2">Welcome!</h4><p class="text-muted mb-0">Your Family Lighthouse is ready. Redirecting you now…</p><div class="mt-3"><div class="spinner-border spinner-border-sm text-primary"></div></div></div>';
            document.getElementById('ep-reg-success').classList.remove('d-none');
            document.getElementById('ep-register-form').style.display='none';
            setTimeout(function(){window.location.href=r.data.redirect;},1500);
          }
        }else{
          var msg=r.data||'Registration failed.';
          showErr(errEl,msg);
          if(msg.toLowerCase().indexOf('exist')!==-1){
            showErr(document.getElementById('ep-login-error'),'An account with this email already exists. Please sign in.');
            showTab('login');
          }
        }
      })
      .catch(function(){btnSaving(btn,false);showErr(document.getElementById('ep-reg-error'),'Network error. Please try again.');});
  });

  // OTP verification form
  document.getElementById('ep-otp-form').addEventListener('submit',function(e){
    e.preventDefault();
    var btn=document.getElementById('ep-otp-btn');
    var errEl=document.getElementById('ep-otp-error');
    errEl.classList.add('d-none');
    var otp=document.getElementById('ep-otp-code').value.trim();
    var pendingEmail=document.getElementById('ep-pending-email').value;
    if(!otp||otp.length!==6||!/^\d{6}$/.test(otp)){showErr(errEl,'Please enter the 6-digit code.');return;}
    if(!pendingEmail){showErr(errEl,'Session lost. Please go back and register again.');return;}
    btnSaving(btn,true);
    var fd=new FormData();
    fd.append('action','lhp_verify_ep_otp');
    fd.append('nonce',nonce);
    fd.append('email',pendingEmail);
    fd.append('otp',otp);
    fd.append('flow','ep_welcome');
    fetch(ajaxUrl,{method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(r){
        if(r.success){
          paneOtp.innerHTML='<div style="text-align:center;padding:2rem 1rem"><div style="width:80px;height:80px;border-radius:50%;background:#d1fae5;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem"><i class="bi bi-check-circle-fill text-success" style="font-size:2.5rem"></i></div><h4 class="fw-bold mb-2">Email Verified!</h4><p class="text-muted mb-0">Your Family Lighthouse is ready. Redirecting…</p><div class="mt-3"><div class="spinner-border spinner-border-sm text-primary"></div></div></div>';
          setTimeout(function(){window.location.href=r.data.redirect;},1500);
        }else{
          btnSaving(btn,false);
          showErr(errEl,r.data||'Verification failed. Please try again.');
        }
      })
      .catch(function(){btnSaving(btn,false);showErr(errEl,'Network error. Please try again.');});
  });

  // OTP back button
  document.getElementById('ep-otp-back').addEventListener('click',function(){
    document.getElementById('ep-otp-error').classList.add('d-none');
    showTab('register');
  });

  // Login form
  document.getElementById('ep-login-form').addEventListener('submit',function(e){
    e.preventDefault();
    var btn=document.getElementById('ep-login-btn');
    var errEl=document.getElementById('ep-login-error');
    errEl.classList.add('d-none');
    var email=document.getElementById('ep-login-email').value.trim();
    var pass=document.getElementById('ep-login-pass').value;
    if(!email||!pass){showErr(errEl,'Please enter your email and password.');return;}
    btnSaving(btn,true);
    var fd=new FormData();
    fd.append('action','lhp_login');
    fd.append('nonce',nonce);
    fd.append('email',email);
    fd.append('password',pass);
    fetch(ajaxUrl,{method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(r){
        if(r.success){window.location.href=r.data.redirect;}
        else{btnSaving(btn,false);showErr(errEl,r.data||'Login failed.');}
      })
      .catch(function(){btnSaving(btn,false);showErr(document.getElementById('ep-login-error'),'Network error. Please try again.');});
  });
})();
</script>
<?php get_footer(); ?>
<?php
    exit;
}

/* ─────────────────────────────────────────────────────────────
   ASSET LOADING
   Strategy: enqueue on ALL front-end pages (is_admin() check only).
   The JS IIFE exits immediately if window.LHP is missing,
   so there is zero impact on non-portal pages.
   We also output a guaranteed wp_footer inline script that
   bootstraps the LHP object even if wp_localize_script failed.
───────────────────────────────────────────────────────────── */
/* Tag portal pages with a body class so CSS can hide the theme page title */
add_filter( 'body_class', function( $classes ) {
    global $post;
    if ( ! $post ) return $classes;
    $lhp_shortcodes = [
        'lhp_login', 'lhp_register',
        'lhp_planner_dashboard', 'lhp_parent_dashboard',
        'lhp_admin_dashboard', 'lhp_delegated_dashboard',
        'lhp_planner_form',
    ];
    foreach ( $lhp_shortcodes as $sc ) {
        if ( has_shortcode( $post->post_content, $sc ) ) {
            $classes[] = 'lhp-portal-page';
            break;
        }
    }
    // Auth pages keep the theme header — flag them so JS can measure and offset
    foreach ( [ 'lhp_login', 'lhp_register' ] as $sc ) {
        if ( has_shortcode( $post->post_content, $sc ) ) {
            $classes[] = 'lhp-auth-page';
            break;
        }
    }
    return $classes;
} );

add_action( 'wp_enqueue_scripts', 'lhp_enqueue_assets' );
function lhp_enqueue_assets() {
    if ( is_admin() ) return;

    // Bootstrap 5
    wp_enqueue_style(  'bootstrap',       'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',      [], '5.3.3' );
    wp_enqueue_style(  'bootstrap-icons', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css', [], '1.11.3' );
    wp_enqueue_script( 'bootstrap-js',    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', [], '5.3.3', true );

    // Portal
    wp_enqueue_style(  'lhp-style',  LHP_URL . 'assets/css/portal.css', [ 'bootstrap', 'bootstrap-icons' ], LHP_VERSION );
    wp_enqueue_script( 'lhp-script', LHP_URL . 'assets/js/portal.js',   [ 'jquery', 'bootstrap-js' ],       LHP_VERSION, true );

    // Localize
    wp_localize_script( 'lhp-script', 'LHP', lhp_get_config() );

    // CDN preconnect
    add_action( 'wp_head', function () {
        echo '<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>' . "\n";
    }, 1 );
}

function lhp_get_config() {
    return [
        'ajax_url'   => admin_url( 'admin-ajax.php' ),
        'nonce'      => wp_create_nonce( 'flp_nonce' ),
        'role'       => is_user_logged_in() ? LH_Auth::current_role() : '',
        'user_id'    => (string) get_current_user_id(),
        'home_url'   => home_url(),
        'planner_url'   => home_url( '/planner-dashboard' ),
        'parent_url' => home_url( '/dashboard' ),
        'login_url'  => lhp_page_url( 'login' ),
        'admin_url'  => home_url( '/fml-admin' ),
    ];
}

/**
 * Centralized URL lookup. Use instead of hardcoding slug strings.
 */
function lhp_page_url( $key ) {
    static $cache = [];
    if ( isset( $cache[ $key ] ) ) return $cache[ $key ];

    $shortcode_map = [
        'login'     => 'lhp_login',
        'register'  => 'lhp_register',
        'planner'   => 'lhp_planner_dashboard',
        'parent'    => 'lhp_parent_dashboard',
        'admin'     => 'lhp_admin_dashboard',
        'delegated' => 'lhp_delegated_dashboard',
    ];

    if ( isset( $shortcode_map[ $key ] ) ) {
        $sc = $shortcode_map[ $key ];
        $pages = get_posts( [
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            's'              => '[' . $sc . ']',
        ] );
        // Fallback: search post_content directly (WP search doesn't scan shortcode brackets reliably)
        if ( empty( $pages ) ) {
            global $wpdb;
            $row = $wpdb->get_row( $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_status='publish' AND post_type='page' AND post_content LIKE %s LIMIT 1",
                '%[' . $sc . ']%'
            ) );
            if ( $row ) $pages = [ get_post( $row->ID ) ];
        }
        if ( ! empty( $pages ) ) {
            $url = get_permalink( $pages[0]->ID );
            $cache[ $key ] = $url;
            return $url;
        }
    }

    // Fallback to slug-based guesses
    $fallbacks = [
        'login'     => '/lhp-login',
        'register'  => '/lhp-register',
        'planner'   => '/planner-dashboard',
        'parent'    => '/dashboard',
        'admin'     => '/fml-admin',
        'delegated' => '/delegated-dashboard',
    ];
    $url = home_url( $fallbacks[ $key ] ?? '/' );
    $cache[ $key ] = $url;
    return $url;
}

/* ─────────────────────────────────────────────────────────────
   HIDE THEME HEADER + FOOTER ON PORTAL PAGES
───────────────────────────────────────────────────────────── */

/** Central helper — true when the current page contains any LHP shortcode */
function lhp_is_portal_page() {
    global $post;
    if ( ! $post ) return false;
    static $cache = null;
    if ( $cache !== null ) return $cache;
    $shortcodes = [
        'lhp_login', 'lhp_register',
        'lhp_planner_dashboard', 'lhp_parent_dashboard',
        'lhp_admin_dashboard', 'lhp_delegated_dashboard',
        'lhp_planner_form',
    ];
    foreach ( $shortcodes as $sc ) {
        if ( has_shortcode( $post->post_content, $sc ) ) {
            $cache = true;
            return $cache;
        }
    }
    $cache = false;
    return $cache;
}

/**
 * Central guard for chrome hiding.
 * Keep invite route out of header/footer blocking logic.
 */
function lhp_should_hide_theme_chrome() {
    if ( ! is_user_logged_in() ) return false;
    if ( lhp_is_invite_route() ) return false;
    return lhp_is_portal_page();
}

/**
 * LAYER 1 — PHP: remove theme header/footer actions before they fire.
 * Hooked on template_redirect so the main query is already set up.
 */
add_action( 'template_redirect', 'lhp_remove_chrome_actions', 1 );
function lhp_remove_chrome_actions() {
    if ( ! lhp_should_hide_theme_chrome() ) return;

    // ── OceanWP ──
    remove_action( 'ocean_header',        'ocean_get_header_template' );
    remove_action( 'ocean_before_header', 'ocean_top_bar' );
    remove_action( 'ocean_footer',        'ocean_get_footer_template' );
    add_filter( 'ocean_display_top_bar',    '__return_false' );
    add_filter( 'ocean_display_header',     '__return_false' );
    add_filter( 'ocean_display_footer',     '__return_false' );

    // ── Astra ──
    add_filter( 'astra_header_enabled',  '__return_false' );
    add_filter( 'astra_footer_enabled',  '__return_false' );

    // ── GeneratePress ──
    remove_action( 'generate_header',        'generate_construct_header' );
    remove_action( 'generate_footer',        'generate_construct_footer' );
    remove_action( 'generate_before_footer', 'generate_construct_sidebars' );

    // ── Hello Elementor ──
    remove_action( 'elementor/page_templates/header-footer/before_content', 'hello_elementor_header_footer' );
    remove_action( 'hello_elementor_header', 'hello_elementor_header' );
    remove_action( 'hello_elementor_footer', 'hello_elementor_footer' );

    // ── Elementor Pro Theme Builder (header/footer locations) ──
    add_action( 'elementor/theme/before_do_header', function() {
        ob_start();
    }, 0 );
    add_action( 'elementor/theme/after_do_header', function() {
        ob_end_clean();
    }, 9999 );
    add_action( 'elementor/theme/before_do_footer', function() {
        ob_start();
    }, 0 );
    add_action( 'elementor/theme/after_do_footer', function() {
        ob_end_clean();
    }, 9999 );
}

/**
 * LAYER 2 — CSS in <head>: high-specificity rules using body.lhp-portal-page.
 * Uses the class added by our body_class filter so specificity beats themes.
 */
add_action( 'wp_head', 'lhp_hide_chrome_head', 9999 );
function lhp_hide_chrome_head() {
    if ( ! lhp_should_hide_theme_chrome() ) return;
    $admin_offset = is_admin_bar_showing() ? '32px' : '0px';
    ?>
<style id="lhp-hide-chrome-head">
/* ── header elements ── */
body.lhp-portal-page header,
body.lhp-portal-page #masthead,
body.lhp-portal-page #site-header,
body.lhp-portal-page #ocean-header,
body.lhp-portal-page #oceanwp-header,
body.lhp-portal-page .site-header,
body.lhp-portal-page .oceanwp-header,
body.lhp-portal-page .main-navigation,
body.lhp-portal-page #site-navigation,
body.lhp-portal-page .top-bar,
body.lhp-portal-page #top-bar,
body.lhp-portal-page .header-top,
body.lhp-portal-page .site-top-bar,
body.lhp-portal-page .announcement-bar,
body.lhp-portal-page .header-bar,
body.lhp-portal-page .header-wrap,
body.lhp-portal-page #header-wrap,
body.lhp-portal-page #header,
body.lhp-portal-page .elementor-location-header,
body.lhp-portal-page [data-elementor-type="header"] { display: none !important; }

/* ── footer elements ── */
body.lhp-portal-page footer,
body.lhp-portal-page #footer,
body.lhp-portal-page #colophon,
body.lhp-portal-page #site-footer,
body.lhp-portal-page #ocean-footer,
body.lhp-portal-page .site-footer,
body.lhp-portal-page .oceanwp-footer,
body.lhp-portal-page .footer-widgets,
body.lhp-portal-page #footer-widgets,
body.lhp-portal-page .footer-bottom,
body.lhp-portal-page #footer-bottom,
body.lhp-portal-page .footer-bar,
body.lhp-portal-page .main-footer,
body.lhp-portal-page .elementor-location-footer,
body.lhp-portal-page [data-elementor-type="footer"] { display: none !important; }

/* ── strip body/page padding added by themes for sticky headers ── */
body.lhp-portal-page,
body.lhp-portal-page html { padding-top: 0 !important; margin-top: 0 !important; }
body.lhp-portal-page #page,
body.lhp-portal-page .site,
body.lhp-portal-page #wrapper,
body.lhp-portal-page #outer-wrap,
body.lhp-portal-page .ocean-wrap,
body.lhp-portal-page .site-content,
body.lhp-portal-page #content { padding-top: 0 !important; margin-top: 0 !important; }

/* ── reset plugin layout offsets (no header/footer present) ── */
:root {
    --lhp-site-offset: <?php echo esc_attr( $admin_offset ); ?>;
    --lhp-footer-offset: 0px;
}
</style>
    <?php
}

/**
 * LAYER 3 — CSS injected via wp_footer at priority 1.
 * Fires AFTER the theme outputs the footer HTML, so it always wins.
 */
add_action( 'wp_footer', 'lhp_hide_chrome_footer', 1 );
function lhp_hide_chrome_footer() {
    if ( ! lhp_should_hide_theme_chrome() ) return;
    echo '<style id="lhp-hide-chrome-footer">
body.lhp-portal-page footer,
body.lhp-portal-page #footer,
body.lhp-portal-page #colophon,
body.lhp-portal-page #site-footer,
body.lhp-portal-page #ocean-footer,
body.lhp-portal-page .site-footer,
body.lhp-portal-page .oceanwp-footer,
body.lhp-portal-page .footer-widgets,
body.lhp-portal-page #footer-widgets,
body.lhp-portal-page .footer-bottom,
body.lhp-portal-page #footer-bottom,
body.lhp-portal-page .footer-bar,
body.lhp-portal-page .main-footer,
body.lhp-portal-page .elementor-location-footer,
body.lhp-portal-page [data-elementor-type="footer"] { display: none !important; }
body.lhp-portal-page header,
body.lhp-portal-page #masthead,
body.lhp-portal-page #site-header,
body.lhp-portal-page #ocean-header,
body.lhp-portal-page .site-header,
body.lhp-portal-page .top-bar,
body.lhp-portal-page #top-bar,
body.lhp-portal-page .site-top-bar,
body.lhp-portal-page .announcement-bar,
body.lhp-portal-page .elementor-location-header,
body.lhp-portal-page [data-elementor-type="header"],
body.lhp-portal-page #header { display: none !important; }
</style>' . "\n";
}

/* ─────────────────────────────────────────────────────────────
   FOOTER INLINE SCRIPT — cannot be cached, always runs.
   Re-sets window.LHP in case wp_localize_script didn't fire,
   and confirms the script loaded.
───────────────────────────────────────────────────────────── */
// Disable Elementor lightbox inside .lhp-root so portal links open normally
add_action( 'wp_footer', function() {
    if ( ! lhp_is_portal_page() ) return;
    ?>
    <script>
    (function() {
      if (window.elementorFrontend && elementorFrontend.hooks) {
        elementorFrontend.hooks.addFilter('lightbox/imageData', function(data){ return false; });
      }
      // Also mark all existing + future .lhp-root links as lightbox-exempt
      document.addEventListener('click', function(e) {
        var a = e.target.closest('.lhp-root a[href]');
        if (a) {
          a.setAttribute('data-elementor-open-lightbox', 'no');
          a.setAttribute('data-no-lightbox', '');
        }
      }, true);
    })();
    </script>
    <?php
}, 98 );

add_action( 'wp_footer', 'lhp_footer_inline', 99 );
function lhp_footer_inline() {
    if ( is_admin() ) return;
    $config = lhp_get_config();
    ?>
<script id="lhp-inline-config">
/* Lighthouse Portal — inline config (uncacheable) */
(function(){
    var cfg = <?php echo wp_json_encode( $config ); ?>;

    /* Always set / overwrite window.LHP so portal.js gets fresh config */
    window.LHP = cfg;

    /* If portal.js has not yet run (deferred/cached race), re-trigger init */
    if (!window._lhpBooted) {
        var retryInit = function() {
            if (window._lhpBooted) return;
            if (typeof jQuery === 'undefined') return;
            if (typeof bootstrap === 'undefined') return;
            jQuery(document).trigger('lhp:retryinit');
        };
        if (document.readyState === 'complete') {
            retryInit();
        } else {
            window.addEventListener('load', retryInit);
        }
    }
})();
</script>
    <?php
}
