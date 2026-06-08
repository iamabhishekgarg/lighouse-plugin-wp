=== Lighthouse Portal ===
Contributors: reckoningitsol
Tags: estate planner, lighthouse, family portal, will, legacy
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.9.5
License: GPLv2 or later

Role-based frontend portal for Estate Planners and families.

== Changelog ==

= 1.9.5 - 2026-06-08 =
* Fixed #2: Toast close (X) icon now consistently anchored to top-right corner — changed flex alignment from center to start; added top margin; scoped to `.lhp-toast-close` class
* Fixed #3: "Back to Sign In" button now matches size of "Sign In" and "Send Reset Link" — changed from small `lhp-link` anchor to full `btn btn-outline-secondary btn-lg` on both forgot and reset panels

= 1.9.4 - 2026-06-08 =
* Fixed #1: Full Name field now rejects numeric-only and special-char-only values on registration (both Parent and Planner) — new `isName()` regex validator
* Fixed #4: Login button stuck in "Signing in…" state after browser back-navigation — `pageshow` event resets button and `_loginInFlight` flag
* Fixed #5: Initials badge shows only first letter instead of full initials (e.g. "S" not "SK") — all 4 dashboard templates now derive initials from all words; JS post-save update matches
* Fixed #6: Expired/invalid reset link showed password form before token validation — new `lhp_validate_reset_key` AJAX action checks token on page load; form hidden, error shown immediately if invalid
* Fixed #7: Co-owner had no Change Password UI after login — added Change Password section to parent dashboard profile tab; works for both primary and co-owner
* Fixed #8: No notification sent to primary user when co-owner deletes their account — `lhp_owner_delete_profile` now emails primary owner(s) on self-delete
* Fixed #9: Beneficiaries validation error showed "Invalid email in children" — now shows "Invalid email in Beneficiaries"
* Fixed #10: Optional phone field accepted invalid formats in Beneficiaries — phone now runs `isPhone()` validation when provided
* Fixed #11: Default beneficiary row was deletable leaving empty state — remove button blocked when only one row remains in any repeater table
* Fixed #12: Save Changes triggered loader on empty Beneficiaries form without validation — requires at least one filled row before AJAX fires
* Fixed #13: Modal close (X) button disappeared after dismissing validation toast — empty `.lhp-toast-container` removed from DOM after last toast exits, no longer intercepts clicks
* Fixed #14: Concurrent saves by two users caused last-write-wins data loss — `_flp_updated_at` timestamp stamped on every save; incoming save rejected if client timestamp is stale
* Fixed #15: Duplicate Access & Unlock entries (same name + email) were saveable — deduplicated on save with info toast
* Fixed #16: Registration email copy improved — "If you don't have an account yet, visit [login] and click 'Create one free' to register."
* Fixed #17: Beneficiaries allowed duplicate email or phone — blocked on save with specific error message
* Fixed #18: Personal Items saveable without selecting a beneficiary — recipient field now required when row has any data
* Fixed #19: Deleted beneficiary remained assigned in Personal Items — saving Beneficiaries section clears `recipient` in Personal Items where name no longer exists
* Fixed #20: Item Description not mandatory in Personal Items — description field now required when row has any data
* Fixed #21: Corrupted PDF accepted in Letters & Messages upload — server-side magic byte check (`%PDF-`) rejects non-PDF files masquerading as PDFs
* Fixed #22: Deleted co-owner's End of Life Preferences carried over to new co-owner — `_flp_burial.owner2` cleared when co-owner is deleted

= 1.9.3 - 2026-06-08 =
* Fixed: Letters & Messages read-only view — sender/recipient labels ambiguous (`· Robin.` format); replaced with explicit colour-coded pill badges: blue "Recipient" + purple "Written by" on letters; "Recipient:" + "From:" badges on file attachments
* Fixed: Delegated users could see upload zone and delete button in Documents tab of record detail modal — upload zone, file input, and delete buttons now hidden; upload + delete event handlers skipped entirely for `lighthouse_delegated` role
* Added: Bidirectional contact sync between Beneficiaries and Access & Unlock sections — saving either section propagates matching `full_name` email + phone to the other; only contact fields sync, not privilege/relationship
* Added: Portal footer on all dashboard pages — "Questions or support: 832-317-5533" with `background: #161C52`, white text; injected as `position: fixed` via JS; `.lhp-main-content` gets `padding-bottom: 50px` to prevent overlap

