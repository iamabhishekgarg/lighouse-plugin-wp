<?php
// Redirects handled by class-lh-auth.php template_redirect hook before output.
?>
<div class="lhp-root">
<div class="lhp-auth-center-wrap">

  <div class="lhp-auth-center-card">
    <!-- Brand header -->
    <div class="lhp-auth-center-brand">
      <i class="bi bi-house-heart-fill lhp-brand-icon-sm"></i>
      <span class="fw-bold">Family Lighthouse</span>
    </div>

    <!-- STEP 0: Role selection -->
    <div class="lhp-reg-step active" id="reg-step-0">
      <div class="text-center mb-4">
        <h2 class="fw-bold">Create your account</h2>
        <p class="text-muted">Choose how you'll be using our Family Lighthouse</p>
      </div>
      <div class="row g-3 mb-4">
        <div class="col-6">
          <div class="lhp-role-card h-100" data-role="lighthouse_planner">
            <div class="lhp-role-card-inner text-center p-4">
              <div class="lhp-role-icon-wrap mb-3"><i class="bi bi-briefcase-fill"></i></div>
              <h5 class="fw-bold mb-1">Estate Planner</h5>
              <p class="text-muted small mb-0">Manage multiple family records &amp; invite clients</p>
            </div>
          </div>
        </div>
        <div class="col-6">
          <div class="lhp-role-card h-100" data-role="lighthouse_parent">
            <div class="lhp-role-card-inner text-center p-4">
              <div class="lhp-role-icon-wrap mb-3"><i class="bi bi-people-fill"></i></div>
              <h5 class="fw-bold mb-1">Parent / Family</h5>
              <p class="text-muted small mb-0">Manage your own personal Family Lighthouse record</p>
            </div>
          </div>
        </div>
      </div>
      <p class="text-center text-muted" style="font-size:14px">
        Already have an account? <a href="<?php echo esc_url( home_url('/login') ); ?>" class="lhp-link fw-semibold">Sign in</a>
      </p>
    </div>

    <!-- STEP 1A: Planner form -->
    <div class="lhp-reg-step" id="reg-step-planner">
      <div class="mb-4">
        <button class="btn btn-link lhp-back-btn p-0 mb-2" data-target="reg-step-0">
          <i class="bi bi-arrow-left"></i> Back
        </button>
        <h2 class="fw-bold">Estate Planner Account</h2>
        <p class="text-muted">Set up your practice profile</p>
      </div>

      <div class="alert alert-danger d-none align-items-center gap-2" id="planner-alert" role="alert">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
        <span class="lhp-alert-msg"></span>
      </div>

      <div class="row g-3">
        <div class="col-12">
          <label class="form-label fw-semibold" for="planner-name">Full Name *</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" class="form-control" id="planner-name" placeholder="Estate Planner Full Name">
          </div>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold" for="planner-firm">Law Firm Name *</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-building"></i></span>
            <input type="text" class="form-control" id="planner-firm" placeholder="Law Firm Name">
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="planner-email">Work Email *</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" id="planner-email" placeholder="work@firm.com">
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="planner-phone">Phone Number *</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
            <input type="tel" class="form-control" id="planner-phone" placeholder="10-digit number" maxlength="10" inputmode="numeric" pattern="[0-9]{10}">
          </div>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold" for="planner-ref">Internal Reference ID <span class="text-muted fw-normal">(optional)</span></label>
          <input type="text" class="form-control" id="planner-ref" placeholder="e.g. BAR-12345">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="planner-pass">Password *</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control" id="planner-pass" placeholder="Min. 8 characters">
            <button class="btn btn-outline-secondary lhp-toggle-pass" type="button" data-target="planner-pass" tabindex="-1"><i class="bi bi-eye"></i></button>
          </div>
          <div class="lhp-pass-strength mt-1" id="planner-pass-strength" style="display:none">
            <div class="lhp-pass-strength-bar"><div class="lhp-pass-strength-fill" id="planner-strength-fill"></div></div>
            <small id="planner-strength-label" class="text-muted" aria-live="polite"></small>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="planner-pass2">Confirm Password *</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
            <input type="password" class="form-control" id="planner-pass2" placeholder="Repeat password">
            <button class="btn btn-outline-secondary lhp-toggle-pass" type="button" data-target="planner-pass2" tabindex="-1"><i class="bi bi-eye"></i></button>
          </div>
          <div class="invalid-feedback" id="planner-pass-match-err"></div>
        </div>
      </div>

      <div class="d-grid mt-4">
        <button class="btn lhp-btn-primary-solid btn-lg" id="lhp-reg-planner" type="button">
          <span class="btn-label"><i class="bi bi-check-circle me-2"></i>Create Estate Planner Account</span>
          <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Creating…</span>
        </button>
      </div>
    </div>

    <input type="hidden" id="par-invite-token" value="">

    <!-- STEP 1B: Parent form -->
    <div class="lhp-reg-step" id="reg-step-parent">
      <div class="mb-4">
        <button class="btn btn-link lhp-back-btn p-0 mb-2" data-target="reg-step-0">
          <i class="bi bi-arrow-left"></i> Back
        </button>
        <h2 class="fw-bold">Parent / Family Account</h2>
        <p class="text-muted">Set up your personal Family Lighthouse</p>
      </div>

      <div class="alert alert-danger d-none align-items-center gap-2" id="par-alert" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i><span id="par-alert-msg"></span>
      </div>

      <div class="row g-3">
        <div class="col-12">
          <label class="form-label fw-semibold" for="par-name">Full Name *</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" class="form-control" id="par-name" placeholder="Full Name">
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="par-email">Email Address *</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" id="par-email" placeholder="your@email.com">
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="par-phone">Mobile Phone *</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
            <input type="tel" class="form-control" id="par-phone" placeholder="10-digit number" maxlength="10" inputmode="numeric" pattern="[0-9]{10}">
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="par-pass">Password *</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control" id="par-pass" placeholder="Min. 8 characters">
            <button class="btn btn-outline-secondary lhp-toggle-pass" type="button" data-target="par-pass" tabindex="-1"><i class="bi bi-eye"></i></button>
          </div>
          <div class="lhp-pass-strength mt-1" id="par-pass-strength" style="display:none">
            <div class="lhp-pass-strength-bar"><div class="lhp-pass-strength-fill" id="par-strength-fill"></div></div>
            <small id="par-strength-label" class="text-muted" aria-live="polite"></small>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold" for="par-pass2">Confirm Password *</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
            <input type="password" class="form-control" id="par-pass2" placeholder="Repeat password">
            <button class="btn btn-outline-secondary lhp-toggle-pass" type="button" data-target="par-pass2" tabindex="-1"><i class="bi bi-eye"></i></button>
          </div>
        </div>
      </div>

      <div class="d-grid mt-4">
        <button class="btn lhp-btn-primary-solid btn-lg" id="lhp-reg-parent" type="button">
          <span class="btn-label"><i class="bi bi-check-circle me-2"></i>Create My Account</span>
          <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Creating…</span>
        </button>
      </div>
    </div>

  </div><!-- .lhp-auth-center-card -->
</div><!-- .lhp-auth-center-wrap -->
</div><!-- .lhp-root -->
