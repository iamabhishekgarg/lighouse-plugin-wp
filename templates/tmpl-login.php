<?php
// Redirects handled by class-lh-auth.php template_redirect hook before output.
?>
<!-- Login Page -->
<div class="lhp-root">
<div class="lhp-auth-center-wrap">
<div class="lhp-auth-split">

  <!-- LEFT PANEL -->
  <div class="lhp-auth-brand-panel">
    <div class="lhp-auth-brand-inner">
      <div class="lhp-brand-logo">
        <i class="bi bi-house-heart-fill"></i>
        <span>Family Lighthouse</span>
      </div>
      <h1 class="lhp-brand-headline">Your legacy,<br>protected &amp; organized.</h1>
      <p class="lhp-brand-sub">A secure portal for estate planners and families to store, manage, and pass on what matters most.</p>
      <ul class="lhp-brand-features">
        <li><i class="bi bi-shield-check-fill"></i> Bank-grade secure storage</li>
        <li><i class="bi bi-people-fill"></i> Estate Planner &amp; family access controls</li>
        <li><i class="bi bi-file-earmark-heart-fill"></i> Letters, wishes &amp; keepsakes</li>
        <li><i class="bi bi-lock-fill"></i> Controlled release on your terms</li>
      </ul>
    </div>
  </div>

  <!-- RIGHT PANEL: FORM -->
  <div class="lhp-auth-form-panel">
    <div class="lhp-auth-form-inner">

      <!-- ── LOGIN PANEL (default) ── -->
      <div id="lhp-login-panel">
        <div class="lhp-auth-form-header">
          <h2>Welcome back</h2>
          <p>Sign in to your Family Lighthouse portal</p>
        </div>

        <!-- Alert area -->
        <div id="login-alert" class="alert alert-danger d-none align-items-center gap-2" role="alert">
          <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
          <span class="lhp-alert-msg"></span>
        </div>

        <!-- Email -->
        <div class="mb-3">
          <label class="form-label fw-semibold" for="login-email">Email Address</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" id="login-email"
                   placeholder="you@example.com" autocomplete="email">
          </div>
          <div class="invalid-feedback" id="login-email-err"></div>
        </div>

        <!-- Password -->
        <div class="mb-2">
          <label class="form-label fw-semibold" for="login-pass">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control" id="login-pass"
                   placeholder="Enter your password" autocomplete="current-password">
            <button class="btn btn-outline-secondary lhp-toggle-pass" type="button" data-target="login-pass" tabindex="-1">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>

        <!-- Forgot password link -->
        <div class="text-end mb-4">
          <a href="#" id="lhp-forgot-link" class="lhp-link" style="font-size:13px">Forgot your password?</a>
        </div>

        <!-- Submit -->
        <div class="d-grid mb-3">
          <button class="btn lhp-btn-primary-solid btn-lg" id="lhp-login-btn" type="button">
            <span class="btn-label">Sign In</span>
            <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Signing in…</span>
          </button>
        </div>

        <p class="text-center text-muted" style="font-size:14px">
          Don&#39;t have an account?
          <a href="<?php echo esc_url( home_url( '/register' ) ); ?>" class="lhp-link fw-semibold">Create one free</a>
        </p>

        <p class="lhp-legal-note text-center mt-4">
          <i class="bi bi-shield-lock text-muted"></i>
          This Lighthouse is not a will and is not a legal document.
        </p>
      </div><!-- /#lhp-login-panel -->

      <!-- ── FORGOT PASSWORD PANEL ── -->
      <div id="lhp-forgot-panel" style="display:none">
        <div class="lhp-auth-form-header">
          <h2>Reset Password</h2>
          <p>Enter your email address and we&#39;ll send you a link to reset your password.</p>
        </div>

        <!-- Alert area -->
        <div id="forgot-alert" class="alert alert-danger d-none align-items-center gap-2" role="alert">
          <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
          <span class="lhp-alert-msg"></span>
        </div>

        <!-- Email -->
        <div class="mb-4">
          <label class="form-label fw-semibold" for="forgot-email">Email Address</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" id="forgot-email"
                   placeholder="you@example.com" autocomplete="email">
          </div>
        </div>

        <!-- Submit -->
        <div class="d-grid mb-3">
          <button class="btn lhp-btn-primary-solid btn-lg" id="lhp-forgot-btn" type="button">
            <span class="btn-label"><i class="bi bi-send me-2"></i>Send Reset Link</span>
            <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Sending…</span>
          </button>
        </div>

        <p class="text-center text-muted" style="font-size:14px">
          <a href="#" id="lhp-back-to-login" class="lhp-link">
            <i class="bi bi-arrow-left me-1"></i>Back to Sign In
          </a>
        </p>
      </div><!-- /#lhp-forgot-panel -->

      <!-- ── RESET PASSWORD PANEL ── -->
      <div id="lhp-reset-panel" style="display:none">
        <div class="lhp-auth-form-header">
          <h2>Set New Password</h2>
          <p>Enter a new password for your account.</p>
        </div>

        <div id="reset-alert" class="alert alert-danger d-none align-items-center gap-2" role="alert">
          <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
          <span class="lhp-alert-msg"></span>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold" for="reset-pass">New Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control" id="reset-pass"
                   placeholder="New password" autocomplete="new-password">
            <button class="btn btn-outline-secondary lhp-toggle-pass" type="button" data-target="reset-pass" tabindex="-1">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label fw-semibold" for="reset-pass2">Confirm Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control" id="reset-pass2"
                   placeholder="Confirm new password" autocomplete="new-password">
            <button class="btn btn-outline-secondary lhp-toggle-pass" type="button" data-target="reset-pass2" tabindex="-1">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>

        <div class="d-grid mb-3">
          <button class="btn lhp-btn-primary-solid btn-lg" id="lhp-reset-btn" type="button">
            <span class="btn-label"><i class="bi bi-lock-fill me-2"></i>Set New Password</span>
            <span class="btn-loading d-none"><span class="spinner-border spinner-border-sm me-2"></span>Saving…</span>
          </button>
        </div>

        <p class="text-center text-muted" style="font-size:14px">
          <a href="#" id="lhp-back-to-login-reset" class="lhp-link">
            <i class="bi bi-arrow-left me-1"></i>Back to Sign In
          </a>
        </p>

        <input type="hidden" id="reset-key" value="">
        <input type="hidden" id="reset-login" value="">
      </div><!-- /#lhp-reset-panel -->

      <!-- ── RESET EMAIL SENT PANEL ── -->
      <div id="lhp-forgot-success" style="display:none">
        <div class="text-center py-4">
          <div style="width:72px;height:72px;border-radius:50%;background:#d1fae5;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
            <i class="bi bi-envelope-check-fill" style="font-size:32px;color:#059669;"></i>
          </div>
          <h2 class="fw-bold mb-2" style="font-size:24px">Check your email</h2>
          <p class="text-muted mb-4" style="font-size:15px;line-height:1.6">
            If an account exists with that email address, you&#39;ll receive a password reset link shortly.<br>
            <small class="text-muted">Don&#39;t see it? Check your spam folder.</small>
          </p>
          <a href="#" id="lhp-back-to-login-2" class="btn lhp-btn-primary-solid">
            <i class="bi bi-arrow-left me-2"></i>Back to Sign In
          </a>
        </div>
      </div><!-- /#lhp-forgot-success -->

    </div><!-- /.lhp-auth-form-inner -->
  </div><!-- /.lhp-auth-form-panel -->

</div><!-- .lhp-auth-split -->
</div><!-- .lhp-auth-center-wrap -->
</div><!-- .lhp-root -->
