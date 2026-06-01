<?php
/**
 * FLP_Migration — one-time DB migration from _lhp_ to _flp_ prefixes
 * and old URL slugs to new Family Lighthouse slugs.
 *
 * Gated by option 'flp_db_migration_v1'. Runs once on init priority 20.
 */
class FLP_Migration {

    static function init() {
        if ( get_option( 'flp_db_migration_v1' ) ) return;
        self::run();

        // 301 redirects for old URLs → new URLs
        add_action( 'template_redirect', [ __CLASS__, 'redirect_old_urls' ], 5 );
    }

    static function run() {
        global $wpdb;

        // ── 1. Migrate user meta: _lhp_* → _flp_* ──────────────────
        $wpdb->query(
            "UPDATE {$wpdb->usermeta}
             SET meta_key = CONCAT('_flp_', SUBSTRING(meta_key, 6))
             WHERE meta_key LIKE '_lhp_%'"
        );

        // ── 2. Migrate post meta: _lhp_* → _flp_* ──────────────────
        $wpdb->query(
            "UPDATE {$wpdb->postmeta}
             SET meta_key = CONCAT('_flp_', SUBSTRING(meta_key, 6))
             WHERE meta_key LIKE '_lhp_%'"
        );

        // ── 3. Migrate WP options ────────────────────────────────────
        $option_map = [
            'lhp_page_migrated'  => 'flp_page_migrated',
            'lhp_version'        => 'flp_version',
            '_lhp_activity_log'  => '_flp_activity_log',
        ];
        foreach ( $option_map as $old_key => $new_key ) {
            $val = get_option( $old_key );
            if ( $val !== false ) {
                update_option( $new_key, $val );
                delete_option( $old_key );
            }
        }

        // ── 4. Rename WP page slugs ──────────────────────────────────
        $slug_map = [
            'lhp-login'        => 'login',
            'lhp-admin'        => 'fml-admin',
            'lhp-delegated'    => 'delegated-dashboard',
            'my-lighthouse'    => 'dashboard',
            'lighthouse-form'  => 'register',
        ];
        foreach ( $slug_map as $old_slug => $new_slug ) {
            // Skip if new slug page already exists
            if ( get_page_by_path( $new_slug ) ) continue;
            $old_page = get_page_by_path( $old_slug );
            if ( $old_page ) {
                wp_update_post( [
                    'ID'        => $old_page->ID,
                    'post_name' => $new_slug,
                ] );
            }
        }

        // ── 5. Mark migration complete ───────────────────────────────
        update_option( 'flp_db_migration_v1', 1 );

        error_log( '[FLP_Migration] v1 migration complete: _lhp_→_flp_ meta keys and page slugs updated.' );
    }

    /**
     * 301-redirect old URL slugs to new ones.
     * Fires on template_redirect (priority 5) so it beats other redirects.
     */
    static function redirect_old_urls() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        $redirects = [
            '/lhp-login'     => '/login',
            '/lhp-admin'     => '/fml-admin',
            '/lhp-delegated' => '/delegated-dashboard',
            '/my-lighthouse' => '/dashboard',
            '/lighthouse-form' => '/register',
        ];

        foreach ( $redirects as $old => $new ) {
            // Match the slug at the start of the path (with or without trailing slash / query string)
            if ( strpos( $uri, $old ) === 0 &&
                 ( strlen( $uri ) === strlen( $old ) || $uri[ strlen( $old ) ] === '/' || $uri[ strlen( $old ) ] === '?' || $uri[ strlen( $old ) ] === '#' ) ) {
                $target = $new . substr( $uri, strlen( $old ) );
                wp_safe_redirect( home_url( $target ), 301 );
                exit;
            }
        }
    }
}
