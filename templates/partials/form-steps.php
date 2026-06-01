<?php // templates/partials/form-steps.php — v1.3 (relationship + validation fixes) ?>

<!-- Step 1: Subject -->
<div class="lhp-modal-step active" id="ms-1">
  <div class="lhp-step-heading"><i class="bi bi-person-vcard"></i>Who Is This Lighthouse For?</div>
  <div class="row g-3">

    <!-- ── NEW: Relationship to creator ── -->
    <div class="col-12">
      <label class="form-label fw-semibold" for="s-relationship-to-owner">Relationship to You <span class="text-danger">*</span></label>
      <select class="form-select" id="s-relationship-to-owner">
        <option value="">— Select Relationship —</option>
        <option value="self">Myself</option>
        <option value="parent">Parent / Guardian</option>
        <option value="spouse">Spouse / Partner</option>
        <option value="child">Adult Child</option>
        <option value="sibling">Sibling</option>
        <option value="grandparent">Grandparent</option>
        <option value="in-law">In-Law</option>
        <option value="friend">Close Friend</option>

        <option value="other">Other</option>
      </select>
      <div class="form-text text-muted"><i class="bi bi-info-circle me-1"></i>Choose who this Lighthouse record is being created for.</div>
    </div>

    <div class="col-md-6">
      <label class="form-label fw-semibold" for="s-full-name">Full Legal Name <span class="text-danger">*</span></label>
      <input type="text" class="form-control" id="s-full-name" placeholder="Full Legal Name">
      <div class="invalid-feedback">Full name is required.</div>
    </div>
    <div class="col-md-6">
      <label class="form-label fw-semibold" for="s-pref-name">Preferred Name</label>
      <input type="text" class="form-control" id="s-pref-name" placeholder="Nickname / Preferred">
    </div>
    <div class="col-md-4">
      <label class="form-label fw-semibold" for="s-dob">Date of Birth <span class="text-danger">*</span></label>
      <input type="date" class="form-control" id="s-dob">
      <div class="invalid-feedback">Date of birth is required.</div>
    </div>
    <div class="col-md-4">
      <label class="form-label fw-semibold" for="s-phone">Phone</label>
      <input type="tel" class="form-control lhp-phone-field" id="s-phone" placeholder="+1 (555) 000-0000">
      <div class="form-check mt-1"><input type="checkbox" class="form-check-input" data-optout="phone" id="oo-s-phone"><label class="form-check-label small text-muted" for="oo-s-phone">Prefer not to include</label></div>
      <div class="invalid-feedback">Enter a valid phone number (10+ digits).</div>
    </div>
    <div class="col-md-4">
      <label class="form-label fw-semibold" for="s-email">Email</label>
      <input type="email" class="form-control" id="s-email" placeholder="Optional">
      <div class="form-check mt-1"><input type="checkbox" class="form-check-input" data-optout="email" id="oo-s-email"><label class="form-check-label small text-muted" for="oo-s-email">Prefer not to include</label></div>
    </div>
    <div class="col-12">
      <label class="form-label fw-semibold" for="s-address">Primary Address <span class="text-danger">*</span></label>
      <input type="text" class="form-control" id="s-address" placeholder="Street, City, State, ZIP">
      <div class="invalid-feedback">Primary address is required.</div>
    </div>
  </div>

</div>

<!-- Step 2: Beneficiaries -->
<div class="lhp-modal-step" id="ms-2">
  <div class="lhp-step-heading"><i class="bi bi-people"></i>Beneficiaries</div>
  <div class="lhp-repeater">
    <table class="table lhp-repeater-table">
      <thead><tr><th>Full Name <span class="text-danger">*</span></th><th>Relationship <span class="text-danger">*</span></th><th>Email</th><th>Phone</th><th style="width:40px"></th></tr></thead>
      <tbody id="children-body"></tbody>
    </table>
    <button class="btn btn-sm btn-outline-primary lhp-add-row mt-2" data-table="children"><i class="bi bi-plus-circle me-1"></i>Add Beneficiary</button>
  </div>
</div>

<!-- Step 3: Access & Unlock -->
<div class="lhp-modal-step" id="ms-3">
  <div class="lhp-step-heading"><i class="bi bi-shield-lock"></i>Access and Unlocking</div>
  <h6 class="fw-semibold mb-2">Who Can Access This Lighthouse?</h6>
  <div class="lhp-repeater mb-4">
    <table class="table lhp-repeater-table">
      <thead><tr><th>Full Name</th><th>Privilege</th><th>Email</th><th>Phone</th><th style="width:40px"></th></tr></thead>
      <tbody id="access-body"></tbody>
    </table>
    <button class="btn btn-sm btn-outline-primary lhp-add-row mt-2" data-table="access"><i class="bi bi-plus-circle me-1"></i>Add Person</button>
  </div>
