<?php
// Access controlled by class-lh-auth.php template_redirect
$user = wp_get_current_user();
$role = LH_Auth::current_role();
$initials   = strtoupper(implode('', array_map(function($w){ return substr($w,0,1); }, preg_split('/\s+/', trim($user->display_name)))));
$first_name = get_user_meta( $user->ID, 'first_name', true ) ?: explode( ' ', trim( $user->display_name ) )[0];
$is_super  = in_array( $role, [ 'lhp_super_admin', 'administrator' ] );
?>
<div class="lhp-root">
<div class="lhp-toast-container position-fixed top-0 end-0 p-3" style="z-index:11000"></div>

<div class="lhp-portal-layout">

  <!-- ═══ SIDEBAR ══════════════════════════════════════ -->
  <aside class="lhp-sidebar" id="lhp-sidebar">
    <div class="lhp-sidebar-header">
      <div class="lhp-sidebar-brand">
        <i class="bi bi-house-heart-fill lhp-brand-icon-sm"></i>
        <span>Family Lighthouse</span>
      </div>
      <button class="lhp-sidebar-toggle d-xl-none" id="lhp-sidebar-close"><i class="bi bi-x-lg"></i></button>
    </div>

    <div class="lhp-sidebar-user">
      <div class="lhp-avatar-lg"><?php echo esc_html($first_name); ?></div>
      <div class="lhp-sidebar-user-info">
        <div class="fw-semibold text-white lhp-text-truncate"><?php echo esc_html($user->display_name); ?></div>
        <div class="lhp-sidebar-badge">
          <?php if($is_super): ?><i class="bi bi-shield-fill-check me-1"></i>Super Admin
          <?php else: ?><i class="bi bi-building me-1"></i>Law Firm<?php endif; ?>
        </div>
      </div>
    </div>

    <nav class="lhp-sidebar-nav">
      <div class="lhp-sidebar-nav-label">Platform Control</div>
      <a href="#" class="lhp-nav-item active" data-view="overview"><i class="bi bi-grid-1x2-fill"></i><span>Overview</span></a>
      <a href="#" class="lhp-nav-item" data-view="records"><i class="bi bi-folder2-open"></i><span>All Records</span></a>
      <a href="#" class="lhp-nav-item" data-view="planners"><i class="bi bi-briefcase-fill"></i><span>Planners</span></a>
      <a href="#" class="lhp-nav-item" data-view="parents"><i class="bi bi-people-fill"></i><span>Clients</span></a>
      <a href="#" class="lhp-nav-item" data-view="firms"><i class="bi bi-building"></i><span>Law Firms</span></a>
      <?php if($is_super): ?>
      <div class="lhp-sidebar-nav-label mt-3">Administration</div>
      <a href="#" class="lhp-nav-item" data-view="activity"><i class="bi bi-activity"></i><span>Activity Log</span></a>
      <a href="#" class="lhp-nav-item" data-view="create-user"><i class="bi bi-person-plus-fill"></i><span>Create User</span></a>
      <?php endif; ?>
    </nav>

    <div class="lhp-sidebar-footer">
      <button class="lhp-nav-item lhp-logout-btn w-100 border-0 bg-transparent text-start" id="lhp-logout">
        <i class="bi bi-box-arrow-left"></i><span>Sign Out</span>
      </button>
    </div>
  </aside>
  <div class="lhp-sidebar-backdrop d-xl-none" id="lhp-sidebar-backdrop"></div>

  <!-- TOPBAR mobile -->
  <div class="lhp-topbar d-xl-none">
    <button class="lhp-topbar-menu" id="lhp-sidebar-open"><i class="bi bi-list"></i></button>
    <div class="lhp-topbar-brand"><i class="bi bi-house-heart-fill"></i> Admin Panel</div>
    <div class="lhp-avatar-sm"><?php echo esc_html($initials); ?></div>
  </div>

  <!-- ═══ MAIN ══════════════════════════════════════════ -->
  <main class="lhp-main-content">

    <!-- ─── OVERVIEW ───────────────────────────────────── -->
    <div class="lhp-view active" id="view-overview">
      <div class="lhp-page-header">
        <div>
          <h1 class="lhp-page-title">Platform Overview</h1>
          <p class="lhp-page-sub">Full system control — Family Lighthouse Portal</p>
        </div>
        <span class="lhp-admin-role-badge"><i class="bi bi-shield-fill-check me-1"></i><?php echo $is_super ? 'Super Admin' : 'Law Firm Admin'; ?></span>
      </div>


      <!-- Demo data controls -->
      <div class="lhp-card mb-4" id="demo-controls-card">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
          <div>
            <div class="fw-bold mb-1"><i class="bi bi-database-fill me-2 text-primary"></i>Demo Data</div>
            <div class="text-muted small">Load sample clients &amp; records to test the platform. Clear anytime.</div>
          </div>
          <div class="d-flex gap-2">
            <button class="btn lhp-btn-primary-solid" id="lhp-seed-demo">
              <span class="btn-label"><i class="bi bi-database-fill-add me-2"></i>Load Demo Data</span>
              <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Creating…</span>
            </button>
            <button class="btn btn-outline-danger" id="lhp-clear-demo">
              <span class="btn-label"><i class="bi bi-trash me-2"></i>Clear All Demo Data</span>
              <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Clearing…</span>
            </button>
          </div>
        </div>
        <div class="alert alert-success d-none mt-3 mb-0" id="seed-success"></div>
        <div class="alert alert-danger d-none mt-3 mb-0" id="seed-error"></div>
      </div>
      <!-- Stat cards -->
      <div class="row g-3 mb-4" id="admin-stats-row">
        <div class="col-6 col-lg-3">
          <div class="lhp-stat-card lhp-stat-primary">
            <div class="lhp-stat-icon"><i class="bi bi-folder2"></i></div>
            <div><div class="lhp-stat-num" id="as-records">—</div><div class="lhp-stat-lbl">Total Records</div></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="lhp-stat-card lhp-stat-success">
            <div class="lhp-stat-icon"><i class="bi bi-check-circle-fill"></i></div>
            <div><div class="lhp-stat-num" id="as-complete">—</div><div class="lhp-stat-lbl">Complete</div></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="lhp-stat-card lhp-stat-warn">
            <div class="lhp-stat-icon"><i class="bi bi-briefcase"></i></div>
            <div><div class="lhp-stat-num" id="as-planners">—</div><div class="lhp-stat-lbl">Planners</div></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="lhp-stat-card lhp-stat-purple">
            <div class="lhp-stat-icon"><i class="bi bi-people-fill"></i></div>
            <div><div class="lhp-stat-num" id="as-parents">—</div><div class="lhp-stat-lbl">Clients</div></div>
          </div>
        </div>
      </div>

      <!-- Two-column: avg completion + recent activity -->
      <div class="row g-4">
        <div class="col-lg-4">
          <div class="lhp-card h-100">
            <div class="lhp-card-section-title"><i class="bi bi-pie-chart me-2"></i>Platform Health</div>
            <div class="text-center my-3">
              <div style="position:relative;width:120px;height:120px;margin:0 auto">
                <svg viewBox="0 0 36 36" style="width:120px;height:120px;transform:rotate(-90deg)">
                  <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e2e8f0" stroke-width="3"/>
                  <path id="admin-arc" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#4f6ef7" stroke-width="3" stroke-dasharray="0,100" stroke-linecap="round"/>
                </svg>
                <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column">
                  <strong id="admin-avg-pct" style="font-size:22px;font-weight:800">—%</strong>
                  <small class="text-muted">Avg Complete</small>
                </div>
              </div>
            </div>
            <div class="row text-center g-2 mt-2">
              <div class="col-6"><div class="fw-bold fs-5" id="ph-complete">—</div><small class="text-muted">Complete</small></div>
              <div class="col-6"><div class="fw-bold fs-5" id="ph-draft">—</div><small class="text-muted">In Progress</small></div>
            </div>
          </div>
        </div>
        <div class="col-lg-8">
          <div class="lhp-card h-100">
            <div class="lhp-card-section-title"><i class="bi bi-clock-history me-2"></i>Recent Activity</div>
            <div id="admin-activity-feed" style="max-height:280px;overflow-y:auto">
              <div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ─── ALL RECORDS ─────────────────────────────────── -->
    <div class="lhp-view" id="view-records">
      <div class="lhp-page-header">
        <div><h1 class="lhp-page-title">All Records</h1><p class="lhp-page-sub">View and manage every Lighthouse record in the system</p></div>
      </div>
      <div class="lhp-card">
        <div class="lhp-card-toolbar">
          <div class="input-group" style="max-width:300px">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" id="admin-rec-search" placeholder="Search records…">
          </div>
          <select class="form-select form-select-sm" id="admin-rec-filter" style="width:auto">
            <option value="">All Status</option>
            <option value="complete">Complete</option>
            <option value="draft">In Progress</option>
          </select>
        </div>
        <div class="table-responsive">
          <table class="table lhp-table align-middle mb-0">
            <thead><tr>
              <th>Subject</th><th>DOB</th><th>Completion</th><th>Status</th>
              <th>Planner</th><th>Client</th><th>Created</th><th>Actions</th>
            </tr></thead>
            <tbody id="admin-records-body">
              <tr><td colspan="8" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ─── USER VIEWS (planners / parents / firms) ─────── -->
    <?php foreach(['planners','parents','firms'] as $uv): ?>
    <div class="lhp-view" id="view-<?php echo $uv; ?>">
      <div class="lhp-page-header">
        <div>
          <h1 class="lhp-page-title"><?php echo ucfirst($uv); ?></h1>
          <p class="lhp-page-sub">Manage <?php echo $uv; ?> on the platform</p>
        </div>
        <?php if($is_super): ?>
        <button class="btn lhp-btn-primary-solid" data-bs-toggle="modal" data-bs-target="#lhp-create-user-modal" data-preset-role="<?php echo $uv === 'planners' ? 'lighthouse_planner' : ($uv === 'firms' ? 'lighthouse_law_firm' : 'lighthouse_parent'); ?>">
          <i class="bi bi-person-plus-fill me-2"></i>Add <?php echo rtrim($uv,'s'); ?>
        </button>
        <?php endif; ?>
      </div>
      <div class="lhp-card">
        <div class="lhp-card-toolbar">
          <div class="input-group" style="max-width:280px">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control lhp-user-search" data-view="<?php echo $uv; ?>" placeholder="Search…">
          </div>
        </div>
        <div class="table-responsive">
          <table class="table lhp-table align-middle mb-0">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Firm</th><th>Records</th><th>Registered</th><th>Status</th><?php if($is_super):?><th>Actions</th><?php endif;?></tr></thead>
            <tbody class="lhp-user-tbody" id="tbody-<?php echo $uv; ?>">
              <tr><td colspan="8" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- ─── ACTIVITY LOG ────────────────────────────────── -->
    <div class="lhp-view" id="view-activity">
      <div class="lhp-page-header">
        <div><h1 class="lhp-page-title">Activity Log</h1><p class="lhp-page-sub">Monitor all platform events</p></div>
      </div>
      <div class="lhp-card">
        <div id="activity-log-full" style="max-height:600px;overflow-y:auto"></div>
      </div>
    </div>

    <!-- ─── CREATE USER ─────────────────────────────────── -->
    <div class="lhp-view" id="view-create-user">
      <div class="lhp-page-header">
        <div><h1 class="lhp-page-title">Create User</h1><p class="lhp-page-sub">Add a new user to the platform</p></div>
      </div>
      <?php include LHP_DIR . 'templates/tmpl-create-user-form.php'; ?>
    </div>

  </main>
