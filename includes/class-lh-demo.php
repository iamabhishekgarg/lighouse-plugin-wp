<?php
class LH_Demo {
    static function init() {
        add_action( 'wp_ajax_lhp_seed_demo_data',  [ __CLASS__, 'lhp_seed_demo_data' ] );
        add_action( 'wp_ajax_lhp_clear_demo_data', [ __CLASS__, 'lhp_clear_demo_data' ] );
    }

    static function lhp_seed_demo_data() {
        LH_Ajax::verify();
        $role = LH_Auth::current_role();
        $allowed = ['administrator', 'lhp_super_admin', 'lighthouse_law_firm'];
        if (!current_user_can('administrator') && !in_array($role, $allowed))
            wp_send_json_error('Only platform administrators can seed demo data.');

        $created = ['planners' => 0, 'clients' => 0, 'records' => 0, 'parents' => 0];
        $accounts = [];

        /* ── 1. Create Demo Planner ── */
        $planner_email = 'demo.planner@lighthouse.test';
        $existing_planner = get_user_by('email', $planner_email);
        if ($existing_planner) {
            $planner_id = $existing_planner->ID;
        } else {
            $planner_id = wp_insert_user([
                'user_login' => $planner_email,
                'user_email' => $planner_email,
                'user_pass' => 'Demo@1234',
                'display_name' => 'Sarah Mitchell',
                'role' => 'lighthouse_planner',
            ]);
            if (is_wp_error($planner_id))
                wp_send_json_error('Failed to create planner: ' . $planner_id->get_error_message());
            update_user_meta($planner_id, '_flp_firm_name', 'Mitchell & Associates Law');
            update_user_meta($planner_id, '_flp_phone', '+12125550100');
            update_user_meta($planner_id, '_flp_internal_ref', 'PLN-2024-001');
            $created['planners']++;
        }
        $accounts[] = [
            'role' => 'Estate Planner',
            'name' => 'Sarah Mitchell',
            'email' => $planner_email,
            'password' => 'Demo@1234',
            'url' => home_url('/planner-dashboard'),
            'note' => 'Has 4 clients with 3 full Lighthouse records',
        ];

        /* ── 2. Clients + Records ── */
        $clients_data = [
            [
                'client' => [
                    'name' => 'Margaret Williams',
                    'email' => 'margaret.w@demo.test',
                    'phone' => '+13035550101',
                    'relationship' => 'Parent',
                    'notes' => 'Long-time client. Estate planning in progress.',
                ],
                'record' => [
                    'subject' => [
                        'full_name' => 'Eleanor Williams',
                        'preferred_name' => 'Ellie',
                        'dob' => '1942-03-15',
                        'address' => '42 Maple Street, Denver, CO 80203',
                        'email' => 'eleanor@demo.test',
                        'phone' => '+13035550155',
                    ],
                    'children' => [
                        ['full_name' => 'Sarah Williams', 'relationship' => 'Daughter', 'email' => 'sarah.w@demo.test', 'phone' => '+13035550200'],
                        ['full_name' => 'Tom Williams', 'relationship' => 'Son', 'email' => 'tom.w@demo.test', 'phone' => '+13035550201'],
                    ],
                    'access_people' => [
                        ['full_name' => 'Margaret Williams', 'email' => 'margaret.w@demo.test', 'phone' => '+13035550101'],
                    ],
                    'access_grant' => 'after_death',
                    'personal_items' => [
                        ['description' => 'Gold wedding ring', 'recipient' => 'Sarah Williams', 'notes' => 'Family heirloom, 3 generations old'],
                        ['description' => 'Antique mahogany desk', 'recipient' => 'Tom Williams', 'notes' => "From grandfather's study"],
                        ['description' => 'Pearl necklace set', 'recipient' => 'Sarah Williams', 'notes' => 'Worn at every family celebration'],
                    ],
                    'burial' => [
                        'preference' => 'cremation',
                        'requests' => 'Scatter ashes at Lookout Mountain. Simple family gathering only.',
                        'funeral_home' => 'Green Meadows Memorial',
                    ],
                    'bank_accounts' => [
                        ['institution' => 'Chase Bank', 'account_type' => 'Checking', 'last_four' => '4521'],
                        ['institution' => 'Fidelity', 'account_type' => 'Savings', 'last_four' => '8832'],
                    ],
                    'life_insurance' => [
                        ['provider' => 'MetLife', 'policy' => 'ML-4892-XX', 'notes' => 'Beneficiary: Margaret Williams'],
                    ],
                    'letters' => [
                        ['recipient' => 'Sarah Williams', 'content' => 'My dearest Sarah, You have been my sunshine since the day you were born. Courage is not the absence of fear — it is deciding something else matters more. I love you always. — Mom'],
                        ['recipient' => 'Tom Williams', 'content' => 'My dear Tom, Life moves faster than we expect. Take care of your sister and always make time for what truly matters. I am so proud of you. All my love. — Mom'],
                    ],
                    'status' => 'complete',
                    'completion' => 100,
                ],
            ],
            [
                'client' => [
                    'name' => 'Robert Chen',
                    'email' => 'robert.chen@demo.test',
                    'phone' => '+14155550182',
                    'relationship' => 'Spouse',
                    'notes' => 'Business owner. Multiple assets to manage.',
                ],
                'record' => [
                    'subject' => [
                        'full_name' => 'David Chen',
                        'preferred_name' => 'Dave',
                        'dob' => '1948-07-22',
                        'address' => '18 Harbor View, San Francisco, CA 94102',
                        'email' => 'david.c@demo.test',
                        'phone' => '+14155550177',
                    ],
                    'children' => [
                        ['full_name' => 'Amy Chen', 'relationship' => 'Daughter', 'email' => 'amy.c@demo.test', 'phone' => '+14155550210'],
                        ['full_name' => 'Kevin Chen', 'relationship' => 'Son', 'email' => 'kevin.c@demo.test', 'phone' => '+14155550211'],
                        ['full_name' => 'Lisa Chen', 'relationship' => 'Daughter', 'email' => 'lisa.c@demo.test', 'phone' => '+14155550212'],
                    ],
                    'access_people' => [
                        ['full_name' => 'Robert Chen', 'email' => 'robert.chen@demo.test', 'phone' => '+14155550182'],
                        ['full_name' => 'Dr. Paul Wong', 'email' => 'pwong@demolaw.test', 'phone' => '+14155550300'],
                    ],
                    'access_grant' => 'authorized_by_planner',
                    'personal_items' => [
                        ['description' => '1968 Rolex Submariner', 'recipient' => 'Kevin Chen', 'notes' => 'First purchase after founding the business'],
                        ['description' => 'Chess set collection', 'recipient' => 'Amy Chen', 'notes' => '28 antique sets from around the world'],
                    ],
                    'burial' => [
                        'preference' => 'burial',
                        'requests' => 'Traditional ceremony. Buried at Colma Cemetery alongside parents.',
                        'funeral_home' => 'Cypress Lawn Memorial Park',
                    ],
                    'bank_accounts' => [
                        ['institution' => 'Bank of America', 'account_type' => 'Checking', 'last_four' => '7734'],
                        ['institution' => 'Wells Fargo', 'account_type' => 'Savings', 'last_four' => '2219'],
                        ['institution' => 'Charles Schwab', 'account_type' => 'Other', 'last_four' => '6601'],
                    ],
                    'life_insurance' => [
                        ['provider' => 'Northwestern Mutual', 'policy' => 'NM-88741', 'notes' => '$2M policy — beneficiary: Robert Chen'],
                        ['provider' => 'Prudential', 'policy' => 'PRU-4412-B', 'notes' => 'Term life, expires 2029'],
                    ],
                    'letters' => [
                        ['recipient' => 'Amy Chen', 'content' => "Amy, your determination reminds me of your grandmother. Run toward what excites you. I love you. — Dad"],
                        ['recipient' => 'Kevin Chen', 'content' => "Kevin, lead with kindness. The business is built on relationships, not numbers. Take care of your sisters. — Dad"],
                        ['recipient' => 'Lisa Chen', 'content' => "My youngest, you were the unexpected gift that completed our family. Never stop painting. — Dad"],
                    ],
                    'status' => 'complete',
                    'completion' => 100,
                ],
            ],
            [
                'client' => [
                    'name' => 'Sandra Patel',
                    'email' => 'sandra.patel@demo.test',
                    'phone' => '+17185550143',
                    'relationship' => 'Adult Child',
                    'notes' => 'Filing on behalf of elderly parent. Partial data collected.',
                ],
                'record' => [
                    'subject' => [
                        'full_name' => 'Patricia Patel',
                        'preferred_name' => 'Pat',
                        'dob' => '1951-11-08',
                        'address' => '7 Sunrise Lane, Austin, TX 78701',
                        'email' => 'patricia.p@demo.test',
                        'phone' => '+15125550188',
                    ],
                    'children' => [
                        ['full_name' => 'Raj Patel', 'relationship' => 'Son', 'email' => 'raj.p@demo.test', 'phone' => '+15125550220'],
                        ['full_name' => 'Priya Sharma', 'relationship' => 'Daughter', 'email' => 'priya.s@demo.test', 'phone' => '+15125550221'],
                    ],
                    'access_people' => [
                        ['full_name' => 'Sandra Patel', 'email' => 'sandra.patel@demo.test', 'phone' => '+17185550143'],
                    ],
                    'access_grant' => 'after_death',
                    'personal_items' => [
                        ['description' => '22-carat gold bangles (12 pieces)', 'recipient' => 'Priya Sharma', 'notes' => 'Wedding gift from mother-in-law'],
                        ['description' => 'Handwritten recipe collection', 'recipient' => 'Raj Patel', 'notes' => '200+ family recipes, some 100 years old'],
                    ],
                    'burial' => [
                        'preference' => 'cremation',
                        'requests' => 'Hindu ceremony. Ashes to be scattered in River Ganga, Varanasi.',
                        'funeral_home' => '',
                    ],
                    'bank_accounts' => [],
                    'life_insurance' => [],
                    'letters' => [],
                    'status' => 'draft',
                    'completion' => 65,
                ],
            ],
            [
                'client' => [
                    'name' => "James O'Brien",
                    'email' => 'james.obrien@demo.test',
                    'phone' => '+16465550194',
                    'relationship' => 'Parent',
                    'notes' => 'New client. Initial consultation completed.',
                ],
                'record' => null,
            ],
        ];

        foreach ($clients_data as $entry) {
            $c = $entry['client'];
            $cid = wp_insert_post([
                'post_type' => 'lh_client',
                'post_title' => $c['name'],
                'post_status' => 'publish',
                'post_author' => $planner_id,
            ]);
            update_post_meta($cid, '_flp_planner_id', $planner_id);
            update_post_meta($cid, '_flp_email', $c['email']);
            update_post_meta($cid, '_flp_phone', $c['phone']);
            update_post_meta($cid, '_flp_relationship', $c['relationship']);
            update_post_meta($cid, '_flp_notes', $c['notes']);
            $created['clients']++;

            if (!empty($entry['record'])) {
                $rec = $entry['record'];
                $sub = $rec['subject'];
                $rid = wp_insert_post([
                    'post_type' => 'lh_record',
                    'post_title' => $sub['full_name'] . "'s Lighthouse",
                    'post_status' => 'publish',
                    'post_author' => $planner_id,
                ]);
                update_post_meta($rid, '_flp_client_id', $cid);
                update_post_meta($rid, '_flp_planner_id', $planner_id);
                update_post_meta($rid, '_flp_owner_id', 0);
                update_post_meta($rid, '_flp_subject', $sub);
                update_post_meta($rid, '_flp_children', $rec['children'] ?? []);
                update_post_meta($rid, '_flp_access_people', $rec['access_people'] ?? []);
                update_post_meta($rid, '_flp_access_grant', $rec['access_grant'] ?? '');
                update_post_meta($rid, '_flp_personal_items', $rec['personal_items'] ?? []);
                update_post_meta($rid, '_flp_burial', $rec['burial'] ?? []);
                update_post_meta($rid, '_flp_bank_accounts', $rec['bank_accounts'] ?? []);
                update_post_meta($rid, '_flp_life_insurance', $rec['life_insurance'] ?? []);
                update_post_meta($rid, '_flp_letters', $rec['letters'] ?? []);
                update_post_meta($rid, '_flp_status', $rec['status']);
                update_post_meta($rid, '_flp_completion', $rec['completion']);
                $created['records']++;
            }
        }

        /* ── 3. Demo Parent ── */
        $parent_email = 'demo.parent@lighthouse.test';
        $existing_par = get_user_by('email', $parent_email);
        if (!$existing_par) {
            $parent_id = wp_insert_user([
                'user_login' => $parent_email,
                'user_email' => $parent_email,
                'user_pass' => 'Demo@1234',
                'display_name' => "James O'Brien Sr.",
                'role' => 'lighthouse_parent',
            ]);
            update_user_meta($parent_id, '_flp_phone', '+16465550194');
            update_user_meta($parent_id, '_flp_relationship', 'Parent');

            $prid = wp_insert_post([
                'post_type' => 'lh_record',
                'post_title' => "James O'Brien Sr.'s Lighthouse",
                'post_status' => 'publish',
                'post_author' => $parent_id,
            ]);
            update_post_meta($prid, '_flp_owner_id', $parent_id);
            update_post_meta($prid, '_flp_planner_id', $planner_id);
            update_post_meta($prid, '_flp_status', 'draft');
            update_post_meta($prid, '_flp_completion', 40);
            update_post_meta($prid, '_flp_subject', [
                'full_name' => "James O'Brien Sr.",
                'preferred_name' => 'Jim',
                'dob' => '1939-09-01',
                'address' => '55 Park Ave, New York, NY 10022',
                'email' => $parent_email,
                'phone' => '+16465550194',
            ]);
            update_post_meta($prid, '_flp_children', [
                ['full_name' => "James O'Brien Jr.", 'relationship' => 'Son', 'email' => 'james.ob@demo.test', 'phone' => '+16465550194'],
                ['full_name' => 'Colleen Murphy', 'relationship' => 'Daughter', 'email' => 'colleen.m@demo.test', 'phone' => '+16465550195'],
            ]);
            update_post_meta($prid, '_flp_access_grant', 'after_death');
            update_user_meta($parent_id, '_flp_record_id', $prid);
            $created['parents']++;

            $accounts[] = [
                'role' => 'Parent / Family Member',
                'name' => "James O'Brien Sr.",
                'email' => $parent_email,
                'password' => 'Demo@1234',
                'url' => home_url('/dashboard'),
                'note' => 'Has 1 draft Lighthouse (40% complete)',
            ];
        }

        /* ── 4. Demo Delegated User ── */
        $del_email = 'demo.delegated@lighthouse.test';
        if (!get_user_by('email', $del_email)) {
            $del_id = wp_insert_user([
                'user_login' => $del_email,
                'user_email' => $del_email,
                'user_pass' => 'Demo@1234',
                'display_name' => 'Colleen Murphy',
                'role' => 'lighthouse_delegated',
            ]);
            if (isset($prid)) {
                $delegated_entry = [
                    'id' => 'demo_del_001',
                    'user_id' => $del_id,
                    'name' => 'Colleen Murphy',
                    'email' => $del_email,
                    'relationship' => 'Daughter',
                    'condition_type' => 'immediate',
                    'condition_date' => '',
                    'condition_event' => '',
                    'status' => 'active',
                    'added_date' => date('M j, Y'),
                    'added_by' => 'Demo Setup',
                ];
                update_post_meta($prid, '_flp_delegated_users', [$delegated_entry]);
                $del_records = [$prid];
                update_user_meta($del_id, '_flp_delegated_record_ids', $del_records);
            }
            $accounts[] = [
                'role' => 'Delegated User',
                'name' => 'Colleen Murphy',
                'email' => $del_email,
                'password' => 'Demo@1234',
                'url' => home_url('/delegated-dashboard'),
                'note' => 'View-only access to parent record',
            ];
        }

        LH_Ajax::log_activity('demo_seeded', 'Full demo suite created: ' . $created['clients'] . ' clients, ' . $created['records'] . ' records.');

        wp_send_json_success([
            'message' => 'Demo suite ready! ' . $created['planners'] . ' planner, ' . $created['clients'] . ' clients, ' . $created['records'] . ' records, ' . $created['parents'] . ' parent, 1 delegated user.',
            'counts' => $created,
            'accounts' => $accounts,
        ]);
    }

    static function lhp_clear_demo_data() {
        LH_Ajax::verify();
        $role = LH_Auth::current_role();
        if (!current_user_can('administrator') && !in_array($role, ['lhp_super_admin', 'lighthouse_law_firm']))
            wp_send_json_error('Only platform administrators can clear demo data.');

        $types = ['lh_client', 'lh_record'];
        $total = 0;
        foreach ($types as $t) {
            $ids = get_posts(['post_type' => $t, 'posts_per_page' => -1, 'fields' => 'ids', 'post_status' => 'any']);
            foreach ($ids as $id) {
                wp_delete_post($id, true);
                $total++;
            }
        }
        $demo_emails = [
            'demo.planner@lighthouse.test',
            'demo.parent@lighthouse.test',
            'demo.delegated@lighthouse.test',
        ];
        $users_del = 0;
        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ($demo_emails as $em) {
            $u = get_user_by('email', $em);
            if ($u) {
                wp_delete_user($u->ID);
                $users_del++;
            }
        }
        wp_send_json_success('Cleared: ' . $total . ' records/clients, ' . $users_del . ' demo accounts deleted.');
    }
}
