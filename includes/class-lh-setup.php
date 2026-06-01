<?php
class LH_Setup {
    static function activate() {
        LH_Roles::register();
        LH_CPT::register();
        self::migrate_attorney_to_planner();
        lhp_rewrite_rules();
        flush_rewrite_rules();
        self::create_pages();
    }
    static function deactivate() { flush_rewrite_rules(); }

    static function migrate_attorney_to_planner() {
        // Migrate role slug
        $users = get_users([ 'role__in' => [ 'lighthouse_attorney' ] ]);
        foreach ( $users as $u ) {
            $u->remove_role( 'lighthouse_attorney' );
            $u->add_role( 'lighthouse_planner' );
        }
        // Migrate post meta key
        global $wpdb;
        $wpdb->update( $wpdb->postmeta, [ 'meta_key' => '_flp_planner_id' ], [ 'meta_key' => '_lhp_attorney_id' ] );

        // Migrate WP page: attorney-dashboard → planner-dashboard
        $old_page = get_page_by_path( 'attorney-dashboard' );
        $new_page = get_page_by_path( 'planner-dashboard' );
        if ( $old_page ) {
            if ( $new_page && $new_page->ID !== $old_page->ID ) {
                wp_delete_post( $old_page->ID, true );
            } else {
                wp_update_post([
                    'ID'          => $old_page->ID,
                    'post_name'   => 'planner-dashboard',
                    'post_title'  => 'Estate Planner Dashboard',
                    'post_content' => '[lhp_planner_dashboard]',
                ]);
                update_post_meta( $old_page->ID, '_wp_page_template', 'elementor_header_footer' );
            }
        }
    }

    static function create_pages() {
        $pages = [
            'login'      => [ 'title' => 'Login',              'content' => '[lhp_login]'              ],
            'register'       => [ 'title' => 'Create Account',     'content' => '[lhp_register]'           ],
            'planner-dashboard'            => [ 'title' => 'Estate Planner Dashboard', 'content' => '[lhp_planner_dashboard]' ],
            'dashboard'         => [ 'title' => 'My Lighthouse',      'content' => '[lhp_parent_dashboard]'   ],
            'fml-admin'      => [ 'title' => 'Admin Panel',        'content' => '[lhp_admin_dashboard]'    ],
            'delegated-dashboard'  => [ 'title' => 'Shared Access',      'content' => '[lhp_delegated_dashboard]'],
        ];
        foreach ( $pages as $slug => $data ) {
            if ( ! get_page_by_path( $slug ) ) {
                $id = wp_insert_post([
                    'post_title'   => $data['title'],
                    'post_name'    => $slug,
                    'post_content' => $data['content'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ]);
                update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
            }
        }
    }

    static function fix_page_templates() {
        $slugs = ['planner-dashboard','dashboard','fml-admin','delegated-dashboard'];
        foreach ( $slugs as $slug ) {
            $page = get_page_by_path( $slug );
            if ( $page ) {
                $tpl = get_post_meta( $page->ID, '_wp_page_template', true );
                if ( $tpl !== 'elementor_header_footer' ) {
                    update_post_meta( $page->ID, '_wp_page_template', 'elementor_header_footer' );
                }
            }
        }
    }
}
