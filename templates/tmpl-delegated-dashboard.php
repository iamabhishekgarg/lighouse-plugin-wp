<?php
$user     = wp_get_current_user();
$initials   = strtoupper(substr($user->display_name,0,1));
$first_name = get_user_meta($user->ID,'first_name',true) ?: explode(' ',trim($user->display_name))[0];

$lhp_invited_by    = esc_js( get_user_meta($user->ID,'_flp_invited_by_name',true) ?: '' );
$lhp_needs_pass    = (int) get_user_meta($user->ID,'_flp_needs_password',true);
$lhp_is_new_invite = isset($_GET['lhp_new_invite']) ? 1 : 0;
?>
<div class="lhp-root">
<div class="lhp-toast-container position-fixed top-0 end-0 p-3" style="z-index:11000"></div>

<div class="lhp-portal-layout">
  <aside class="lhp-sidebar" id="lhp-sidebar">
    <div class="lhp-sidebar-header">
      <div class="lhp-sidebar-brand"><i class="bi bi-house-heart-fill lhp-brand-icon-sm"></i><span>Family Lighthouse</span></div>
      <button class="lhp-sidebar-toggle d-xl-none" id="lhp-sidebar-close"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="lhp-sidebar-user">
      <div class="lhp-avatar-lg"><?php echo esc_html($first_name); ?></div>
      <div class="lhp-sidebar-user-info">
        <div class="fw-semibold text-white lhp-text-truncate"><?php echo esc_html($user->display_name); ?></div>
        <div class="lhp-sidebar-badge"><i class="bi bi-eye-fill me-1"></i>Delegated Access</div>
      </div>
    </div>
    <nav class="lhp-sidebar-nav">
      <div class="lhp-sidebar-nav-label">Navigation</div>
      <a href="#" class="lhp-nav-item active" data-view="shared"><i class="bi bi-folder-symlink-fill"></i><span>Shared Lighthouses</span></a>
      <a href="#" class="lhp-nav-item" data-view="del-profile"><i class="bi bi-person-circle"></i><span>My Profile</span></a>
    </nav>
    <div class="lhp-sidebar-footer">
      <button class="lhp-nav-item lhp-logout-btn w-100 border-0 bg-transparent text-start" id="lhp-logout">
        <i class="bi bi-box-arrow-left"></i><span>Sign Out</span>
      </button>
    </div>
  </aside>
  <div class="lhp-sidebar-backdrop d-xl-none" id="lhp-sidebar-backdrop"></div>
  <div class="lhp-topbar d-xl-none">
    <button class="lhp-topbar-menu" id="lhp-sidebar-open"><i class="bi bi-list"></i></button>
    <div class="lhp-topbar-brand"><i class="bi bi-house-heart-fill"></i> Shared Access</div>
    <div class="lhp-avatar-sm"><?php echo esc_html($initials); ?></div>
  </div>

  <main class="lhp-main-content">

    <!-- ── SHARED RECORDS ────────────────────────────────── -->
    <div class="lhp-view active" id="view-shared">
      <div class="lhp-page-header">
        <div>
          <h1 class="lhp-page-title">Shared Lighthouses</h1>
          <p class="lhp-page-sub">Lighthouse records shared with you — view only access</p>
        </div>
      </div>

      <!-- Access notice -->
      <div class="alert d-flex gap-3 mb-4" style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:10px">
        <i class="bi bi-info-circle-fill text-primary fs-5 flex-shrink-0"></i>
        <div>
          <strong>View-Only Access</strong><br>
          <span class="text-muted small">You can view these Lighthouse records but cannot edit them. If you need to make changes, please contact the record owner.</span>
        </div>
      </div>

      <div id="delegated-records-grid" class="row g-3">
        <div class="col-12 text-center py-5 text-muted"><span class="spinner-border spinner-border-sm text-primary me-2"></span>Loading shared records…</div>
      </div>
    </div>

    <!-- ── PROFILE ────────────────────────────────────────── -->
    <div class="lhp-view" id="view-del-profile">
      <div class="lhp-page-header"><div><h1 class="lhp-page-title">My Profile</h1></div></div>
      <div class="lhp-card" style="max-width:500px">
        <div class="lhp-card-section-title"><i class="bi bi-person-circle me-2"></i>Account Details</div>
        <div class="mb-3"><label class="form-label fw-semibold" for="del-prof-name">Full Name</label><input type="text" class="form-control" id="del-prof-name"></div>
        <div class="mb-3"><label class="form-label fw-semibold" for="del-prof-email">Email <small class="text-muted">(read only)</small></label><input type="email" class="form-control" id="del-prof-email" readonly></div>
        <button class="btn lhp-btn-primary-solid" id="lhp-save-del-profile"><i class="bi bi-check-circle me-2"></i>Save</button>
      </div>
    </div>

  </main>
</div>

