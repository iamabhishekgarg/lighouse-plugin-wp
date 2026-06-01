<?php
class LH_CPT {
    static function register() {
        $show_ui   = current_user_can('administrator');
        $show_menu = current_user_can('administrator');

        register_post_type( 'lh_record', [
            'label'           => 'Planners',
            'public'          => false,
            'show_ui'         => $show_ui,
            'show_in_menu'    => $show_menu,
            'menu_icon'       => 'dashicons-briefcase',
            'menu_position'   => 25,
            'supports'        => [ 'title', 'author' ],
            'capability_type' => 'post',
            'map_meta_cap'    => true,
            'rewrite'         => false,
            'labels'          => [
                'name'          => 'Planners',
                'singular_name' => 'Planner Record',
                'menu_name'     => 'Planners',
                'all_items'     => 'All Planners',
                'add_new'       => 'Add Record',
                'add_new_item'  => 'Add New Record',
                'edit_item'     => 'Edit Record',
                'view_item'     => 'View Record',
                'search_items'  => 'Search Planners',
                'not_found'     => 'No planners found.',
            ],
        ]);

        register_post_type( 'lh_client', [
            'label'           => 'Clients',
            'public'          => false,
            'show_ui'         => $show_ui,
            'show_in_menu'    => 'edit.php?post_type=lh_record',
            'supports'        => [ 'title', 'author' ],
            'capability_type' => 'post',
            'map_meta_cap'    => true,
            'menu_icon'       => 'dashicons-groups',
            'rewrite'         => false,
            'labels'          => [
                'name'          => 'Clients',
                'singular_name' => 'Client',
                'menu_name'     => 'Clients',
                'all_items'     => 'All Clients',
                'add_new'       => 'Add Client',
                'add_new_item'  => 'Add New Client',
                'edit_item'     => 'Edit Client',
                'view_item'     => 'View Client',
                'search_items'  => 'Search Clients',
                'not_found'     => 'No clients found.',
            ],
        ]);
    }
}
