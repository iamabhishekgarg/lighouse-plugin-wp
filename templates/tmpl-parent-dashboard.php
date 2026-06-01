<?php
$user      = wp_get_current_user();
$initials   = strtoupper(substr($user->display_name,0,1));
$first_name = get_user_meta($user->ID,'first_name',true) ?: explode(' ',trim($user->display_name))[0];
// Fetch record — check primary owner first, then co-owner (owner2)
$all_recs = get_posts([
    'post_type'      => 'lh_record',
    'posts_per_page' => -1,
    'orderby'        => 'meta_value_num',
    'meta_key'       => '_flp_owner_id',
    'order'          => 'DESC',
    'meta_query'     => [
        'relation' => 'OR',
        ['key' => '_flp_owner_id',  'value' => $user->ID],
        ['key' => '_flp_owner2_id', 'value' => $user->ID],
    ],
]);
// Prefer record where user is primary owner; fall back to co-owned record
$record_id = 0;
foreach ($all_recs as $rec) {
    if ((int)get_post_meta($rec->ID, '_flp_owner_id', true) === $user->ID) {
        $record_id = $rec->ID; break;
    }
}
if (!$record_id && !empty($all_recs)) $record_id = $all_recs[0]->ID;
// Fall back to legacy meta
if (!$record_id) $record_id = (int)get_user_meta($user->ID,'_flp_record_id',true);

$lhp_invited_by   = esc_js( get_user_meta($user->ID,'_flp_invited_by_name',true) ?: '' );
$lhp_needs_pass   = (int) get_user_meta($user->ID,'_flp_needs_password',true);
$lhp_is_new_invite = isset($_GET['lhp_new_invite']) ? 1 : 0;

// Planner co-branding
$planner_id       = $record_id ? (int) get_post_meta($record_id, '_flp_planner_id', true) : 0;
$planner_name     = '';
$planner_logo_url = '';
if ($planner_id) {
    $p_data       = get_userdata($planner_id);
    $planner_name = get_user_meta($planner_id, '_flp_firm_name', true)
                    ?: ($p_data ? $p_data->display_name : '');
    $logo_att_id  = (int) get_user_meta($planner_id, '_flp_logo_id', true);
    if ($logo_att_id) $planner_logo_url = wp_get_attachment_url($logo_att_id);
}
?>
<div class="lhp-root">
<div class="lhp-toast-container position-fixed top-0 end-0 p-3" style="z-index:11000"></div>

