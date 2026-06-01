<?php
class LH_Shortcodes {
    static function init() {
        add_shortcode( 'lhp_login',               [ __CLASS__, 'login_page'            ] );
        add_shortcode( 'lhp_register',            [ __CLASS__, 'register_page'         ] );
        add_shortcode( 'lhp_planner_dashboard',   [ __CLASS__, 'planner_dashboard'     ] );
        add_shortcode( 'lhp_parent_dashboard',    [ __CLASS__, 'parent_dashboard'      ] );
        add_shortcode( 'lhp_admin_dashboard',     [ __CLASS__, 'admin_dashboard'       ] );
        add_shortcode( 'lhp_delegated_dashboard', [ __CLASS__, 'delegated_dashboard'   ] );
        add_shortcode( 'lhp_planner_form',        [ __CLASS__, 'planner_form'          ] );
        // BC aliases — old shortcodes still work
        add_shortcode( 'lhp_attorney_dashboard',  [ __CLASS__, 'planner_dashboard'     ] );
        add_shortcode( 'lhp_attorney_form',       [ __CLASS__, 'planner_form'          ] );
    }
    static function login_page()          { ob_start(); include LHP_DIR . 'templates/tmpl-login.php';              return ob_get_clean(); }
    static function register_page()       { ob_start(); include LHP_DIR . 'templates/tmpl-register.php';           return ob_get_clean(); }
    static function planner_dashboard()   { ob_start(); include LHP_DIR . 'templates/tmpl-planner-dashboard.php'; return ob_get_clean(); }
    static function parent_dashboard()    { ob_start(); include LHP_DIR . 'templates/tmpl-parent-dashboard.php';   return ob_get_clean(); }
    static function admin_dashboard()     { ob_start(); include LHP_DIR . 'templates/tmpl-admin-dashboard.php';    return ob_get_clean(); }
    static function delegated_dashboard() { ob_start(); include LHP_DIR . 'templates/tmpl-delegated-dashboard.php'; return ob_get_clean(); }
    static function planner_form()        { ob_start(); include LHP_DIR . 'templates/tmpl-planner-form.php';        return ob_get_clean(); }
}
