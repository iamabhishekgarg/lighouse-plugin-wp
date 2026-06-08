<?php
$user        = wp_get_current_user();
$firm        = get_user_meta($user->ID,'_flp_firm_name',true);
$ep_subtitle = $firm ?: 'Estate Planner';
$initials    = strtoupper(implode('', array_map(function($w){ return substr($w,0,1); }, preg_split('/\s+/', trim($user->display_name)))));
$first_name  = get_user_meta($user->ID,'first_name',true) ?: explode(' ',trim($user->display_name))[0];
$logo_id     = (int) get_user_meta($user->ID,'_flp_logo_id',true);
$logo_url    = $logo_id ? wp_get_attachment_url($logo_id) : '';
?>
<div class="lhp-root">
<div class="lhp-toast-container position-fixed top-0 end-0 p-3" style="z-index:11000"></div>

<div class="lhp-portal-layout">

  <!-- ══ SIDEBAR ═══════════════════════════════════════════ -->
  <aside class="lhp-sidebar" id="lhp-sidebar">
    <div class="lhp-sidebar-header">
      <div class="lhp-sidebar-brand">
        <i class="bi bi-house-heart-fill lhp-brand-icon-sm"></i>
        <span>Family Lighthouse</span>
      </div>
      <button class="lhp-sidebar-toggle d-xl-none" id="lhp-sidebar-close" aria-label="Close sidebar"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </div>

    <div class="lhp-sidebar-user">
      <?php if ($logo_url): ?>
        <div class="lhp-avatar-lg lhp-avatar-logo"><img src="<?php echo esc_url($logo_url); ?>" alt="Logo" class="lhp-sidebar-avatar-img"></div>
      <?php else: ?>
        <div class="lhp-avatar-lg"><?php echo esc_html($first_name); ?></div>
      <?php endif; ?>
      <div class="lhp-sidebar-user-info">
        <div class="fw-semibold text-white lhp-text-truncate"><?php echo esc_html($user->display_name); ?></div>
        <?php if($firm): ?><div class="lhp-sidebar-firm"><?php echo esc_html($firm); ?></div><?php endif; ?>
      </div>
    </div>
    <nav class="lhp-sidebar-nav">
      <div class="lhp-sidebar-nav-label">Workspace</div>
      <a href="#" class="lhp-nav-item active" data-view="ep-profile"><i class="bi bi-person-circle"></i><span>My Profile</span></a>
      <a href="#" class="lhp-nav-item" data-view="ep-links"><i class="bi bi-share-fill"></i><span>Share Links</span></a>
      <a href="#" class="lhp-nav-item" data-view="ep-reviews" id="ep-reviews-nav"><i class="bi bi-shield-check"></i><span>Pending Reviews <span class="badge bg-danger ms-1 d-none" id="ep-reviews-badge">0</span></span></a>
    </nav>
    <div class="lhp-sidebar-footer">
      <button class="lhp-nav-item lhp-logout-btn w-100 border-0 bg-transparent text-start" id="lhp-logout">
        <i class="bi bi-box-arrow-left"></i><span>Sign Out</span>
      </button>
    </div>
  </aside>
  <div class="lhp-sidebar-backdrop d-xl-none" id="lhp-sidebar-backdrop"></div>

  <!-- TOPBAR (mobile) -->
  <div class="lhp-topbar d-xl-none">
    <button class="lhp-topbar-menu" id="lhp-sidebar-open"><i class="bi bi-list"></i></button>
    <div class="lhp-topbar-brand"><i class="bi bi-house-heart-fill"></i> Family Lighthouse</div>
    <div class="lhp-avatar-sm"><?php echo esc_html($initials); ?></div>
  </div>

  <!-- ══ MAIN CONTENT ═══════════════════════════════════════ -->
  <main class="lhp-main-content">

    <!-- ─── PROFILE VIEW ──────────────────────────────────── -->
    <div class="lhp-view active" id="view-ep-profile">
      <div class="lhp-page-header">
        <div><h1 class="lhp-page-title">My Profile</h1><p class="lhp-page-sub">Your estate planner profile shown to clients</p></div>
      </div>
      <div class="lhp-card" style="max-width:680px">
        <div class="lhp-card-section-title"><i class="bi bi-building me-2"></i>Estate Planner Profile</div>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Firm Logo</label>
            <div class="d-flex align-items-center gap-3">
              <div id="ep-logo-preview" style="width:100px;height:100px;border-radius:10px;border:2px dashed var(--lhp-border);display:flex;align-items:center;justify-content:center;overflow:hidden;background:#f8fafc;padding:6px">
                <?php if ($logo_url): ?>
                  <img src="<?php echo esc_url($logo_url); ?>" style="max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain">
                <?php else: ?>
                  <i class="bi bi-image text-muted" style="font-size:2rem"></i>
                <?php endif; ?>
              </div>
              <div>
                <button class="btn btn-sm btn-outline-primary" id="ep-upload-logo"><i class="bi bi-upload me-1"></i>Upload Logo</button>
                <input type="file" id="ep-logo-input" accept="image/png,image/jpeg,image/svg+xml,image/webp" style="display:none">
                <p class="text-muted small mt-1 mb-0">JPG, PNG, SVG or WEBP. Square recommended.</p>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold" for="ep-name">Full Name</label>
            <div class="input-group"><span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" class="form-control" id="ep-name"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold" for="ep-email">Email <small class="text-muted fw-normal">(read only)</small></label>
            <div class="input-group"><span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" id="ep-email" readonly></div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold" for="ep-firm">Firm Name <span class="text-danger">*</span></label>
            <div class="input-group"><span class="input-group-text"><i class="bi bi-building"></i></span>
            <input type="text" class="form-control" id="ep-firm" placeholder="Your firm or practice name"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold" for="ep-phone">Phone</label>
            <div class="input-group"><span class="input-group-text"><i class="bi bi-telephone"></i></span>
            <input type="tel" class="form-control" id="ep-phone"></div>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold" for="ep-bio">About / Bio</label>
            <textarea class="form-control" id="ep-bio" rows="3" placeholder="Brief description shown to clients on their welcome page"></textarea>
          </div>
        </div>
        <div class="mt-4">
          <button class="btn lhp-btn-primary-solid" id="lhp-save-ep-profile">
            <i class="bi bi-check-circle me-2"></i>Save Profile
          </button>
        </div>
      </div>
    </div>

    <!-- ─── SHARE LINKS VIEW ───────────────────────────────── -->
    <div class="lhp-view" id="view-ep-links">
      <div class="lhp-page-header">
        <div><h1 class="lhp-page-title">Share Links</h1><p class="lhp-page-sub">Invite clients to create their own co-branded Family Lighthouse</p></div>
        <button class="btn lhp-btn-primary-solid" id="ep-generate-link">
          <i class="bi bi-plus-circle-fill me-2"></i>Invite Specific Client
        </button>
      </div>

      <!-- Stat cards -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-lg-4">
          <div class="lhp-stat-card lhp-stat-primary">
            <div class="lhp-stat-icon"><i class="bi bi-link-45deg"></i></div>
            <div><div class="lhp-stat-num" id="ep-stat-total-links">0</div><div class="lhp-stat-lbl">Links Shared</div></div>
          </div>
        </div>
        <div class="col-6 col-lg-4">
          <div class="lhp-stat-card lhp-stat-success">
            <div class="lhp-stat-icon"><i class="bi bi-person-check-fill"></i></div>
            <div><div class="lhp-stat-num" id="ep-stat-clients-created">0</div><div class="lhp-stat-lbl">Clients Created</div></div>
          </div>
        </div>
        <div class="col-6 col-lg-4">
          <div class="lhp-stat-card lhp-stat-purple">
            <div class="lhp-stat-icon"><i class="bi bi-folder2-open"></i></div>
            <div><div class="lhp-stat-num" id="ep-stat-active">0</div><div class="lhp-stat-lbl">With Family Lighthouse</div></div>
          </div>
        </div>
      </div>

      <!-- Per-client invite links table -->
      <div class="lhp-card-section-title ms-1 mb-2"><i class="bi bi-envelope-paper me-2"></i>Personal Client Invitations</div>
      <div class="lhp-card p-0">
        <div class="table-responsive">
          <table class="table lhp-table align-middle mb-0">
            <thead>
              <tr><th>Client</th><th>Status</th><th>Sent</th><th>Actions</th></tr>
            </thead>
            <tbody id="ep-links-body">
              <tr><td colspan="4" class="text-center py-5 text-muted">
                <i class="bi bi-envelope-paper fs-1 d-block mb-2 opacity-25"></i>
                No personal invitations sent yet. Click "Invite Specific Client" to send one.
              </td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ─── PENDING REVIEWS VIEW ──────────────────────── -->
    <div class="lhp-view" id="view-ep-reviews">
      <div class="lhp-page-header">
        <div>
          <h1 class="lhp-page-title">Pending Reviews</h1>
          <p class="lhp-page-sub">Clients who submitted death verification documents awaiting your approval</p>
        </div>
      </div>
      <div id="ep-reviews-list">
        <div class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm text-primary me-2"></span>Loading…</div>
      </div>
    </div>

  </main>