<div class="lhp-portal-layout">
  <aside class="lhp-sidebar" id="lhp-sidebar">
    <div class="lhp-sidebar-header">
      <?php if ($planner_name): ?>
        <div class="lhp-sidebar-brand lhp-sidebar-brand-planner">
          <?php if ($planner_logo_url): ?>
            <img src="<?php echo esc_url($planner_logo_url); ?>"
                 alt="<?php echo esc_attr($planner_name); ?>"
                 class="lhp-sidebar-planner-logo"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
            <span class="lhp-sidebar-planner-initials" style="display:none"><?php echo esc_html(strtoupper(substr($planner_name,0,1))); ?></span>
          <?php else: ?>
            <span class="lhp-sidebar-planner-initials"><?php echo esc_html(strtoupper(substr($planner_name,0,1))); ?></span>
          <?php endif; ?>
          <span><?php echo esc_html($planner_name); ?></span>
        </div>
      <?php else: ?>
        <div class="lhp-sidebar-brand"><i class="bi bi-house-heart-fill lhp-brand-icon-sm"></i><span>Family Lighthouse</span></div>
      <?php endif; ?>
      <button class="lhp-sidebar-toggle d-xl-none" id="lhp-sidebar-close" aria-label="Close sidebar"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </div>
    <div class="lhp-sidebar-user">
      <div class="lhp-avatar-lg"><?php echo esc_html($initials); ?></div>
      <div class="lhp-sidebar-user-info">
        <div class="fw-semibold text-white lhp-text-truncate"><?php echo esc_html($user->display_name); ?></div>
      </div>
    </div>
    <nav class="lhp-sidebar-nav">
      <div class="lhp-sidebar-nav-label">Navigation</div>
      <a href="#" class="lhp-nav-item active" data-view="my-records"><i class="bi bi-collection-fill"></i><span>My Lighthouse</span></a>
      <a href="#" class="lhp-nav-item" data-view="my-profile"><i class="bi bi-person-circle"></i><span>My Profile</span></a>
    </nav>
    <div class="lhp-sidebar-footer">
      <button class="lhp-nav-item lhp-logout-btn w-100 border-0 bg-transparent text-start" id="lhp-logout">
        <i class="bi bi-box-arrow-left"></i><span>Sign Out</span>
      </button>
      <?php if ($planner_name): ?>
        <div class="lhp-sidebar-powered">
          <i class="bi bi-house-heart-fill me-1"></i>Powered by Family Lighthouse
        </div>
      <?php endif; ?>
    </div>
  </aside>
  <div class="lhp-sidebar-backdrop d-xl-none" id="lhp-sidebar-backdrop"></div>
  <div class="lhp-topbar d-xl-none">
    <button class="lhp-topbar-menu" id="lhp-sidebar-open"><i class="bi bi-list"></i></button>
    <div class="lhp-topbar-brand"><i class="bi bi-house-heart-fill"></i> My Lighthouse</div>
    <div class="lhp-avatar-sm"><?php echo esc_html($initials); ?></div>
  </div>

  <main class="lhp-main-content">
    <input type="hidden" id="lhp-my-record-id" value="<?php echo esc_attr($record_id); ?>">

    <!-- ── MY RECORDS LIST ───────────────────────────────── -->
    <div class="lhp-view active" id="view-my-records">
      <div class="lhp-page-header">
        <div><h1 class="lhp-page-title">My Lighthouse</h1><p class="lhp-page-sub">Your personal end-of-life information vault</p></div>
      </div>
      <div id="my-records-grid" class="row g-3">
        <div class="col-12 text-center py-5 text-muted"><span class="spinner-border spinner-border-sm text-primary me-2"></span>Loading…</div>
      </div>
    </div>

    <!-- ── SINGLE RECORD DETAIL (section cards) ──────────── -->
    <div class="lhp-view" id="view-my-record">
      <div class="lhp-page-header">
        <div>
          <h1 class="lhp-page-title" id="active-record-title">My Lighthouse</h1>
          <p class="lhp-page-sub">Your personal end-of-life information vault</p>
        </div>
        <div>
          <button class="btn lhp-btn-primary-solid" id="lhp-parent-review-btn" title="View a complete read-only summary of this Lighthouse">
            <i class="bi bi-clipboard2-check-fill me-2"></i>Review Full Lighthouse
          </button>
        </div>
      </div>
      <div class="row g-3" id="lhp-sections-grid">
        <div class="col-12 text-center py-5 text-muted"><span class="spinner-border spinner-border-sm text-primary me-2"></span>Loading…</div>
      </div>
      <div class="lhp-legal-note mt-4"><i class="bi bi-info-circle-fill me-2 text-muted"></i>This Lighthouse is <strong>not a will</strong> and is not a legal document.</div>
    </div>

    <!-- ── PROFILE ────────────────────────────────────────── -->
    <div class="lhp-view" id="view-my-profile">
      <div class="lhp-page-header"><div><h1 class="lhp-page-title">My Profile</h1><p class="lhp-page-sub">Manage your account, password, and co-owner</p></div></div>
      <div class="row g-3">

        <!-- Left: Account Details (expands full-width for owner2 users) -->
        <div class="col-md-6" id="ppar-account-col">
          <div class="lhp-card h-100">
            <div class="lhp-card-section-title"><i class="bi bi-person-circle me-2"></i>Account</div>
            <div class="mb-3"><label class="form-label fw-semibold" for="ppar-name">Full Name</label><div class="input-group"><span class="input-group-text"><i class="bi bi-person"></i></span><input type="text" class="form-control" id="ppar-name"></div></div>
            <div class="mb-3"><label class="form-label fw-semibold" for="ppar-email">Email <small class="text-muted">(read only)</small></label><div class="input-group"><span class="input-group-text"><i class="bi bi-envelope"></i></span><input type="email" class="form-control" id="ppar-email" readonly></div></div>
            <div class="mb-3"><label class="form-label fw-semibold" for="ppar-phone">Phone</label><div class="input-group"><span class="input-group-text"><i class="bi bi-telephone"></i></span><input type="tel" class="form-control" id="ppar-phone"></div></div>
            <button class="btn lhp-btn-primary-solid" id="lhp-save-pprofile"><i class="bi bi-check-circle me-2"></i>Save Profile</button>
            <button class="btn btn-outline-danger btn-sm ms-2" id="lhp-delete-my-account"><i class="bi bi-trash me-1"></i>Delete My Account</button>
          </div>
        </div>

        <!-- Right: Co-Owner (hidden for owner2 users via JS) -->
        <div class="col-md-6" id="ppar-coowner-col">
          <div class="lhp-card h-100">
            <div class="lhp-card-section-title lhp-coowner-section-title"><i class="bi bi-person-plus-fill me-2"></i>Co-Owner <span class="text-muted fw-normal small">(optional)</span></div>
            <div id="ppar-owner2-status"></div>

            <!-- Shown when no co-owner set -->
            <div id="ppar-o2-empty" style="display:none">
              <p class="text-muted small mb-3 lhp-coowner-desc">No co-owner added yet. A co-owner can access this Lighthouse with their own login.</p>
              <button class="btn lhp-btn-primary-solid btn-sm" id="ppar-o2-add-btn">
                <i class="bi bi-person-plus me-1"></i>Add Co-Owner
              </button>
            </div>

            <!-- Shown when co-owner is set (or being added) -->
            <div id="ppar-o2-fields" style="display:none">
              <div class="mb-2"><label class="form-label fw-semibold" for="ppar-o2-name">Full Name</label><div class="input-group"><span class="input-group-text"><i class="bi bi-person"></i></span><input type="text" class="form-control" id="ppar-o2-name"></div></div>
              <div class="mb-2"><label class="form-label fw-semibold" for="ppar-o2-email">Email</label><div class="input-group"><span class="input-group-text"><i class="bi bi-envelope"></i></span><input type="email" class="form-control" id="ppar-o2-email"></div></div>
              <div class="mb-2"><label class="form-label fw-semibold" for="ppar-o2-phone">Phone</label><div class="input-group"><span class="input-group-text"><i class="bi bi-telephone"></i></span><input type="tel" class="form-control" id="ppar-o2-phone"></div></div>
              <button class="btn lhp-btn-primary-solid btn-sm" id="lhp-save-o2-profile"><i class="bi bi-check-circle me-1"></i>Save Co-Owner</button>
              <button class="btn btn-outline-danger btn-sm ms-1 d-none" id="lhp-delete-o2"><i class="bi bi-trash me-1"></i>Delete Co-Owner</button>
              <button class="btn btn-outline-secondary btn-sm ms-1" id="ppar-o2-cancel-btn" style="display:none"><i class="bi bi-x me-1"></i>Cancel</button>
            </div>

            <div class="alert alert-success d-none mt-2 py-1 px-2 small" id="ppar-o2-success" role="status" aria-live="polite"></div>
            <div class="alert alert-danger d-none mt-2 py-1 px-2 small" id="ppar-o2-error" role="alert" aria-live="assertive"></div>
          </div>
        </div>

      </div>
    </div>

  </main>