<!-- ═══ MODAL: View Record (read-only) ═══════════════════ -->
<div class="modal fade" id="lhp-record-detail-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content lhp-modal-content">
      <div class="modal-header lhp-modal-header">
        <div><h5 class="modal-title" id="rd-modal-title">Lighthouse Record</h5><p class="text-white-50 small mb-0">View-only access</p></div>
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
        <h5 class="modal-title"><i class="bi bi-house-heart-fill me-2"></i>Welcome to Family Lighthouse</h5>
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
        <div class="alert alert-danger d-none" id="welcome-error"></div>
        <div class="alert alert-success d-none" id="welcome-success"></div>
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

<!-- ═══ MODAL: Self-Service Access Activation ═════════════ -->
<div class="modal fade" id="lhp-self-activate-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content lhp-modal-content">
      <div class="modal-header lhp-modal-header">
        <h5 class="modal-title"><i class="bi bi-file-earmark-medical me-2"></i>Request Lighthouse Access</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body lhp-modal-body">
        <input type="hidden" id="sa-record-id" value="0">

        <!-- Step 1: Upload document -->
        <div id="sa-step-1">
          <div class="alert alert-info d-flex gap-2 align-items-start mb-3">
            <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
            <span>Upload a death certificate, obituary, or legal notice confirming the owner has passed. We will then send a verification code to your email.</span>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Verification Document <span class="text-danger">*</span></label>
            <div class="lhp-upload-drop-zone" id="sa-drop-zone">
              <i class="bi bi-file-earmark-arrow-up fs-3 text-muted mb-2 d-block"></i>
              <div class="text-muted small">Drag &amp; drop or <span class="lhp-link" id="sa-browse-trigger">browse</span></div>
              <div class="text-muted" style="font-size:11px">PDF, JPG, PNG — max 10 MB</div>
              <input type="file" id="sa-file-input" accept=".pdf,.jpg,.jpeg,.png" class="d-none">
            </div>
            <div id="sa-file-preview" class="mt-2 d-none">
              <div class="d-flex align-items-center gap-2 p-2 border rounded">
                <i class="bi bi-file-earmark-check text-success"></i>
                <span id="sa-file-name" class="small fw-semibold text-truncate flex-grow-1"></span>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" id="sa-file-clear" aria-label="Remove file"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
              </div>
            </div>
            <div class="text-danger small mt-1 d-none" id="sa-file-error">Please attach a document before continuing.</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Notes <span class="text-muted fw-normal">(optional)</span></label>
            <textarea class="form-control" id="sa-notes" rows="2" placeholder="e.g. Death certificate from County of LA, June 2026"></textarea>
          </div>
          <div class="alert alert-warning py-2 px-3 small mb-3">
            <i class="bi bi-shield-exclamation me-1"></i>
            <strong>Important:</strong> Accepted documents: death certificate, obituary, coroner's report, probate court notice. Your submission will be reviewed by the estate planner and access may be revoked if the document is not valid.
          </div>
          <label class="lhp-declaration-label" for="sa-declaration">
            <input type="checkbox" id="sa-declaration" class="lhp-declaration-chk">
            <span class="lhp-declaration-box">
              <i class="bi bi-check2 lhp-declaration-tick"></i>
            </span>
            <span class="lhp-declaration-text">
              I confirm this is a genuine legal document. I understand that submitting a false document is fraudulent and access will be revoked immediately.
            </span>
          </label>
          <div class="text-danger small mt-1 d-none" id="sa-declaration-error"><i class="bi bi-exclamation-circle me-1"></i>You must confirm before proceeding.</div>
          <div class="alert alert-danger d-none" id="sa-step1-error" role="alert"></div>
          <button class="btn lhp-btn-primary-solid w-100" id="sa-upload-btn" type="button">
            <span class="btn-label"><i class="bi bi-upload me-2"></i>Upload &amp; Send OTP to My Email</span>
            <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Uploading…</span>
          </button>
        </div>

        <!-- Step 2: OTP verification -->
        <div id="sa-step-2" style="display:none">
          <div class="alert alert-success d-flex gap-2 align-items-start mb-3">
            <i class="bi bi-check-circle-fill flex-shrink-0 mt-1"></i>
            <span>Document uploaded. A 6-digit verification code has been sent to your email. Enter it below to activate your access.</span>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Email Verification Code <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-lg text-center fw-bold" id="sa-otp-input"
              placeholder="000000" maxlength="6" inputmode="numeric" pattern="[0-9]{6}"
              autocomplete="one-time-code" style="letter-spacing:0.3em;font-size:1.5rem">
            <div class="text-muted small mt-1">Code expires in 15 minutes. <span class="lhp-link" id="sa-resend-otp" style="cursor:pointer">Resend code</span></div>
          </div>
          <div class="alert alert-danger d-none" id="sa-step2-error" role="alert"></div>
          <button class="btn lhp-btn-primary-solid w-100" id="sa-verify-btn" type="button">
            <span class="btn-label"><i class="bi bi-shield-check me-2"></i>Verify &amp; Activate Access</span>
            <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Verifying…</span>
          </button>
        </div>
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