</div>

<!-- Step 4: Personal Items & Burial -->
<div class="lhp-modal-step" id="ms-4">
  <div class="lhp-step-heading"><i class="bi bi-gift"></i>Personal Items &amp; End of Life Preferences</div>
  <h6 class="fw-semibold mb-2">Personal Items &amp; Keepsakes</h6>
  <div class="lhp-repeater mb-4">
    <table class="table lhp-repeater-table">
      <thead><tr><th>Item Description</th><th>Who Should Receive It</th><th>Notes</th><th style="width:40px"></th></tr></thead>
      <tbody id="items-body"></tbody>
    </table>
    <button class="btn btn-sm btn-outline-primary lhp-add-row mt-2" data-table="items"><i class="bi bi-plus-circle me-1"></i>Add Item</button>
  </div>
  <h6 class="fw-semibold mb-3">Burial &amp; Funeral Wishes</h6>
  <div class="lhp-choice-cards mb-3">
    <label class="lhp-choice-card"><input type="radio" name="burial_pref" value="burial"><div class="lhp-choice-inner"><i class="bi bi-flower1"></i><span>Burial</span></div></label>
    <label class="lhp-choice-card"><input type="radio" name="burial_pref" value="cremation"><div class="lhp-choice-inner"><i class="bi bi-wind"></i><span>Cremation</span></div></label>
  </div>
  <div class="mb-3 burial-ashes" style="display:none"><label class="form-label fw-semibold" for="burial-ashes">Ashes Management</label><input type="text" class="form-control" id="burial-ashes" placeholder="e.g. Scatter at sea, kept in urn, etc."></div>
  <div class="mb-3"><label class="form-label fw-semibold" for="burial-requests">Specific Requests</label><textarea class="form-control" id="burial-requests" rows="3" placeholder="e.g. Music, flowers, dress code, readings…"></textarea></div>
  <div class="mb-3"><label class="form-label fw-semibold" for="funeral-home">Preferred Funeral Home</label><input type="text" class="form-control" id="funeral-home" placeholder="e.g. Smith &amp; Sons Funeral Home"></div>
  <div class="mb-3"><label class="form-label fw-semibold" for="burial-donation">Charitable Donations</label><input type="text" class="form-control" id="burial-donation" placeholder="e.g. Red Cross, local food bank"></div>
  <div class="mb-3"><label class="form-label fw-semibold" for="burial-other">Other Wishes</label><input type="text" class="form-control" id="burial-other" placeholder="e.g. No black clothing, celebrate outdoors"></div>
</div>

<!-- Step 5: Financials & Letters -->
<div class="lhp-modal-step" id="ms-5">
  <div class="lhp-step-heading"><i class="bi bi-bank"></i>Financial Accounts &amp; Letters</div>
  <h6 class="fw-semibold mb-2">Bank Accounts</h6>
  <div class="alert alert-info py-2 mb-3 small"><i class="bi bi-shield-lock-fill me-2"></i><strong>Security note:</strong> Enter only the <strong>last 4 digits</strong> of each account number — never the full number.</div>
  <div class="lhp-repeater mb-4">
    <table class="table lhp-repeater-table">
      <thead><tr><th>Financial Institution</th><th>Account Type</th><th>Last 4 Digits</th><th style="width:40px"></th></tr></thead>
      <tbody id="bank-body"></tbody>
    </table>
    <button class="btn btn-sm btn-outline-primary lhp-add-row mt-2" data-table="bank"><i class="bi bi-plus-circle me-1"></i>Add Account</button>
  </div>
  <h6 class="fw-semibold mb-2">Life Insurance</h6>
  <div class="lhp-repeater mb-4">
    <table class="table lhp-repeater-table">
      <thead><tr><th>Provider</th><th>Policy Identifier</th><th>Notes</th><th style="width:40px"></th></tr></thead>
      <tbody id="insurance-body"></tbody>
    </table>
    <button class="btn btn-sm btn-outline-primary lhp-add-row mt-2" data-table="insurance"><i class="bi bi-plus-circle me-1"></i>Add Policy</button>
  </div>
  <h6 class="fw-semibold mb-2">Letters &amp; Messages</h6>
  <div class="lhp-repeater mb-4">
    <table class="table lhp-repeater-table">
      <thead><tr><th>Recipient</th><th>Owner</th><th>Letter</th><th style="width:40px"></th></tr></thead>
      <tbody id="letters-body"></tbody>
    </table>
    <button class="btn btn-sm btn-outline-primary lhp-add-row mt-2" data-table="letters"><i class="bi bi-plus-circle me-1"></i>Add Letter</button>
  </div>

  <h6 class="fw-semibold mb-1">Supporting Documents <span class="text-muted fw-normal small">(optional)</span></h6>
  <p class="text-muted small mb-2">Upload wills, photos, IDs, or any supporting files. PDF, DOC, DOCX, JPG, PNG — max 10 MB each.</p>
  <input type="file" id="ms5-file-input" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display:none">
  <div class="lhp-upload-zone" id="ms5-upload-zone">
    <i class="bi bi-cloud-arrow-up-fill"></i>
    <div class="fw-semibold">Click to select files</div>
    <small class="text-muted">Multiple files allowed</small>
  </div>
  <div id="ms5-pending-list" class="mt-2"></div>
  <div id="ms5-uploaded-list" class="mt-2"></div>