</div>

<!-- ═══ MODAL: Section Edit ═════════════════════════════ -->
<div class="modal fade" id="lhp-section-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content lhp-modal-content">
      <div class="modal-header lhp-modal-header">
        <h5 class="modal-title" id="section-modal-title">Edit Section</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body lhp-modal-body" id="section-modal-body"></div>
      <div class="modal-footer">
        <div class="alert alert-danger d-none mb-0 py-2 flex-grow-1" id="section-save-error" role="alert" aria-live="assertive"></div>
        <div class="alert alert-success d-none mb-0 py-2 flex-grow-1" id="section-save-success" role="status" aria-live="polite"></div>
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn lhp-btn-primary-solid" id="lhp-save-section"><i class="bi bi-check-circle me-2"></i>Save Changes</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ MODAL: Create New Record ════════════════════════ -->

<!-- ═══ MODAL: Full Record Review (Parent) ════════════════════ -->
<div class="modal fade" id="lhp-record-detail-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content lhp-modal-content">
      <div class="modal-header lhp-modal-header">
        <div>
          <h5 class="modal-title" id="rd-modal-title">Lighthouse Review</h5>
          <p class="text-white-50 small mb-0">Complete read-only view of your Family Lighthouse record</p>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body lhp-modal-body" id="rd-modal-body">
        <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      </div>
    </div>
  </div>
