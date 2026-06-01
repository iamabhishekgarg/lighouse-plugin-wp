<?php
class LH_Auth {

    static function init() {
        add_action( 'template_redirect', [ __CLASS__, 'handle_magic_login' ], 0 );
        add_action( 'template_redirect', [ __CLASS__, 'handle_redirects' ], 1 );
        add_filter( 'login_redirect',    [ __CLASS__, 'login_redirect' ], 10, 3 );
        add_filter( 'show_admin_bar',    [ __CLASS__, 'hide_admin_bar' ] );
        add_action( 'admin_init',        [ __CLASS__, 'block_admin' ] );
    }

    static function handle_magic_login() {
        $token = sanitize_text_field( $_GET['magic'] ?? '' );
        if ( !$token ) return;

        $users = get_users([
            'meta_key'   => '_flp_magic_token',
            'meta_value' => $token,
            'number'     => 1,
        ]);
        if ( empty($users) ) return;

        $user   = $users[0];
        $expiry = (int) get_user_meta( $user->ID, '_flp_magic_expiry', true );

        // Delete token regardless (one-time use)
        delete_user_meta( $user->ID, '_flp_magic_token' );
        delete_user_meta( $user->ID, '_flp_magic_expiry' );

        if ( $expiry < time() ) return; // expired — just show page normally

        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, true );

        $role = self::current_role( $user );
        if ( $role === 'lighthouse_delegated' )
            $dest = home_url('/delegated-dashboard');
        elseif ( $role === 'lighthouse_planner' )
            $dest = home_url('/planner-dashboard');
        else
            $dest = home_url('/dashboard');