= 1.9.2 - 2026-06-01 =
* Fixed: "Prefer not to include" toggle unchecked on form re-open — `repForm()` builds rows as raw HTML strings so DOM post-processing never ran; fix bakes `checked` + `is-checked` class directly into HTML string inside `buildRowHtml()`
* Fixed: Server-side validation rejected `__optout__` sentinel as invalid email — PHP now skips email format check when value equals sentinel
* Fixed: Empty email/phone without "Prefer not to include" checked allowed save — both step wizard and section modal save paths now block save and highlight fields red
* Fixed: "Prefer not to include" sentinel shown as blank `—` in Record Detail and review summary — table helper renders sentinel as italic "Prefer not to include"; review step reads checkbox state
* Fixed: Letters view — owner shown as unlabelled `·` dot; delegated users couldn't identify sender vs recipient — now shows explicit "From:" / "To:" labels on letters and "For:" / "From:" on file attachments
* Added: Death verification document upload required before planner can activate manual delegated access — new modal with file drop zone, notes field; PHP stores attachment with `_lhp_death_verification` meta; delegated entry shows verified badge + filename after activation
* Added: `lhp_upload_death_doc` AJAX action — validates mime type (PDF/JPG/PNG), 10 MB limit, stores as WP attachment

= 1.9.1 - 2026-06-01 =
* Fixed: `btn-outline-primary` dark background bleed from Bootstrap CSS variable — added `background: transparent !important` to `.lhp-root .btn-outline-primary`; removed conflicting duplicate color block
* Fixed: Section card "Edit" button was outline style while "Add Info" was solid — all section cards now use `lhp-btn-primary-solid` for visual consistency
* Fixed: Logo container `border-radius: 50%` clipped rectangular firm logos to ellipse — changed to `10px` rounded rectangle
* Fixed: Sidebar name/firm not updating after Save Profile — wrong `.fw-bold` selector (template uses `.fw-semibold`); firm now targets `.lhp-sidebar-firm` directly, creates/removes element as needed
* Fixed: Profile logo preview blank on page load — `loadEPProfile()` now reads `logo_url` from API and restores preview box + sidebar avatar without re-upload
* Fixed: Theme header overlapping auth cards on `/lhp-login` and `/lhp-register` — JS measures all fixed/sticky elements and applies their height as `paddingTop` on `.lhp-auth-center-wrap`; re-fires on resize
* Fixed: Modal closing mid-upload on fixed `1800ms` timer — now closes inside upload callback when files pending, preventing premature dismiss
* Fixed: Toast XSS — `msg` now passed through `esc()` before inserting into DOM
* Added: Sidebar avatar (`lhp-avatar-lg`) shows user's first name instead of single initial when no logo uploaded; all 4 dashboards updated (planner, parent, admin, delegated)
* Added: `logo_url` field to `lhp_get_ep_profile` AJAX response so frontend can restore logo without re-upload
* Added: `lhp-auth-page` body class on login/register pages for JS header-offset targeting
* Added: Explicit "From:" label on letters and "For:" / "From:" labels on file attachments in delegated read-only view — previously displayed as unlabelled `·` separator
* Added: `:focus-visible` CSS rules on form controls and repeater inputs — keyboard users get outline, mouse users don't
* Added: `.lhp-step-item.done + .lhp-step-connector` — step connector line fills green when step is complete
* Added: `@media (prefers-reduced-motion: reduce)` block — disables all animations/transitions/transforms for accessibility
* Added: `for` attributes on all missing form labels across planner, parent, admin, and delegated dashboard templates
* Changed: Empty state copy corrected — "Click 'Generate New Link'" → "Click 'Invite Specific Client'"
* Changed: Logo container `max-height` increased 52px → 64px for better sidebar visibility

