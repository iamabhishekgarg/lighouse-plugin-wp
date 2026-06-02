/* ═══════════════════════════════════════════════════════════
   Lighthouse Portal — portal.js  v1.2
   Single IIFE — jQuery noConflict safe
═══════════════════════════════════════════════════════════ */
(function ($) {
  "use strict";
  var cfg = window.LHP || {};

  // ── Re-read LHP config in case wp_footer inline updated it ──
  cfg = window.LHP || cfg;

  // ── Early exit if LHP not configured ──
  if (!cfg.ajax_url) return;

  // ── Mark as loaded ──
  window._lhpBooted = false; // will be set true after $(function) runs

  /* ═══════════════════════════════════════════════════════
     CORE UTILITIES
  ═══════════════════════════════════════════════════════ */
  var _nonceRefreshing = false;
  var _nonceQueue = [];

  function refreshNonce(onDone) {
    if (_nonceRefreshing) { _nonceQueue.push(onDone); return; }
    _nonceRefreshing = true;
    $.post(cfg.ajax_url, { action: "lhp_refresh_nonce" })
      .done(function(r) {
        if (r && r.success && r.data && r.data.nonce) cfg.nonce = r.data.nonce;
      })
      .always(function() {
        _nonceRefreshing = false;
        if (onDone) onDone();
        var q = _nonceQueue.splice(0); q.forEach(function(fn){ if (fn) fn(); });
      });
  }

  function ajax(action, data, done, fail) {
    if (!cfg.ajax_url || !cfg.nonce) {
      console.error("[LHP] Missing ajax_url or nonce");
      if (fail) fail("Configuration error. Please refresh the page.");
      return;
    }
    function doPost(retried) {
      $.post(cfg.ajax_url, Object.assign({ action: action, nonce: cfg.nonce }, data))
        .done(function(r) {
          if (r && r.success) {
            if (done) done(r.data);
          } else {
            var msg = r && r.data ? r.data : "Request failed";
            if (!retried && msg === "Security check failed.") {
              refreshNonce(function() { doPost(true); });
              return;
            }
            if (fail) fail(msg);
          }
        })
        .fail(function(xhr) {
          var msg = "Server error (" + xhr.status + "). Please try again.";
          console.error("[LHP] HTTP error:", action, xhr.status, xhr.responseText);
          if (fail) fail(msg);
        });
    }
    doPost(false);
  }

  /* Bootstrap 5: always get existing instance, only create if none exists */
  function getModal(id) {
    var el = document.getElementById(id);
    if (!el) return null;
    return bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
  }
  function showModal(id) {
    var m = getModal(id);
    if (m) m.show();
    else return;
  }
  function hideModal(id) {
    var el = document.getElementById(id);
    if (!el) return;
    var m = bootstrap.Modal.getInstance(el);
    if (m) m.hide();
  }

  function toast(msg, type) {
    if (typeof bootstrap === "undefined") {
      return;
    }
    type = type || "success";
    var icons = {
      success: "bi-check-circle-fill text-success",
      error: "bi-exclamation-triangle-fill text-danger",
      info: "bi-info-circle-fill text-primary",
    };
    var id = "toast-" + Date.now();
    var $ct = $(".lhp-toast-container");
    if (!$ct.length) {
      $ct = $(
        '<div class="lhp-toast-container position-fixed top-0 end-0 p-3" style="z-index:11000"></div>',
      );
      $("body").append($ct);
    }
    $ct.append(
      '<div id="' +
        id +
        '" class="toast align-items-center border-0 shadow" role="alert" style="min-width:280px">' +
        '<div class="d-flex"><div class="toast-body d-flex align-items-center gap-2">' +
        '<i class="bi ' +
        (icons[type] || icons.info) +
        '"></i>' +
        esc(msg) +
        '</div><button type="button" class="btn-close ms-2 me-2 flex-shrink-0 align-self-center" data-bs-dismiss="toast"></button></div></div>',
    );
    var el = document.getElementById(id);
    if (el) {
      var t = new bootstrap.Toast(el, { delay: 3500 });
      t.show();
      el.addEventListener("hidden.bs.toast", function () {
        $(this).remove();
      });
    }
  }

  function showAlert(sel, msg) {
    var $el = $(sel);
    // Prefer inner .lhp-alert-msg span so icon is preserved; fall back to full text
    var $inner = $el.find(".lhp-alert-msg");
    if ($inner.length) $inner.text(msg);
    else $el.text(msg);
    $el.removeClass("d-none").addClass("d-flex");
  }
  function hideAlert(sel) {
    var $el = $(sel);
    $el.find(".lhp-alert-msg").text("");
    $el.addClass("d-none").removeClass("d-flex");
  }

  function btnLoad($btn, loading) {
    $btn.prop("disabled", loading);
    // Use only inline styles — never class toggling — so Bootstrap d-none !important never conflicts
    $btn.find(".btn-label").css("display", loading ? "none" : "");
    $btn.find(".btn-loading").removeClass("d-none").css("display", loading ? "" : "none");
  }

  function isEmail(e) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(e).trim());
  }
  function esc(s) {
    return String(s || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function isPhone(p) {
    if (!p) return true;
    // This regex matches international and local formats (+1, 10-12 digits)
    var phoneRegex = /^[+]?[(]?[0-9]{3}[)]?[-\s.]?[0-9]{3}[-\s.]?[0-9]{4,10}$/;
    return phoneRegex.test(String(p).trim());
  }

  // Shared: "Other" dropdown → input+▼, ▼ restores select
  function otherMakeInput($sel, placeholder) {
    // Read names from the select's own options — more reliable than currentBeneficiaries
    var names = [];
    $sel.find('option').each(function() {
      var v = $(this).val();
      if (v && v !== 'Other' && v !== 'Everyone') names.push(v);
    });
    var $wrap = $('<div class="input-group input-group-sm lhp-other-wrap"></div>');
    var $inp = $('<input type="text" class="form-control form-control-sm lhp-other-input" data-field="' + ($sel.data("field") || '') + '" placeholder="' + placeholder + '">');
    var $btn = $('<button type="button" class="btn btn-outline-secondary btn-sm lhp-revert-sel" tabindex="-1" data-names=\'' + JSON.stringify(names) + '\'>▼</button>');
    $wrap.append($inp).append($btn);
    $sel.replaceWith($wrap);
    $inp.focus();
  }
  function otherRestoreSelect($btn) {
    var $wrap = $btn.closest(".lhp-other-wrap");
    var raw = $btn.data("names") || [];
    var docId = $btn.data("doc-id");
    if (docId) {
      // Restore a doc-recipient-sel select
      var $sel = $('<select class="form-select form-select-sm doc-recipient-sel" data-doc-id="' + esc(String(docId)) + '" style="max-width:180px"></select>');
      $sel.append('<option value="">Select Recipient</option>');
      raw.forEach(function(n){ $sel.append('<option value="' + esc(n) + '">' + esc(n) + '</option>'); });
      $sel.append('<option value="Other">Other</option>');
      $wrap.replaceWith($sel);
      return;
    }
    var field = $wrap.find(".lhp-other-input").data("field") || '';
    var $sel = $('<select class="form-select form-select-sm" data-field="' + field + '"></select>');
    $sel.append('<option value="">— Select —</option>');
    raw.forEach(function(n){ $sel.append('<option value="' + esc(n) + '">' + esc(n) + '</option>'); });
    $sel.append('<option value="Other">Other</option>');
    $wrap.replaceWith($sel);
  }

  /* ── switchView: show a named view panel ─────────────── */
  function switchView(viewName) {
    $(".lhp-view").removeClass("active");
    $("#view-" + viewName).addClass("active");
    $("#lhp-sidebar").removeClass("open");
    $("#lhp-sidebar-backdrop").removeClass("active");
    try { localStorage.setItem('lhp_active_view', viewName); } catch(e) {}
  }

  /* ── debounce: delay fn execution until typing stops ── */
  function lhpCopyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text);
      return;
    }
    var ta = document.createElement("textarea");
    ta.value = text;
    ta.style.cssText = "position:fixed;left:-9999px;top:-9999px;opacity:0";
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    try { document.execCommand("copy"); } catch (e) {}
    document.body.removeChild(ta);
  }

  function debounce(fn, ms) {
    var timer;
    return function () {
      var args = arguments,
        ctx = this;
      clearTimeout(timer);
      timer = setTimeout(function () {
        fn.apply(ctx, args);
      }, ms || 300);
    };
  }

  /* ── Password toggle ──────────────────────────────────── */
  $(document).on("click", ".lhp-toggle-pass", function (e) {
    e.preventDefault();
    var $inp = $("#" + $(this).data("target"));
    var show = $inp.attr("type") === "password";
    $inp.attr("type", show ? "text" : "password");
    $(this)
      .find(".bi")
      .toggleClass("bi-eye", !show)
      .toggleClass("bi-eye-slash", show);
  });

  /* ── Password strength ────────────────────────────────── */
  $(document).on("input", "#planner-pass, #par-pass, #ppar-new-pass", function () {
    var id = $(this).attr("id");
    var pfx = id === "par-pass" ? "par" : id === "ppar-new-pass" ? "ppar" : "planner";
    var p = $(this).val();
    var $w = $("#" + pfx + "-pass-strength");
    if (!p) {
      $w.hide();
      return;
    }
    $w.show();
    var s = passStrength(p);
    $("#" + pfx + "-strength-fill")
      .css("width", s.w + "%")
      .attr("class", "lhp-pass-strength-fill " + s.cls);
    $("#" + pfx + "-strength-label").text(s.label);
  });
  function passStrength(p) {
    if (p.length < 6) return { w: 20, cls: "bg-danger", label: "Too weak" };
    if (p.length < 8) return { w: 40, cls: "bg-warning", label: "Weak" };
    var score = [/[A-Z]/, /[0-9]/, /[^A-Za-z0-9]/].filter(function (rx) {
      return rx.test(p);
    }).length;
    if (score === 0) return { w: 55, cls: "bg-warning", label: "Fair" };
    if (score === 1) return { w: 75, cls: "bg-info", label: "Good" };
    return { w: 100, cls: "bg-success", label: "Strong" };
  }

  /* ── Email live validation ───────────────────────────── */
  $(document).on("blur", "input[type=email]", function () {
    var v = $(this).val();
    var $oo = $(this).closest("td,div").find('[data-optout="email"], [data-optout="sec-email"]');
    if ($oo.length && $oo.is(":checked")) { $(this).removeClass("is-invalid is-valid"); return; }
    $(this).toggleClass("is-invalid", !!(v && !isEmail(v)));
    $(this).toggleClass("is-valid", !!(v && isEmail(v)));
  });

  /* ── Last-4-digits live validation ──────────────────────── */
  $(document).on("input blur", ".lhp-last-four", function () {
    var v = $(this).val().replace(/\D/g, ""); // strip non-digits
    $(this).val(v); // auto-strip non-numeric input
    if (v && v.length !== 4) {
      $(this).addClass("is-invalid").attr("title", "Must be exactly 4 digits");
    } else {
      $(this).removeClass("is-invalid");
    }
  });

  /* ── atty-phone / par-phone: digits-only, max 10 ────── */
  $(document).on("input", "#planner-phone, #par-phone", function () {
    var cleaned = $(this).val().replace(/\D/g, "").slice(0, 10);
    $(this).val(cleaned);
  });

  /* ── Phone live validation ───────────────────────────── */
  // Targets IDs from tmpl-register.php and tmpl-planner-dashboard.php
  $(document).on(
    "blur",
    "#planner-phone, #par-phone, #cm-phone, #prof-phone, #ppar-phone, #icu-phone",
    function () {
      var v = $(this).val();
      if (v) {
        var valid = isPhone(v);
        $(this)
          .toggleClass("is-invalid", !valid)
          .toggleClass("is-valid", valid);
      } else {
        $(this).removeClass("is-invalid is-valid");
      }
    },
  );

  /* ── Sidebar ──────────────────────────────────────────── */
  function initSidebar() {
    $("#lhp-sidebar-open").on("click", function () {
      $("#lhp-sidebar").addClass("open");
      $("#lhp-sidebar-backdrop").addClass("active");
    });
    $("#lhp-sidebar-close, #lhp-sidebar-backdrop").on("click", function () {
      $("#lhp-sidebar").removeClass("open");
      $("#lhp-sidebar-backdrop").removeClass("active");
    });
    $(document).on("click", ".lhp-nav-item[data-view]", function (e) {
      e.preventDefault();
      var v = $(this).data("view");
      switchView(v);
      $(".lhp-nav-item").removeClass("active");
      $(this).addClass("active");
    });
  }

  /* ── Logout ───────────────────────────────────────────── */
  function doLogout() {
    ajax("lhp_logout", {}, function (d) {
      window.location.href = d.redirect;
    });
  }
  $(document).on("click", "#lhp-logout", doLogout);

  /* ═══════════════════════════════════════════════════════
     LOGIN
  ═══════════════════════════════════════════════════════ */
  function initLogin() {
    var _loginInFlight = false;
    function doLogin() {
      if (_loginInFlight) return;
      hideAlert("#login-alert");
      var email = $("#login-email").val().trim();
      var pass = $("#login-pass").val();
      if (!email || !isEmail(email)) {
        showAlert("#login-alert", "Please enter a valid email address.");
        $("#login-email").addClass("is-invalid").trigger("focus");
        return;
      }
      if (!pass) {
        showAlert("#login-alert", "Please enter your password.");
        return;
      }
      _loginInFlight = true;
      var $btn = $("#lhp-login-btn");
      btnLoad($btn, true);
      ajax(
        "lhp_login",
        { email: email, password: pass },
        function (data) {
          _loginInFlight = false;
          toast("Signed in. Redirecting…");
          setTimeout(function () {
            window.location.href = data.redirect;
          }, 700);
        },
        function (msg) {
          _loginInFlight = false;
          btnLoad($btn, false);
          showAlert("#login-alert", msg);
          $("#login-pass").val("").trigger("focus");
        },
      );
    }
    $("#lhp-login-btn").off("click").on("click", doLogin);
    $(document).on("keydown", "#login-email, #login-pass", function (e) {
      if (e.key === "Enter") doLogin();
    });
  }

  /* ═══════════════════════════════════════════════════════
     FORGOT PASSWORD
  ═══════════════════════════════════════════════════════ */
  function initForgotPassword() {
    // Show forgot password panel
    $(document).on("click", "#lhp-forgot-link", function (e) {
      e.preventDefault();
      $("#lhp-login-panel").hide();
      $("#lhp-forgot-success").hide();
      $("#lhp-forgot-panel").show();
      hideAlert("#forgot-alert");
      $("#forgot-email").val("").trigger("focus");
    });

    // Back to login from forgot panel
    $(document).on("click", "#lhp-back-to-login", function (e) {
      e.preventDefault();
      $("#lhp-forgot-panel").hide();
      $("#lhp-forgot-success").hide();
      $("#lhp-login-panel").show();
    });

    // Back to login from success panel
    $(document).on("click", "#lhp-back-to-login-2", function (e) {
      e.preventDefault();
      $("#lhp-forgot-success").hide();
      $("#lhp-forgot-panel").hide();
      $("#lhp-login-panel").show();
    });

    // Submit forgot password request
    function doForgotPassword() {
      var email = $("#forgot-email").val().trim();
      hideAlert("#forgot-alert");
      if (!email || !isEmail(email)) {
        showAlert("#forgot-alert", "Please enter a valid email address.");
        $("#forgot-email").addClass("is-invalid").trigger("focus");
        return;
      }
      $("#forgot-email").removeClass("is-invalid");
      var $btn = $("#lhp-forgot-btn");
      btnLoad($btn, true);
      ajax(
        "lhp_forgot_password",
        { email: email },
        function () {
          btnLoad($btn, false);
          $("#lhp-forgot-panel").hide();
          $("#lhp-forgot-success").show();
        },
        function (msg) {
          btnLoad($btn, false);
          showAlert("#forgot-alert", msg);
        }
      );
    }

    $("#lhp-forgot-btn").on("click", doForgotPassword);
    $(document).on("keydown", "#forgot-email", function (e) {
      if (e.key === "Enter") doForgotPassword();
    });
  }

  /* ═══════════════════════════════════════════════════════
     RESET PASSWORD  (arrives via email link: /login?action=reset&key=...&login=...)
  ═══════════════════════════════════════════════════════ */
  function initResetPassword() {
    var params = new URLSearchParams(window.location.search);
    if (params.get("action") === "reset" && params.get("key") && params.get("login")) {
      $("#lhp-login-panel, #lhp-forgot-panel, #lhp-forgot-success").hide();
      $("#lhp-reset-panel").show();
      $("#reset-key").val(params.get("key"));
      $("#reset-login").val(params.get("login"));
      $("#reset-pass").trigger("focus");
    }

    function doReset() {
      var pass  = $("#reset-pass").val();
      var pass2 = $("#reset-pass2").val();
      var key   = $("#reset-key").val();
      var login = $("#reset-login").val();
      hideAlert("#reset-alert");
      if (pass.length < 8) {
        showAlert("#reset-alert", "Password must be at least 8 characters.");
        return;
      }
      if (pass !== pass2) {
        showAlert("#reset-alert", "Passwords do not match.");
        return;
      }
      var $btn = $("#lhp-reset-btn");
      btnLoad($btn, true);
      ajax("lhp_reset_password", { key: key, login: login, password: pass, password2: pass2 },
        function (data) {
          btnLoad($btn, false);
          toast("Password updated! Redirecting…");
          setTimeout(function () {
            window.location.href = data.redirect || cfg.login_url;
          }, 1200);
        },
        function (msg) {
          btnLoad($btn, false);
          showAlert("#reset-alert", msg);
        }
      );
    }

    $("#lhp-reset-btn").on("click", doReset);
    $(document).on("keydown", "#reset-pass, #reset-pass2", function (e) {
      if (e.key === "Enter") doReset();
    });

    $(document).on("click", "#lhp-back-to-login-reset", function (e) {
      e.preventDefault();
      $("#lhp-reset-panel").hide();
      $("#lhp-login-panel").show();
      if (window.history && window.history.replaceState) {
        window.history.replaceState({}, document.title, window.location.pathname);
      }
    });
  }

  /* ═══════════════════════════════════════════════════════
     REGISTER
  ═══════════════════════════════════════════════════════ */
  function initRegister() {
    // Auto-navigate to parent form if invite token in URL
    var inviteToken = (new URLSearchParams(window.location.search)).get("invite") || "";
    if (inviteToken) {
      $("#par-invite-token").val(inviteToken);
      $(".lhp-reg-step").removeClass("active");
      $("#reg-step-parent").addClass("active");
    }

    $(document).on("click", ".lhp-role-card", function () {
      var role = $(this).data("role");
      $(".lhp-reg-step").removeClass("active");
      $(
        "#reg-step-" + (role === "lighthouse_planner" ? "planner" : "parent"),
      ).addClass("active");
    });
    $(document).on("click", ".lhp-back-btn", function () {
      $(".lhp-reg-step").removeClass("active");
      $("#" + $(this).data("target")).addClass("active");
    });

    $("#lhp-reg-planner").on("click", function () {
      var $btn = $(this);
      hideAlert("#planner-alert");
      var d = {
        role: "lighthouse_planner",
        full_name: $("#planner-name").val().trim(),
        firm_name: $("#planner-firm").val().trim(),
        email: $("#planner-email").val().trim(),
        phone: $("#planner-phone").val().trim(),
        internal_ref: $("#planner-ref").val().trim(),
        password: $("#planner-pass").val(),
      };
      if (!d.full_name || !d.firm_name || !d.email || !d.phone || !d.password) {
        showAlert("#planner-alert", "Please fill in all required fields.");
        return;
      }
      if (!isEmail(d.email)) {
        showAlert("#planner-alert", "Please enter a valid email.");
        return;
      }
      if (!/^\d{10}$/.test(d.phone)) {
        showAlert("#planner-alert", "Phone number must be at least 10 digits.");
        return;
      }
      if (d.password.length < 8) {
        showAlert("#planner-alert", "Password must be at least 8 characters.");
        return;
      }
      if (d.password !== $("#planner-pass2").val()) {
        showAlert("#planner-alert", "Passwords do not match.");
        return;
      }
      btnLoad($btn, true);
      ajax(
        "lhp_register",
        d,
        function (r) {
          toast("Account created.");
          setTimeout(function () {
            window.location.href = r.redirect;
          }, 700);
        },
        function (m) {
          btnLoad($btn, false);
          showAlert("#planner-alert", m);
        },
      );
    });

    $(document).on("input change", "#par-name,#par-email,#par-phone,#par-pass,#par-pass2", function () {
      $(this).removeClass("is-invalid");
    });

    $("#lhp-reg-parent").on("click", function () {
      var $btn = $(this);
      hideAlert("#par-alert");
      $("#par-name,#par-email,#par-phone,#par-pass,#par-pass2").removeClass("is-invalid");
      var d = {
        role: "lighthouse_parent",
        full_name: $("#par-name").val().trim(),
        email: $("#par-email").val().trim(),
        phone: $("#par-phone").val().trim(),
        password: $("#par-pass").val(),
        invite_token: $("#par-invite-token").val() || "",
      };
      if (!d.full_name) { showAlert("#par-alert", "Full Name is required."); $("#par-name").addClass("is-invalid").focus(); return; }
      if (!d.email)     { showAlert("#par-alert", "Email Address is required."); $("#par-email").addClass("is-invalid").focus(); return; }
      if (!isEmail(d.email)) { showAlert("#par-alert", "Please enter a valid email."); $("#par-email").addClass("is-invalid").focus(); return; }
      if (!/^\d{10}$/.test(d.phone)) { showAlert("#par-alert", "Mobile Phone must be exactly 10 digits."); $("#par-phone").addClass("is-invalid").focus(); return; }
      if (!d.password)  { showAlert("#par-alert", "Password is required."); $("#par-pass").addClass("is-invalid").focus(); return; }
      if (d.password.length < 8) { showAlert("#par-alert", "Password must be at least 8 characters."); $("#par-pass").addClass("is-invalid").focus(); return; }
      if (d.password !== $("#par-pass2").val()) { showAlert("#par-alert", "Passwords do not match."); $("#par-pass2").addClass("is-invalid").focus(); return; }
      btnLoad($btn, true);
      ajax(
        "lhp_register",
        d,
        function (r) {
          toast("Account created.");
          setTimeout(function () {
            window.location.href = r.redirect;
          }, 700);
        },
        function (m) {
          btnLoad($btn, false);
          showAlert("#par-alert", m);
        },
      );
    });
  }

  /* ═══════════════════════════════════════════════════════
     PLANNER DASHBOARD
  ═══════════════════════════════════════════════════════ */
  var allRecords = [];

  function loadEPPendingReviews() {
    var $list = $("#ep-reviews-list");
    $list.html('<div class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm text-primary me-2"></span>Loading…</div>');
    ajax("lhp_get_pending_reviews", {}, function(items) {
      var badge = $("#ep-reviews-badge");
      if (!items.length) {
        badge.addClass("d-none");
        $list.html('<div class="lhp-empty-state"><i class="bi bi-shield-check"></i><h3>No Pending Reviews</h3><p class="text-muted">When clients submit death verification documents, they appear here for your approval.</p></div>');
        return;
      }
      badge.text(items.length).removeClass("d-none");
      var html = items.map(function(item) {
        return '<div class="lhp-section-card mb-3 p-4">' +
          '<div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">' +
          '<div>' +
            '<div class="fw-bold">' + esc(item.person_name) + '</div>' +
            '<div class="text-muted small">Record: ' + esc(item.record_title) + ' · Owner: ' + esc(item.owner_name) + '</div>' +
            '<div class="text-muted small">Submitted: ' + esc(item.submitted_at || '—') + '</div>' +
            (item.death_doc_name ? '<div class="small mt-1"><i class="bi bi-paperclip me-1"></i><strong>' + esc(item.death_doc_name) + '</strong></div>' : '') +
          '</div>' +
          '<div class="d-flex gap-2">' +
            '<button class="btn btn-success btn-sm ep-approve-btn" data-id="' + esc(item.entry_id) + '" data-rid="' + esc(String(item.record_id)) + '" data-name="' + esc(item.person_name) + '"><i class="bi bi-check-circle me-1"></i>Approve</button>' +
            '<button class="btn btn-outline-danger btn-sm ep-reject-btn" data-id="' + esc(item.entry_id) + '" data-rid="' + esc(String(item.record_id)) + '" data-name="' + esc(item.person_name) + '"><i class="bi bi-x-circle me-1"></i>Reject</button>' +
          '</div></div></div>';
      }).join('');
      $list.html(html);
    }, function() {
      $list.html('<div class="alert alert-danger">Failed to load pending reviews.</div>');
    });
  }

  function initEPDashboard() {
    try {
    initSidebar();
    loadEPProfile();
    loadEPLinks();
    loadEPUniqueLink();
    loadEPPendingReviews();

    // Pending review approve
    $(document).on("click", ".ep-approve-btn", function () {
      var $btn = $(this), id = $btn.data("id"), rid = parseInt($btn.data("rid")), name = $btn.data("name");
      if (!confirm("Approve access for " + name + "? They will receive an email and can view the Lighthouse immediately.")) return;
      $btn.prop("disabled", true);
      ajax("lhp_approve_access_person", { record_id: rid, entry_id: id },
        function () { toast(name + " approved — access granted.", "success"); loadEPPendingReviews(); },
        function (m) { toast(m, "error"); $btn.prop("disabled", false); }
      );
    });

    // Pending review reject
    $(document).on("click", ".ep-reject-btn", function () {
      var $btn = $(this), id = $btn.data("id"), rid = parseInt($btn.data("rid")), name = $btn.data("name");
      var reason = prompt("Reason for rejecting " + name + "'s request (sent to them by email):");
      if (reason === null) return;
      $btn.prop("disabled", true);
      ajax("lhp_reject_access_person", { record_id: rid, entry_id: id, reason: reason },
        function () { toast(name + " rejected — document not accepted.", "info"); loadEPPendingReviews(); },
        function (m) { toast(m, "error"); $btn.prop("disabled", false); }
      );
    });

    // Copy permanent welcome link
    $(document).off("click","#ep-copy-unique-link").on("click","#ep-copy-unique-link", function() {
      var url = $("#ep-unique-link-url").val();
      if (!url || url === "Loading…") return;
      if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() { toast("Link copied to clipboard."); });
      } else {
        var el = document.getElementById("ep-unique-link-url");
        el.select(); document.execCommand("copy");
        toast("Link copied to clipboard.");
      }
    });
    $("#lhp-save-ep-profile").off("click").on("click", saveEPProfile);

    // Logo upload
    $(document).off("click", "#ep-upload-logo").on("click", "#ep-upload-logo", function () {
      $("#ep-logo-input").trigger("click");
    });
    $(document).off("change", "#ep-logo-input").on("change", "#ep-logo-input", function () {
      var file = this.files[0];
      if (!file) return;
      if (file.size > 2 * 1024 * 1024) { toast("Logo must be under 2 MB.", "error"); return; }
      var fd = new FormData();
      fd.append("action", "lhp_upload_logo");
      fd.append("nonce", cfg.nonce);
      fd.append("file", file);
      $.ajax({ url: cfg.ajax_url, type: "POST", data: fd, processData: false, contentType: false })
        .done(function (r) {
          if (r.success) {
            var url = r.data.url;
            epLogoId = r.data.attachment_id;
            $("#ep-logo-preview").html('<img src="' + url + '" style="max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain">');
            var $sidebarAvatarWrap = $(".lhp-sidebar-user .lhp-avatar-lg");
            var imgTag = '<img src="' + url + '" alt="Logo" class="lhp-sidebar-avatar-img">';
            $sidebarAvatarWrap.addClass("lhp-avatar-logo").html(imgTag);
            toast("Logo uploaded.");
          } else { toast(r.data || "Upload failed.", "error"); }
        })
        .fail(function () { toast("Network error. Please try again.", "error"); });
    });

    // Generate link modal
    $(document).off("click", "#ep-generate-link").on("click", "#ep-generate-link", function () {
      $("#ep-link-error,#ep-link-success").addClass("d-none").text("");
      $("#ep-link-name").val("");
      $("#ep-link-email").val("");
      showModal("ep-link-modal");
    });
    $(document).off("click", "#ep-do-generate-link").on("click", "#ep-do-generate-link", function () {
      var $btn = $(this);
      var name = $("#ep-link-name").val().trim();
      var email = $("#ep-link-email").val().trim();
      $("#ep-link-error,#ep-link-success").addClass("d-none").text("");
      if (!email || !isEmail(email)) {
        $("#ep-link-error").text("Valid email required.").removeClass("d-none");
        return;
      }
      btnLoad($btn, true);
      ajax("lhp_generate_client_link", { name: name, email: email },
        function () {
          btnLoad($btn, false);
          toast("Invitation sent to " + esc(email) + ".");
          loadEPLinks();
          setTimeout(function () { hideModal("ep-link-modal"); }, 800);
        },
        function (m) {
          btnLoad($btn, false);
          $("#ep-link-error").text(m).removeClass("d-none");
        }
      );
    });

    // Delete share link
    $(document).off("click", ".lhp-del-link-btn").on("click", ".lhp-del-link-btn", function () {
      var token = $(this).data("token");
      var $btn = $(this);
      var $cell = $btn.closest("td");
      var orig = $cell.html();
      $cell.html(
        '<span class="small me-2">Delete this link?</span>' +
        '<button class="btn btn-xs btn-danger me-1 lhp-del-link-yes">Delete</button>' +
        '<button class="btn btn-xs btn-outline-secondary lhp-del-link-no">Cancel</button>'
      );
      $cell.find(".lhp-del-link-no").one("click", function () { $cell.html(orig); });
      $cell.find(".lhp-del-link-yes").one("click", function () {
        ajax("lhp_delete_share_link", { token: token },
          function () { toast("Link deleted."); loadEPLinks(); },
          function (m) { $cell.html(orig); toast(m, "error"); }
        );
      });
    });
    } catch(e) { console.error("[LHP EP Dashboard] Init error:", e); }
  }

  var epLogoId = 0;

  function loadEPProfile() {
    ajax("lhp_get_ep_profile", {}, function (d) {
      $("#ep-name").val(d.name);
      $("#ep-email").val(d.email);
      $("#ep-firm").val(d.firm_name);
      $("#ep-phone").val(d.phone);
      $("#ep-bio").val(d.bio || "");
      epLogoId = d.logo_id || 0;
      if (d.logo_url) {
        $("#ep-logo-preview").html('<img src="' + esc(d.logo_url) + '" style="max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain">');
        var $av = $(".lhp-sidebar-user .lhp-avatar-lg");
        if (!$av.hasClass("lhp-avatar-logo")) {
          $av.addClass("lhp-avatar-logo").html('<img src="' + esc(d.logo_url) + '" alt="Logo" class="lhp-sidebar-avatar-img">');
        }
      }
    });
  }

  function saveEPProfile() {
    var firm = $("#ep-firm").val().trim();
    if (!firm) { toast("Firm Name is required.", "error"); return; }
    var $btn = $("#lhp-save-ep-profile");
    btnLoad($btn, true);
    ajax("lhp_save_ep_profile", {
      name: $("#ep-name").val(),
      firm_name: firm,
      phone: $("#ep-phone").val(),
      bio: $("#ep-bio").val(),
      logo_id: epLogoId,
    }, function () {
      btnLoad($btn, false);
      toast("Profile saved.");
      loadEPUniqueLink();
      var newName = $("#ep-name").val().trim();
      var newFirm = $("#ep-firm").val().trim();
      $(".lhp-sidebar-user .lhp-sidebar-user-info .fw-semibold").text(newName);
      var $firm = $(".lhp-sidebar-user .lhp-sidebar-firm");
      if (newFirm) {
        if ($firm.length) { $firm.text(newFirm); }
        else { $(".lhp-sidebar-user .lhp-sidebar-user-info").append('<div class="lhp-sidebar-firm">' + esc(newFirm) + '</div>'); }
      } else { $firm.remove(); }
    }, function (m) {
      btnLoad($btn, false);
      toast(m, "error");
    });
  }

  function loadEPUniqueLink() {
    ajax("lhp_get_ep_token", {}, function(d) {
      $("#ep-unique-link-url").val(d.url);
      $("#ep-unique-link-count").text(d.count);
      $("#ep-preview-unique-link").attr("href", d.url).show();
    }, function() {
      $("#ep-unique-link-url").val("Error loading link. Please refresh.");
    });
  }

  function loadEPLinks() {
    ajax("lhp_get_ep_links", {}, function (d) {
      var links = d.links || [];
      $("#ep-stat-total-links").text(links.length);
      var created = links.filter(function (l) { return l.client_id; }).length;
      $("#ep-stat-clients-created").text(created);
      var withLH = links.filter(function (l) { return l.has_record; }).length;
      $("#ep-stat-active").text(withLH);
      if (!links.length) {
        $("#ep-links-body").html('<tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-link-45deg fs-1 d-block mb-2 opacity-25"></i>No links generated yet. Click "Invite Specific Client" to send one.</td></tr>');
        return;
      }
      $("#ep-links-body").html(links.map(function (l) {
        var status = l.client_id
          ? '<span class="lhp-badge-complete"><i class="bi bi-check-circle me-1"></i>Registered</span>'
          : '<span class="lhp-badge-draft"><i class="bi bi-clock me-1"></i>Pending</span>';
        var lhStatus = l.has_record
          ? '<span class="text-success small"><i class="bi bi-folder2-open me-1"></i>Lighthouse Created</span>'
          : '<span class="text-muted small">—</span>';
        var resendBtn = l.client_id
          ? ''
          : '<button class="btn btn-sm btn-outline-secondary lhp-resend-invite-btn" data-token="' + esc(l.token) + '" title="Resend invite email"><i class="bi bi-send me-1"></i>Resend</button>';
        return '<tr><td><div class="fw-semibold">' + esc(l.client_email) + '</div><div class="text-muted small">' + esc(l.client_name || "") + '</div></td>' +
          '<td>' + status + '<br>' + lhStatus + '</td>' +
          '<td class="text-muted small">' + esc(l.created) + '</td>' +
          '<td><div class="d-flex gap-1">' +
          resendBtn +
          '<button class="btn btn-sm btn-outline-danger lhp-del-link-btn" data-token="' + esc(l.token) + '" title="Delete invite link"><i class="bi bi-trash"></i></button>' +
          '</div></td></tr>';
      }).join(""));
    });
  }
  function loadRecords() {
    $("#lhp-records-body").html(
      '<tr><td colspan="7" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm text-primary me-2"></span>Loading…</td></tr>',
    );
    ajax(
      "lhp_get_records",
      {},
      function (records) {
        allRecords = records;
        renderStats(records);
        renderTable(records);
      },
      function (m) {
        toast(m, "error");
      },
    );
  }
  function renderStats(records) {
    var complete = records.filter(function (r) {
      return r.status === "complete";
    }).length;
    var draft = records.length - complete;
    $("#stat-total, #stat-total-records").text(records.length);
    $("#stat-complete, #stat-complete-records").text(complete);
    $("#stat-draft-records").text(draft);
    $("#all-records-count").text(records.length);
  }
  function renderTable(records) {
    if (!records.length) {
      $("#lhp-empty").show();
      $("#lhp-table-card").hide();
      return;
    }
    $("#lhp-empty").hide();
    $("#lhp-table-card").show();
    $("#lhp-records-body").html(
      records
        .map(function (r) {
          var badge =
            r.status === "complete"
              ? '<span class="lhp-badge-complete"><i class="bi bi-check-circle me-1"></i>Complete</span>'
              : '<span class="lhp-badge-draft"><i class="bi bi-clock me-1"></i>In Progress</span>';
          return (
            '<tr data-name="' +
            (r.subject_name || "").toLowerCase() +
            '" data-status="' +
            r.status +
            '">' +
            '<td><div class="fw-semibold">' +
            (esc(r.subject_name) ||
              '<span class="text-muted fst-italic">No name yet</span>') +
            "</div></td>" +
            '<td class="text-muted">' +
            (esc(r.subject_dob) || "—") +
            "</td>" +
            '<td><div class="lhp-progress-wrap"><div class="lhp-prog"><div class="lhp-prog-fill" style="width:' +
            r.completion +
            '%"></div></div><span class="lhp-prog-pct">' +
            r.completion +
            "%</span></div></td>" +
            "<td>" +
            badge +
            "</td>" +
            '<td class="text-muted small">' +
            esc(r.created) +
            "</td>" +
            '<td><div class="d-flex gap-2">' +
            '<button class="btn btn-sm btn-outline-secondary lhp-view-btn" data-id="' +
            r.id +
            '" title="View full record"><i class="bi bi-eye me-1"></i>View</button>' +
            '<button class="btn btn-sm lhp-btn-outline-primary lhp-edit-btn" data-id="' +
            r.id +
            '"><i class="bi bi-pencil-square me-1"></i>Edit</button>' +
            '<button class="btn btn-sm btn-outline-danger lhp-delete-btn" data-id="' +
            r.id +
            '"><i class="bi bi-trash"></i></button>' +
            "</div></td></tr>"
          );
        })
        .join(""),
    );
  }
  function filterRecords(q) {
    var status = $("#lhp-filter-status").val();
    renderTable(
      allRecords.filter(function (r) {
        return (
          (!q || (r.subject_name || "").toLowerCase().includes(q)) &&
          (!status || r.status === status)
        );
      }),
    );
  }

  /* ═══════════════════════════════════════════════════════
     MULTI-STEP FORM MODAL
  ═══════════════════════════════════════════════════════ */
  var msStep = 1,
    msTotalSteps = 7,
    formPendingFiles = [];

  function initFormModal() {
    var el = document.getElementById("lhp-form-modal");
    if (!el) return;

    el.addEventListener("hidden.bs.modal", function () {
      msStep = 1;
      formPendingFiles = [];
      renderMs5PendingList();
    });
    // Off first to prevent double-binding if called twice
    $("#ms-next").off("click").on("click", msNext);
    $("#ms-prev")
      .off("click")
      .on("click", function () {
        if (msStep > 1) {
          msStep--;
          updateStep();
        }
      });
    // Delegated — safe to call multiple times
    $(document)
      .off("click", ".lhp-add-row")
      .on("click", ".lhp-add-row", function () {
        addRow($(this).data("table"));
      });
    $(document)
      .off("click", ".lhp-remove-row")
      .on("click", ".lhp-remove-row", function () {
        $(this).closest("tr").remove();
      });
    $(document)
      .off("input", "#letters-body textarea")
      .on("input", "#letters-body textarea", function () {
        this.style.height = "auto";
        this.style.height = this.scrollHeight + "px";
      });
    $(document)
      .off("mousedown", "#letters-body textarea")
      .on("mousedown", "#letters-body textarea", function () {
        var ta = this;
        $(document).one("mouseup.letresize", function () {
          var h = parseInt(ta.style.height);
          if (h && !isNaN(h)) {
            ta.style.setProperty("height", h + "px", "important");
          }
        });
      });
    $(document)
      .off("change", 'input[name^="burial_pref_"]')
      .on("change", 'input[name^="burial_pref_"]', syncChoice);

    initDelegatedStep();
    initFormUploads();

    $(document).off("click",".ms5-del-file").on("click",".ms5-del-file",function(){
      var attId = $(this).data("att");
      var rid = parseInt($("#lhp-record-id").val()) || 0;
      if (!rid) return;
      ajax("lhp_delete_file", { record_id: rid, attachment_id: attId }, function(docs){
        renderMs5UploadedDocs(docs, rid);
        toast("File removed.", "info");
      });
    });
  }

  /* ── Form wizard: step-5 file staging ── */
  function initFormUploads() {
    var ALLOWED_TYPES = ["application/pdf","application/msword",
      "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
      "image/jpeg","image/png"];
    var MAX_BYTES = 10 * 1024 * 1024;

    $(document).off("click","#ms5-upload-zone").on("click","#ms5-upload-zone",function(){
      $("#ms5-file-input").trigger("click");
    });

    $(document).off("change","#ms5-file-input").on("change","#ms5-file-input",function(){
      var files = Array.from(this.files || []);
      var errors = [];
      files.forEach(function(f){
        if (!ALLOWED_TYPES.includes(f.type)) {
          errors.push(esc(f.name) + ": unsupported file type.");
          return;
        }
        if (f.size > MAX_BYTES) {
          errors.push(esc(f.name) + ": exceeds 10 MB limit.");
          return;
        }
        // Prevent duplicates by name
        var dup = formPendingFiles.some(function(p){ return p.name === f.name && p.size === f.size; });
        if (!dup) formPendingFiles.push(f);
      });
      if (errors.length) toast(errors.join(" "), "error");
      renderMs5PendingList();
      $(this).val("");
    });

    $(document).off("click",".ms5-remove-pending").on("click",".ms5-remove-pending",function(){
      var idx = parseInt($(this).data("idx"));
      formPendingFiles.splice(idx, 1);
      renderMs5PendingList();
    });
  }

  function renderMs5PendingList() {
    if (!formPendingFiles.length) {
      $("#ms5-pending-list").html("");
      return;
    }
    var html = formPendingFiles.map(function(f, i){
      var sizeStr = f.size >= 1048576
        ? (f.size/1048576).toFixed(1)+" MB"
        : Math.round(f.size/1024)+" KB";
      return '<div class="lhp-pending-file-item">' +
        '<i class="bi bi-file-earmark-fill text-warning"></i>' +
        '<span class="lhp-pf-name" title="'+esc(f.name)+'">'+esc(f.name)+'</span>' +
        '<span class="lhp-pf-size">'+sizeStr+'</span>' +
        '<span class="lhp-pending-file-badge">pending</span>' +
        '<button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1 ms5-remove-pending" data-idx="'+i+'" title="Remove"><i class="bi bi-x-circle"></i></button>' +
        '</div>';
    }).join("");
    $("#ms5-pending-list").html(html);
  }

  function renderMs5UploadedDocs(docs, recordId) {
    if (!docs || !docs.length) { $("#ms5-uploaded-list").html(""); return; }
    var html = '<div class="mb-1 text-muted small fw-semibold">Already uploaded:</div>' +
      docs.map(function(d){
        var icon = d.type && d.type.includes("pdf")
          ? "bi-file-earmark-pdf-fill text-danger"
          : d.type && d.type.includes("image")
            ? "bi-file-image-fill text-success"
            : "bi-file-earmark-text-fill text-primary";
        return '<div class="lhp-file-item">' +
          '<i class="bi '+icon+' lhp-file-icon"></i>' +
          '<div><div class="lhp-file-name">'+esc(d.name)+'</div>' +
          '<div class="lhp-file-meta">'+esc(d.size)+' &middot; '+esc(d.uploaded)+'</div></div>' +
          '<div class="lhp-file-actions ms-auto">' +
          '<a href="'+d.url+'" download="'+esc(d.name)+'" target="_blank" class="btn btn-sm btn-outline-secondary" title="Download"><i class="bi bi-download"></i></a>' +
          (recordId ? '<button class="btn btn-sm btn-outline-danger ms5-del-file" data-att="'+d.id+'" title="Delete"><i class="bi bi-trash"></i></button>' : '') +
          '</div></div>';
      }).join("");
    $("#ms5-uploaded-list").html(html);
  }

  function uploadPendingFiles(recordId, files, onDone) {
    if (!files.length) { if (onDone) onDone(); return; }
    var file = files.shift();
    var fd = new FormData();
    fd.append("action", "lhp_upload_file");
    fd.append("nonce", cfg.nonce);
    fd.append("record_id", recordId);
    fd.append("file", file);
    $.ajax({ url: cfg.ajax_url, type: "POST", data: fd, processData: false, contentType: false })
      .done(function(r) {
        if (r && !r.success && r.data === "Security check failed.") {
          refreshNonce(null);
        }
      })
      .always(function(){ uploadPendingFiles(recordId, files, onDone); });
  }

  function syncChoice() {
    $(this)
      .closest(".lhp-choice-cards")
      .find(".lhp-choice-inner")
      .each(function () {
        var checked = $(this).prev("input").is(":checked");
        $(this).toggleClass("lhp-choice-selected", checked);
      });
    var name = $(this).attr("name");
    if (name && name.indexOf("burial_pref") === 0) {
      var prefix = name.replace("burial_pref", "").replace(/^_/, "");
      var $ashes = prefix ? $('.burial-ashes[data-owner="' + prefix + '"]') : $("#burial-ashes").closest(".burial-ashes");
      if ($(this).val() === "cremation" && $(this).is(":checked")) {
        $ashes.show();
      } else {
        $ashes.hide();
      }
    }
  }

  function openFormModal(recordId, clientId) {
    resetForm();
    $("#lhp-record-id").val(recordId || 0);
    $("#lhp-record-client-id").val(clientId || currentClientId || 0);
    msStep = 1;
    if (recordId) {
      ajax(
        "lhp_get_record",
        { record_id: recordId },
        function (data) {
          populateForm(data);
          updateStep();
          showModal("lhp-form-modal");
        },
        function (m) {
          toast(m, "error");
        },
      );
    } else {
      updateStep();
      showModal("lhp-form-modal");
    }
  }

  function resetForm() {
    $(
      "#lhp-form-modal input[type=text],#lhp-form-modal input[type=email],#lhp-form-modal input[type=tel],#lhp-form-modal input[type=date],#lhp-form-modal textarea",
    ).val("");
    $(
      "#lhp-form-modal input[type=radio],#lhp-form-modal input[type=checkbox]",
    ).prop("checked", false);
    $("#lhp-form-modal .lhp-choice-inner").removeClass("lhp-choice-selected");
    $(
      "#children-body,#access-body,#items-body,#bank-body,#insurance-body,#letters-body",
    ).html("");
    $("#form-error,#form-success").addClass("d-none").text("");
    ["children", "access", "items", "bank", "insurance", "letters"].forEach(
      function (t) {
        addRow(t);
      },
    );
    delegatedEntries = [];
    renderDelegatedList();
    formPendingFiles = [];
    $("#ms5-pending-list,#ms5-uploaded-list").html("");
  }

  function populateForm(data) {
    computeOwnerLabels(data);
    var s = data.subject || {};
    $("#s-full-name").val(s.full_name || "");
    $("#s-pref-name").val(s.preferred_name || "");
    $("#s-dob").val(s.dob || "");
    $("#s-phone").val(s.phone || "");
    $("#s-email").val(s.email || "");
    $("#s-address").val(s.address || "");
    // ── NEW: relationship ──
    $("#s-relationship-to-owner").val(s.relationship_to_owner || "");
    var b = data.burial || {};
    var bo1 = b.owner1 || {}, bo2 = b.owner2 || {};
    if (bo1.preference) $('input[name="burial_pref_owner1"][value="' + bo1.preference + '"]').prop("checked", true).trigger("change");
    if (bo2.preference) $('input[name="burial_pref_owner2"][value="' + bo2.preference + '"]').prop("checked", true).trigger("change");
    if (bo1.preference) $('input[name="burial_pref"][value="' + bo1.preference + '"]').prop("checked", true).trigger("change");
    $("#burial-requests-o1").val(bo1.requests || "");
    $("#burial-home-o1").val(bo1.funeral_home || "");
    $("#burial-requests-o2").val(bo2.requests || "");
    $("#burial-home-o2").val(bo2.funeral_home || "");
    $("#burial-ashes").val(bo1.ashes || bo2.ashes || "");
    $("#burial-donation").val(bo1.donation || bo2.donation || "");
    $("#burial-other").val(bo1.other || bo2.other || "");
    function fill(bodyId, table, arr) {
      $("#" + bodyId).html("");
      (arr && arr.length ? arr : [null]).forEach(function (r) {
        addRow(table, r || undefined);
      });
    }
    currentBeneficiaries = data.children || [];
    fill("children-body", "children", data.children || []);
    fill("access-body", "access", data.access_people || []);
    fill("items-body", "items", data.personal_items || []);
    fill("bank-body", "bank", data.bank_accounts || []);
    fill("insurance-body", "insurance", data.life_insurance || []);
    fill("letters-body", "letters", data.letters || []);
    renderMs5UploadedDocs(data.docs || [], data.id || parseInt($("#lhp-record-id").val()) || 0);
    // Load delegated users for step 6
    if (data.delegated_users) {
      delegatedEntries = data.delegated_users;
    } else {
      delegatedEntries = [];
    }
    renderDelegatedList();

    // Render pending access_people activation panel in Step 3 for planner
    renderPendingAccessPanel(data.access_people || [], data.id || parseInt($("#lhp-record-id").val()) || 0);
  }

  function renderPendingAccessPanel(accessPeople, recordId) {
    var pending = accessPeople.filter(function(p) {
      return p.privilege === 'view_after_death' && p.status !== 'active' && p.user_id;
    });
    var selfActivated = accessPeople.filter(function(p) {
      return p.self_activated && p.status === 'active' && p.death_doc_id;
    });
    var $panel = $("#ms-3-pending-panel");
    if (!pending.length && !selfActivated.length) { $panel.hide().html(''); return; }

    var html = '';

    // Pending: planner can activate
    if (pending.length) {
      html += '<div class="alert alert-warning mt-3" role="alert">' +
        '<div class="fw-semibold mb-2"><i class="bi bi-hourglass-split me-2"></i>Pending death verification — ' + pending.length + ' person' + (pending.length > 1 ? 's' : '') + '</div>' +
        '<p class="small mb-2">These people need death verification before their access activates:</p>' +
        '<div class="d-flex flex-wrap gap-2">' +
        pending.map(function(p) {
          return '<button class="btn btn-sm btn-warning acc-activate-btn" data-id="' + esc(p.id || '') + '" data-rid="' + esc(String(recordId)) + '">' +
            '<i class="bi bi-file-earmark-medical me-1"></i>Activate: ' + esc(p.full_name || p.name || p.email) + '</button>';
        }).join('') +
        '</div></div>';
    }

    // pending_review: planner must approve or reject
    var pendingReview = accessPeople.filter(function(p) {
      return p.status === 'pending_review' && p.self_activated;
    });
    if (pendingReview.length) {
      html += '<div class="alert alert-warning mt-3 border-warning" role="alert">' +
        '<div class="fw-semibold mb-2"><i class="bi bi-eye me-2"></i>Awaiting your review — ' + pendingReview.length + ' document' + (pendingReview.length > 1 ? 's' : '') + ' submitted</div>' +
        '<p class="small mb-3">These people uploaded a death certificate and verified via OTP. Review the document and approve or reject:</p>' +
        '<div class="d-flex flex-column gap-2">' +
        pendingReview.map(function(p) {
          var docUrl = p.death_doc_id ? '' : '';
          return '<div class="d-flex align-items-center gap-2 p-2 border rounded bg-white flex-wrap">' +
            '<div class="flex-grow-1"><span class="fw-semibold small">' + esc(p.full_name || p.name || p.email) + '</span>' +
            (p.death_doc_name ? '<span class="text-muted small ms-2"><i class="bi bi-paperclip me-1"></i>' + esc(p.death_doc_name) + '</span>' : '') +
            (p.submitted_at ? '<span class="text-muted small ms-2">Submitted: ' + esc(p.submitted_at.split(' ')[0]) + '</span>' : '') +
            '</div>' +
            '<div class="d-flex gap-2">' +
            '<button class="btn btn-sm btn-success acc-approve-btn" data-id="' + esc(p.id || '') + '" data-rid="' + esc(String(recordId)) + '" data-name="' + esc(p.full_name || p.name || '') + '">' +
            '<i class="bi bi-check-circle me-1"></i>Approve</button>' +
            '<button class="btn btn-sm btn-outline-danger acc-reject-btn" data-id="' + esc(p.id || '') + '" data-rid="' + esc(String(recordId)) + '" data-name="' + esc(p.full_name || p.name || '') + '">' +
            '<i class="bi bi-x-circle me-1"></i>Reject</button>' +
            '</div></div>';
        }).join('') +
        '</div></div>';
    }

    $panel.html(html).show();
  }

  function updateStep() {
    $(".lhp-modal-step").removeClass("active");
    $("#ms-" + msStep).addClass("active");
    $(".lhp-step-item").each(function () {
      var s = parseInt($(this).data("step"));
      $(this).removeClass("active done");
      if (s === msStep) $(this).addClass("active");
      else if (s < msStep) {
        $(this).addClass("done");
        $(this).find(".lhp-step-num").html('<i class="bi bi-check2"></i>');
      } else $(this).find(".lhp-step-num").text(s);
    });
    $("#ms-prev").css("visibility", msStep > 1 ? "visible" : "hidden");
    $("#ms-next").html(
      msStep === msTotalSteps
        ? '<i class="bi bi-cloud-check-fill me-2"></i>Save Record'
        : 'Continue <i class="bi bi-arrow-right ms-1"></i>',
    );
    $("#ms-progress-text").text("Step " + msStep + " of 7");
    if (msStep === msTotalSteps) buildReview();
  }

  function msNext() {
    // ── Step 1: Subject info validation ───────────────────
    if (msStep === 1) {
      var errs = [];

      // Relationship required
      if (!$("#s-relationship-to-owner").val()) {
        $("#s-relationship-to-owner").addClass("is-invalid");
        errs.push("Please select the relationship to the Lighthouse subject.");
      } else {
        $("#s-relationship-to-owner").removeClass("is-invalid");
      }

      // Full name required
      if (!$("#s-full-name").val().trim()) {
        $("#s-full-name").addClass("is-invalid");
        errs.push("Full legal name is required.");
      } else {
        $("#s-full-name").removeClass("is-invalid");
      }

      // Date of birth required
      if (!$("#s-dob").val()) {
        $("#s-dob").addClass("is-invalid");
        errs.push("Date of birth is required.");
      } else {
        $("#s-dob").removeClass("is-invalid");
      }

      // Email validation (only if provided, skip if optout checked)
      var sEmail = $("#s-email").val().trim();
      var sEmailOo = $("#oo-s-email").is(":checked");
      if (sEmail && !sEmailOo && !isEmail(sEmail)) {
        $("#s-email").addClass("is-invalid");
        errs.push("Please enter a valid email address.");
      } else {
        $("#s-email").removeClass("is-invalid");
      }

      // Phone validation (only if provided, skip if optout checked)
      var phone = $("#s-phone").val().trim();
      var phoneOo = $("#oo-s-phone").is(":checked");
      if (phone && !phoneOo && !isPhone(phone)) {
        $("#s-phone").addClass("is-invalid");
        errs.push("Please enter a valid phone number (10+ digits, e.g. +1 555 000 0000).");
      } else {
        $("#s-phone").removeClass("is-invalid");
      }

      // Address required
      if (!$("#s-address").val().trim()) {
        $("#s-address").addClass("is-invalid");
        errs.push("Primary address is required.");
      } else {
        $("#s-address").removeClass("is-invalid");
      }

      if (errs.length) {
        toast(errs[0], "error");
        // Focus first invalid field
        $("#ms-1 .is-invalid").first().trigger("focus");
        return;
      }
    }

    // ── Step 2: Beneficiaries validation ──
    if (msStep === 2) {
      var childValid = true;
      $("#children-body tr").each(function () {
        var $fn = $(this).find('[data-field="full_name"]');
        var $rel = $(this).find('[data-field="relationship"]');
        var $email = $(this).find('[data-field="email"]');
        var $phone = $(this).find('[data-field="phone"]');
        var emailOo = $(this).find('[data-optout="email"]');
        var phoneOo = $(this).find('[data-optout="phone"]');
        var rowHasData = $(this).find("[data-field]").filter(function () {
          return $(this).val().trim() !== "";
        }).length > 0;
        if (rowHasData) {
          if (!$fn.val().trim()) {
            $fn.addClass("is-invalid"); childValid = false;
          } else { $fn.removeClass("is-invalid"); }
          if (!$rel.val().trim()) {
            $rel.addClass("is-invalid"); childValid = false;
          } else { $rel.removeClass("is-invalid"); }
          // email: must be filled OR optout checked
          if (!emailOo.is(":checked") && !$email.val().trim()) {
            $email.addClass("is-invalid"); childValid = false;
          } else if ($email.val().trim() && !emailOo.is(":checked") && !isEmail($email.val().trim())) {
            $email.addClass("is-invalid"); childValid = false;
          } else { $email.removeClass("is-invalid"); }
          // phone: must be filled OR optout checked
          if (!phoneOo.is(":checked") && !$phone.val().trim()) {
            $phone.addClass("is-invalid"); childValid = false;
          } else if ($phone.val().trim() && !phoneOo.is(":checked") && !isPhone($phone.val().trim())) {
            $phone.addClass("is-invalid"); childValid = false;
          } else { $phone.removeClass("is-invalid"); }
        }
      });
      if (!childValid) {
        toast("Each beneficiary needs Full Name, Relationship, and either an Email/Phone or 'Prefer not to include' checked.", "error");
        return;
      }
    }

    // ── Step 3: Access & Unlock validation ──
    if (msStep === 3) {
      var accessStepCheck = validateAccessRows("access-body");
      if (!accessStepCheck.valid) {
        toast(accessStepCheck.messages[0] || "Please complete all required Access & Unlock fields.", "error");
        return;
      }
    }

    // ── Step 5: Bank account last-4-digits validation ──────
    if (msStep === 5) {
      var bankValid = true;
      $("#bank-body tr").each(function () {
        var $lf = $(this).find('[data-field="last_four"]');
        var val = $lf.val().trim();
        // Validate only if the row has any data
        var rowHasData = $(this).find("[data-field]").filter(function () {
          return $(this).val().trim() !== "";
        }).length > 0;
        if (rowHasData && val) {
          if (!/^\d{4}$/.test(val)) {
            $lf.addClass("is-invalid");
            bankValid = false;
          } else {
            $lf.removeClass("is-invalid");
          }
        } else {
          $lf.removeClass("is-invalid");
        }
      });
      if (!bankValid) {
        toast("Bank account 'Last 4 Digits' must be exactly 4 numbers (e.g. 5678).", "error");
        return;
      }
    }

    if (msStep === 2) {
      currentBeneficiaries = getTableData("children-body").filter(function(r) { return r.full_name; });
      rebuildBeneficiarySelects();
    }
    if (msStep < msTotalSteps) {
      msStep++;
      updateStep();
    } else submitForm();
  }

  function getTableData(bodyId) {
    var rows = [];
    $("#" + bodyId + " tr").each(function () {
      var row = {};
      var $tr = $(this);
      $tr
        .find("[data-field]")
        .each(function () {
          row[$(this).data("field")] = $(this).val();
        });
      $tr.find("[data-optout]:checked").each(function () {
        var target = $(this).data("optout");
        row[target] = '__optout__';
      });
      if (
        Object.values(row).some(function (v) {
          return v && String(v).trim();
        })
      )
        rows.push(row);
    });
    return rows;
  }

  function applyAccessBeneficiaryDetails($row, nameVal) {
    if (!$row || !$row.length || !nameVal) return;
    var $email = $row.find('input[data-field="email"]');
    var $phone = $row.find('input[data-field="phone"]');
    for (var i = 0; i < currentBeneficiaries.length; i++) {
      if (currentBeneficiaries[i].full_name === nameVal) {
        if ($email.length) $email.val(currentBeneficiaries[i].email || "");
        if ($phone.length) $phone.val(currentBeneficiaries[i].phone || "");
        $row.find('[data-optout="email"]').prop("checked", false);
        $row.find('[data-optout="phone"]').prop("checked", false);
        break;
      }
    }
  }

  function validateAccessRows(bodyId) {
    var valid = true;
    var messages = [];
    var fieldLabels = {
      full_name: "Name",
      privilege: "Privilege",
      email: "Email",
      phone: "Phone",
    };
    $("#" + bodyId + " tr").each(function(idx) {
      var $row = $(this);
      var rowData = {};
      $row.find("[data-field]").each(function() {
        var key = $(this).data("field");
        rowData[key] = ($(this).val() || "").trim();
      });
      var rowHasData = Object.keys(fieldLabels).some(function(key) {
        return (rowData[key] || "") !== "";
      });
      if (!rowHasData) {
        $row.find("[data-field]").removeClass("is-invalid");
        return;
      }
      Object.keys(fieldLabels).forEach(function(key) {
        var $field = $row.find('[data-field="' + key + '"]');
        var $oo = $row.find('[data-optout="' + key + '"]');
        if ($oo.is(":checked")) { $field.removeClass("is-invalid"); return; }
        if (!rowData[key]) {
          $field.addClass("is-invalid");
          messages.push("Row " + (idx + 1) + ": " + fieldLabels[key] + " is required.");
          valid = false;
        } else if (key === "email" && !isEmail(rowData[key])) {
          $field.addClass("is-invalid");
          messages.push("Row " + (idx + 1) + ": Invalid email format.");
          valid = false;
        } else {
          $field.removeClass("is-invalid");
        }
      });
    });
    return { valid: valid, messages: messages };
  }

  function collectPayload() {
    return {
      record_id: parseInt($("#lhp-record-id").val()) || 0,
      client_id: parseInt($("#lhp-record-client-id").val()) || 0,
      subject: JSON.stringify({
        full_name: $("#s-full-name").val(),
        preferred_name: $("#s-pref-name").val(),
        dob: $("#s-dob").val(),
        address: $("#s-address").val(),
        email: $("#oo-s-email").is(":checked") ? "" : $("#s-email").val(),
        phone: $("#oo-s-phone").is(":checked") ? "" : $("#s-phone").val(),
        relationship_to_owner: $("#s-relationship-to-owner").val(),
      }),
      children: JSON.stringify(getTableData("children-body")),
      access_people: JSON.stringify(getTableData("access-body")),
      personal_items: JSON.stringify(getTableData("items-body")),
      burial: JSON.stringify({
        owner1: {
          preference: $('input[name="burial_pref_owner1"]:checked').val() || $('input[name="burial_pref"]:checked').val() || '',
          requests: $('#burial-requests-o1').val() || $('#burial-requests').val() || '',
          funeral_home: $('#burial-home-o1').val() || $('#funeral-home').val() || '',
          ashes: $('#burial-ashes').val() || '',
          donation: $('#burial-donation').val() || '',
          other: $('#burial-other').val() || '',
        },
        owner2: {
          preference: $('input[name="burial_pref_owner2"]:checked').val() || '',
          requests: $('#burial-requests-o2').val() || '',
          funeral_home: $('#burial-home-o2').val() || '',
          ashes: '',
          donation: '',
          other: '',
        },
      }),
      bank_accounts: JSON.stringify(getTableData("bank-body")),
      life_insurance: JSON.stringify(getTableData("insurance-body")),
      letters: JSON.stringify(getTableData("letters-body")),
    };
  }

  function buildReview() {
    var s = {
      full_name: $("#s-full-name").val(),
      dob: $("#s-dob").val(),
      address: $("#s-address").val(),
      phone: $("#s-phone").val(),
      email: $("#s-email").val(),
      relationship: $("#s-relationship-to-owner").val() || "—",
    };

    // Relationship label map
    var relMap = {
      self: "Myself", parent: "Parent / Guardian", spouse: "Spouse / Partner",
      child: "Adult Child", sibling: "Sibling", grandparent: "Grandparent",
      "in-law": "In-Law", friend: "Close Friend", other: "Other"
    };
    var relLabel = relMap[s.relationship] || s.relationship;

    var bankRows = getTableData("bank-body");
    var childRows = getTableData("children-body");
    var accessRows = getTableData("access-body");
    var itemRows = getTableData("items-body");
    var insRows = getTableData("insurance-body");
    var letterRows = getTableData("letters-body");

    // Build bank display (mask to "•••• XXXX")
    var bankDisplay = bankRows.length
      ? bankRows.map(function(b){ return esc((b.institution||"?") + " — " + (b.account_type||"") + " •••• " + (b.last_four||"????") ); }).join("<br>")
      : "None";

    var ol = getOwnerLabels();
    var burialO1 = $('input[name="burial_pref_owner1"]:checked').val();
    var burialO2 = $('input[name="burial_pref_owner2"]:checked').val();
    var burialVal = burialO1 ? ol.o1 + ': ' + burialO1 : '';
    burialVal += burialO2 ? (burialVal ? ' | ' : '') + ol.o2 + ': ' + burialO2 : '';

    var items = [
      { icon: "bi-diagram-2", label: "Relationship to You", value: relLabel, highlight: true },
      { icon: "bi-person-vcard", label: "Subject Name", value: (s.full_name || "—") + (s.dob ? " · DOB: " + s.dob : "") },
      { icon: "bi-telephone", label: "Phone", value: $("#oo-s-phone").is(":checked") ? "Prefer not to include" : (s.phone || "—") },
      { icon: "bi-envelope", label: "Email", value: $("#oo-s-email").is(":checked") ? "Prefer not to include" : (s.email || "—") },
      { icon: "bi-geo-alt", label: "Address", value: s.address || "—" },
      { icon: "bi-people", label: "Children / Heirs", value: childRows.length ? childRows.length + (childRows.length === 1 ? " person: " : " people: ") + childRows.map(function(c){return c.full_name||"?";}).join(", ") : "None added" },
      { icon: "bi-shield-lock", label: "Access Contacts", value: accessRows.length ? accessRows.length + (accessRows.length === 1 ? " person: " : " people: ") + accessRows.map(function(a){return a.full_name||"?";}).join(", ") : "None added" },

      { icon: "bi-flower1", label: "Burial Preference", value: burialVal || "—" },
      { icon: "bi-gift", label: "Personal Items", value: itemRows.length ? itemRows.length + (itemRows.length === 1 ? " item" : " items") : "None added" },
      { icon: "bi-bank", label: "Bank Accounts", value: bankRows.length ? bankRows.length + (bankRows.length === 1 ? " account" : " accounts") : "None added" },
      { icon: "bi-file-earmark-text", label: "Life Insurance", value: insRows.length ? insRows.length + (insRows.length === 1 ? " policy" : " policies") : "None added" },
      { icon: "bi-envelope-heart", label: "Letters & Messages", value: letterRows.length ? letterRows.length + (letterRows.length === 1 ? " letter" : " letters") : "None added" },
      { icon: "bi-people-fill", label: "Delegated Users", value: delegatedEntries.length ? delegatedEntries.length + (delegatedEntries.length === 1 ? " person" : " people") : "None added" },
    ];
    $("#lhp-review-summary").html(
      items
        .map(function (i) {
          var highlightStyle = i.highlight ? "background:var(--lhp-primary-light,#eff6ff);border-left:4px solid var(--lhp-primary);" : "";
          return (
            '<div class="lhp-review-item" style="' + highlightStyle + '">' +
            '<div class="lhp-review-item-label"><i class="bi ' + i.icon + ' me-1"></i>' + i.label + "</div>" +
            '<div class="lhp-review-item-value">' + (i.raw ? i.value : esc(i.value)) + "</div></div>"
          );
        })
        .join(""),
    );
  }

  function submitForm() {
    if (!$("#lhp-acknowledge").is(":checked")) {
      toast("Please check the acknowledgement box.", "error");
      return;
    }
    var $btn = $("#ms-next");
    $btn
      .prop("disabled", true)
      .html(
        '<span class="spinner-border spinner-border-sm me-2"></span>Saving Record…',
      );
    ajax(
      "lhp_save_record",
      collectPayload(),
      function (data) {
        $("#form-success")
          .text("Saved! " + data.completion + "% complete.")
          .removeClass("d-none");
        toast("Record saved.");
        $("#lhp-record-id").val(data.record_id);
        $btn.prop("disabled", false).html('<i class="bi bi-cloud-check-fill me-2"></i>Save Record');
        loadRecords();
        // Flush buffered delegated entries for newly-created records
        var tempEntries = delegatedEntries.filter(function(d) { return String(d.id).indexOf('temp_') === 0; });
        tempEntries.forEach(function(d) {
          ajax('lhp_save_delegated_user', {
            record_id: data.record_id, name: d.name, email: d.email,
            relationship: d.relationship, condition_type: d.condition_type,
            condition_date: d.condition_date, condition_event: d.condition_event,
          }, function(res) {
            delegatedEntries = delegatedEntries.filter(function(x) { return x.id !== d.id; });
            delegatedEntries.push(res.entry);
          });
        });
        if (formPendingFiles.length) {
          uploadPendingFiles(data.record_id, formPendingFiles.slice(), function () {
            formPendingFiles = [];
            renderMs5PendingList();
            setTimeout(function () { hideModal("lhp-form-modal"); }, 800);
          });
        } else {
          setTimeout(function () { hideModal("lhp-form-modal"); }, 1800);
        }
      },
      function (msg) {
        $btn
          .prop("disabled", false)
          .html('<i class="bi bi-cloud-check-fill me-2"></i>Save Record');
        $("#form-error").text(msg).removeClass("d-none");
        toast(msg, "error");
      },
    );
  }

  /* ── addRow: append a new row to an existing tbody in DOM ── */
  function addRow(table, d) {
    var bodyId = {
      children: "children-body",
      access: "access-body",
      items: "items-body",
      bank: "bank-body",
      insurance: "insurance-body",
      letters: "letters-body",
    }[table];
    var html = buildRowHtml(table, d || {});
    if (!bodyId || !html) return;
    $("#" + bodyId).append(html);
  }

  /* ═══════════════════════════════════════════════════════
     PARENT DASHBOARD
  ═══════════════════════════════════════════════════════ */
  var myData = {},
    currentSection = "",
    currentBeneficiaries = [];

  function initWelcomeModal() {
    var cfg = window.lhpInviteConfig || {};
    if (!cfg.isNewInvite && !cfg.needsPassword) return;

    var heading = cfg.invitedBy
      ? "You've been invited by " + cfg.invitedBy + "!"
      : "Welcome to Our Family Lighthouse!";
    var sub = cfg.invitedBy
      ? "Your estate planner has set up a Family Lighthouse record for you. Create a password to access it."
      : "Please create a password to secure your account.";
    $("#lhp-welcome-heading").text(heading);
    $("#lhp-welcome-subtext").text(sub);
    getModal("lhp-welcome-modal").show();

    $(document).on("click", "#lhp-welcome-save-pass", function () {
      var $btn = $(this);
      var pass  = $("#welcome-pass").val();
      var pass2 = $("#welcome-pass2").val();
      $("#welcome-error").addClass("d-none").text("");
      if (pass.length < 8) {
        $("#welcome-error").removeClass("d-none").text("Password must be at least 8 characters.");
        return;
      }
      if (pass !== pass2) {
        $("#welcome-error").removeClass("d-none").text("Passwords do not match.");
        return;
      }
      btnLoad($btn, true);
      ajax("lhp_set_invite_password", { password: pass, password2: pass2 }, function () {
        $("#welcome-success").removeClass("d-none").text("Password saved! Welcome to your Family Lighthouse.");
        btnLoad($btn, false);
        setTimeout(function () {
          var modal = bootstrap.Modal.getInstance(document.getElementById("lhp-welcome-modal"));
          if (modal) modal.hide();
          if (window.history && window.history.replaceState) {
            var url = window.location.href.replace(/[?&]lhp_new_invite=1/, "");
            window.history.replaceState({}, document.title, url);
          }
        }, 1500);
      }, function (msg) {
        btnLoad($btn, false);
        $("#welcome-error").removeClass("d-none").text(msg);
      });
    });
  }

  function initParentDashboard() {
    initSidebar();
    initParentMultiRecords();
    loadParentProfile();
    // Also init delegated step for parent forms
    initDelegatedStep();
    $("#lhp-save-pprofile").on("click", saveParentProfile);
    $(document).off("click", "#lhp-save-o2-profile").on("click", "#lhp-save-o2-profile", saveParentOwner2);
    // Add Co-Owner button → show form
    $(document).off("click", "#ppar-o2-add-btn").on("click", "#ppar-o2-add-btn", function() {
      $("#ppar-o2-empty").hide();
      $("#ppar-o2-fields").show();
      $("#ppar-o2-cancel-btn").show();
      $("#ppar-o2-name").trigger("focus");
    });
    // Cancel → hide form, show empty state
    $(document).off("click", "#ppar-o2-cancel-btn").on("click", "#ppar-o2-cancel-btn", function() {
      $("#ppar-o2-fields").hide();
      $("#ppar-o2-cancel-btn").hide();
      $("#ppar-o2-empty").show();
      $("#ppar-o2-name,#ppar-o2-email,#ppar-o2-phone").val("");
      $("#ppar-o2-error,#ppar-o2-success").addClass("d-none");
    });
    $(document).off("click", "#lhp-show-pass-form").on("click", "#lhp-show-pass-form", function () {
      $("#ppar-pass-form").slideDown(180);
      $(this).hide();
    });
    $(document).off("click", "#lhp-cancel-pass").on("click", "#lhp-cancel-pass", function () {
      $("#ppar-pass-form").slideUp(180);
      $("#lhp-show-pass-form").show();
      $("#ppar-new-pass,#ppar-confirm-pass").val("");
      $("#ppar-pass-success,#ppar-pass-error").addClass("d-none");
    });
    $(document).off("click", "#lhp-change-pass").on("click", "#lhp-change-pass", changeParentPassword);

    $(document).off("click", ".lhp-open-section").on("click", ".lhp-open-section", function () {
      openSectionModal($(this).data("section"));
    });
    $(document).off("click", ".lhp-add-row").on("click", ".lhp-add-row", function () {
      addRow($(this).data("table"));
    });
    $(document).off("click", ".lhp-remove-row").on("click", ".lhp-remove-row", function () {
      $(this).closest("tr").remove();
    });
    $(document).on(
      "change",
      'input[name^="burial_pref_"]',
      syncChoice,
    );

    // Init existing "Other" selects on page load
    setTimeout(function() {
      $('#items-body select[data-field="recipient"], #letters-body select[data-field="recipient"]').each(function() {
        if ($(this).val() === 'Other') otherMakeInput($(this), 'Enter name');
      });
    }, 100);

    // Section modal save - use delegated to avoid double-binding
    $(document)
      .off("click", "#lhp-save-section")
      .on("click", "#lhp-save-section", saveSection);

    // ── NEW: "Review Full Lighthouse" button (parent) ──────
    $(document).on("click", "#lhp-parent-review-btn", function () {
      var recordId = parseInt($("#lhp-my-record-id").val()) || 0;
      if (!recordId) {
        toast("Please open a Family Lighthouse record first.", "info");
        return;
      }
      openRecordDetail(recordId);
    });

    // ── Welcome / invite modal ──────────────────────────
    initWelcomeModal();
  }

  function loadMyRecord(recordId, cb) {
    ajax(
      "lhp_get_record",
      { record_id: recordId },
      function (data) {
        myData = data;
        currentBeneficiaries = data.children || [];
        computeOwnerLabels(data);
        updateRings(data.completion);
        renderSectionCards(data);
        if (typeof cb === 'function') cb();
      },
      function (m) {
        toast(m, "error");
      },
    );
  }
  function updateRings(pct) {
    pct = parseInt(pct) || 0;
    $("#header-pct,#sidebar-pct").text(pct + "%");
    $("#header-arc,#sidebar-arc").attr("stroke-dasharray", pct + ",100");
    // Dynamic arc stroke colour: red→yellow→green
    var colour = pct < 40 ? "#ef4444" : pct < 80 ? "#f59e0b" : "#10b981";
    $("#header-arc").attr("stroke", colour);
    // Update subtitle text
    var sub =
      pct >= 100
        ? "Profile complete!"
        : pct > 0
          ? "Keep filling in sections"
          : "Start filling in sections";
    $("#header-completion-pill .lhp-pill-sub").text(sub);
  }

  var sectionDefs = [
    { key: "children", icon: "bi-people", title: "Beneficiaries" },
    { key: "access", icon: "bi-shield-lock", title: "Access & Unlock" },
    { key: "personal_items", icon: "bi-gift", title: "Personal Items" },
    { key: "burial", icon: "bi-flower2", title: "End of Life Preferences" },
    { key: "bank_accounts", icon: "bi-bank", title: "Bank Accounts" },
    {
      key: "life_insurance",
      icon: "bi-file-earmark-text",
      title: "Life Insurance",
    },
    { key: "letters", icon: "bi-envelope-heart", title: "Letters & Messages" },
    { key: "documents", icon: "bi-paperclip", title: "Documents & Photos" },
    { key: "social_media", icon: "bi-phone-fill", title: "Social Media" },
    { key: "additional_wishes", icon: "bi-journal-text", title: "Additional Instructions & Wishes" },
  ];

  function secPreview(key, data) {
    var d = data[key] || {};
    function lst(arr, f) {
      return arr && arr.length
        ? arr
            .slice(0, 3)
            .map(function (i) {
              return i[f] || "";
            })
            .filter(Boolean)
            .join(", ") +
            (arr.length > 3 ? " +" + (arr.length - 3) + " more" : "")
        : null;
    }
    if (key === "children") return lst(data.children, "full_name");
    if (key === "access") return lst(data.access_people, "full_name");
    if (key === "personal_items")
      return lst(data.personal_items, "description");
    if (key === "burial") {
      var b1 = d.owner1 || {};
      var b2 = d.owner2 || {};
      var parts = [];
      if (b1.preference) parts.push(esc((getOwnerLabels().o1 || 'Owner 1')) + ': ' + b1.preference);
      if (b2.preference) parts.push(esc((getOwnerLabels().o2 || 'Owner 2')) + ': ' + b2.preference);
      return parts.length ? parts.join(' | ') : null;
    }
    if (key === "bank_accounts") return lst(data.bank_accounts, "institution");
    if (key === "life_insurance") return lst(data.life_insurance, "provider");
    if (key === "documents") {
      var docFiles = (data.docs||[]).filter(function(f){ return f._section !== 'letters'; });
      if (!docFiles.length) return null;
      var withRecip = docFiles.filter(function(doc){ return doc.recipient; });
      return docFiles.length + " file(s)" + (withRecip.length ? ", " + withRecip.length + " assigned" : "");
    }
    if (key === "letters") {
      var letFiles = (data.docs||[]).filter(function(d){ return d._section === 'letters'; });
      var letAssigned = letFiles.filter(function(d){ return d.recipient; });
      var letterCount = (data.letters||[]).length;
      var parts = [];
      if (letterCount) parts.push(letterCount + (letterCount === 1 ? ' message' : ' messages'));
      if (letFiles.length) parts.push(letFiles.length + (letFiles.length === 1 ? ' file' : ' files') + (letAssigned.length ? ', ' + letAssigned.length + ' assigned' : ''));
      return parts.length ? parts.join(', ') : null;
    }
    if (key === "social_media") return data.social_media ? data.social_media.slice(0, 60) + (data.social_media.length > 60 ? "…" : "") : null;
    if (key === "additional_wishes") return data.additional_wishes ? data.additional_wishes.slice(0, 60) + (data.additional_wishes.length > 60 ? "…" : "") : null;
    return null;
  }

  function renderSectionCards(data) {
    var html = sectionDefs
      .map(function (def) {
        var preview = secPreview(def.key, data);
        var filled = !!preview;
        var badge = filled
          ? '<span class="lhp-section-status-filled"><i class="bi bi-check-circle-fill me-1"></i>Filled</span>'
          : '<span class="lhp-section-status-empty"><i class="bi bi-exclamation-circle me-1"></i>Empty</span>';
        var btnCls = "lhp-btn-primary-solid";
        var btnIcon = filled ? "bi-pencil-square" : "bi-plus-circle-fill";
        var btnLbl = filled ? "Edit" : "Add Info";
        return (
          '<div class="col-sm-6 col-xl-3"><div class="lhp-section-card h-100">' +
          '<div class="lhp-section-card-head">' +
          '<div class="lhp-section-icon-wrap"><i class="bi ' +
          def.icon +
          '"></i></div>' +
          '<div class="flex-grow-1 min-w-0"><div class="lhp-section-title">' +
          def.title +
          "</div></div>" +
          badge +
          "</div>" +
          '<div class="lhp-section-preview">' +
          esc(preview || "Not filled in yet") +
          "</div>" +
          '<div class="lhp-section-card-footer">' +
          '<button class="btn btn-sm w-100 ' +
          btnCls +
          ' lhp-open-section" data-section="' +
          def.key +
          '">' +
          '<i class="bi ' +
          btnIcon +
          ' me-1"></i>' +
          btnLbl +
          "</button>" +
          "</div></div></div>"
        );
      })
      .join("");
    $("#lhp-sections-grid").html('<div class="row g-3">' + html + "</div>");
  }

  function openSectionModal(section) {
    currentSection = section;
    computeOwnerLabels(myData);
    var def =
      sectionDefs.find(function (d) {
        return d.key === section;
      }) || {};
    $("#section-modal-title").html(
      '<i class="bi ' +
        (def.icon || "bi-pencil") +
        ' me-2"></i>' +
        (def.title || section),
    );
    $("#section-modal-body").html(buildSectionForm(section, myData));
    $("#section-save-error,#section-save-success").addClass("d-none").text("");
    if (section === "burial" && myData.burial) {
      ['owner1','owner2'].forEach(function(prefix) {
        var pref = (myData.burial[prefix] || {}).preference;
        if (pref) $('input[name="burial_pref_' + prefix + '"][value="' + pref + '"]').prop('checked', true).trigger('change');
      });
    }
    var SEC_MAX = 10 * 1024 * 1024;
    var SEC_TYPES = ["application/pdf","application/msword",
      "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
      "image/jpeg","image/png"];

    function bindFileUpload(uploadZoneId, inputId, listId) {
      var secRidU = parseInt($("#lhp-my-record-id").val()) || 0;
      $(document).off("click","#" + uploadZoneId).on("click","#" + uploadZoneId, function(){
        $("#" + inputId).trigger("click");
      });
      $(document).off("dragover","#" + uploadZoneId).on("dragover","#" + uploadZoneId, function(e){
        e.preventDefault(); $(this).addClass("lhp-upload-zone-hover");
      });
      $(document).off("dragleave","#" + uploadZoneId).on("dragleave","#" + uploadZoneId, function(){
        $(this).removeClass("lhp-upload-zone-hover");
      });
      $(document).off("drop","#" + uploadZoneId).on("drop","#" + uploadZoneId, function(e){
        e.preventDefault(); $(this).removeClass("lhp-upload-zone-hover");
        uploadFiles(Array.from(e.originalEvent.dataTransfer.files || []), secRidU, listId);
      });
      $(document).off("change","#" + inputId).on("change","#" + inputId, function(){
        var files = Array.from(this.files || []);
        $(this).val("");
        uploadFiles(files, secRidU, listId);
      });
    }

    function uploadFiles(files, secRidU, listId) {
      var section = listId === "sec-docs-list" ? "documents" : "letters";
      var zoneId = listId.replace("-list", "-upload-zone");
      var $zone = $("#" + zoneId);
      var zoneOrigHtml = $zone.html();
      var pending = Array.from(files);

      function showZoneProgress(fileName, pct) {
        $zone.html(
          '<div class="lhp-upload-progress-wrap">' +
          '<i class="bi bi-cloud-arrow-up-fill"></i>' +
          '<div class="fw-semibold mb-2">' + esc(fileName) + '</div>' +
          '<div class="lhp-upload-progress-track"><div class="lhp-upload-progress-bar" style="width:' + pct + '%"></div></div>' +
          '<small class="text-muted mt-1 d-block">' + pct + '%</small>' +
          '</div>'
        );
      }

      function uploadNext() {
        if (!pending.length) { $zone.html(zoneOrigHtml); return; }
        var file = pending.shift();
        if (!SEC_TYPES.includes(file.type)) { toast(file.name + ": unsupported file type.", "error"); uploadNext(); return; }
        if (file.size > SEC_MAX) { toast(file.name + ": exceeds 10 MB limit.", "error"); uploadNext(); return; }
        showZoneProgress(file.name, 0);
        var fd = new FormData();
        fd.append("action","lhp_upload_file");
        fd.append("nonce",cfg.nonce);
        fd.append("record_id",secRidU);
        fd.append("section", section);
        fd.append("file",file);
        $.ajax({
          url: cfg.ajax_url, type: "POST", data: fd, processData: false, contentType: false,
          xhr: function() {
            var xhrObj = $.ajaxSettings.xhr();
            if (xhrObj.upload) {
              xhrObj.upload.addEventListener("progress", function(e) {
                if (e.lengthComputable) showZoneProgress(file.name, Math.round(e.loaded / e.total * 100));
              });
            }
            return xhrObj;
          }
        })
          .done(function(r){
            if (r.success) {
              var curRecip = {};
              var curOwner = {};
              $(".doc-recipient-sel").each(function() {
                curRecip[$(this).data("doc-id")] = $(this).val();
              });
              $(".doc-owner-sel").each(function() {
                curOwner[$(this).data("doc-id")] = $(this).val();
              });
              var merged = (r.data.docs || []).map(function(d) {
                var patch = {};
                if (curRecip[d.id]) patch.recipient = curRecip[d.id];
                if (curOwner[d.id]) patch.owner = curOwner[d.id];
                return Object.keys(patch).length ? Object.assign({}, d, patch) : d;
              });
              myData.docs = merged;
              if ($("#sec-docs-list").length) {
                var docDocs = merged.filter(function(d){ return d._section !== 'letters'; });
                $("#sec-docs-list").html(renderDocsEditable(docDocs));
              }
              if ($("#let-docs-list").length) {
                var letDocs = merged.filter(function(d){ return d._section === 'letters'; });
                $("#let-docs-list").html(renderDocsEditable(letDocs));
              }
              renderSectionCards(myData);
              if (!pending.length) toast("All files uploaded.");
            } else {
              if (r.data === "Security check failed.") {
                refreshNonce(function() { toast("Session refreshed — please try uploading again.", "info"); });
              } else {
                toast(r.data, "error");
              }
            }
          })
          .always(uploadNext);
      }
      uploadNext();
    }

    $(document).off("click",".rd-del-file").on("click",".rd-del-file", function(){
      var delRid = parseInt($("#lhp-my-record-id").val()) || 0;
      ajax("lhp_delete_file", { record_id: delRid, attachment_id: $(this).data("att") }, function(docs){
        var curRecip = {};
        var curOwner = {};
        $(".doc-recipient-sel").each(function() {
          curRecip[$(this).data("doc-id")] = $(this).val();
        });
        $(".doc-owner-sel").each(function() {
          curOwner[$(this).data("doc-id")] = $(this).val();
        });
        var merged = (docs || []).map(function(d) {
          var patch = {};
          if (curRecip[d.id]) patch.recipient = curRecip[d.id];
          if (curOwner[d.id]) patch.owner = curOwner[d.id];
          return Object.keys(patch).length ? Object.assign({}, d, patch) : d;
        });
        myData.docs = merged;
        if ($("#sec-docs-list").length) {
          var docDocsDel = merged.filter(function(d){ return d._section !== 'letters'; });
          $("#sec-docs-list").html(renderDocsEditable(docDocsDel));
        }
        if ($("#let-docs-list").length) {
          var letDocsDel = merged.filter(function(d){ return d._section === 'letters'; });
          $("#let-docs-list").html(renderDocsEditable(letDocsDel));
        }
        renderSectionCards(myData);
        toast("File removed.", "info");
      });
    });

    if (section === "documents") {
      $("#lhp-save-section").html('<i class="bi bi-check-circle me-2"></i>Save Changes');
      bindFileUpload("sec-docs-upload-zone", "sec-docs-input", "sec-docs-list");
    } else if (section === "letters") {
      $("#lhp-save-section").html('<i class="bi bi-check-circle me-2"></i>Save Changes');
      bindFileUpload("let-docs-upload-zone", "let-docs-input", "let-docs-list");
    } else {
      $("#lhp-save-section").html('<i class="bi bi-check-circle me-2"></i>Save Changes');
    }
    showModal("lhp-section-modal");
  }

  function buildSectionForm(section, data) {
    var fc = "form-control",
      fs = "form-select",
      s = data.subject || {},
      b = data.burial || {};
    function inp(id, type, val, ph) {
      return (
        '<input type="' +
        type +
        '" class="' +
        fc +
        '" id="' +
        id +
        '" value="' +
        esc(val) +
        '" placeholder="' +
        ph +
        '">'
      );
    }
    switch (section) {
      case "subject":
        return (
          '<div class="row g-3">' +
          '<div class="col-12"><label class="form-label fw-semibold">Relationship to You <span class="text-danger">*</span></label>' +
          '<select class="form-select" id="sec-relationship-to-owner">' +
          '<option value="">— Select Relationship —</option>' +
          ['self:Myself','parent:Parent / Guardian','spouse:Spouse / Partner','child:Adult Child',
           'sibling:Sibling','grandparent:Grandparent','in-law:In-Law','friend:Close Friend',
           'other:Other'].map(function(o){
             var parts = o.split(':'); var val = parts[0]; var lbl = parts[1];
             return '<option value="' + val + '"' + ((s.relationship_to_owner||'') === val ? ' selected' : '') + '>' + lbl + '</option>';
           }).join('') +
          '</select>' +
          '<div class="form-text text-muted"><i class="bi bi-info-circle me-1"></i>Who is this Lighthouse for?</div></div>' +
          '<div class="col-md-6"><label class="form-label fw-semibold">Full Legal Name *</label>' +
          inp("sec-full-name", "text", s.full_name || "", "Full Legal Name") +
          "</div>" +
          '<div class="col-md-6"><label class="form-label fw-semibold">Preferred Name</label>' +
          inp("sec-pref-name", "text", s.preferred_name || "", "Nickname") +
          "</div>" +
          '<div class="col-md-4"><label class="form-label fw-semibold">Date of Birth</label>' +
          inp("sec-dob", "date", s.dob || "", "") +
          "</div>" +
           '<div class="col-md-4"><label class="form-label fw-semibold">Phone</label>' +
           '<input type="tel" class="form-control lhp-phone-field" id="sec-phone" value="' + esc(s.phone||'') + '" placeholder="+1 (555) 000-0000">' +
           '<label class="lhp-optout" for="oo-sec-phone"><input type="checkbox" class="lhp-optout-chk" data-optout="sec-phone" id="oo-sec-phone"><span>Prefer not to include</span></label>' +
           '<div class="invalid-feedback">Enter a valid phone number.</div></div>' +
           '<div class="col-md-4"><label class="form-label fw-semibold">Email</label>' +
           inp("sec-email", "email", s.email || "", "Optional") +
           '<label class="lhp-optout" for="oo-sec-email"><input type="checkbox" class="lhp-optout-chk" data-optout="sec-email" id="oo-sec-email"><span>Prefer not to include</span></label></div>' +
          '<div class="col-12"><label class="form-label fw-semibold">Primary Address</label>' +
          inp("sec-address", "text", s.address || "", "Street, City, State, ZIP") +
          "</div></div>"
        );
      case "burial": {
        var b1 = (b.owner1 || {});
        var b2 = (b.owner2 || {});
        var ol = getOwnerLabels();
        function burialPanel(prefix, ownerLabel, saved) {
          var ashesHidden = saved.preference !== 'cremation' ? ' style="display:none"' : '';
          return '<div class="lhp-card mb-3 p-3">' +
            '<h6 class="fw-bold mb-3"><i class="bi bi-person-fill me-1"></i>' + esc(ownerLabel) + '</h6>' +
            '<div class="lhp-choice-cards mb-3">' +
            '<label class="lhp-choice-card"><input type="radio" name="burial_pref_' + prefix + '" value="burial"' + (saved.preference === 'burial' ? ' checked' : '') + '><div class="lhp-choice-inner"><i class="bi bi-flower1"></i><span>Burial</span></div></label>' +
            '<label class="lhp-choice-card"><input type="radio" name="burial_pref_' + prefix + '" value="cremation"' + (saved.preference === 'cremation' ? ' checked' : '') + '><div class="lhp-choice-inner"><i class="bi bi-wind"></i><span>Cremation</span></div></label></div>' +
            '<div class="mb-2 burial-ashes" data-owner="' + prefix + '"' + ashesHidden + '><label class="form-label fw-semibold">Ashes Management</label><input type="text" class="form-control burial-ashes-input" data-owner="' + prefix + '" value="' + esc(saved.ashes || '') + '" placeholder="e.g. Scatter at sea, kept in urn, etc."></div>' +
            '<div class="mb-2"><label class="form-label fw-semibold">Specific Requests</label><textarea class="form-control burial-req" data-owner="' + prefix + '" rows="3" placeholder="e.g. Music, flowers, dress code, readings…">' + esc(saved.requests || '') + '</textarea></div>' +
            '<div class="mb-2"><label class="form-label fw-semibold">Preferred Funeral Home</label><input type="text" class="form-control burial-home" data-owner="' + prefix + '" value="' + esc(saved.funeral_home || '') + '" placeholder="e.g. Smith &amp; Sons Funeral Home"></div>' +
            '<div class="mb-2"><label class="form-label fw-semibold">Charitable Donations</label><input type="text" class="form-control burial-donation" data-owner="' + prefix + '" value="' + esc(saved.donation || '') + '" placeholder="e.g. Red Cross, local food bank"></div>' +
            '<div class="mb-2"><label class="form-label fw-semibold">Other Wishes</label><input type="text" class="form-control burial-other" data-owner="' + prefix + '" value="' + esc(saved.other || '') + '" placeholder="e.g. No black clothing, celebrate outdoors"></div>' +
            '</div>';
        }
        return burialPanel('owner1', ol.o1, b1) + (_noOwner2 ? '' : burialPanel('owner2', ol.o2, b2));
      }
      case "children":
        return repForm(
          "children",
          "children-body",
          data.children || [],
          ["full_name", "relationship", "email", "phone"],
          ['Full Name <span class="text-danger">*</span>', 'Relationship <span class="text-danger">*</span>', "Email", "Phone"],
        );
      case "access": {
        var accRows = (data.access_people && data.access_people.length ? data.access_people : [{}]).map(function(d) {
          return buildRowHtml("access", d);
        }).join("");
        return (
          '<div class="lhp-repeater"><table class="table lhp-repeater-table mb-0">' +
          '<thead><tr><th>Full Name <span class="text-danger">*</span></th><th>Privilege <span class="text-danger">*</span></th><th>Email</th><th>Phone</th><th style="width:44px"></th></tr></thead>' +
          '<tbody id="access-body">' + accRows + '</tbody>' +
          '</table>' +
          '<button class="btn btn-sm btn-outline-primary mt-2 lhp-add-row" data-table="access">' +
          '<i class="bi bi-plus-circle me-1"></i>Add Row</button></div>'
        );
      }
      case "personal_items":
        return repForm(
          "items",
          "items-body",
          data.personal_items || [],
          ["description", "recipient", "notes"],
          ["Item Description", "Who Should Receive It", "Notes"],
        );
      case "bank_accounts": {
        return repForm(
          "bank",
          "bank-body",
          data.bank_accounts || [],
          ["institution", "account_type", "last_four", "owner"],
          ["Institution", "Account Type", "Last 4 Digits", "Owner"],
        );
      }
      case "life_insurance": {
        var o2li = data.owner2 || {};
        var o1Name = data.owner_name || (data.subject && data.subject.full_name) || '';
        var o1First = o1Name.split(' ')[0] || 'Owner 1';
        var ownerRef =
          '<div class="alert alert-light py-2 mb-3 small border">' +
          '<i class="bi bi-info-circle me-1"></i>' +
          '<strong>' + esc(o1First) + ':</strong> ' + esc(o1Name || 'Not set') +
          (o2li.full_name ? '&nbsp;&nbsp;<strong>Owner 2:</strong> ' + esc(o2li.full_name) : ' &nbsp;&nbsp;<strong>Owner 2:</strong> Not set') +
          ' &nbsp;<span class="text-muted">(edit in Bank Accounts section)</span>' +
          '</div>';
        return ownerRef + repForm(
          "insurance",
          "insurance-body",
          data.life_insurance || [],
          ["provider", "policy", "notes", "owner"],
          ["Provider", "Policy #", "Notes", "Owner"],
        );
      }
      case "letters": {
        var letterDocs = (data.docs||[]).filter(function(d){ return d._section === 'letters'; });
        var letExistingDocs = letterDocs.length
          ? renderDocsEditable(letterDocs)
          : '<p class="text-muted small text-center py-2">No documents uploaded yet.</p>';
        var letRows = (data.letters && data.letters.length ? data.letters : [{}]).map(function(d) {
          return buildRowHtml("letters", d);
        }).join("");
        return (
          '<div class="lhp-repeater"><table class="table lhp-repeater-table mb-0">' +
          '<thead><tr><th>Recipient</th><th>Owner</th><th>Letter</th><th style="width:44px"></th></tr></thead>' +
          '<tbody id="letters-body">' + letRows + '</tbody>' +
          '</table>' +
          '<button class="btn btn-sm btn-outline-primary mt-2 lhp-add-row" data-table="letters">' +
          '<i class="bi bi-plus-circle me-1"></i>Add Row</button></div>'
        ) +
        '<h6 class="fw-semibold mt-4 mb-2">Letters and messages for your loved ones</h6>' +
        '<p class="text-muted small mb-2">Attach documents, photos or any files. Assign recipients below. PDF, DOC, DOCX, JPG, PNG — max 10 MB each.</p>' +
        '<input type="file" id="let-docs-input" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display:none">' +
        '<div class="lhp-upload-zone" id="let-docs-upload-zone">' +
        '<i class="bi bi-cloud-arrow-up-fill"></i>' +
        '<div class="fw-semibold">Drag &amp; drop or click to select files</div>' +
        '<small class="text-muted">Multiple files allowed</small>' +
        '</div>' +
        '<div id="let-docs-list" class="mt-3">' + letExistingDocs + '</div>';
      }
      case "documents": {
        var docDocs = (data.docs||[]).filter(function(d){ return d._section !== 'letters'; });
        var existingDocsHtml = renderDocsEditable(docDocs);
        return '<p class="text-muted mb-3 small"><i class="bi bi-info-circle me-1"></i>Assign a recipient and owner to each document or photo. Use <strong>Everyone</strong> to share with all beneficiaries.</p>' +
          '<div id="sec-docs-list" class="mb-3">' + existingDocsHtml + '</div>' +
          '<p class="text-muted mb-2 small fw-semibold">Add More Files</p>' +
          '<input type="file" id="sec-docs-input" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display:none">' +
          '<div class="lhp-upload-zone" id="sec-docs-upload-zone">' +
          '<i class="bi bi-cloud-arrow-up-fill"></i>' +
          '<div class="fw-semibold">Drag &amp; drop or click to select files</div>' +
          '<small class="text-muted">PDF, DOC, DOCX, JPG, PNG — max 10 MB each</small>' +
          '</div>';
      }
      case "social_media":
        return (
          '<p class="text-muted mb-3 small">Provide instructions on how your social media accounts should be managed after you pass. For example: delete accounts, memorialize, or leave as-is.</p>' +
          '<div class="mb-3"><label class="form-label fw-semibold">Social Media Instructions</label>' +
          '<textarea class="form-control" id="sec-social-media" rows="6" placeholder="e.g. Delete all accounts. / Memorialize my Facebook profile. / Leave accounts as-is.">' +
          esc(data.social_media || "") + '</textarea></div>'
        );
      case "additional_wishes":
        return (
          '<p class="text-muted mb-3 small">Use this space for any additional instructions, wishes, or information that does not fit in other sections.</p>' +
          '<div class="mb-3"><label class="form-label fw-semibold">Additional Instructions &amp; Wishes</label>' +
          '<textarea class="form-control" id="sec-additional-wishes" rows="8" placeholder="Enter any additional wishes, instructions, or information here…">' +
          esc(data.additional_wishes || "") + '</textarea></div>'
        );
    }
    return "";
  }

  var _ownerLabels = { o1: 'Owner 1', o2: 'Owner 2', both: 'Owner 1 & 2' };
  var _noOwner2 = true;

  function computeOwnerLabels(data) {
    if (!data) data = typeof myData !== 'undefined' ? myData : null;
    if (!data) { _ownerLabels = { o1: 'Owner 1', o2: 'Owner 2', both: 'Owner 1 & 2' }; _noOwner2 = true; return; }
    var o1 = data.owner_name || (data.subject && data.subject.full_name) || '';
    var o2 = data.owner2_name || (data.owner2 && data.owner2.full_name) || '';
    var f1 = o1.split(' ')[0] || 'Owner 1';
    var f2 = o2.split(' ')[0] || 'Owner 2';
    _ownerLabels = { o1: f1, o2: f2, both: f1 + ' & ' + f2 };
    _noOwner2 = !o2;
  }

  function getOwnerLabels() { return _ownerLabels; }

  /* ── Build a single repeater row as HTML string (no DOM needed) ── */
  function buildRowHtml(table, d) {
    d = d || {};
    var fc = "form-control form-control-sm",
      fs = "form-select form-select-sm";
    var rm =
      '<td><button type="button" class="lhp-remove-row" title="Remove"><i class="bi bi-trash3"></i></button></td>';
    function v(k) {
      var val = d[k];
      return esc(val === '__optout__' ? '' : (val || ''));
    }
    function inp(field, type, ph) {
      return (
        '<input type="' +
        type +
        '" class="' +
        fc +
        '" data-field="' +
        field +
        '" value="' +
        v(field) +
        '" placeholder="' +
        ph +
        '">'
      );
    }
    function optout(field) {
      var id = 'oo-' + field + '-' + Math.random().toString(36).slice(2, 8);
      var isOo = d[field] === '__optout__';
      return '<label class="lhp-optout' + (isOo ? ' is-checked' : '') + '" for="' + id + '"><input type="checkbox" class="lhp-oo lhp-optout-chk" data-optout="' + field + '" id="' + id + '"' + (isOo ? ' checked' : '') + '><span>Prefer not to include</span></label>';
    }
    function inpOptout(field, type, ph) {
      return '<div>' + inp(field, type, ph) + optout(field) + '</div>';
    }
    function ta(field, ph, rows) {
      rows = rows || 3;
      return (
        '<textarea class="' +
        fc +
        '" data-field="' +
        field +
        '" rows="' + rows + '" placeholder="' +
        ph +
        '">' +
        v(field) +
        "</textarea>"
      );
    }
    function sel(field, opts) {
      var os = opts
        .map(function (o) {
          return (
            '<option value="' +
            esc(o) +
            '"' +
            ((d[field] || "") === o ? " selected" : "") +
            ">" +
            (o || "Select Type") +
            "</option>"
          );
        })
        .join("");
      return (
        '<select class="' +
        fs +
        '" data-field="' +
        field +
        '">' +
        os +
        "</select>"
      );
    }
    function selPairs(field, pairs) {
      var os = pairs.map(function(p) {
        return '<option value="' + esc(p[0]) + '"' + ((d[field]||'') === p[0] ? ' selected' : '') + '>' + esc(p[1]) + '</option>';
      }).join('');
      return '<select class="' + fs + '" data-field="' + field + '">' + os + '</select>';
    }
    function selBeneficiary(field, extraOpts) {
      var bNames = currentBeneficiaries.map(function(b) { return b.full_name || ''; }).filter(Boolean);
      var opts = [['', 'Select']].concat(bNames.map(function(n) { return [n, n]; }));
      if (extraOpts) extraOpts.forEach(function(o) { opts.push(o); });
      opts.push(['Other', 'Other']);
      // If saved value doesn't match any option, add it as a custom entry
      var saved = d[field] || '';
      if (saved && saved !== 'Other' && !bNames.some(function(n) { return n === saved; })) {
        opts.push([saved, saved]);
      }
      return selPairs(field, opts);
    }
    switch (table) {
      case "children":
        return (
          "<tr><td>" +
          inp("full_name", "text", "Full Name") +
          "</td><td>" +
          inp("relationship", "text", "Relationship") +
          "</td><td>" +
          inpOptout("email", "email", "Email") +
          "</td><td>" +
          inpOptout("phone", "tel", "Phone") +
          "</td>" +
          rm +
          "</tr>"
        );
      case "access":
        return (
          "<tr>" +
          "<td>" + selBeneficiary("full_name") + "</td>" +
          "<td>" + selPairs("privilege", [
            ['', 'Select Privilege'],
            ['unlock_all', 'May unlock Lighthouse for all beneficiaries'],
            ['view_anytime', 'May view interior at any time'],
            ['view_after_death', 'May view interior after I (we) pass away'],
          ]) + "</td>" +
          "<td>" + inpOptout("email", "email", "Email address") + "</td>" +
          "<td>" + inpOptout("phone", "tel", "Phone number") + "</td>" +
          rm +
          "</tr>"
        );
      case "items":
        return (
          "<tr><td>" +
          inp("description", "text", "Description") +
          "</td><td>" +
          selBeneficiary("recipient") +
          "</td><td>" +
          inp("notes", "text", "Notes") +
          "</td>" +
          rm +
          "</tr>"
        );
      case "bank":
      case "insurance": {
        var olBI = getOwnerLabels();
        // Normalize stored owner value (old = display name, new = o1/o2/both key)
        var storedOwner = (d.owner || '').trim();
        var ownerKey = storedOwner;
        if (storedOwner && storedOwner !== 'o1' && storedOwner !== 'o2' && storedOwner !== 'both') {
          if (storedOwner === olBI.o1 || /^owner\s*1$/i.test(storedOwner)) ownerKey = 'o1';
          else if (storedOwner === olBI.o2 || /^owner\s*2$/i.test(storedOwner)) ownerKey = 'o2';
          else if (storedOwner === olBI.both || storedOwner.indexOf(' & ') > -1) ownerKey = 'both';
          else ownerKey = 'o1'; // stale display name (owner was renamed) — default to owner 1
        }
        var ownerPairsBI = [['', 'Select Owner'], ['o1', olBI.o1]];
        if (!_noOwner2) ownerPairsBI.push(['o2', olBI.o2], ['both', olBI.both]);
        var ownerSelBI = '<select class="' + fs + '" data-field="owner">' +
          ownerPairsBI.map(function (p) {
            return '<option value="' + esc(p[0]) + '"' + (ownerKey === p[0] ? ' selected' : '') + '>' + esc(p[1]) + '</option>';
          }).join('') + '</select>';
        if (table === "bank") {
          return (
            "<tr><td>" +
            inp("institution", "text", "Bank Name") +
            "</td><td>" +
            sel("account_type", ["", "Checking", "Savings", "Investment", "Retirement", "Other"]) +
            "</td><td>" +
            '<input type="text" class="' + fc + ' lhp-last-four" data-field="last_four" value="' +
            v("last_four") + '" placeholder="e.g. 5678" maxlength="4" pattern="\\d{4}" ' +
            'inputmode="numeric" title="Enter exactly 4 digits" style="max-width:90px">' +
            "</td><td>" + ownerSelBI + "</td>" + rm + "</tr>"
          );
        }
        return (
          "<tr><td>" +
          inp("provider", "text", "Provider") +
          "</td><td>" +
          inp("policy", "text", "Policy #") +
          "</td><td>" +
          inp("notes", "text", "Notes") +
          "</td><td>" + ownerSelBI + "</td>" + rm + "</tr>"
        );
      }
      case "letters":
        var olL = getOwnerLabels();
        var ownerOptsL = [['', 'Select Owner']];
        ownerOptsL.push(['o1', olL.o1]);
        if (!_noOwner2) ownerOptsL.push(['o2', olL.o2], ['both', olL.both]);
        return (
          '<tr>' +
          '<td style="vertical-align:top">' + selBeneficiary("recipient") + '</td>' +
          '<td style="vertical-align:top">' + selPairs("owner", ownerOptsL) + '</td>' +
          '<td style="vertical-align:top">' + ta("content", "Write your letter…", 2) + '</td>' +
          rm +
          '</tr>'
        );
    }
    return "";
  }

  function rebuildBeneficiarySelects() {
    var fs = "form-select form-select-sm";
    function makeSel(field, currentVal) {
      var bNames = currentBeneficiaries.map(function(b) { return b.full_name || ''; }).filter(Boolean);
      var opts = [['', 'Select']].concat(bNames.map(function(n) { return [n, n]; }));
      opts.push(['Other', 'Other']);
      var os = opts.map(function(p) {
        return '<option value="' + esc(p[0]) + '"' + (currentVal === p[0] ? ' selected' : '') + '>' + esc(p[1]) + '</option>';
      }).join('');
      return '<select class="' + fs + '" data-field="' + field + '">' + os + '</select>';
    }
    [['access-body','full_name'],['items-body','recipient'],['letters-body','recipient']].forEach(function(pair) {
      $('#' + pair[0] + ' tr').each(function() {
        var $sel = $(this).find('select[data-field="' + pair[1] + '"]');
        if ($sel.length) $sel.replaceWith(makeSel(pair[1], $sel.val() || ''));
      });
    });
  }

  function repForm(table, bodyId, arr, fields, labels) {
    var ths =
      fields
        .map(function (f, i) {
          return "<th>" + labels[i] + "</th>";
        })
        .join("") + '<th style="width:44px"></th>';
    // Build row HTML directly as strings — DOM doesn't exist yet
    var rows = (arr && arr.length ? arr : [{}])
      .map(function (d) {
        return buildRowHtml(table, d);
      })
      .join("");
    return (
      '<div class="lhp-repeater"><table class="table lhp-repeater-table mb-0">' +
      "<thead><tr>" +
      ths +
      "</tr></thead>" +
      '<tbody id="' +
      bodyId +
      '">' +
      rows +
      "</tbody>" +
      "</table>" +
      '<button class="btn btn-sm btn-outline-primary mt-2 lhp-add-row" data-table="' +
      table +
      '">' +
      '<i class="bi bi-plus-circle me-1"></i>Add Row</button></div>'
    );
  }

  function saveSection() {
    var recordId = parseInt($("#lhp-my-record-id").val()) || 0;
    if (!recordId) {
      toast("No record found.", "error");
      return;
    }
    var $btn = $("#lhp-save-section"); // ← moved before switch
    var p = { record_id: recordId };
    switch (currentSection) {
      case "subject":
        // Relationship required
        if (!$("#sec-relationship-to-owner").val()) {
          $("#sec-relationship-to-owner").addClass("is-invalid");
          toast("Please select your relationship.", "error");
          return;
        }
        // Full name required
        if (!$("#sec-full-name").val().trim()) {
          $("#sec-full-name").addClass("is-invalid");
          toast("Full legal name is required.", "error");
          return;
        }
        // Email validation
        var secEmail = $("#sec-email").val().trim();
        var secEmailOo = $("#oo-sec-email").is(":checked");
        if (secEmail && !secEmailOo && !isEmail(secEmail)) {
          $("#sec-email").addClass("is-invalid");
          toast("Please enter a valid email address.", "error");
          return;
        }
        // Phone validation
        var secPhone = $("#sec-phone").val().trim();
        var secPhoneOo = $("#oo-sec-phone").is(":checked");
        if (secPhone && !secPhoneOo && !isPhone(secPhone)) {
          $("#sec-phone").addClass("is-invalid");
          toast("Please enter a valid phone number (10+ digits).", "error");
          return;
        }
        $("#sec-relationship-to-owner,#sec-full-name,#sec-email,#sec-phone").removeClass("is-invalid");
        p.subject = JSON.stringify({
          full_name: $("#sec-full-name").val(),
          preferred_name: $("#sec-pref-name").val(),
          dob: $("#sec-dob").val(),
          address: $("#sec-address").val(),
          email: secEmailOo ? "" : secEmail,
          phone: secPhoneOo ? "" : secPhone,
          relationship_to_owner: $("#sec-relationship-to-owner").val(),
        });
        break;
      case "children":
        var childValid = true;
        $("#children-body tr").each(function () {
          var $fn = $(this).find('[data-field="full_name"]');
          var $rel = $(this).find('[data-field="relationship"]');
          var $email = $(this).find('[data-field="email"]');
          var $phone = $(this).find('[data-field="phone"]');
          var emailOo = $(this).find('[data-optout="email"]');
          var phoneOo = $(this).find('[data-optout="phone"]');
          var rowHasData = $(this).find("[data-field]").filter(function () {
            return $(this).val().trim() !== "";
          }).length > 0 || emailOo.is(":checked") || phoneOo.is(":checked");
          if (rowHasData) {
            if (!$fn.val().trim()) {
              $fn.addClass("is-invalid"); childValid = false;
            } else { $fn.removeClass("is-invalid"); }
            if (!$rel.val().trim()) {
              $rel.addClass("is-invalid"); childValid = false;
            } else { $rel.removeClass("is-invalid"); }
            if (!emailOo.is(":checked") && !$email.val().trim()) {
              $email.addClass("is-invalid"); childValid = false;
            } else if ($email.val().trim() && !emailOo.is(":checked") && !isEmail($email.val().trim())) {
              $email.addClass("is-invalid"); childValid = false;
            } else { $email.removeClass("is-invalid"); }
            if (!phoneOo.is(":checked") && !$phone.val().trim()) {
              $phone.addClass("is-invalid"); childValid = false;
            } else { $phone.removeClass("is-invalid"); }
          }
        });
        if (!childValid) {
          toast("Each beneficiary needs Full Name, Relationship, and either an Email/Phone or 'Prefer not to include' checked.", "error");
          return;
        }
        p.children = JSON.stringify(getTableData("children-body"));
        break;
      case "access":
        var accessCheck = validateAccessRows("access-body");
        if (!accessCheck.valid) {
          toast(accessCheck.messages[0] || "Please complete all required Access & Unlock fields.", "error");
          return;
        }
        p.access_people = JSON.stringify(getTableData("access-body"));
        break;
      case "personal_items":
        p.personal_items = JSON.stringify(getTableData("items-body"));
        break;
      case "burial": {
        function getOwnerBurial(prefix) {
          return {
            preference: $('input[name="burial_pref_' + prefix + '"]:checked').val() || '',
            requests: $('.burial-req[data-owner="' + prefix + '"]').val() || '',
            funeral_home: $('.burial-home[data-owner="' + prefix + '"]').val() || '',
            ashes: $('.burial-ashes-input[data-owner="' + prefix + '"]').val() || '',
            donation: $('.burial-donation[data-owner="' + prefix + '"]').val() || '',
            other: $('.burial-other[data-owner="' + prefix + '"]').val() || '',
          };
        }
        var burialData = { owner1: getOwnerBurial('owner1') };
        if (!_noOwner2) burialData.owner2 = getOwnerBurial('owner2');
        p.burial = JSON.stringify(burialData);
        break;
      }
      case "bank_accounts":
        // Validate last_four
        var bankValid = true;
        $("#bank-body tr").each(function () {
          var $lf = $(this).find('[data-field="last_four"]');
          var lv = $lf.val().replace(/\D/g,"");
          $lf.val(lv); // strip non-numeric
          var rowHasData = $(this).find("[data-field]").filter(function () {
            return $(this).val().trim() !== "";
          }).length > 0;
          if (rowHasData && lv && !/^\d{4}$/.test(lv)) {
            $lf.addClass("is-invalid");
            bankValid = false;
          } else {
            $lf.removeClass("is-invalid");
          }
        });
        if (!bankValid) {
          toast("Last 4 digits must be exactly 4 numbers.", "error");
          return;
        }
        p.bank_accounts = JSON.stringify(getTableData("bank-body"));
        break;
      case "life_insurance":
        p.life_insurance = JSON.stringify(getTableData("insurance-body"));
        break;
      case "letters":
        p.letters = JSON.stringify(getTableData("letters-body"));
        var updatedDocs = (myData.docs || []).map(function(doc) {
          var recipSel = $(".doc-recipient-sel[data-doc-id='" + doc.id + "']");
          var ownerSel = $(".doc-owner-sel[data-doc-id='" + doc.id + "']");
          return Object.assign({}, doc, {
            recipient: recipSel.length ? recipSel.val() : (doc.recipient || ''),
            owner: ownerSel.length ? ownerSel.val() : (doc.owner || ''),
          });
        });
        p.docs = JSON.stringify(updatedDocs);
        break;
      case "documents": {
        var updatedDocs = (myData.docs || []).map(function(doc) {
          var recipSel = $(".doc-recipient-sel[data-doc-id='" + doc.id + "']");
          var ownerSel = $(".doc-owner-sel[data-doc-id='" + doc.id + "']");
          return Object.assign({}, doc, {
            recipient: recipSel.length ? recipSel.val() : (doc.recipient || ''),
            owner: ownerSel.length ? ownerSel.val() : (doc.owner || ''),
          });
        });
        p.docs = JSON.stringify(updatedDocs);
        break;
      }
      case "social_media":
        p.social_media = $("#sec-social-media").val();
        break;
      case "additional_wishes":
        p.additional_wishes = $("#sec-additional-wishes").val();
        break;
    }
    $btn
      .addClass('lhp-btn-saving')
      .html('<span class="spinner-border spinner-border-sm me-2"></span>Saving Record…');
    ajax(
      "lhp_save_record",
      p,
      function (data) {
        $btn.removeClass('lhp-btn-saving').html('<i class="bi bi-check-circle me-2"></i>Save Changes');
        toast("Section saved.");
        updateRings(data.completion);
        loadMyRecord(recordId, function () {
          hideModal("lhp-section-modal");
        });
      },
      function (m) {
        $btn.removeClass('lhp-btn-saving').html('<i class="bi bi-check-circle me-2"></i>Save Changes');
        $("#section-save-error").text(m).removeClass("d-none");
      },
    );
  }

  function saveParentOwner2() {
    var rid = parseInt($("#lhp-my-record-id").val()) || 0;
    if (!rid) { toast("No record found.", "error"); return; }
    var name = $("#ppar-o2-name").val().trim();
    var email = $("#ppar-o2-email").val().trim();
    if (!name || !email) { toast("Name and email are both required.", "error"); return; }
    if (!isEmail(email)) { toast("Valid email required.", "error"); return; }
    ajax("lhp_save_owner2", {
      record_id: rid, name: name, email: email, phone: $("#ppar-o2-phone").val().trim(),
    }, function () {
      $("#ppar-o2-success").text("Co-owner saved! They will receive login instructions via email.").removeClass("d-none");
      $("#ppar-o2-error").addClass("d-none");
      $("#ppar-o2-cancel-btn").hide();
      toast("Co-owner updated.");
      loadParentProfile();
    }, function (m) {
      $("#ppar-o2-error").text(m).removeClass("d-none");
      $("#ppar-o2-success").addClass("d-none");
    });
  }
  function changeParentPassword() {
    var pass = $("#ppar-new-pass").val();
    var confirm = $("#ppar-confirm-pass").val();
    $("#ppar-pass-success,#ppar-pass-error").addClass("d-none");
    if (!pass) { $("#ppar-pass-error").text("Enter a new password.").removeClass("d-none"); return; }
    if (pass.length < 8) { $("#ppar-pass-error").text("Password must be at least 8 characters.").removeClass("d-none"); return; }
    if (pass !== confirm) { $("#ppar-pass-error").text("Passwords do not match.").removeClass("d-none"); return; }
    ajax("lhp_set_password", { password: pass }, function () {
      $("#ppar-pass-success").text("Password updated!").removeClass("d-none");
      $("#ppar-pass-error").addClass("d-none");
      $("#ppar-new-pass,#ppar-confirm-pass").val("");
    }, function (m) {
      $("#ppar-pass-error").text(m).removeClass("d-none");
    });
  }
  function loadParentProfile() {
    var rid = parseInt($("#lhp-my-record-id").val()) || 0;
    ajax("lhp_get_profile", { record_id: rid || undefined }, function (d) {
      $("#ppar-name").val(d.name);
      $("#ppar-email").val(d.email);
      $("#ppar-phone").val(d.phone);
      window._lhpIsPrimaryOwner = d.is_primary_owner !== false;

      if (!window._lhpIsPrimaryOwner) {
        // ── OWNER 2 VIEW ────────────────────────────────────
        // Right panel: show owner1 details read-only
        // lhp_get_owner2 is NOT called — owner2 cannot manage co-owners
        $(".lhp-coowner-section-title").html('<i class="bi bi-person-fill me-2"></i>Primary Account Holder');
        $("#ppar-o2-name,#ppar-o2-email,#ppar-o2-phone").prop("readonly", true);
        $("#lhp-save-o2-profile, #lhp-delete-o2, #ppar-o2-cancel-btn, #ppar-o2-add-btn").hide();
        if (d.owner1) {
          $("#ppar-o2-fields").show();
          $("#ppar-o2-empty").hide();
          $("#ppar-o2-name").val(d.owner1.name);
          $("#ppar-o2-email").val(d.owner1.email);
          $("#ppar-o2-phone").val(d.owner1.phone);
          $("#ppar-owner2-status").html('<div class="alert alert-info py-2 small"><i class="bi bi-info-circle me-1"></i>You are a co-owner of this Lighthouse.</div>');
        } else {
          $("#ppar-o2-fields").hide();
          $("#ppar-o2-empty").hide();
          $("#ppar-owner2-status").html('');
        }

      } else {
        // ── PRIMARY OWNER VIEW ──────────────────────────────
        // Reset panel to normal editable co-owner form
        $(".lhp-coowner-section-title").html('<i class="bi bi-person-plus-fill me-2"></i>Co-Owner <span class="text-muted fw-normal small">(optional)</span>');
        $("#ppar-o2-name,#ppar-o2-email,#ppar-o2-phone").prop("readonly", false);
        $("#lhp-save-o2-profile").show();

        // Now load owner2 data (only makes sense for primary owner)
        if (rid) {
          ajax("lhp_get_owner2", { record_id: rid }, function (o2) {
            if (o2 && o2.owner2_id) {
              $("#ppar-o2-name").val(o2.full_name || '');
              $("#ppar-o2-email").val(o2.email || '');
              $("#ppar-o2-phone").val(o2.phone || '');
              $("#ppar-owner2-status").html('<div class="alert alert-info py-2 small"><i class="bi bi-check-circle me-1"></i>Co-owner has login access. They can sign in with their own credentials.</div>');
              $("#ppar-o2-fields").show();
              $("#ppar-o2-empty").hide();
              $("#lhp-delete-o2").removeClass("d-none").data("o2id", o2.owner2_id);
            } else {
              $("#ppar-o2-name,#ppar-o2-email,#ppar-o2-phone").val('');
              $("#ppar-owner2-status").html('');
              $("#lhp-delete-o2").addClass("d-none").removeData("o2id");
              $("#ppar-o2-fields").hide();
              $("#ppar-o2-empty").show();
            }
          });
        } else {
          $("#ppar-o2-fields").hide();
          $("#ppar-o2-empty").hide();
        }
      }
    });
  }
  function saveParentProfile() {
    ajax(
      "lhp_save_profile",
      {
        name: $("#ppar-name").val(),
        phone: $("#ppar-phone").val(),
      },
      function () {
        toast("Profile saved.");
        var newName = $("#ppar-name").val().trim();
        if (newName) {
          var _parts = newName.trim().split(/\s+/);
          var _ini   = _parts[0].charAt(0).toUpperCase();
          var _av    = _ini + (_parts.length > 1 ? _parts[_parts.length-1].charAt(0).toUpperCase() : '');
          $(".lhp-sidebar-user-info .fw-bold").text(newName);
          $("#active-record-title").text(newName + "'s Lighthouse");
          var $avLg = $(".lhp-sidebar-user .lhp-avatar-lg");
          if (!$avLg.hasClass("lhp-avatar-logo")) $avLg.text(_av);
          $(".lhp-avatar-sm").text(_ini);
        }
        // Reload record (section cards) + records list (card titles)
        var rid = parseInt($("#lhp-my-record-id").val()) || 0;
        if (rid) loadMyRecord(rid);
        if (typeof loadMyRecords === 'function') loadMyRecords();
      },
      function (m) {
        toast(m, "error");
      },
    );
  }

  /* ═══════════════════════════════════════════════════════
     SUPER ADMIN DASHBOARD
  ═══════════════════════════════════════════════════════ */
  var adminAllRecords = [];

  function initAdminDashboard() {
    initSidebar();
    loadAdminStats();

    $(document).on("click", ".lhp-nav-item[data-view]", function (e) {
      e.preventDefault();
      var v = $(this).data("view");
      if (v === "records") loadAdminRecords();
      else if (v === "planners")
        loadAdminUsers("lighthouse_planner", "planners");
      else if (v === "parents") loadAdminUsers("lighthouse_parent", "parents");
      else if (v === "firms") loadAdminUsers("lighthouse_law_firm", "firms");
      else if (v === "activity") loadFullActivityLog();
    });

    $(document).on("input", ".lhp-user-search", function () {
      var view = $(this).data("view");
      var roleMap = {
        planners: "lighthouse_planner",
        parents: "lighthouse_parent",
        firms: "lighthouse_law_firm",
      };
      loadAdminUsers(roleMap[view], view, $(this).val().toLowerCase());
    });
    $(document).on(
      "input",
      "#admin-rec-search",
      debounce(function () {
        filterAdminRecords($(this).val().toLowerCase());
      }, 250),
    );
    $(document).on("change", "#admin-rec-filter", function () {
      filterAdminRecords($("#admin-rec-search").val().toLowerCase());
    });
    $(document).on("click", ".lhp-admin-view-record", function () {
      openRecordDetail($(this).data("id"));
    });

    $(document).off("click", ".lhp-admin-del-user").on("click", ".lhp-admin-del-user", function () {
      $("#del-user-id").val($(this).data("id"));
      $("#del-user-name").text($(this).data("name"));
      showModal("lhp-confirm-user-modal");
    });
    $("#lhp-confirm-del-user").off("click").on("click", function () {
      ajax(
        "lhp_admin_delete_user",
        { user_id: $("#del-user-id").val() },
        function () {
          hideModal("lhp-confirm-user-modal");
          toast("User deleted.", "success");
          loadAdminStats();
        },
        function (m) {
          toast(m, "error");
        },
      );
    });
    $(document).off("click", ".lhp-toggle-status").on("click", ".lhp-toggle-status", function () {
      var status = $(this).data("status") === "active" ? "suspended" : "active";
      ajax(
        "lhp_admin_toggle_user",
        { user_id: $(this).data("id"), status: status },
        function () {
          toast("Status updated.");
          $(document).find(".lhp-nav-item.active").trigger("click");
        },
        function (m) {
          toast(m, "error");
        },
      );
    });

    $("#lhp-do-create-user").on("click", function () {
      doCreateUser(
        "#cu-name",
        "#cu-email",
        "#cu-role",
        "#cu-firm",
        "#cu-phone",
        "#cu-error",
        $(this),
        "lhp-create-user-modal",
      );
    });
    $("#lhp-inline-create-user").on("click", function () {
      doCreateUser(
        "#icu-name",
        "#icu-email",
        "#icu-role",
        "#icu-firm",
        "#icu-phone",
        "#inline-cu-error",
        $(this),
        null,
        "#inline-cu-success",
      );
    });
    $(document).on("show.bs.modal", "#lhp-create-user-modal", function (e) {
      var preset = e.relatedTarget
        ? $(e.relatedTarget).data("preset-role")
        : null;
      if (preset) $("#cu-role").val(preset);
    });
  }

  function loadAdminStats() {
    ajax("lhp_admin_stats", {}, function (data) {
      $("#as-records").text(data.total_records);
      $("#as-complete").text(data.complete_records);
      $("#as-planners").text(data.total_planners);
      $("#as-parents").text(data.total_parents);
      $("#ph-complete").text(data.complete_records);
      $("#ph-draft").text(data.draft_records);
      $("#admin-avg-pct").text(data.avg_completion + "%");
      $("#admin-arc").attr("stroke-dasharray", data.avg_completion + ",100");
      renderActivityFeed(data.activity_log, "#admin-activity-feed");
    });
  }

  function loadAdminRecords() {
    $("#admin-records-body").html(
      '<tr><td colspan="8" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm text-primary me-2"></span>Loading…</td></tr>',
    );
    ajax(
      "lhp_admin_get_records",
      {},
      function (records) {
        adminAllRecords = records;
        renderAdminRecords(records);
      },
      function (m) {
        toast(m, "error");
      },
    );
  }
  function renderAdminRecords(records) {
    if (!records.length) {
      $("#admin-records-body").html(
        '<tr><td colspan="8" class="text-center py-5 text-muted">No records found.</td></tr>',
      );
      return;
    }
    $("#admin-records-body").html(
      records
        .map(function (r) {
          var badge =
            r.status === "complete"
              ? '<span class="lhp-badge-complete"><i class="bi bi-check-circle me-1"></i>Complete</span>'
              : '<span class="lhp-badge-draft"><i class="bi bi-clock me-1"></i>In Progress</span>';
          return (
            "<tr><td><strong>" +
            esc(r.subject_name || "(No name)") +
            "</strong></td>" +
            '<td class="text-muted small">' +
            esc(r.subject_dob || "—") +
            "</td>" +
            '<td><div class="lhp-progress-wrap"><div class="lhp-prog"><div class="lhp-prog-fill" style="width:' +
            r.completion +
            '%"></div></div><span class="lhp-prog-pct">' +
            r.completion +
            "%</span></div></td>" +
            "<td>" +
            badge +
            "</td>" +
            '<td class="small">' +
            esc(r.planner_name) +
            "</td>" +
            '<td class="small">' +
            esc(r.owner_name) +
            "</td>" +
            '<td class="text-muted small">' +
            esc(r.created) +
            "</td>" +
            '<td><button class="btn btn-sm lhp-btn-outline-primary lhp-admin-view-record" data-id="' +
            r.id +
            '"><i class="bi bi-eye me-1"></i>View</button></td></tr>'
          );
        })
        .join(""),
    );
  }
  function filterAdminRecords(q) {
    var status = $("#admin-rec-filter").val();
    renderAdminRecords(
      adminAllRecords.filter(function (r) {
        return (
          (!q ||
            (r.subject_name || "").toLowerCase().includes(q) ||
            (r.planner_name || "").toLowerCase().includes(q) ||
            (r.owner_name || "").toLowerCase().includes(q)) &&
          (!status || r.status === status)
        );
      }),
    );
  }
  function loadAdminUsers(role, view, search) {
    $("#tbody-" + view).html(
      '<tr><td colspan="8" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm text-primary me-2"></span>Loading…</td></tr>',
    );
    ajax(
      "lhp_admin_get_users",
      { role: role, search: search || "" },
      function (users) {
        if (!users.length) {
          $("#tbody-" + view).html(
            '<tr><td colspan="8" class="text-center py-5 text-muted">No users found.</td></tr>',
          );
          return;
        }
        $("#tbody-" + view).html(
          users
            .map(function (u) {
              var rp = roleHtml(u.role);
              var st =
                '<span class="lhp-status-' +
                (u.status || "active") +
                '">' +
                (u.status === "inactive" ? "Inactive" : "Active") +
                "</span>";
              return (
                "<tr><td><strong>" +
                esc(u.name) +
                '</strong></td><td class="small">' +
                esc(u.email) +
                "</td><td>" +
                rp +
                "</td>" +
                '<td class="small text-muted">' +
                esc(u.firm || "—") +
                "</td>" +
                '<td><span class="badge bg-light text-dark border">' +
                u.record_count +
                "</span></td>" +
                '<td class="small text-muted">' +
                esc(u.registered) +
                "</td><td>" +
                st +
                "</td>" +
                '<td><div class="d-flex gap-1">' +
                '<button class="btn btn-sm btn-outline-secondary lhp-toggle-status" data-id="' +
                u.id +
                '" data-status="' +
                (u.status || "active") +
                '" title="Toggle"><i class="bi bi-toggle-on"></i></button>' +
                '<button class="btn btn-sm btn-outline-danger lhp-admin-del-user" data-id="' +
                u.id +
                '" data-name="' +
                esc(u.name) +
                '" title="Delete"><i class="bi bi-trash"></i></button>' +
                "</div></td></tr>"
              );
            })
            .join(""),
        );
      },
      function (m) {
        toast(m, "error");
      },
    );
  }
  function roleHtml(role) {
    var map = {
      lhp_super_admin:
        '<span class="lhp-role-pill lhp-role-super"><i class="bi bi-shield-fill-check me-1"></i>Super Admin</span>',
      lighthouse_law_firm:
        '<span class="lhp-role-pill lhp-role-firm"><i class="bi bi-building me-1"></i>Law Firm</span>',
      lighthouse_planner:
        '<span class="lhp-role-pill lhp-role-planner"><i class="bi bi-briefcase me-1"></i>Estate Planner</span>',
      lighthouse_parent:
        '<span class="lhp-role-pill lhp-role-parent"><i class="bi bi-people me-1"></i>Parent</span>',
    };
    return map[role] || '<span class="badge bg-secondary">' + role + "</span>";
  }
  function renderActivityFeed(log, target) {
    if (!log || !log.length) {
      $(target).html(
        '<p class="text-muted small text-center py-3">No activity yet.</p>',
      );
      return;
    }
    var icons = {
      user_created: "bi-person-plus",
      user_deleted: "bi-person-x",
      user_status: "bi-toggle-on",
      note_added: "bi-chat-dots",
      file_uploaded: "bi-paperclip",
    };
    $(target).html(
      log
        .map(function (item) {
          var icon = icons[item.type] || "bi-info-circle";
          var cls = "lhp-act-" + (icons[item.type] ? item.type : "default");
          return (
            '<div class="lhp-activity-item"><div class="lhp-activity-icon ' +
            cls +
            '"><i class="bi ' +
            icon +
            '"></i></div>' +
            '<div class="lhp-activity-body"><div class="lhp-activity-msg">' +
            esc(item.message) +
            "</div>" +
            '<div class="lhp-activity-meta">' +
            esc(item.actor_name) +
            " &middot; " +
            esc(item.date) +
            "</div></div></div>"
          );
        })
        .join(""),
    );
  }
  function loadFullActivityLog() {
    ajax("lhp_admin_stats", { activity_limit: 200 }, function (data) {
      renderActivityFeed(data.activity_log, "#activity-log-full");
    });
  }

  function openRecordDetail(recordId) {
    showModal("lhp-record-detail-modal");
    $("#rd-modal-body").html(
      '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>',
    );
    ajax("lhp_get_record", { record_id: recordId }, function (data) {
      $("#rd-modal-title").text(
        (data.subject && data.subject.full_name
          ? data.subject.full_name
          : "Record") + " — Detail",
      );
      $("#rd-modal-body").html(
        '<ul class="nav nav-tabs lhp-record-tabs mb-4" id="rd-tabs">' +
          '<li class="nav-item"><a class="nav-link active" href="#rd-info"  data-bs-toggle="tab"><i class="bi bi-info-circle me-1"></i>Info</a></li>' +
          '<li class="nav-item"><a class="nav-link"        href="#rd-notes" data-bs-toggle="tab"><i class="bi bi-chat-dots me-1"></i>Notes</a></li>' +
          '<li class="nav-item"><a class="nav-link"        href="#rd-docs"  data-bs-toggle="tab"><i class="bi bi-paperclip me-1"></i>Documents</a></li>' +
          "</ul>" +
          '<div class="tab-content">' +
          '<div class="tab-pane fade show active" id="rd-info">' +
          buildRDInfo(data) +
          "</div>" +
          '<div class="tab-pane fade" id="rd-notes">' +
          buildRDNotes(recordId) +
          "</div>" +
          '<div class="tab-pane fade" id="rd-docs">' +
          buildRDDocs(data, recordId) +
          "</div>" +
          "</div>",
      );
      // Load notes
      ajax("lhp_get_notes", { record_id: recordId }, function (notes) {
        $("#rd-notes-list").html(renderNotes(notes));
      });
      // Note submit
      $(document)
        .off("click", "#rd-submit-note")
        .on("click", "#rd-submit-note", function () {
          var text = $("#rd-note-input").val().trim();
          if (!text) return;
          ajax(
            "lhp_save_note",
            { record_id: recordId, note: text },
            function (notes) {
              $("#rd-notes-list").html(renderNotes(notes));
              $("#rd-note-input").val("");
              toast("Note saved.");
            },
          );
        });
      // File upload
      $(document)
        .off("change", "#rd-file-input")
        .on("change", "#rd-file-input", function () {
          var file = this.files[0];
          if (!file) return;
          var $zone = $("#rd-upload-zone");
          var zoneOrig = $zone.html();
          $zone.html(
            '<div class="lhp-upload-progress-wrap">' +
            '<i class="bi bi-cloud-arrow-up-fill"></i>' +
            '<div class="fw-semibold mb-2">' + esc(file.name) + '</div>' +
            '<div class="lhp-upload-progress-track"><div class="lhp-upload-progress-bar" id="rd-prog-bar" style="width:0%"></div></div>' +
            '<small class="text-muted mt-1 d-block" id="rd-prog-pct">0%</small>' +
            '</div>'
          );
          var fd = new FormData();
          fd.append("action", "lhp_upload_file");
          fd.append("nonce", cfg.nonce);
          fd.append("record_id", recordId);
          fd.append("file", file);
          $.ajax({
            url: cfg.ajax_url,
            type: "POST",
            data: fd,
            processData: false,
            contentType: false,
            xhr: function() {
              var xhrObj = $.ajaxSettings.xhr();
              if (xhrObj.upload) {
                xhrObj.upload.addEventListener("progress", function(e) {
                  if (e.lengthComputable) {
                    var pct = Math.round(e.loaded / e.total * 100);
                    $("#rd-prog-bar").css("width", pct + "%");
                    $("#rd-prog-pct").text(pct + "%");
                  }
                });
              }
              return xhrObj;
            }
          })
            .done(function (r) {
              if (r.success) {
                $("#rd-docs-list").html(renderDocs(r.data.docs, recordId));
                toast("File uploaded.");
              } else {
                if (r.data === "Security check failed.") {
                  refreshNonce(function() { toast("Session refreshed — please try uploading again.", "info"); });
                } else {
                  toast(r.data, "error");
                }
              }
            })
            .always(function () {
              $zone.html(zoneOrig);
            });
        });
      $(document).off("click", "#rd-upload-zone").on("click", "#rd-upload-zone", function () {
        $("#rd-file-input").trigger("click");
      });
      // Delete file
      $(document)
        .off("click", ".rd-del-file")
        .on("click", ".rd-del-file", function () {
          ajax(
            "lhp_delete_file",
            { record_id: recordId, attachment_id: $(this).data("att") },
            function (docs) {
              $("#rd-docs-list").html(renderDocs(docs, recordId));
              toast("File removed.", "info");
            },
          );
        });
    });
  }
  function buildRDInfo(d) {
    var s = d.subject || {};

    /* ── helpers ── */
    function field(icon, label, val) {
      return (
        '<div class="lhp-rd-field">' +
        '<div class="lhp-rd-label"><i class="bi ' +
        icon +
        '"></i>' +
        label +
        "</div>" +
        '<div class="lhp-rd-value">' +
        esc(val || "—") +
        "</div>" +
        "</div>"
      );
    }
    function section(icon, title, colour, body) {
      return (
        '<div class="lhp-rd-section">' +
        '<div class="lhp-rd-section-head" style="border-left-color:' +
        colour +
        '">' +
        '<i class="bi ' +
        icon +
        '" style="color:' +
        colour +
        '"></i> <strong>' +
        title +
        "</strong>" +
        "</div>" +
        '<div class="lhp-rd-section-body">' +
        body +
        "</div>" +
        "</div>"
      );
    }
    function table(headers, rows) {
      if (!rows || !rows.length)
        return '<p class="text-muted small mb-0">None recorded.</p>';
      var ths = headers
        .map(function (h) {
          return "<th>" + h + "</th>";
        })
        .join("");
      var trs = rows
        .map(function (r) {
          return (
            "<tr>" +
            r
              .map(function (c) {
                if (c && typeof c === 'object' && c.raw) return "<td>" + c.raw + "</td>";
              return "<td>" + (c === '__optout__' ? '<em class="text-muted small">Prefer not to include</em>' : esc(c || "—")) + "</td>";
              })
              .join("") +
            "</tr>"
          );
        })
        .join("");
      return (
        '<div class="table-responsive"><table class="table table-sm lhp-rd-table mb-0"><thead><tr>' +
        ths +
        "</tr></thead><tbody>" +
        trs +
        "</tbody></table></div>"
      );
    }
    function badge(val) {
      var ok = val === "complete";
      return (
        '<span class="' +
        (ok ? "lhp-badge-complete" : "lhp-badge-draft") +
        '">' +
        (ok ? "✓ Complete" : "⏳ In Progress") +
        "</span>"
      );
    }

    /* ── 1. Subject overview ── */
    var relMap = {
      self:"Myself", parent:"Parent / Guardian", spouse:"Spouse / Partner",
      child:"Adult Child", sibling:"Sibling", grandparent:"Grandparent",
      "in-law":"In-Law", friend:"Close Friend", other:"Other"
    };
    var relLabel = s.relationship_to_owner ? (relMap[s.relationship_to_owner] || s.relationship_to_owner) : "—";
    var subjectBlock =
      '<div class="row g-3">' +
      '<div class="col-sm-4">' +
      field("bi-diagram-2", "Relationship to Creator", relLabel) +
      "</div>" +
      '<div class="col-sm-4">' +
      field("bi-person", "Full Name", s.full_name) +
      "</div>" +
      '<div class="col-sm-4">' +
      field("bi-calendar", "Date of Birth", s.dob) +
      "</div>" +
      '<div class="col-sm-4">' +
      field("bi-geo-alt", "Address", s.address) +
      "</div>" +
      '<div class="col-sm-4">' +
      field("bi-telephone", "Phone", s.phone) +
      "</div>" +
      '<div class="col-sm-4">' +
      field("bi-envelope", "Email", s.email) +
      "</div>" +
      '<div class="col-sm-2"><div class="lhp-rd-field"><div class="lhp-rd-label"><i class="bi bi-flag"></i>Status</div><div class="lhp-rd-value">' +
      badge(d.status) +
      "</div></div></div>" +
      "</div>";

    /* ── 2. Children & Heirs ── */
    var children = d.children || [];
    var childrenBlock = table(
      ["Full Name", "Relationship", "Email", "Phone"],
      children.map(function (c) {
        return [c.full_name, c.relationship, c.email, c.phone];
      }),
    );

    /* ── 3. Access Contacts ── */
    var privilegeLabels = {
      unlock_all: 'May unlock Lighthouse for all beneficiaries',
      view_anytime: 'May view interior at any time',
      view_after_death: 'May view interior after I (we) pass away',
    };
    var accessBlock = table(
        ["Name", "Privilege", "Status", "Email", "Phone"],
        (d.access_people || []).map(function (a) {
          var statusBadge = a.status === 'active'
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-warning text-dark">Pending</span>';
          return [a.full_name, privilegeLabels[a.privilege] || a.privilege || '—', { raw: statusBadge }, a.email, a.phone];
        }),
      );

    /* ── 4. Personal Items ── */
    var personalBlock = table(
      ["Item", "Who Should Receive It", "Notes"],
      (d.personal_items || []).map(function (p) {
        return [p.description, p.recipient, p.notes];
      }),
    );

    /* ── 5. End of Life Preferences ── */
    var burial = d.burial || {};
    var _rdO2Name = d.owner2_name || (d.owner2 && d.owner2.full_name) || '';
    var olrd = { o1: (d.owner_name || '').split(' ')[0] || 'Owner 1', o2: _rdO2Name.split(' ')[0] || 'Owner 2' };
    function burialOwnerHtml(ownerLabel, bOwner) {
      if (!bOwner || !bOwner.preference) return '';
      var rows = '<div class="col-12 mb-1"><span class="fw-semibold small text-uppercase text-muted lhp-owner-tag"><i class="bi bi-person-fill me-1"></i>' + esc(ownerLabel) + '</span></div>' +
        '<div class="col-sm-3">' + field("bi-flower1", "Method", bOwner.preference) + '</div>';
      if (bOwner.preference === 'cremation' && bOwner.ashes) rows += '<div class="col-sm-3">' + field("bi-wind", "Ashes", bOwner.ashes) + '</div>';
      if (bOwner.funeral_home) rows += '<div class="col-sm-3">' + field("bi-building", "Funeral Home", bOwner.funeral_home) + '</div>';
      if (bOwner.donation) rows += '<div class="col-sm-3">' + field("bi-heart", "Donations", bOwner.donation) + '</div>';
      if (bOwner.requests) rows += '<div class="col-12">' + field("bi-chat-text", "Requests", bOwner.requests) + '</div>';
      if (bOwner.other) rows += '<div class="col-12">' + field("bi-three-dots", "Other Wishes", bOwner.other) + '</div>';
      return rows;
    }
    var b1src = burial.owner1 || (burial.preference ? burial : null);
    var b2src = burial.owner2 || null;
    var b1html = burialOwnerHtml(olrd.o1, b1src);
    var b2html = burialOwnerHtml(olrd.o2, b2src);
    var burialBlock = (b1html || b2html)
      ? '<div class="row g-2">' + b1html + (b1html && b2html ? '<div class="col-12"><hr class="my-1"></div>' : '') + b2html + '</div>'
      : '<p class="text-muted small mb-0">Not filled in yet.</p>';

    /* ── 6. Bank Accounts ── */
    var o2rd = d.owner2 || {};
    // Resolve stored owner value to current actual names
    var resolveOwner = function(val) {
      if (!val) return '—';
      var v = val.trim();
      // Stable keys (new format)
      if (v === 'o1') return olrd.o1;
      if (v === 'o2') return _noOwner2 ? olrd.o1 : olrd.o2;
      if (v === 'both') return _noOwner2 ? olrd.o1 : olrd.o1 + ' & ' + olrd.o2;
      // Legacy patterns
      if (/^owner\s*2$/i.test(v)) return olrd.o2;
      if (/^owner\s*1$/i.test(v)) return olrd.o1;
      if (v.indexOf(' & ') > -1) return olrd.o1 + ' & ' + olrd.o2;
      // Legacy stored name — try to match current names
      if (v === olrd.o1) return olrd.o1;
      if (v === olrd.o2) return olrd.o2;
      return v;
    };
    var ownerSummary = '<div class="row g-2 mb-3">' +
      '<div class="col-sm-6">' + field("bi-person-fill", olrd.o1, s.full_name || '—') + '</div>' +
      (!_noOwner2 ? '<div class="col-sm-6">' + field("bi-person-plus-fill", olrd.o2, o2rd.full_name || '—') + '</div>' : '') +
      '</div>';
    var bankBlock = ownerSummary + table(
      ["Institution", "Account Type", "Last 4 Digits", "Owner"],
      (d.bank_accounts || []).map(function (b) {
        return [b.institution, b.account_type, b.last_four, resolveOwner(b.owner)];
      }),
    );

    /* ── 7. Life Insurance ── */
    var insuranceBlock = table(
      ["Provider", "Policy #", "Notes", "Owner"],
      (d.life_insurance || []).map(function (i) {
        return [i.provider, i.policy, i.notes, resolveOwner(i.owner)];
      }),
    );

    /* ── 8. Letters ── */
    var allDocs = d.docs || [];
    var docOnlyFiles = allDocs.filter(function(doc) { return doc._section !== 'letters'; });
    var letDocFiles  = allDocs.filter(function(doc) { return doc._section === 'letters'; });
    function docListHtml(docs) {
      var olDoc = getOwnerLabels();
      return '<ul class="list-unstyled mb-0">' + docs.map(function(doc) {
        var ownerLabel = doc.owner === "both" ? olDoc.both : (doc.owner === "owner1" ? olDoc.o1 : (doc.owner === "owner2" ? olDoc.o2 : (doc.owner || '')));
        return '<li class="small mb-1"><i class="bi bi-paperclip me-1"></i><strong>' + esc(doc.name) + '</strong>' +
          (doc.recipient ? ' <span class="text-muted"><strong>For:</strong> ' + esc(doc.recipient) + '</span>' : '') +
          (ownerLabel ? ' <span class="text-muted"> · <strong>From:</strong> ' + esc(ownerLabel) + '</span>' : '') + '</li>';
      }).join('') + '</ul>';
    }
    var olL = { o1: (d.owner_name || '').split(' ')[0] || 'Owner 1', o2: (d.owner2_name || '').split(' ')[0] || 'Owner 2' };
    olL.both = olL.o1 + ' & ' + olL.o2;
    var textLettersHtml = (d.letters && d.letters.length)
      ? (d.letters || []).map(function (l) {
            return (
              '<div class="lhp-letter-item">' +
              '<div class="lhp-letter-to">' +
              '<span><i class="bi bi-envelope-fill me-1"></i><strong>To:</strong> ' + esc(l.recipient) + '</span>' +
              (l.owner ? '<span class="lhp-letter-from text-muted small"><strong>From:</strong> ' + esc(l.owner === 'both' ? olL.o1 + ' & ' + olL.o2 : olL[l.owner] || l.owner) + '</span>' : '') +
              '</div>' +
              '<div class="lhp-letter-body">' +
              esc(l.content || "(no content)") +
              "</div>" +
              "</div>"
            );
          }).join("")
      : '';
    var lettersHtml = (textLettersHtml || letDocFiles.length)
      ? textLettersHtml + (letDocFiles.length ? (textLettersHtml ? '<hr class="my-2">' : '') + docListHtml(letDocFiles) : '')
      : '<p class="text-muted small mb-0">No letters recorded.</p>';

    /* ── 9. Documents ── */
    var docsBlock = docOnlyFiles.length
      ? docListHtml(docOnlyFiles)
      : '<p class="text-muted small mb-0">No documents uploaded.</p>';

    /* ── 10. Social Media ── */
    var socialMediaBlock = d.social_media
      ? '<p class="small mb-0" style="white-space:pre-wrap">' + esc(d.social_media) + '</p>'
      : '<p class="text-muted small mb-0">Not filled in yet.</p>';

    /* ── 11. Additional Wishes ── */
    var additionalWishesBlock = d.additional_wishes
      ? '<p class="small mb-0" style="white-space:pre-wrap">' + esc(d.additional_wishes) + '</p>'
      : '<p class="text-muted small mb-0">Not filled in yet.</p>';

    /* ── Assemble ── */
    var otherOwnerBlock = o2rd.full_name
      ? '<div class="row g-2">' +
        '<div class="col-sm-4">' + field("bi-person-fill", "Name", o2rd.full_name) + '</div>' +
        '<div class="col-sm-4">' + field("bi-envelope", "Email", o2rd.email) + '</div>' +
        '<div class="col-sm-4">' + field("bi-telephone", "Phone", o2rd.phone) + '</div>' +
        '</div>'
      : '<p class="text-muted small mb-0">Not filled in yet.</p>';

    return (
      section("bi-people", "Beneficiaries", "#0891b2", childrenBlock) +
      section("bi-shield-lock", "Access & Unlock", "#7c3aed", accessBlock) +
      section("bi-gift", "Personal Items", "#db2777", personalBlock) +
      section("bi-flower2", "Burial & Funeral Wishes", "#059669", burialBlock) +
      section("bi-bank", "Bank Accounts", "#d97706", bankBlock) +
      section(
        "bi-file-earmark-text",
        "Life Insurance",
        "#dc2626",
        insuranceBlock,
      ) +
      section(
        "bi-envelope-heart",
        "Letters & Messages",
        "#be185d",
        lettersHtml,
      ) +
      section("bi-paperclip", "Documents & Photos", "#0369a1", docsBlock) +
      section("bi-phone-fill", "Social Media", "#7c3aed", socialMediaBlock) +
      section("bi-journal-text", "Additional Instructions & Wishes", "#059669", additionalWishesBlock)
    );
  }
  function buildRDNotes(recordId) {
    return (
      '<div id="rd-notes-list" class="mb-3"><div class="text-muted small text-center">Loading…</div></div>' +
      '<div class="d-flex gap-2 mt-3"><textarea class="form-control form-control-sm" id="rd-note-input" rows="2" placeholder="Write a note…"></textarea>' +
      '<button class="btn lhp-btn-primary-solid btn-sm px-3" id="rd-submit-note" aria-label="Add note" style="align-self:flex-end;white-space:nowrap"><i class="bi bi-send-fill" aria-hidden="true"></i></button></div>'
    );
  }
  function buildRDDocs(data, recordId) {
    return (
      '<div id="rd-docs-list" class="mb-3">' +
      renderDocs(data.docs || [], recordId) +
      "</div>" +
      '<div class="lhp-upload-zone" id="rd-upload-zone">' +
      '<i class="bi bi-cloud-arrow-up-fill"></i>' +
      '<div class="mt-2 fw-semibold">Drag &amp; drop or click to select files</div>' +
      '<small class="text-muted">JPG, PNG, PDF, DOC — Max 10MB</small>' +
      '<input type="file" id="rd-file-input" class="d-none" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">' +
      "</div>"
    );
  }
  function renderNotes(notes) {
    if (!notes || !notes.length)
      return '<p class="text-muted small text-center py-2">No notes yet.</p>';
    return notes
      .map(function (n) {
        return (
          '<div class="lhp-note-item"><div class="lhp-note-header">' +
          '<span class="lhp-note-author">' +
          esc(n.author) +
          "</span>" +
          '<span class="lhp-note-date ms-auto">' +
          esc(n.date) +
          "</span></div>" +
          '<div class="lhp-note-text">' +
          esc(n.text) +
          "</div></div>"
        );
      })
      .join("");
  }
  function renderDocs(docs, recordId) {
    if (!docs || !docs.length)
      return '<p class="text-muted small text-center py-2">No documents uploaded yet.</p>';
    return docs
      .map(function (d) {
        var icon =
          d.type && d.type.includes("pdf")
            ? "bi-file-earmark-pdf-fill text-danger"
            : d.type && d.type.includes("image")
              ? "bi-file-image-fill text-success"
              : "bi-file-earmark-text-fill text-primary";
        return (
          '<div class="lhp-file-item"><i class="bi ' +
          icon +
          ' lhp-file-icon"></i>' +
          '<div><div class="lhp-file-name">' +
          esc(d.name) +
          '</div><div class="lhp-file-meta">' +
          esc(d.size) +
          " &middot; " +
          esc(d.uploaded) +
          " by " +
          esc(d.uploader) +
          "</div></div>" +
          '<div class="lhp-file-actions ms-auto">' +
          '<a href="' +
          d.url +
          '" download="' + esc(d.name) + '" target="_blank" class="btn btn-sm btn-outline-secondary" title="Download"><i class="bi bi-download"></i></a>' +
          '<button class="btn btn-sm btn-outline-danger rd-del-file" data-att="' +
          d.id +
          '" title="Delete"><i class="bi bi-trash"></i></button>' +
          "</div></div>"
        );
      })
      .join("");
  }

  function renderDocsEditable(docs) {
    if (!docs || !docs.length)
      return '<p class="text-muted small text-center py-3" id="doc-empty-msg">No documents uploaded yet. Use the zone below to add files.</p>';
    var benefNames = currentBeneficiaries.map(function(b){ return b.full_name||''; }).filter(Boolean);
    var recipOpts = ['', 'Everyone'].concat(benefNames).concat(['Other']);
    var ol = getOwnerLabels();
    var ownerOpts = [["", "Select Owner"], ["owner1", ol.o1]];
    if (!_noOwner2) ownerOpts.push(["owner2", ol.o2], ["both", ol.both]);
    return docs.map(function(d) {
      var icon = d.type && d.type.includes('pdf')
        ? 'bi-file-earmark-pdf-fill text-danger'
        : d.type && d.type.includes('image')
          ? 'bi-file-image-fill text-success'
          : 'bi-file-earmark-text-fill text-primary';
      var recipSel = '<select class="form-select form-select-sm doc-recipient-sel" data-doc-id="' + esc(String(d.id)) + '" style="max-width:180px">' +
        recipOpts.map(function(o){
          return '<option value="' + esc(o) + '"' + ((d.recipient||'') === o ? ' selected' : '') + '>' + (o || 'Select Recipient') + '</option>';
        }).join('') + '</select>';
      var ownerSel = '<select class="form-select form-select-sm doc-owner-sel" data-doc-id="' + esc(String(d.id)) + '" style="max-width:200px">' +
        ownerOpts.map(function(o){
          return '<option value="' + esc(o[0]) + '"' + ((d.owner||'') === o[0] ? ' selected' : '') + '>' + esc(o[1]) + '</option>';
        }).join('') + '</select>';
      return '<div class="lhp-file-item">' +
        '<i class="bi ' + icon + ' lhp-file-icon"></i>' +
        '<div class="flex-grow-1 min-w-0">' +
        '<div class="lhp-file-name">' + esc(d.name) + '</div>' +
        '<div class="lhp-file-meta">' + esc(d.size) + ' &middot; ' + esc(d.uploaded) + '</div>' +
        '</div>' +
        '<div class="lhp-file-actions ms-auto d-flex align-items-center gap-2">' +
        recipSel +
        ownerSel +
        '<a href="' + d.url + '" download="' + esc(d.name) + '" target="_blank" class="btn btn-sm btn-outline-secondary" title="Download"><i class="bi bi-download"></i></a>' +
        '<button class="btn btn-sm btn-outline-danger rd-del-file" data-att="' + esc(String(d.id)) + '" title="Delete"><i class="bi bi-trash"></i></button>' +
        '</div></div>';
    }).join('');
  }

  function doCreateUser(nf, ef, rf, ff, phf, errf, $btn, modalId, successSel) {
    $(errf).addClass("d-none").text("");
    var name = $(nf).val().trim(),
      email = $(ef).val().trim(),
      role = $(rf).val();
    if (!name || !email || !role) {
      $(errf).text("Name, email and role are required.").removeClass("d-none");
      return;
    }
    if (!isEmail(email)) {
      $(errf).text("Please enter a valid email.").removeClass("d-none");
      return;
    }
    btnLoad($btn, true);
    ajax(
      "lhp_admin_create_user",
      {
        name: name,
        email: email,
        role: role,
        firm_name: $(ff).val().trim(),
        phone: $(phf).val().trim(),
      },
      function () {
        btnLoad($btn, false);
        toast("User created and invite sent.");
        if (successSel)
          $(successSel)
            .text("User created. Invite sent to " + esc(email) + ".")
            .removeClass("d-none");
        $(nf).val("");
        $(ef).val("");
        $(ff).val("");
        $(phf).val("");
        if (modalId) hideModal(modalId);
        loadAdminStats();
      },
      function (m) {
        btnLoad($btn, false);
        $(errf).text(m).removeClass("d-none");
      },
    );
  }

  /* ═══════════════════════════════════════════════════════
     CLIENT MANAGEMENT (Planner)
  ═══════════════════════════════════════════════════════ */
  var allClients = [],
    currentClientId = 0;

  function loadClients() {
    $("#clients-tbody").html(
      '<tr><td colspan="7" class="text-center py-5 text-muted">' +
        '<span class="spinner-border spinner-border-sm text-primary me-2"></span>Loading clients…' +
        "</td></tr>",
    );
    $("#clients-empty").hide();

    ajax(
      "lhp_get_clients",
      {},
      function (clients) {
        allClients = clients;
        var totalRec = clients.reduce(function (s, c) {
          return s + (c.record_count || 0);
        }, 0);
        $("#stat-clients").text(clients.length);
        $("#stat-total-records").text(totalRec);
        $("#clients-count").text(clients.length);

        if (!clients.length) {
          // Clear spinner, show empty state
          $("#clients-tbody").html(
            '<tr><td colspan="7" class="text-center py-5">' +
              '<div class="lhp-tab-empty" style="display:block;padding:40px 20px">' +
              '<i class="bi bi-people fs-1 text-primary opacity-25 d-block mb-3"></i>' +
              "<h5>No Clients Yet</h5>" +
              '<p class="text-muted mb-3">Add your first client to start managing Lighthouse records.</p>' +
              '<button class="btn lhp-btn-primary-solid" id="lhp-add-client-inline">' +
              '<i class="bi bi-person-plus-fill me-2"></i>Add First Client</button>' +
              "</div></td></tr>",
          );
          return;
        }
        filterClientTable("");
      },
      function (m) {
        $("#clients-tbody").html(
          '<tr><td colspan="7" class="text-center py-4 text-danger">' +
            '<i class="bi bi-exclamation-triangle me-2"></i>' +
            esc(m) +
            "</td></tr>",
        );
        toast(m, "error");
      },
    );
  }

  function filterClientTable(q) {
    var filtered = q
      ? allClients.filter(function (c) {
          return (
            (c.name || "").toLowerCase().includes(q) ||
            (c.email || "").toLowerCase().includes(q)
          );
        })
      : allClients;

    if (!filtered.length) {
      $("#clients-tbody").html(
        '<tr><td colspan="7" class="text-center py-3 text-muted">No clients match your search.</td></tr>',
      );
      return;
    }
    $("#clients-tbody").html(
      filtered
        .map(function (c) {
          return (
            "<tr>" +
            '<td><div class="d-flex align-items-center gap-2">' +
            '<div class="lhp-avatar-sm">' +
            esc(c.name.charAt(0).toUpperCase()) +
            "</div>" +
            "<strong>" +
            esc(c.name) +
            "</strong></div></td>" +
            '<td class="small text-muted">' +
            (c.email
              ? '<a href="mailto:' + esc(c.email) + '">' + esc(c.email) + "</a>"
              : "—") +
            "</td>" +
            '<td class="small text-muted">' +
            (esc(c.phone) || "—") +
            "</td>" +
            '<td><span class="badge bg-light text-dark border">' +
            c.record_count +
            " record" +
            (c.record_count !== 1 ? "s" : "") +
            "</span></td>" +
            '<td class="small text-muted">' +
            esc(c.created) +
            "</td>" +
            '<td><div class="d-flex gap-1">' +
            '<button class="btn btn-sm lhp-btn-outline-primary lhp-view-client-records" data-id="' +
            c.id +
            '" data-name="' +
            esc(c.name) +
            '" title="View Records"><i class="bi bi-folder2-open me-1"></i>Records</button>' +
            '<button class="btn btn-sm btn-outline-secondary lhp-edit-client" data-id="' +
            c.id +
            '" title="Edit"><i class="bi bi-pencil"></i></button>' +
            '<button class="btn btn-sm btn-outline-danger lhp-delete-client" data-id="' +
            c.id +
            '" data-name="' +
            esc(c.name) +
            '" title="Delete"><i class="bi bi-trash"></i></button>' +
            "</div></td></tr>"
          );
        })
        .join(""),
    );
  }

  function openClientDetail(clientId, clientName) {
    currentClientId = clientId;
    $("#current-client-id").val(clientId);
    $("#client-detail-name").text(clientName);

    var client =
      allClients.find(function (c) {
        return c.id === clientId;
      }) || {};
    var meta = [client.email, client.phone]
      .filter(Boolean)
      .join(" · ");
    $("#client-detail-meta").text(meta);

    // Highlight the active row
    $("#clients-tbody tr").removeClass("lhp-row-active");
    $("#clients-tbody tr")
      .filter(function () {
        return $(this).find(".lhp-view-client-records").data("id") === clientId;
      })
      .addClass("lhp-row-active");

    // Show detail panel below table
    $("#client-detail-panel").slideDown(200);
    loadClientRecords(clientId);
  }

  function loadClientRecords(clientId) {
    var $body = $("#client-records-body");
    $body.html(
      '<tr><td colspan="6" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm text-primary me-2"></span>Loading…</td></tr>',
    );
    ajax(
      "lhp_get_client_records",
      { client_id: clientId },
      function (records) {
        if (!records.length) {
          $body.html(
            '<tr><td colspan="6" class="text-center py-5 text-muted">No records yet. <button class="btn btn-link p-0 lhp-add-record-for-client-inline">Add the first Lighthouse.</button></td></tr>',
          );
          return;
        }
        $body.html(
          records
            .map(function (r) {
              var badge =
                r.status === "complete"
                  ? '<span class="lhp-badge-complete"><i class="bi bi-check-circle me-1"></i>Complete</span>'
                  : '<span class="lhp-badge-draft"><i class="bi bi-clock me-1"></i>In Progress</span>';
              return (
                "<tr>" +
                "<td><strong>" +
                esc(r.subject_name || "(No name yet)") +
                "</strong></td>" +
                '<td class="text-muted small">' +
                (r.subject_dob || "—") +
                "</td>" +
                '<td><div class="lhp-progress-wrap"><div class="lhp-prog"><div class="lhp-prog-fill" style="width:' +
                r.completion +
                '%"></div></div><span class="lhp-prog-pct">' +
                r.completion +
                "%</span></div></td>" +
                "<td>" +
                badge +
                "</td>" +
                '<td class="text-muted small">' +
                esc(r.created) +
                "</td>" +
                '<td><div class="d-flex gap-2">' +
                '<button class="btn btn-sm btn-outline-secondary lhp-view-btn" data-id="' +
                r.id +
                '"><i class="bi bi-eye me-1"></i>View</button>' +
                '<button class="btn btn-sm lhp-btn-outline-primary lhp-edit-btn" data-id="' +
                r.id +
                '"><i class="bi bi-pencil-square me-1"></i>Edit</button>' +
                '<button class="btn btn-sm btn-outline-danger lhp-delete-btn" data-id="' +
                r.id +
                '"><i class="bi bi-trash"></i></button>' +
                "</div></td></tr>"
              );
            })
            .join(""),
        );
      },
      function (m) {
        toast(m, "error");
      },
    );
  }

  /* ── Client modal ─────────────────────────────────────── */
  function openClientModal(clientId) {
    var el = document.getElementById("lhp-client-modal");
    if (!el) {
      toast("Could not open the form. Please refresh.", "error");
      return;
    }
    $("#edit-client-id").val(clientId || 0);
    $("#client-modal-title").html(
      clientId
        ? '<i class="bi bi-pencil-square me-2"></i>Edit Client'
        : '<i class="bi bi-person-plus me-2"></i>Add Client',
    );
    $("#cm-name,#cm-email,#cm-phone,#cm-notes").val("");
    $("#client-modal-error").addClass("d-none").text("");
    if (clientId) {
      var c =
        allClients.find(function (x) {
          return x.id === clientId;
        }) || {};
      $("#cm-name").val(c.name || "");
      $("#cm-email").val(c.email || "");
      $("#cm-phone").val(c.phone || "");
    }
    showModal("lhp-client-modal");
  }

  /* ═══════════════════════════════════════════════════════
     DELEGATED ACCESS UI (inside form modal step 6)
  ═══════════════════════════════════════════════════════ */
  var delegatedEntries = []; // in-memory for the current open record

  function initDelegatedStep() {
    // Condition type change
    $(document).on("change", "#del-condition-type", function () {
      var v = $(this).val();
      $(".del-date-row").toggle(v === "date");
      $(".del-event-row").toggle(v === "manual");
    });
    // Show add form
    $(document).on("click", "#lhp-add-delegated", function () {
      $("#del-add-form").show();
      $("#del-name,#del-email,#del-condition-event").val("");
      $("#del-condition-date").val("");
      $("#del-condition-type").val("immediate").trigger("change");
      $("#del-error").addClass("d-none");
    });
    $(document).on("click", "#del-cancel-btn", function () {
      $("#del-add-form").hide();
    });
    // Save delegated entry
    $(document).on("click", "#del-save-btn", function () {
      var $btn = $(this);
      var name = $("#del-name").val().trim();
      var email = $("#del-email").val().trim();
      var rel = $("#del-relationship").val();
      var cond = $("#del-condition-type").val();
      var cdate = $("#del-condition-date").val();
      var cevent = $("#del-condition-event").val().trim();
      var rid = parseInt($("#lhp-record-id").val()) || 0;
      $("#del-error").addClass("d-none");
      if (!name || !email) {
        $("#del-error")
          .text("Name and email are required.")
          .removeClass("d-none");
        return;
      }
      if (!isEmail(email)) {
        $("#del-error")
          .text("Please enter a valid email.")
          .removeClass("d-none");
        return;
      }

      btnLoad($btn, true);
      var payload = {
        record_id: rid,
        name: name,
        email: email,
        relationship: rel,
        condition_type: cond,
        condition_date: cdate,
        condition_event: cevent,
      };
      if (rid) {
        // Save to server immediately if record exists
        ajax(
          "lhp_save_delegated_user",
          payload,
          function (data) {
            btnLoad($btn, false);
            delegatedEntries.push(data.entry);
            renderDelegatedList();
            $("#del-add-form").hide();
            toast("Access granted to " + esc(name) + ".");
          },
          function (m) {
            btnLoad($btn, false);
            $("#del-error").text(m).removeClass("d-none");
          },
        );
      } else {
        // Buffer until record is created
        delegatedEntries.push({
          id: "temp_" + Date.now(),
          name: name,
          email: email,
          relationship: rel,
          condition_type: cond,
          condition_date: cdate,
          condition_event: cevent,
          status: cond === "immediate" ? "active" : "pending",
        });
        btnLoad($btn, false);
        renderDelegatedList();
        $("#del-add-form").hide();
        toast("Added. Save the record to confirm.");
      }
    });
    // Remove entry
    $(document).on("click", ".del-remove-btn", function () {
      var id = $(this).data("id");
      var rid = parseInt($("#lhp-record-id").val()) || 0;
      if (rid && !String(id).startsWith("temp_")) {
        ajax(
          "lhp_remove_delegated_user",
          { record_id: rid, entry_id: id },
          function () {
            delegatedEntries = delegatedEntries.filter(function (d) {
              return d.id !== id;
            });
            renderDelegatedList();
            toast("Access revoked.", "info");
          },
        );
      } else {
        delegatedEntries = delegatedEntries.filter(function (d) {
          return d.id !== id;
        });
        renderDelegatedList();
      }
    });
    // Shared helper — open death verify modal for either delegated or access_person entry
    function openDeathVerifyModal(entryId, recordId, entryType) {
      $("#dv-entry-id").val(entryId);
      $("#dv-entry-type").val(entryType || "delegated");
      $("#dv-record-id-dv").val(recordId);
      $("#dv-file-input").val("").data("file", null);
      $("#dv-file-preview").addClass("d-none");
      $("#dv-file-error").addClass("d-none");
      $("#dv-notes").val("");
      showModal("lhp-death-verify-modal");
    }

    // Activate access — delegated users (Step 6)
    $(document).on("click", ".del-activate-btn", function () {
      var id = $(this).data("id");
      var rid = parseInt($("#lhp-record-id").val()) || 0;
      if (!rid) { toast("Save the record first.", "info"); return; }
      openDeathVerifyModal(id, rid, "delegated");
    });

    // Approve pending_review access
    $(document).on("click", ".acc-approve-btn", function () {
      var id   = $(this).data("id");
      var rid  = parseInt($(this).data("rid")) || 0;
      var name = $(this).data("name") || "this person";
      if (!confirm("Approve access for " + name + "? They will be able to view the Lighthouse immediately.")) return;
      ajax("lhp_approve_access_person", { record_id: rid, entry_id: id },
        function () {
          toast(name + "'s access approved. They can now view the Lighthouse.", "success");
          ajax("lhp_get_record", { record_id: rid }, function(data) {
            renderPendingAccessPanel(data.access_people || [], rid);
          });
        },
        function (m) { toast(m, "error"); }
      );
    });

    // Reject self-activated access
    $(document).on("click", ".acc-reject-btn", function () {
      var id   = $(this).data("id");
      var rid  = parseInt($(this).data("rid")) || 0;
      var name = $(this).data("name") || "this person";
      var reason = prompt("Reject access for " + name + "?\n\nOptional — enter reason (will be emailed to them):");
      if (reason === null) return; // cancelled
      ajax("lhp_reject_access_person", { record_id: rid, entry_id: id, reason: reason },
        function () {
          toast(name + "'s access rejected and they have been notified.", "info");
          // Refresh panel
          ajax("lhp_get_record", { record_id: rid }, function(data) {
            renderPendingAccessPanel(data.access_people || [], rid);
          });
        },
        function (m) { toast(m, "error"); }
      );
    });

    // Activate access — access people (section modal)
    $(document).on("click", ".acc-activate-btn", function () {
      var id = $(this).data("id");
      var rid = parseInt($(this).data("rid")) || 0;
      if (!rid) { toast("Record ID not found.", "error"); return; }
      openDeathVerifyModal(id, rid, "access_person");
    });

    // Death verify modal — browse + drag & drop
    $(document).on("click", "#dv-drop-zone", function (e) {
      if ($(e.target).is("#dv-file-input") || $(e.target).is("#dv-browse-trigger") || $(e.target).closest("#dv-browse-trigger").length) return;
      document.getElementById("dv-file-input").click();
    });
    $(document).on("click", "#dv-browse-trigger", function (e) {
      e.stopPropagation();
      document.getElementById("dv-file-input").click();
    });
    function dvHandleFile(f) {
      if (!f) return;
      var allowed = ["application/pdf","image/jpeg","image/jpg","image/png"];
      if (allowed.indexOf(f.type) === -1) { toast("Only PDF, JPG or PNG accepted.", "error"); return; }
      if (f.size > 10 * 1024 * 1024) { toast("File must be under 10 MB.", "error"); return; }
      $("#dv-file-input").data("file", f);
      $("#dv-file-name").text(f.name);
      $("#dv-file-preview").removeClass("d-none");
      $("#dv-file-error").addClass("d-none");
      $("#dv-drop-zone").removeClass("lhp-drop-hover");
    }
    $(document).on("change", "#dv-file-input", function () { dvHandleFile(this.files[0]); });
    $(document).on("dragover dragenter", "#dv-drop-zone", function (e) {
      e.preventDefault(); e.stopPropagation(); $(this).addClass("lhp-drop-hover");
    });
    $(document).on("dragleave dragend", "#dv-drop-zone", function (e) {
      e.preventDefault(); e.stopPropagation(); $(this).removeClass("lhp-drop-hover");
    });
    $(document).on("drop", "#dv-drop-zone", function (e) {
      e.preventDefault(); e.stopPropagation(); $(this).removeClass("lhp-drop-hover");
      var files = e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files;
      if (files && files.length) dvHandleFile(files[0]);
    });
    $(document).on("click", "#dv-file-clear", function () {
      $("#dv-file-input").val("").data("file", null);
      $("#dv-file-preview").addClass("d-none");
    });

    // Death verify — confirm & activate (handles both delegated users and access people)
    $(document).on("click", "#dv-confirm-btn", function () {
      var $btn = $(this);
      var id        = $("#dv-entry-id").val();
      var entryType = $("#dv-entry-type").val() || "delegated";
      var rid       = parseInt($("#dv-record-id-dv").val()) || parseInt($("#lhp-record-id").val()) || 0;
      var file      = $("#dv-file-input").data("file");
      if (!file) { $("#dv-file-error").removeClass("d-none"); return; }
      btnLoad($btn, true);
      var fd = new FormData();
      fd.append("action", "lhp_upload_death_doc");
      fd.append("nonce", cfg.nonce);
      fd.append("record_id", rid);
      fd.append("entry_id", id);
      fd.append("notes", $("#dv-notes").val().trim());
      fd.append("file", file);
      $.ajax({ url: cfg.ajax_url, type: "POST", data: fd, processData: false, contentType: false })
        .done(function (r) {
          if (!r.success) { btnLoad($btn, false); toast(r.data || "Upload failed.", "error"); return; }
          var activateAction = entryType === "access_person"
            ? "lhp_activate_access_person"
            : "lhp_activate_delegated_access";
          ajax(activateAction, { record_id: rid, entry_id: id, doc_id: r.data.attachment_id },
            function () {
              if (entryType === "delegated") {
                var entry = delegatedEntries.find(function (d) { return d.id === id; });
                if (entry) { entry.status = "active"; entry.death_doc_id = r.data.attachment_id; entry.death_doc_name = r.data.file_name; }
                renderDelegatedList();
              } else {
                // Refresh the Access & Unlock section modal view
                if (myData && myData.access_people) {
                  var ap = myData.access_people.find(function(p){ return (p.id || '') === id; });
                  if (ap) { ap.status = "active"; ap.death_doc_id = r.data.attachment_id; ap.death_doc_name = r.data.file_name; }
                }
                $(".acc-activate-btn[data-id='" + id + "']").closest(".d-flex").remove();
              }
              hideModal("lhp-death-verify-modal");
              btnLoad($btn, false);
              toast("Access activated. Verification document saved.");
            },
            function (m) { btnLoad($btn, false); toast(m, "error"); }
          );
        })
        .fail(function () { btnLoad($btn, false); toast("Upload failed. Try again.", "error"); });
    });
  }

  function renderDelegatedList() {
    var $list = $("#delegated-list");
    if (!delegatedEntries.length) {
      $list.html(
        '<p class="text-muted small">No delegated users yet. Use the button below to add trusted people.</p>',
      );
      return;
    }
    var condIcons = {
      immediate: "bi-lightning-fill text-success",
      date: "bi-calendar-check text-warning",
      manual: "bi-hand-index-fill text-info",
    };
    var html = delegatedEntries
      .map(function (d) {
        var statusBadge =
          d.status === "active"
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-warning text-dark">Pending</span>';
        var condText =
          d.condition_type === "immediate"
            ? "Immediate access"
            : d.condition_type === "date"
              ? "From " + esc(d.condition_date)
              : "Manual activation — " + esc(d.condition_event || "event");
        var activateBtn =
          d.status !== "active" && d.condition_type === "manual"
            ? '<button class="btn btn-sm btn-outline-success del-activate-btn" data-id="' +
              d.id +
              '"><i class="bi bi-file-earmark-medical me-1"></i>Verify &amp; Activate</button>'
            : "";
        var deathDocBadge = d.death_doc_id
          ? '<div class="small mt-1 text-success"><i class="bi bi-patch-check-fill me-1"></i>Verified — <span class="text-muted">' + esc(d.death_doc_name || "document uploaded") + '</span></div>'
          : "";
        return (
          '<div class="lhp-delegated-item">' +
          '<div class="d-flex align-items-center gap-3">' +
          '<div class="lhp-avatar-sm-del">' +
          esc((d.user_name || d.name || "?").charAt(0).toUpperCase()) +
          "</div>" +
          '<div class="flex-grow-1">' +
          '<div class="fw-semibold">' +
          esc(d.user_name || d.name) +
          "</div>" +
          '<div class="text-muted small">' +
          esc(d.user_email || d.email) +
          (d.relationship ? " · " + esc(d.relationship) : "") +
          "</div>" +
          '<div class="small mt-1"><i class="bi ' +
          (condIcons[d.condition_type] || "bi-clock") +
          ' me-1"></i>' +
          condText +
          "</div>" +
          deathDocBadge +
          "</div>" +
          '<div class="d-flex gap-2 align-items-center">' +
          statusBadge +
          activateBtn +
          '<button class="btn btn-sm btn-outline-danger del-remove-btn" data-id="' +
          d.id +
          '"><i class="bi bi-x"></i></button>' +
          "</div>" +
          "</div>" +
          "</div>"
        );
      })
      .join("");
    $list.html(html);
  }

  function loadDelegatedForRecord(recordId) {
    if (!recordId) {
      delegatedEntries = [];
      renderDelegatedList();
      return;
    }
    ajax("lhp_get_delegated_users", { record_id: recordId }, function (data) {
      delegatedEntries = data;
      renderDelegatedList();
    });
  }

  /* ═══════════════════════════════════════════════════════
     PARENT: MULTIPLE RECORDS
  ═══════════════════════════════════════════════════════ */
  function initParentMultiRecords() {
    loadMyRecords();
  }

  function loadMyRecords() {
    var $grid = $("#my-records-grid");
    $grid.html(
      '<div class="col-12 text-center py-5 text-muted"><span class="spinner-border spinner-border-sm text-primary me-2"></span>Loading…</div>',
    );
    ajax(
      "lhp_get_my_records",
      {},
      function (records) {
        if (!records.length) {
          $grid.html(
            '<div class="col-12"><div class="lhp-empty-state"><i class="bi bi-house-heart"></i><h3>No Family Lighthouse Yet</h3><p class="text-muted">Your Family Lighthouse record will be created when your estate planner or family member sets it up.</p></div></div>',
          );
          return;
        }
        var html = records
          .map(function (r) {
            return (
              '<div class="col-sm-6 col-lg-4">' +
              '<div class="lhp-record-card h-100">' +
              '<div class="lhp-record-card-head">' +
              '<div class="flex-grow-1">' +
              '<div class="fw-bold">' +
              esc(r.title || "My Lighthouse") +
              "</div>" +
              (r.subject_name
                ? '<div class="text-muted small">' +
                  esc(r.subject_name) +
                  "</div>"
                : "") +
              '<div class="mt-1">' +
              (r.status === "complete"
                ? '<span class="lhp-badge-complete"><i class="bi bi-check-circle me-1"></i>Complete</span>'
                : '<span class="lhp-badge-draft"><i class="bi bi-clock me-1"></i>In Progress</span>') +
              "</div>" +
              "</div>" +
              "</div>" +
              '<div class="text-muted small mb-3"><i class="bi bi-calendar me-1"></i>Created ' +
              esc(r.created) +
              "</div>" +
              '<button class="btn w-100 lhp-btn-primary-solid lhp-open-my-record" data-id="' +
              r.id +
              '" data-title="' +
              esc(r.title) +
              '"><i class="bi bi-pencil-fill me-2"></i>Edit This Lighthouse</button>' +
              "</div></div>"
            );
          })
          .join("");
        $grid.html('<div class="row g-3">' + html + "</div>");
      },
      function (m) {
        toast(m, "error");
      },
    );
  }

  /* ═══════════════════════════════════════════════════════
     DELEGATED USER DASHBOARD
  ═══════════════════════════════════════════════════════ */
  function initDelegatedDashboard() {
    initSidebar();
    loadDelegatedRecords();
    loadDelegatedProfile();
    initWelcomeModal();
    initSelfActivation();
    $("#lhp-save-del-profile").on("click", function () {
      ajax(
        "lhp_save_profile",
        { name: $("#del-prof-name").val() },
        function () { toast("Profile saved."); },
        function (m) { toast(m, "error"); },
      );
    });
    $(document).on("click", ".lhp-view-delegated-record", function () {
      openRecordDetail(parseInt($(this).data("id")));
    });

    // Unlock all beneficiaries
    $(document).on("click", ".lhp-unlock-all-btn", function () {
      var $btn = $(this);
      var rid  = parseInt($btn.data("id")) || 0;
      if (!confirm("This will send email notifications to all listed beneficiaries informing them the Lighthouse has been unlocked. Are you sure?")) return;
      $btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-2"></span>Unlocking…');
      ajax("lhp_unlock_all_beneficiaries", { record_id: rid },
        function (d) {
          toast("Lighthouse unlocked. " + (d.notified || 0) + " beneficiar" + (d.notified === 1 ? "y" : "ies") + " notified by email.");
          loadDelegatedRecords();
        },
        function (m) {
          $btn.prop("disabled", false).html('<i class="bi bi-unlock-fill me-2"></i>Unlock for All Beneficiaries');
          toast(m, "error");
        }
      );
    });
  }

  function initSelfActivation() {
    var saDocId = 0;

    // Open modal
    $(document).on("click", ".lhp-self-activate-btn", function () {
      var rid = $(this).data("rid");
      $("#sa-record-id").val(rid);
      saDocId = 0;
      $("#sa-file-input").val("").data("file", null);
      $("#sa-file-preview").addClass("d-none");
      $("#sa-file-error,#sa-step1-error,#sa-step2-error,#sa-declaration-error").addClass("d-none");
      $("#sa-declaration").prop("checked", false);
      $("#sa-notes").val("");
      $("#sa-otp-input").val("");
      $("#sa-step-1").show();
      $("#sa-step-2").hide();
      showModal("lhp-self-activate-modal");
    });

    // File browse — use native .click() to avoid jQuery bubble loop
    // (#sa-file-input is inside #sa-drop-zone; trigger() would bubble back up infinitely)
    $(document).on("click", "#sa-drop-zone", function (e) {
      if ($(e.target).is("#sa-file-input") || $(e.target).is("#sa-browse-trigger") || $(e.target).closest("#sa-browse-trigger").length) return;
      document.getElementById("sa-file-input").click();
    });
    $(document).on("click", "#sa-browse-trigger", function (e) {
      e.stopPropagation();
      document.getElementById("sa-file-input").click();
    });

    function saHandleFile(f) {
      if (!f) return;
      var allowed = ["application/pdf","image/jpeg","image/jpg","image/png"];
      if (allowed.indexOf(f.type) === -1) { toast("Only PDF, JPG or PNG accepted.", "error"); return; }
      if (f.size > 10 * 1024 * 1024) { toast("File must be under 10 MB.", "error"); return; }
      $("#sa-file-input").data("file", f);
      $("#sa-file-name").text(f.name);
      $("#sa-file-preview").removeClass("d-none");
      $("#sa-file-error").addClass("d-none");
      $("#sa-drop-zone").removeClass("lhp-drop-hover");
    }

    $(document).on("change", "#sa-file-input", function () {
      saHandleFile(this.files[0]);
    });

    // Drag & drop
    $(document).on("dragover dragenter", "#sa-drop-zone", function (e) {
      e.preventDefault(); e.stopPropagation();
      $(this).addClass("lhp-drop-hover");
    });
    $(document).on("dragleave dragend", "#sa-drop-zone", function (e) {
      e.preventDefault(); e.stopPropagation();
      $(this).removeClass("lhp-drop-hover");
    });
    $(document).on("drop", "#sa-drop-zone", function (e) {
      e.preventDefault(); e.stopPropagation();
      $(this).removeClass("lhp-drop-hover");
      var files = e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files;
      if (files && files.length) saHandleFile(files[0]);
    });

    $(document).on("click", "#sa-file-clear", function () {
      $("#sa-file-input").val("").data("file", null);
      $("#sa-file-preview").addClass("d-none");
    });

    // Step 1: upload doc + send OTP
    $(document).on("click", "#sa-upload-btn", function () {
      var $btn = $(this);
      var file = $("#sa-file-input").data("file");
      var rid  = parseInt($("#sa-record-id").val()) || 0;
      if (!file) { $("#sa-file-error").removeClass("d-none"); return; }
      if (!rid)  { toast("Record ID missing.", "error"); return; }
      if (!$("#sa-declaration").is(":checked")) {
        $("#sa-declaration-error").removeClass("d-none"); return;
      }
      $("#sa-declaration-error").addClass("d-none");
      $("#sa-step1-error").addClass("d-none");
      btnLoad($btn, true);
      var fd = new FormData();
      fd.append("action", "lhp_upload_death_doc");
      fd.append("nonce", cfg.nonce);
      fd.append("record_id", rid);
      fd.append("entry_id", "self");
      fd.append("notes", $("#sa-notes").val().trim());
      fd.append("file", file);
      $.ajax({ url: cfg.ajax_url, type: "POST", data: fd, processData: false, contentType: false })
        .done(function (r) {
          btnLoad($btn, false);
          if (!r.success) { $("#sa-step1-error").text(r.data || "Upload failed.").removeClass("d-none"); return; }
          saDocId = r.data.attachment_id;
          // Send OTP
          ajax("lhp_send_access_otp", { record_id: rid },
            function () {
              $("#sa-step-1").hide();
              $("#sa-step-2").show();
              $("#sa-otp-input").trigger("focus");
            },
            function (m) { $("#sa-step1-error").text(m).removeClass("d-none"); }
          );
        })
        .fail(function () { btnLoad($btn, false); $("#sa-step1-error").text("Upload failed. Try again.").removeClass("d-none"); });
    });

    // Resend OTP
    $(document).on("click", "#sa-resend-otp", function () {
      var rid = parseInt($("#sa-record-id").val()) || 0;
      ajax("lhp_send_access_otp", { record_id: rid },
        function () { toast("New code sent to your email.", "info"); },
        function (m) { toast(m, "error"); }
      );
    });

    // Step 2: verify OTP + activate
    $(document).on("click", "#sa-verify-btn", function () {
      var $btn = $(this);
      var otp = $("#sa-otp-input").val().trim();
      var rid = parseInt($("#sa-record-id").val()) || 0;
      if (!otp || otp.length < 6) { $("#sa-step2-error").text("Enter the 6-digit code from your email.").removeClass("d-none"); return; }
      $("#sa-step2-error").addClass("d-none");
      btnLoad($btn, true);
      ajax("lhp_verify_access_otp", { record_id: rid, otp: otp, doc_id: saDocId },
        function () {
          hideModal("lhp-self-activate-modal");
          btnLoad($btn, false);
          toast("Access activated! You can now view the Lighthouse.", "success");
          loadDelegatedRecords();
        },
        function (m) { btnLoad($btn, false); $("#sa-step2-error").text(m).removeClass("d-none"); }
      );
    });

    // OTP — allow only digits
    $(document).on("input", "#sa-otp-input", function () {
      this.value = this.value.replace(/[^0-9]/g, "").slice(0, 6);
    });

    // Declaration checkbox — JS visual fallback for browsers without :has()
    $(document).on("change", "#sa-declaration", function () {
      $("#sa-declaration-error").addClass("d-none");
    });
  }

  function loadDelegatedRecords() {
    var $grid = $("#delegated-records-grid");
    $grid.html(
      '<div class="col-12 text-center py-5 text-muted"><span class="spinner-border spinner-border-sm text-primary me-2"></span>Loading…</div>',
    );
    ajax(
      "lhp_get_my_delegated_records",
      {},
      function (records) {
        if (!records.length) {
          $grid.html(
            '<div class="col-12"><div class="lhp-empty-state"><i class="bi bi-folder-symlink"></i><h3>No Shared Records</h3><p class="text-muted">You have not been granted access to any Lighthouse records yet.</p></div></div>',
          );
          return;
        }
        var html = records
          .map(function (r) {
            var isPending       = r.my_status === 'pending';
            var isPendingReview = r.my_status === 'pending_review';
            var isActive        = !isPending && !isPendingReview;
            var statusBadge = isPending
              ? '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Pending Activation</span>'
              : isPendingReview
                ? '<span class="badge bg-info text-dark"><i class="bi bi-clock-history me-1"></i>Under Planner Review</span>'
                : '<span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Access Active</span>';
            var isUnlockAll = r.my_condition === 'unlock_all' || r.my_privilege === 'unlock_all';
            var isUnlocked  = !!r.unlocked_at;
            var actionBtn = isPending
              ? '<div class="lhp-self-activate-wrap" data-rid="' + esc(String(r.id)) + '">' +
                '<p class="text-muted small mb-2"><i class="bi bi-info-circle me-1"></i>The owner has passed? Upload a death certificate + verify your email to activate your access.</p>' +
                '<button class="btn w-100 lhp-btn-primary-solid lhp-self-activate-btn" data-rid="' + esc(String(r.id)) + '">' +
                '<i class="bi bi-file-earmark-medical me-2"></i>Request Access — Upload Verification</button>' +
                '</div>'
              : isPendingReview
                ? '<div class="alert alert-info py-2 px-3 small mb-0"><i class="bi bi-shield-check me-1"></i><strong>Document submitted.</strong> Awaiting estate planner review. You will be notified when access is approved.</div>'
                : '<button class="btn w-100 btn-outline-primary lhp-view-delegated-record mb-2" data-id="' + r.id + '">' +
                  '<i class="bi bi-eye me-2"></i>View Lighthouse</button>' +
                  (isUnlockAll
                    ? isUnlocked
                      ? '<div class="alert alert-success py-2 small mb-0"><i class="bi bi-check-circle me-1"></i>Lighthouse unlocked — beneficiaries have been notified.</div>'
                      : '<button class="btn w-100 btn-warning lhp-unlock-all-btn fw-semibold" data-id="' + r.id + '">' +
                        '<i class="bi bi-unlock-fill me-2"></i>Unlock for All Beneficiaries</button>'
                    : '');
            return (
              '<div class="col-sm-6 col-lg-4">' +
              '<div class="lhp-record-card h-100' + ((isPending || isPendingReview) ? ' lhp-record-card-pending' : '') + '">' +
              '<div class="lhp-record-card-head">' +
              '<div class="lhp-section-icon-wrap" style="width:48px;height:48px;font-size:22px"><i class="bi bi-house-heart-fill"></i></div>' +
              '<div class="flex-grow-1">' +
              '<div class="fw-bold">' + esc(r.subject_name || r.title) + "</div>" +
              '<div class="text-muted small">Owner: ' + esc(r.owner_name) + "</div>" +
              (r.planner_name && r.planner_name !== "—"
                ? '<div class="text-muted small">Estate Planner: ' + esc(r.planner_name) + "</div>"
                : "") +
              "</div></div>" +
              '<div class="d-flex gap-2 mb-3 flex-wrap">' +
              statusBadge +
              (r.relationship ? '<span class="badge bg-light text-dark border">' + esc(r.relationship) + "</span>" : "") +
              "</div>" +
              (!isPending
                ? '<div class="lhp-progress-wrap mb-3"><div class="lhp-prog"><div class="lhp-prog-fill" style="width:' + r.completion + '%"></div></div><span class="lhp-prog-pct">' + r.completion + "%</span></div>"
                : "") +
              actionBtn +
              "</div></div>"
            );
          })
          .join("");
        $grid.html('<div class="row g-3">' + html + "</div>");
      },
      function (m) {
        toast(m, "error");
      },
    );
  }

  function loadDelegatedProfile() {
    ajax("lhp_get_profile", {}, function (d) {
      $("#del-prof-name").val(d.name);
      $("#del-prof-email").val(d.email);
    });
  }

  /* ═══════════════════════════════════════════════════════
     INIT
  ═══════════════════════════════════════════════════════ */
  // ── Listen for retry trigger from inline script ──
  $(document).on("lhp:retryinit", function () {
    if (!window._lhpBooted) jQuery(function () {});
  });

  $(function () {
    // ── Global error handler ─────────────────────────
    window.onerror = function (msg, src, line, col, err) {
      console.error("[LHP GLOBAL ERROR]", msg, "Line:" + line, err);
    };


    try {
      // ── INIT per page ────────────────────────────────────
      if ($(".lhp-auth-split").length || $(".lhp-auth-form-inner").length) {
        initLogin();
        initForgotPassword();
        initResetPassword();
      }
      if ($(".lhp-auth-center-card").length) {
        initRegister();
      }
      if ($("#view-ep-profile").length || $("#view-clients").length) {
        initEPDashboard();
        var savedView = null;
        try { savedView = localStorage.getItem('lhp_active_view'); } catch(e) {}
        if (savedView && savedView !== 'ep-profile' && $("#view-" + savedView).length) {
          $(".lhp-nav-item").removeClass("active");
          $('.lhp-nav-item[data-view="' + savedView + '"]').addClass("active");
          switchView(savedView);
        }
      }
      if ($("#view-my-records").length) {
        initParentDashboard();
      }
      if ($("#view-overview").length) {
        initAdminDashboard();
      }
      if ($("#view-shared").length) {
        initDelegatedDashboard();
      }

      // ════════════════════════════════════════════════════
      // TOP-LEVEL DELEGATED HANDLERS
      // These are bound here — NOT inside init functions —
      // so they ALWAYS work regardless of any init errors.
      // ════════════════════════════════════════════════════

      // ── Optout toggle — JS fallback for browsers without :has() ──
      $(document).on("change", ".lhp-optout-chk", function () {
        $(this).closest(".lhp-optout").toggleClass("is-checked", this.checked);
      });

      // ── Add / Edit / Delete Client ─────────────────────
      $(document).on(
        "click",
        "#lhp-add-client, #lhp-add-client-empty, #lhp-add-client-inline",
        function (e) {
          e.stopPropagation();
          openClientModal(0);
        },
      );
      $(document).on(
        "click",
        ".lhp-edit-client, .lhp-edit-client-btn",
        function (e) {
          e.stopPropagation();
          openClientModal(parseInt($(this).data("id")) || 0);
        },
      );
      $(document).on("click", ".lhp-delete-client", function (e) {
        e.stopPropagation();
        $("#confirm-delete-id").val($(this).data("id"));
        $("#confirm-delete-type").val("client");
        $("#confirm-modal-msg").text(
          'Delete client "' +
            esc($(this).data("name")) +
            '"? All their records will also be deleted.',
        );
        showModal("lhp-confirm-modal");
      });

      // ── View Client Records (expand inline) ────────────
      $(document).on("click", ".lhp-view-client-records", function (e) {
        e.stopPropagation();
        openClientDetail(parseInt($(this).data("id")), $(this).data("name"));
      });
      $(document).on("click", ".lhp-close-detail", function () {
        $("#client-detail-panel").slideUp(180);
        $("#clients-tbody tr").removeClass("lhp-row-active");
        currentClientId = 0;
      });
      $(document).on("click", "#lhp-add-record-for-client", function () {
        openFormModal(0, currentClientId);
      });

      // ── All Records tab ─────────────────────────────────
      $(document).on(
        "click",
        "#lhp-add-record, #lhp-add-record-empty",
        function () {
          openFormModal(0, 0);
        },
      );
      $(document).on("click", ".lhp-view-btn", function () {
        openRecordDetail(parseInt($(this).data("id")));
      });
      $(document).on("click", ".lhp-edit-btn", function () {
        openFormModal(parseInt($(this).data("id")), 0);
      });
      $(document).on("click", ".lhp-resend-invite-btn", function () {
        var $btn = $(this);
        var token = $(this).data("token");
        $btn.prop("disabled", true).html('<i class="bi bi-hourglass-split me-1"></i>Sending…');
        ajax("lhp_resend_invite", { token: token }, function () {
          if ($.contains(document, $btn[0])) {
            $btn.html('<i class="bi bi-check-circle me-1"></i>Sent!');
            setTimeout(function () {
              if ($.contains(document, $btn[0])) {
                $btn.prop("disabled", false).html('<i class="bi bi-send me-1"></i>Resend');
              }
            }, 2500);
          }
          toast("Invite email resent successfully.");
        }, function (m) {
          if ($.contains(document, $btn[0])) $btn.prop("disabled", false).html('<i class="bi bi-send me-1"></i>Resend');
          toast(m, "error");
        });
      });
      $(document).on("click", ".lhp-delete-btn", function () {
        $("#confirm-delete-id").val($(this).data("id"));
        $("#confirm-delete-type").val("record");
        $("#confirm-modal-msg").text(
          "Delete this Lighthouse record? This cannot be undone.",
        );
        showModal("lhp-confirm-modal");
      });

      // ── Confirm delete modal OK button ──────────────────
      $(document).on("click", "#lhp-confirm-ok", function () {
        var $btn = $(this);
        var id   = parseInt($("#confirm-delete-id").val()) || 0;
        var type = $("#confirm-delete-type").val();
        if (!id || !type) return;
        btnLoad($btn, true);
        if (type === "client") {
          ajax("lhp_delete_client", { client_id: id }, function () {
            btnLoad($btn, false);
            hideModal("lhp-confirm-modal");
            toast("Client deleted.");
            loadClients();
          }, function (m) { btnLoad($btn, false); toast(m, "error"); });
        } else if (type === "record") {
          ajax("lhp_delete_record", { record_id: id }, function () {
            btnLoad($btn, false);
            hideModal("lhp-confirm-modal");
            toast("Record deleted.");
            loadRecords();
          }, function (m) { btnLoad($btn, false); toast(m, "error"); });
        }
      });

      // ── Sidebar logout ──────────────────────────────────
      $(document).on("click", "#lhp-logout", doLogout);

      // ── Admin: view record detail ───────────────────────
      $(document).on("click", ".lhp-admin-view-record", function () {
        openRecordDetail(parseInt($(this).data("id")));
      });
      // Admin del/toggle handlers live in initAdminDashboard() with .off().on() guards.

      // ── Admin: create user ──────────────────────────────
      $(document).on("show.bs.modal", "#lhp-create-user-modal", function (e) {
        var preset = e.relatedTarget
          ? $(e.relatedTarget).data("preset-role")
          : null;
        if (preset) $("#cu-role").val(preset);
      });
      $("#lhp-do-create-user").on("click", function () {
        doCreateUser(
          "#cu-name",
          "#cu-email",
          "#cu-role",
          "#cu-firm",
          "#cu-phone",
          "#cu-error",
          $(this),
          "lhp-create-user-modal",
          null,
        );
      });
      $("#lhp-inline-create-user").on("click", function () {
        doCreateUser(
          "#icu-name",
          "#icu-email",
          "#icu-role",
          "#icu-firm",
          "#icu-phone",
          "#inline-cu-error",
          $(this),
          null,
          "#inline-cu-success",
        );
      });

      // ── Parent: open section modal ──────────────────────
      $(document).on("click", ".lhp-open-section", function () {
        openSectionModal($(this).data("section"));
      });
      $(document).on("click", ".lhp-back-to-records", function () {
        switchView("my-records");
      });
      $(document).on("click", ".lhp-open-my-record", function () {
        var id = parseInt($(this).data("id"));
        var title = $(this).data("title");
        $("#lhp-my-record-id").val(id);
        $("#active-record-title").text(title || "My Lighthouse");
        switchView("my-record");
        loadMyRecord(id);
      });

      // ── Delegated dashboard ─────────────────────────────
      $(document).on("click", ".lhp-view-delegated-record", function () {
        openRecordDetail(parseInt($(this).data("id")));
      });

      // rd-upload-zone click is bound with .off().on() inside openRecordDetail().

      /* ── Demo data — delegated so they work whenever rendered ── */
      /* Demo seed — no confirm() (blocked in sandboxed/hosted envs) */
      $(document).on("click", "#lhp-seed-demo", function () {
        var $btn = $(this);
        $("#seed-success,#seed-error").addClass("d-none");
        // Show inline confirm inside the card instead of browser dialog
        var $card = $btn.closest(".lhp-card, #demo-controls-card");
        var $existing = $card.find(".lhp-inline-confirm");
        if ($existing.length) {
          $existing.remove();
          return;
        }

        var $confirm = $(
          '<div class="lhp-inline-confirm alert alert-warning d-flex align-items-center justify-content-between gap-3 mt-3 mb-0">' +
            '<span><i class="bi bi-exclamation-triangle-fill me-2"></i>This will add <strong>4 demo clients</strong> with full Lighthouse records. Proceed?</span>' +
            '<div class="d-flex gap-2 flex-shrink-0">' +
            '<button class="btn btn-sm btn-warning lhp-confirm-yes fw-bold">Yes, Load Data</button>' +
            '<button class="btn btn-sm btn-outline-secondary lhp-confirm-no">Cancel</button>' +
            "</div></div>",
        );
        $card.append($confirm);

        $confirm.find(".lhp-confirm-no").on("click", function () {
          $confirm.remove();
        });
        $confirm.find(".lhp-confirm-yes").on("click", function () {
          $confirm.remove();
          btnLoad($btn, true);
          ajax(
            "lhp_seed_demo_data",
            {},
            function (data) {
              btnLoad($btn, false);
              $("#seed-success")
                .text("\u2713 " + data.message)
                .removeClass("d-none");
              toast("Demo data loaded. Refresh to see clients.", "success");
              setTimeout(function () {
                loadAdminStats();
              }, 500);
            },
            function (m) {
              btnLoad($btn, false);
              $("#seed-error").text(m).removeClass("d-none");
              toast(m, "error");
            },
          );
        });
      });

      $(document).on("click", "#lhp-clear-demo", function () {
        var $btn = $(this);
        $("#seed-success,#seed-error").addClass("d-none");
        var $card = $btn.closest(".lhp-card, #demo-controls-card");
        var $existing = $card.find(".lhp-inline-confirm");
        if ($existing.length) {
          $existing.remove();
          return;
        }

        var $confirm = $(
          '<div class="lhp-inline-confirm alert alert-danger d-flex align-items-center justify-content-between gap-3 mt-3 mb-0">' +
            '<span><i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Delete ALL records, clients and demo account?</strong> This cannot be undone.</span>' +
            '<div class="d-flex gap-2 flex-shrink-0">' +
            '<button class="btn btn-sm btn-danger lhp-confirm-yes fw-bold">Yes, Delete All</button>' +
            '<button class="btn btn-sm btn-outline-secondary lhp-confirm-no">Cancel</button>' +
            "</div></div>",
        );
        $card.append($confirm);

        $confirm.find(".lhp-confirm-no").on("click", function () {
          $confirm.remove();
        });
        $confirm.find(".lhp-confirm-yes").on("click", function () {
          $confirm.remove();
          btnLoad($btn, true);
          ajax(
            "lhp_clear_demo_data",
            {},
            function (data) {
              btnLoad($btn, false);
              $("#seed-success")
                .text("\u2713 " + data)
                .removeClass("d-none");
              toast("All demo data cleared.", "info");
              setTimeout(function () {
                loadAdminStats();
              }, 500);
            },
            function (m) {
              btnLoad($btn, false);
              $("#seed-error").text(m).removeClass("d-none");
              toast(m, "error");
            },
          );
        });
      });
      // ── "Other" dropdown → input+▼, ▼ restores select (global) ──
      $(document).on("mousedown", ".lhp-revert-sel", function(e) { e.preventDefault(); otherRestoreSelect($(this)); });
      $(document).on("change", "#items-body select[data-field='recipient']", function() {
        if ($(this).val() === 'Other') { otherMakeInput($(this), 'Enter name'); }
      });
      $(document).on("change", "#letters-body select[data-field='recipient']", function() {
        if ($(this).val() === 'Other') { otherMakeInput($(this), 'Enter name'); }
      });
      $(document).on("change", ".doc-recipient-sel", function() {
        if ($(this).val() === 'Other') {
          var $sel = $(this);
          var docId = $sel.data('doc-id');
          var names = [];
          $sel.find('option').each(function() {
            var v = $(this).val();
            if (v && v !== 'Other') names.push(v);
          });
          var $wrap = $('<div class="input-group input-group-sm lhp-other-wrap"></div>');
          var $inp = $('<input type="text" class="form-control form-control-sm doc-recipient-sel lhp-other-input" data-doc-id="' + esc(String(docId)) + '" placeholder="Enter name">');
          var $btn = $('<button type="button" class="btn btn-outline-secondary btn-sm lhp-revert-sel" tabindex="-1" data-doc-id="' + esc(String(docId)) + '" data-names=\'' + JSON.stringify(names) + '\'>▼</button>');
          $wrap.append($inp).append($btn);
          $sel.replaceWith($wrap);
          $inp.focus();
        }
      });
      $(document).on("change", "#access-body select[data-field='full_name']", function() {
        var val = $(this).val();
        if (val === "Other") { otherMakeInput($(this), 'Full Name'); return; }
        applyAccessBeneficiaryDetails($(this).closest("tr"), val);
      });
      // privilege change no longer disables name dropdown
      // ── Delete profile buttons (owner self / co-owner) ──
      $(document).on("click", "#lhp-delete-my-account", function() {
        var name = $("#ppar-name").val() || "your account";
        $("#lhp-del-profile-name").text(name);
        $("#lhp-del-profile-id").val("0");
        showModal("lhp-confirm-del-profile-modal");
      });
      $(document).on("click", "#lhp-delete-o2", function() {
        var name = $("#ppar-o2-name").val() || "co-owner";
        $("#lhp-del-profile-name").text(name);
        var o2Id = $(this).data("o2id") || 0;
        $("#lhp-del-profile-id").val(o2Id);
        showModal("lhp-confirm-del-profile-modal");
      });
      $(document).on("click", "#lhp-confirm-del-profile", function() {
        var $btn = $(this);
        $btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1"></span>Deleting…');
        var targetId = parseInt($("#lhp-del-profile-id").val()) || 0;
        ajax("lhp_owner_delete_profile", { user_id: targetId || undefined }, function(r) {
          $btn.prop("disabled", false).html('<i class="bi bi-trash me-1"></i>Delete');
          hideModal("lhp-confirm-del-profile-modal");
          if (r && r.redirect) { window.location.href = r.redirect; return; }
          if (targetId === 0) {
            // Own account deleted — server should have provided a redirect
            window.location.reload();
            return;
          }
          // Co-owner deleted — clear form, show empty state
          toast("Co-owner deleted.");
          $("#ppar-o2-name,#ppar-o2-email,#ppar-o2-phone").val('');
          $("#ppar-owner2-status").empty();
          $("#lhp-delete-o2").addClass("d-none").removeData("o2id");
          $("#ppar-o2-fields").hide();
          $("#ppar-o2-cancel-btn").hide();
          $("#ppar-o2-empty").show();
          loadParentProfile();
          var rid = parseInt($("#lhp-my-record-id").val()) || 0;
          if (rid) loadMyRecord(rid);
        }, function(m) {
          $btn.prop("disabled", false).html('<i class="bi bi-trash me-1"></i>Delete');
          toast(m, "error");
        });
      });

      window._lhpBooted = true;
    } catch (err) {
      console.error("[LHP INIT ERROR]", err.message || err, err.stack || "");
    }
  });

  // ── Auth page: offset .lhp-auth-center-wrap below the visible theme header ──
  if (document.body.classList.contains("lhp-auth-page")) {
    function lhpApplyHeaderOffset() {
      var maxBottom = 0;
      var els = document.querySelectorAll("header, #masthead, #site-header, #ocean-header, .site-header, #header, .header-wrap, #header-wrap, .top-bar, #top-bar, .announcement-bar");
      for (var i = 0; i < els.length; i++) {
        var el = els[i];
        var style = window.getComputedStyle(el);
        if (style.display === "none" || style.visibility === "hidden") continue;
        var rect = el.getBoundingClientRect();
        if (rect.bottom > maxBottom) maxBottom = rect.bottom;
      }
      // Also catch any fixed element pinned at the very top
      var allFixed = document.querySelectorAll("*");
      for (var j = 0; j < allFixed.length; j++) {
        var f = allFixed[j];
        var fs = window.getComputedStyle(f);
        if ((fs.position === "fixed" || fs.position === "sticky") && fs.display !== "none") {
          var fr = f.getBoundingClientRect();
          if (fr.top <= 2 && fr.bottom > maxBottom) maxBottom = fr.bottom;
        }
      }
      var wrap = document.querySelector(".lhp-auth-center-wrap");
      if (wrap && maxBottom > 0) {
        wrap.style.paddingTop = (maxBottom + 24) + "px";
      }
    }
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", lhpApplyHeaderOffset);
    } else {
      lhpApplyHeaderOffset();
    }
    window.addEventListener("resize", lhpApplyHeaderOffset);
  }

  // ── Global function exports (fallback for onclick attributes) ──
  window.lhpOpenClientModal = function (id) {
    openClientModal(id || 0);
  };
  window.lhpOpenFormModal = function (id) {
    openFormModal(id || 0, 0);
  };
})(jQuery);
