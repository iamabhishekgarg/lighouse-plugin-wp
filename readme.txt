=== Lighthouse Portal ===
Contributors: reckoningitsol
Tags: estate planner, lighthouse, family portal, will, legacy
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.9.2
License: GPLv2 or later

Role-based frontend portal for Estate Planners and families.

== Changelog ==

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