</div>


<!-- ═══ MODAL: Welcome (Invite) ═══════════════════════ -->
<div class="modal fade" id="lhp-welcome-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered" style="max-width:480px">
    <div class="modal-content lhp-modal-content">
      <div class="modal-header lhp-modal-header">
        <h5 class="modal-title"><i class="bi bi-house-heart-fill me-2"></i>Welcome to Our Family Lighthouse</h5>
      </div>
      <div class="modal-body lhp-modal-body">
        <div class="text-center mb-4">
          <div style="font-size:3rem;line-height:1;margin-bottom:12px">🏠</div>
          <h4 class="fw-bold mb-1" id="lhp-welcome-heading">You've been invited!</h4>
          <p class="text-muted mb-0" id="lhp-welcome-subtext"></p>
        </div>
        <hr class="my-3">
        <p class="fw-semibold mb-2">Create your password to secure your account:</p>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="welcome-pass">New Password <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control" id="welcome-pass" placeholder="Min. 8 characters">
            <button class="btn btn-outline-secondary lhp-toggle-pass" type="button" data-target="welcome-pass" tabindex="-1"><i class="bi bi-eye"></i></button>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="welcome-pass2">Confirm Password <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
            <input type="password" class="form-control" id="welcome-pass2" placeholder="Repeat password">
            <button class="btn btn-outline-secondary lhp-toggle-pass" type="button" data-target="welcome-pass2" tabindex="-1"><i class="bi bi-eye"></i></button>
          </div>
        </div>
        <div class="alert alert-danger d-none" id="welcome-error" role="alert" aria-live="assertive"></div>
        <div class="alert alert-success d-none" id="welcome-success" role="status" aria-live="polite"></div>
      </div>
      <div class="modal-footer">
        <button class="btn lhp-btn-primary-solid w-100" id="lhp-welcome-save-pass">
          <span class="btn-label"><i class="bi bi-check-circle me-2"></i>Set Password &amp; Enter My Family Lighthouse</span>
          <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Saving…</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ MODAL: Confirm Delete Profile ═══════════════════ -->
<div class="modal fade" id="lhp-confirm-del-profile-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Profile</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Delete <strong id="lhp-del-profile-name"></strong>? This action <strong>cannot be undone</strong> and will remove all associated data.</p>
        <input type="hidden" id="lhp-del-profile-id">
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger btn-sm" id="lhp-confirm-del-profile"><i class="bi bi-trash me-1"></i>Delete</button>
      </div>
    </div>
  </div>
</div>

<script>
window.lhpInviteConfig = {
  isNewInvite:  <?php echo $lhp_is_new_invite; ?>,
  needsPassword:<?php echo $lhp_needs_pass; ?>,
  invitedBy:    "<?php echo $lhp_invited_by; ?>"
};
</script>

</div><!-- .lhp-root -->