</div><!-- .lhp-portal-layout -->

<!-- ═══ MODAL: Record Detail + Notes + Docs ══════════════ -->
<div class="modal fade" id="lhp-record-detail-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header lhp-modal-header">
        <h5 class="modal-title" id="rd-modal-title">Record Detail</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="rd-modal-body">
        <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ MODAL: Death Verification ════════════════════════ -->
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
          <span>Upload a death certificate, obituary, or legal notice to confirm the owner has passed and activate this person's access.</span>
        </div>
        <input type="hidden" id="dv-entry-id">
        <input type="hidden" id="dv-entry-type" value="access_person">
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

<!-- ═══ MODAL: Confirm Delete User ═══════════════════════ -->
<div class="modal fade" id="lhp-confirm-user-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete User</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Delete <strong id="del-user-name"></strong>? This action <strong>cannot be undone</strong> and will remove all associated data.</p>
        <input type="hidden" id="del-user-id">
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger btn-sm" id="lhp-confirm-del-user"><i class="bi bi-trash me-1"></i>Delete</button>
      </div>
    </div>
  </div>
</div>

<!-- ═══ MODAL: Create User ═══════════════════════════════ -->
<div class="modal fade" id="lhp-create-user-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header lhp-modal-header">
        <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Create New User</h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none" id="cu-error"></div>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="cu-name">Full Name *</label>
          <div class="input-group"><span class="input-group-text"><i class="bi bi-person"></i></span>
          <input type="text" class="form-control" id="cu-name" placeholder="Full Name"></div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="cu-email">Email Address *</label>
          <div class="input-group"><span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input type="email" class="form-control" id="cu-email" placeholder="user@example.com"></div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="cu-role">Role *</label>
          <div class="input-group"><span class="input-group-text"><i class="bi bi-shield"></i></span>
          <select class="form-select" id="cu-role">
            <option value="lighthouse_planner">Estate Planner</option>
            <option value="lighthouse_parent">Parent / Client</option>
            <option value="lighthouse_law_firm">Law Firm</option>
            <option value="lhp_super_admin">Super Admin</option>
          </select></div>
        </div>
        <div class="mb-3 cu-firm-row">
          <label class="form-label fw-semibold" for="cu-firm">Firm / Organization</label>
          <input type="text" class="form-control" id="cu-firm" placeholder="Law Firm Name (optional)">
        </div>
        <div class="mb-0">
          <label class="form-label fw-semibold" for="cu-phone">Phone</label>
          <input type="tel" class="form-control" id="cu-phone" placeholder="+1 (555) 000-0000">
        </div>
      </div>
      <div class="modal-footer">
        <div class="flex-grow-1 small text-muted"><i class="bi bi-info-circle me-1"></i>A temporary password will be emailed to the user.</div>
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn lhp-btn-primary-solid" id="lhp-do-create-user">
          <span class="btn-label"><i class="bi bi-check-circle me-2"></i>Create User</span>
          <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Creating…</span>
        </button>
      </div>
    </div>
  </div>
</div>

</div><!-- .lhp-root -->