</div><!-- .lhp-portal-layout -->

<!-- ═══ MODAL: Generate Link ════════════════════════════ -->
<div class="modal fade" id="ep-link-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content lhp-modal-content">
      <div class="modal-header lhp-modal-header">
        <h5 class="modal-title"><i class="bi bi-envelope-paper me-2"></i>Invite Client</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body lhp-modal-body">
        <p class="text-muted small">Enter your client's email address. They will receive an invitation to create their own Family Lighthouse.</p>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="ep-link-name">Client Name</label>
          <input type="text" class="form-control" id="ep-link-name" placeholder="Client's Full Name">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="ep-link-email">Client Email *</label>
          <input type="email" class="form-control" id="ep-link-email" placeholder="client@email.com">
        </div>
        <div class="alert alert-danger d-none" id="ep-link-error" role="alert" aria-live="assertive"></div>
        <div class="alert alert-success d-none" id="ep-link-success" role="status" aria-live="polite"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn lhp-btn-primary-solid" id="ep-do-generate-link">
          <span class="btn-label"><i class="bi bi-send me-2"></i>Send Invitation</span>
          <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Sending…</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ MODAL: Death Verification ═══════════════════════ -->
<div class="modal fade" id="lhp-death-verify-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content lhp-modal-content">
      <div class="modal-header lhp-modal-header">
        <h5 class="modal-title"><i class="bi bi-file-earmark-medical me-2"></i>Verify &amp; Activate Access</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body lhp-modal-body">
        <div class="alert alert-warning d-flex gap-2 align-items-start mb-3">
          <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
          <span>Activating access transfers this Lighthouse to the designated person. Upload a verification document (death certificate, obituary, or legal notice) to confirm the owner has passed.</span>
        </div>
        <input type="hidden" id="dv-entry-id">
        <input type="hidden" id="dv-entry-type" value="delegated">
        <input type="hidden" id="dv-record-id-dv" value="0">
        <div class="mb-3">
          <label class="form-label fw-semibold">Verification Document <span class="text-danger">*</span></label>
          <div class="lhp-upload-drop-zone" id="dv-drop-zone">
            <i class="bi bi-file-earmark-arrow-up fs-3 text-muted mb-2 d-block"></i>
            <div class="text-muted small">Drag &amp; drop or <span class="lhp-link" id="dv-browse-trigger">browse</span></div>
            <div class="text-muted" style="font-size:11px">PDF, JPG, PNG — max 10 MB</div>
            <input type="file" id="dv-file-input" accept=".pdf,.jpg,.jpeg,.png" class="d-none">
          </div>
          <div id="dv-file-preview" class="mt-2 d-none">
            <div class="d-flex align-items-center gap-2 p-2 border rounded">
              <i class="bi bi-file-earmark-check text-success"></i>
              <span id="dv-file-name" class="small fw-semibold text-truncate flex-grow-1"></span>
              <button type="button" class="btn btn-sm btn-link text-danger p-0" id="dv-file-clear" aria-label="Remove file"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </div>
          </div>
          <div class="text-danger small mt-1 d-none" id="dv-file-error">Please attach a verification document before activating.</div>
        </div>
        <div class="mb-2">
          <label class="form-label fw-semibold">Notes <span class="text-muted fw-normal">(optional)</span></label>
          <textarea class="form-control" id="dv-notes" rows="2" placeholder="e.g. Death certificate issued by County of LA, dated June 2026"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn lhp-btn-primary-solid" id="dv-confirm-btn" type="button">
          <span class="btn-label"><i class="bi bi-check-circle me-1"></i>Confirm &amp; Activate</span>
          <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-1"></span>Activating…</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ MODAL: Multi-Step Record Form ════════════════════ -->