= 1.4.1 - 2026-05-15 =
* Fixed: Broken multi-line CSS comment — orphaned `*/` was killing entire stylesheet after line 357, breaking all `.lhp-auth-split` styles (width, border-radius, box-shadow, display: grid)
* Fixed: Invite page `<title>` now uses `pre_get_document_title` instead of legacy `wp_title` filter for WordPress 6.x compatibility
* Fixed: Welcome page background color now fills full viewport (OceanWP `#outer-wrap` and `#wrap` set to transparent)
* Fixed: `.lhp-portal-page .lhp-auth-center-wrap` padding now includes admin bar offset (`--lhp-site-offset`) to prevent card overlap
* Fixed: Upload race condition — files now process sequentially instead of parallel AJAX (prevents skipped files)
* Added: Password strength meter on invite registration form
* Added: Trust badges (Encrypted · Private · Secured) on invite form
* Added: Consolidated `.lhp-auth-split` CSS rule for both login and invite pages
* Changed: Login page background gradient removed from `.lhp-auth-center-wrap` for consistent white-label appearance

= 1.4.0 - 2026-05-15 =
* Breaking: Renamed "attorney" → "planner" everywhere — role slug (`lighthouse_planner`), meta key (`_lhp_planner_id`), URL slug (`/planner-dashboard`), file names, JS/CSS, email copy. DB migration runs automatically.
* Breaking: Old role `lighthouse_attorney` migrated to `lighthouse_planner`. Old meta key `_lhp_attorney_id` migrated to `_lhp_planner_id`.
* BC: Old shortcodes `[lhp_attorney_dashboard]` and `[lhp_attorney_form]` still work. Old URL `/attorney-dashboard` redirects (301) to `/planner-dashboard`.
* Added: Owner select option in Documents & Photos section
* Added: Access & Unlock row-level validation (all fields required if any entered)
* Added: Beneficiary auto-fetch for email + phone in Access & Unlock
* Added: Elementor frontend config fallback for invite page (fixes chunk 404 errors)
* Added: Invite page uses same split-panel layout as login page
* Added: Field-level validation errors with invalid-feedback on invite form
* Added: Automatic rewrite rule flush on version change (fixes FTP deploy 404)
* Added: readme.txt with changelog
* Added: Admin menu restructured — "Planners" (top-level) + "Clients" (submenu)
* Added: Loading skeleton animations + CSS transitions
* Added: Demo seeder extracted to dedicated class (`includes/class-lh-demo.php`)
* Added: Centralized URL helper `lhp_page_url()` + role helpers `is_admin_user()`, `is_portal_user()`
* Added: Agency name + logo on invite welcome page (white-label branding)
* Added: `_section` field on uploaded docs — files stay in correct section (documents vs letters) across page refreshes
* Added: Letter section now shows attachment file count + assigned count in card preview
* Added: Serialized file uploads — processes files one at a time (no race conditions)
* Added: "Other" recipient in documents converts to text input with name entry
* Changed: Burial section — Owner 1 and Owner 2 now have separate preference/requests/funeral home panels
* Changed: Parent dashboard — removed "New Lighthouse" button + create-record modal (one-Lighthouse enforcement)
* Changed: Invite page — theme header/footer hidden for clean white-label appearance
* Changed: Logo container — fixed aspect-ratio for consistent rendering
* Removed: Owner detail cards from Bank Accounts section
* Removed: "Other Owners" section card entirely
* Removed: `lhp_invite_parent` AJAX action — planners can only share invite links, cannot create user accounts directly
* Fixed: Theme header/footer no longer blocked on invite route
* Fixed: Invite page body classes now include Elementor template classes
* Fixed: Invite page title now shows "You're Invited"
* Fixed: OceanWP page header hidden on invite page
* Fixed: Save section button no longer turns invisible after save (keeps `lhp-btn-primary-solid` class)
* Fixed: Section card counts filter by `_section` — letter files don't inflate documents count
* Fixed: "Other" dropdown in ALL beneficiary selects properly converts to text input
* Fixed: Duplicate `secPreview` handler for letters (was returning null before file check)

= 1.3.0 - 2026-05-01 =
* Added: Estate Planner dashboard with profile, logo upload, share links
* Added: Owner 2 as real WP co-owner login flow
* Added: Dynamic owner labels for bank/life insurance selects
* Added: Burial and Letters ownership fields
* Added: One-Lighthouse-per-user enforcement
* Added: Invite registration via /lhp-invite/{token} route
* Fixed: Various UI bugs and label renames (Attorney→Estate Planner)
