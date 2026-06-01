<?php
class LH_Roles {
    static function register() {
        $defs = [
            'lighthouse_planner'  => 'Estate Planner',
            'lighthouse_parent'    => 'Parent / Family Member',
            'lighthouse_law_firm'  => 'Law Firm',
            'lighthouse_delegated' => 'Delegated User',
            'lhp_super_admin'      => 'Lighthouse Super Admin',
        ];
        foreach ( $defs as $key => $label ) {
            if ( ! get_role( $key ) ) {
                $caps = [ 'read' => true ];
                if ( $key === 'lhp_super_admin' ) {
                    $caps = array_merge( $caps, [
                        'lhp_manage_platform'  => true,
                        'lhp_view_all_records' => true,
                        'lhp_manage_users'     => true,
                    ]);
                }
                add_role( $key, $label, $caps );
            }
        }
    }
}