<div class="modal fade" id="lhp-form-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content lhp-modal-content">

      <!-- Step bar -->
      <div class="lhp-step-bar">
        <div class="lhp-step-item" data-step="1"><div class="lhp-step-num">1</div><div class="lhp-step-lbl">Subject</div></div>
        <div class="lhp-step-connector"></div>
        <div class="lhp-step-item" data-step="2"><div class="lhp-step-num">2</div><div class="lhp-step-lbl">Beneficiaries</div></div>
        <div class="lhp-step-connector"></div>
        <div class="lhp-step-item" data-step="3"><div class="lhp-step-num">3</div><div class="lhp-step-lbl">Access</div></div>
        <div class="lhp-step-connector"></div>
        <div class="lhp-step-item" data-step="4"><div class="lhp-step-num">4</div><div class="lhp-step-lbl">Items &amp; Burial</div></div>
        <div class="lhp-step-connector"></div>
        <div class="lhp-step-item" data-step="5"><div class="lhp-step-num">5</div><div class="lhp-step-lbl">Financials</div></div>
        <div class="lhp-step-connector"></div>
        <div class="lhp-step-item" data-step="6"><div class="lhp-step-num">6</div><div class="lhp-step-lbl">Delegated</div></div>
        <div class="lhp-step-connector"></div>
        <div class="lhp-step-item" data-step="7"><div class="lhp-step-num">7</div><div class="lhp-step-lbl">Review</div></div>
      </div>

      <!-- Body -->
      <div class="modal-body lhp-modal-body">
        <?php include LHP_DIR . 'templates/partials/form-steps.php'; ?>
      </div>

      <!-- Footer -->
      <div class="modal-footer lhp-modal-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <small class="text-muted" id="ms-progress-text">Step 1 of 7</small>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary" id="ms-prev" style="visibility:hidden"><i class="bi bi-arrow-left me-1"></i>Back</button>
          <button class="btn lhp-btn-primary-solid" id="ms-next">Continue <i class="bi bi-arrow-right ms-1"></i></button>
        </div>
      </div>

      <!-- Hidden state -->
      <input type="hidden" id="lhp-record-id" value="0">
      <input type="hidden" id="lhp-record-client-id" value="0">
    </div>
  </div>
</div>

<!-- ═══ MODAL: Confirm Delete ════════════════════════════ -->
<div class="modal fade" id="lhp-confirm-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content lhp-modal-content">
      <div class="modal-header lhp-modal-header">
        <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Confirm Delete</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body lhp-modal-body">
        <p id="confirm-modal-msg" class="mb-0"></p>
        <input type="hidden" id="confirm-delete-id">
        <input type="hidden" id="confirm-delete-type">
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger" id="lhp-confirm-ok">
          <span class="btn-label"><i class="bi bi-trash me-1"></i>Delete</span>
          <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-1"></span>Deleting…</span>
        </button>
      </div>
    </div>
  </div>
</div>

</div><!-- .lhp-root -->
