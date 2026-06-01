<?php // Included in admin dashboard view-create-user ?>
<div class="lhp-card" style="max-width:600px">
  <div class="alert alert-danger d-none" id="inline-cu-error"></div>
  <div class="alert alert-success d-none" id="inline-cu-success"></div>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label fw-semibold" for="icu-name">Full Name *</label>
      <div class="input-group"><span class="input-group-text"><i class="bi bi-person"></i></span>
      <input type="text" class="form-control" id="icu-name"></div></div>
    <div class="col-md-6"><label class="form-label fw-semibold" for="icu-email">Email *</label>
      <div class="input-group"><span class="input-group-text"><i class="bi bi-envelope"></i></span>
      <input type="email" class="form-control" id="icu-email"></div></div>
    <div class="col-md-6"><label class="form-label fw-semibold" for="icu-role">Role *</label>
      <select class="form-select" id="icu-role">
        <option value="lighthouse_planner">Estate Planner</option>
        <option value="lighthouse_parent">Parent / Client</option>
        <option value="lighthouse_law_firm">Law Firm</option>
        <option value="lhp_super_admin">Super Admin</option>
      </select></div>
    <div class="col-md-6"><label class="form-label fw-semibold" for="icu-firm">Firm Name</label>
      <input type="text" class="form-control" id="icu-firm" placeholder="Optional"></div>
    <div class="col-12"><label class="form-label fw-semibold" for="icu-phone">Phone</label>
      <input type="tel" class="form-control" id="icu-phone"></div>
  </div>
  <div class="mt-4">
    <button class="btn lhp-btn-primary-solid" id="lhp-inline-create-user">
      <span class="btn-label"><i class="bi bi-check-circle me-2"></i>Create User &amp; Send Invite</span>
      <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Creating…</span>
    </button>
  </div>
</div>