</div>

<!-- Step 6: Delegated Access -->
<div class="lhp-modal-step" id="ms-6">
  <div class="lhp-step-heading"><i class="bi bi-people-fill"></i>Delegated Access</div>
  <p class="text-muted mb-4">Grant trusted individuals access to this Lighthouse. You control <strong>who</strong> gets access and <strong>when</strong> it becomes active.</p>
  <div id="delegated-list" class="mb-3"></div>
  <button class="btn btn-outline-primary" id="lhp-add-delegated"><i class="bi bi-person-plus-fill me-2"></i>Add Delegated User</button>

  <div class="lhp-del-add-form mt-4 p-3 border rounded-3" id="del-add-form" style="display:none;background:var(--lhp-light)">
    <h6 class="fw-bold mb-3"><i class="bi bi-person-plus me-2"></i>Add Trusted Person</h6>
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label fw-semibold" for="del-name">Full Name *</label><input type="text" class="form-control" id="del-name" placeholder="Full Name"></div>
      <div class="col-md-6"><label class="form-label fw-semibold" for="del-email">Email *</label><input type="email" class="form-control" id="del-email" placeholder="their@email.com"></div>
      <div class="col-md-6"><label class="form-label fw-semibold" for="del-relationship">Relationship</label><select class="form-select" id="del-relationship"><option value="">Select</option><option>Spouse</option><option>Parent</option><option>Child</option><option>Sibling</option><option>Friend</option><option>Executor</option><option>Other</option></select></div>
      <div class="col-md-6">
        <label class="form-label fw-semibold" for="del-condition-type">When Can They Access?</label>
        <select class="form-select" id="del-condition-type">
          <option value="immediate">Immediately (right now)</option>
          <option value="date">After a specific date</option>
          <option value="manual">Manually activated (event-based)</option>
        </select>
      </div>
      <div class="col-12 del-date-row" style="display:none">
        <label class="form-label fw-semibold" for="del-condition-date">Access Activation Date</label>
        <input type="date" class="form-control" id="del-condition-date" style="max-width:280px">
        <small class="text-muted">Access will become active on or after this date.</small>
      </div>
      <div class="col-12 del-event-row" style="display:none">
        <label class="form-label fw-semibold" for="del-condition-event">Describe the Event</label>
        <input type="text" class="form-control" id="del-condition-event" placeholder="e.g. After my death, After my hospitalization…">
        <small class="text-muted">Access must be manually activated by you or an estate planner.</small>
      </div>
    </div>
    <div class="d-flex gap-2 mt-3">
      <button class="btn lhp-btn-primary-solid" id="del-save-btn"><span class="btn-label"><i class="bi bi-check-circle me-2"></i>Add Person</span><span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Adding…</span></button>
      <button class="btn btn-outline-secondary" id="del-cancel-btn">Cancel</button>
    </div>
    <div class="alert alert-danger d-none mt-2" id="del-error"></div>
  </div>

</div>

<!-- Step 7: Review & Save -->
<div class="lhp-modal-step" id="ms-7">
  <div class="lhp-step-heading"><i class="bi bi-clipboard2-check"></i>Review &amp; Save</div>
  <p class="text-muted mb-3">Please review all the information below before saving. Use the back button to correct anything.</p>
  <div id="lhp-review-summary" class="lhp-review-grid mb-4"></div>
  <div class="lhp-acknowledge-box">
    <label class="d-flex gap-3 align-items-start">
      <input type="checkbox" class="form-check-input mt-1 flex-shrink-0" id="lhp-acknowledge" style="width:20px;height:20px">
      <span>I understand this Lighthouse is <strong>not a will</strong> and is not a legal document. It does not replace estate planning or professional legal counsel.</span>
    </label>
  </div>
  <div class="alert alert-danger d-none" id="form-error"></div>
  <div class="alert alert-success d-none" id="form-success"></div>
</div>