        wp_safe_redirect( add_query_arg( 'lhp_new_invite', '1', $dest ) );
        exit;
    }

    static function hide_admin_bar( $show ) {
        return self::is_portal_user() ? false : $show;
    }

    static function block_admin() {
        if ( is_admin() && !wp_doing_ajax() ) {
            if ( self::is_portal_user() ) { wp_safe_redirect(home_url('/login')); exit; }
        }
    }

    static function login_redirect( $redirect_to, $request, $user ) {
        if ( is_wp_error($user) ) return $redirect_to;
        $role = self::current_role($user);
        if ( $role === 'lhp_super_admin'       ) return home_url('/fml-admin');
        if ( $role === 'lighthouse_law_firm'   ) return home_url('/fml-admin');
        if ( $role === 'lighthouse_planner'   ) return home_url('/planner-dashboard');
        if ( $role === 'lighthouse_parent'     ) return home_url('/dashboard');
        if ( $role === 'lighthouse_delegated'  ) return home_url('/delegated-dashboard');
        return $redirect_to;
    }

    static function handle_redirects() {
        if ( !is_page() ) return;
        $slug = get_post_field('post_name', get_queried_object_id());
        $role = self::current_role();

        $auth_pages      = ['login','register'];
        $admin_pages     = ['fml-admin'];
        $planner_pages  = ['planner-dashboard'];
        $parent_pages    = ['dashboard'];
        $delegated_pages = ['delegated-dashboard'];

        if ( in_array($slug,$auth_pages) && is_user_logged_in() ) {
            if ( $role === 'lhp_super_admin'      || $role === 'lighthouse_law_firm' ) { wp_safe_redirect(home_url('/fml-admin'));            exit; }
            if ( $role === 'lighthouse_planner'  ) { wp_safe_redirect(home_url('/planner-dashboard')); exit; }
            if ( $role === 'lighthouse_parent'    ) { wp_safe_redirect(home_url('/dashboard'));      exit; }
            if ( $role === 'lighthouse_delegated' ) { wp_safe_redirect(home_url('/delegated-dashboard'));      exit; }
            return;
        }
        if ( in_array($slug,$admin_pages) ) {
            if ( !is_user_logged_in() ) { wp_safe_redirect(home_url('/login')); exit; }
            if ( !in_array($role,['lhp_super_admin','lighthouse_law_firm','administrator']) ) { wp_safe_redirect(home_url('/login')); exit; }
            return;
        }
        if ( in_array($slug,$planner_pages) ) {
            if ( !is_user_logged_in() ) { wp_safe_redirect(home_url('/login')); exit; }
            if ( $role === 'lighthouse_parent'    ) { wp_safe_redirect(home_url('/dashboard'));  exit; }
            if ( $role === 'lighthouse_delegated' ) { wp_safe_redirect(home_url('/delegated-dashboard')); exit; }
            return;
        }
        if ( in_array($slug,$parent_pages) ) {
            if ( !is_user_logged_in() ) { wp_safe_redirect(home_url('/login')); exit; }
            if ( $role === 'lighthouse_planner'  ) { wp_safe_redirect(home_url('/planner-dashboard')); exit; }
            if ( $role === 'lighthouse_delegated' ) { wp_safe_redirect(home_url('/delegated-dashboard'));      exit; }
            if ( in_array($role,['lhp_super_admin','lighthouse_law_firm']) ) { wp_safe_redirect(home_url('/fml-admin')); exit; }
            return;
        }
        if ( in_array($slug,$delegated_pages) ) {
            if ( !is_user_logged_in() ) { wp_safe_redirect(home_url('/login')); exit; }
            if ( $role !== 'lighthouse_delegated' && $role !== 'administrator' ) { wp_safe_redirect(home_url('/login')); exit; }
            return;
        }
    }

    static function current_role( $user = null ) {
        if ( !$user ) {
            if ( !is_user_logged_in() ) return '';
            $user = wp_get_current_user();
        }
        $roles = (array)$user->roles;
        if ( in_array('lhp_super_admin',      $roles) ) return 'lhp_super_admin';
        if ( in_array('lighthouse_law_firm',  $roles) ) return 'lighthouse_law_firm';
        if ( in_array('lighthouse_planner',  $roles) ) return 'lighthouse_planner';
        if ( in_array('lighthouse_parent',    $roles) ) return 'lighthouse_parent';
        if ( in_array('lighthouse_delegated', $roles) ) return 'lighthouse_delegated';
        if ( in_array('administrator',        $roles) ) return 'administrator';
        return '';
    }

    /**
     * Centralized role-group checks. Use instead of hardcoding role arrays.
     */
    static function is_role( $roles, $user = null ) {
        return in_array( self::current_role( $user ), (array) $roles );
    }
    static function is_admin_user( $user = null ) {
        return self::is_role( ['administrator','lhp_super_admin','lighthouse_law_firm'], $user );
    }
    static function is_platform_admin( $user = null ) {
        return self::is_role( ['administrator','lhp_super_admin'], $user );
    }
    static function is_portal_user( $user = null ) {
        return self::is_role( ['lighthouse_planner','lighthouse_parent','lighthouse_law_firm','lhp_super_admin','lighthouse_delegated'], $user );
    }

    /**
     * Verify a user can access a record.
     * Delegated users: check _lhp_delegated_users meta AND access condition.
     */
    static function verify_record_access( $post_id, $user_id = null ) {
        if ( !$user_id ) $user_id = get_current_user_id();
        $role = self::current_role();

        // Admins always have access
        if ( self::is_admin_user() ) return true;

        $owner     = (int)get_post_meta($post_id,'_flp_owner_id',    true);
        $owner2_id = (int)get_post_meta($post_id,'_flp_owner2_id',   true);
        $planner  = (int)get_post_meta($post_id,'_flp_planner_id', true);

        if ( $role === 'lighthouse_planner' && $planner === $user_id ) return true;
        if ( $role === 'lighthouse_parent'   && ( $owner === $user_id || $owner2_id === $user_id ) ) return true;

        // Delegated access check — formal delegated_users entries
        if ( $role === 'lighthouse_delegated' ) {
            $delegated = get_post_meta($post_id,'_flp_delegated_users',true) ?: [];
            foreach ( $delegated as $d ) {
                if ( (int)($d['user_id'] ?? 0) !== $user_id ) continue;
                if ( ($d['status'] ?? '') !== 'active' ) {
                    $cond  = $d['condition_type'] ?? 'immediate';
                    $cdate = $d['condition_date'] ?? '';
                    if ( $cond === 'immediate' ) return true;
                    if ( $cond === 'date' && $cdate && strtotime($cdate) <= time() ) return true;
                    if ( $cond === 'manual' ) return false;
                    continue;
                }
                return true;
            }

            // Also check access_people entries (users added via Access & Unlock section)
            $access_people = get_post_meta($post_id,'_flp_access_people',true) ?: [];
            foreach ( $access_people as $p ) {
                if ( (int)($p['user_id'] ?? 0) !== $user_id ) continue;
                if ( ($p['status'] ?? '') === 'active' ) return true;
                // view_after_death stays pending until manually activated
                return false;
            }

            return false;
        }
        return false;
    }
}
