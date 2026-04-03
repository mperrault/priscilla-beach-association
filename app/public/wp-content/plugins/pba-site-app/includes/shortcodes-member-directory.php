<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'pba_register_member_directory_shortcode');

function pba_register_member_directory_shortcode() {
    add_shortcode('pba_member_directory', 'pba_render_member_directory_shortcode');
}

function pba_render_member_directory_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access this page.</p>';
    }

    $search = isset($_GET['directory_search']) ? sanitize_text_field(wp_unslash($_GET['directory_search'])) : '';

    $rows = pba_supabase_get('Person', array(
        'select' => 'person_id,household_id,first_name,last_name,email_address,status',
        'status' => 'eq.Active',
        'order'  => 'last_name.asc,first_name.asc',
    ));

    if (is_wp_error($rows)) {
        return '<p>Unable to load the member directory right now.</p>';
    }

    if (!is_array($rows)) {
        $rows = array();
    }

    $household_labels = pba_member_directory_get_household_labels($rows);
    $committee_labels = pba_member_directory_get_committee_labels($rows);

    if ($search !== '') {
        $needle = strtolower($search);

        $rows = array_values(array_filter($rows, function ($row) use ($needle, $household_labels, $committee_labels) {
            $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
            $haystack_parts = array(
                isset($row['first_name']) ? (string) $row['first_name'] : '',
                isset($row['last_name']) ? (string) $row['last_name'] : '',
                isset($row['email_address']) ? (string) $row['email_address'] : '',
                isset($household_labels[$person_id]) ? (string) $household_labels[$person_id] : '',
                isset($committee_labels[$person_id]) ? implode(' ', $committee_labels[$person_id]) : '',
            );

            $haystack = strtolower(trim(implode(' ', $haystack_parts)));
            return $haystack !== '' && strpos($haystack, $needle) !== false;
        }));
    }

    ob_start();
    ?>
    <style>
        .pba-member-directory-wrap {
            max-width: 1100px;
            margin: 0 auto;
        }

        .pba-member-directory-search {
            margin: 18px 0 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .pba-member-directory-search input[type="text"] {
            width: 360px;
            max-width: 100%;
            padding: 9px 10px;
        }

        .pba-member-directory-btn {
            display: inline-block;
            padding: 9px 14px;
            border: 1px solid #0d3b66;
            background: #0d3b66;
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
        }

        .pba-member-directory-btn.secondary {
            background: #fff;
            color: #0d3b66;
        }

        .pba-member-directory-meta {
            margin-bottom: 14px;
            color: #666;
            font-size: 14px;
        }

        .pba-member-directory-table {
            width: 100%;
            border-collapse: collapse;
        }

        .pba-member-directory-table th,
        .pba-member-directory-table td {
            border: 1px solid #d7d7d7;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }

        .pba-member-directory-table th {
            background: #f3f3f3;
        }

        .pba-member-directory-muted {
            color: #666;
            font-size: 13px;
        }

        @media (max-width: 700px) {
            .pba-member-directory-table,
            .pba-member-directory-table thead,
            .pba-member-directory-table tbody,
            .pba-member-directory-table th,
            .pba-member-directory-table td,
            .pba-member-directory-table tr {
                display: block;
            }

            .pba-member-directory-table thead {
                display: none;
            }

            .pba-member-directory-table tr {
                margin-bottom: 14px;
                border: 1px solid #d7d7d7;
            }

            .pba-member-directory-table td {
                border: 0;
                border-bottom: 1px solid #eee;
            }

            .pba-member-directory-table td:last-child {
                border-bottom: 0;
            }
        }
    </style>

    <div class="pba-member-directory-wrap">
        <!-- h2>Member Directory</h2 -->
        <p>Browse active PBA members and their household and committee information.</p>

        <form method="get" class="pba-member-directory-search">
            <input
                type="text"
                name="directory_search"
                value="<?php echo esc_attr($search); ?>"
                placeholder="Search by name, email, household, or committee"
            >
            <button type="submit" class="pba-member-directory-btn secondary">Search</button>
            <?php if ($search !== '') : ?>
                <a class="pba-member-directory-btn secondary" href="<?php echo esc_url(home_url('/member-directory/')); ?>">Clear</a>
            <?php endif; ?>
        </form>

        <div class="pba-member-directory-meta">
            Showing <?php echo esc_html((string) count($rows)); ?> active member<?php echo count($rows) === 1 ? '' : 's'; ?>.
        </div>

        <table class="pba-member-directory-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Household</th>
                    <th>Committees</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)) : ?>
                    <tr>
                        <td colspan="4">No members found.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
                        $name = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
                        $email = isset($row['email_address']) ? trim((string) $row['email_address']) : '';
                        $household = isset($household_labels[$person_id]) ? $household_labels[$person_id] : '';
                        $committees = isset($committee_labels[$person_id]) ? $committee_labels[$person_id] : array();
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($name !== '' ? $name : 'Unnamed member'); ?></strong>
                            </td>
                            <td>
                                <?php if ($email !== '') : ?>
                                    <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                                <?php else : ?>
                                    <span class="pba-member-directory-muted">No email listed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html($household !== '' ? $household : ''); ?>
                            </td>
                            <td>
                                <?php if (!empty($committees)) : ?>
                                    <?php echo esc_html(implode(', ', $committees)); ?>
                                <?php else : ?>
                                    <span class="pba-member-directory-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php

    return ob_get_clean();
}

function pba_member_directory_get_household_labels($people_rows) {
    $household_ids = array();

    foreach ($people_rows as $row) {
        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;
        if ($household_id > 0) {
            $household_ids[] = $household_id;
        }
    }

    $household_ids = array_values(array_unique($household_ids));

    if (empty($household_ids)) {
        return array();
    }

    $household_rows = pba_supabase_get('Household', array(
        'select'       => 'household_id,pb_street_number,pb_street_name',
        'household_id' => 'in.(' . implode(',', array_map('intval', $household_ids)) . ')',
        'limit'        => count($household_ids),
    ));

    if (is_wp_error($household_rows) || !is_array($household_rows)) {
        return array();
    }

    $label_by_household_id = array();
    foreach ($household_rows as $row) {
        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;
        if ($household_id < 1) {
            continue;
        }

        $street_number = isset($row['pb_street_number']) ? trim((string) $row['pb_street_number']) : '';
        $street_name = isset($row['pb_street_name']) ? trim((string) $row['pb_street_name']) : '';
        $label_by_household_id[$household_id] = trim($street_number . ' ' . $street_name);
    }

    $labels_by_person_id = array();
    foreach ($people_rows as $row) {
        $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
        $household_id = isset($row['household_id']) ? (int) $row['household_id'] : 0;

        if ($person_id > 0 && $household_id > 0 && isset($label_by_household_id[$household_id])) {
            $labels_by_person_id[$person_id] = $label_by_household_id[$household_id];
        }
    }

    return $labels_by_person_id;
}

function pba_member_directory_get_committee_labels($people_rows) {
    $person_ids = array();

    foreach ($people_rows as $row) {
        $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
        if ($person_id > 0) {
            $person_ids[] = $person_id;
        }
    }

    $person_ids = array_values(array_unique($person_ids));

    if (empty($person_ids)) {
        return array();
    }

    $membership_rows = pba_supabase_get('Person_to_Committee', array(
        'select'    => 'person_id,committee_id,committee_role,is_active',
        'person_id' => 'in.(' . implode(',', array_map('intval', $person_ids)) . ')',
        'is_active' => 'eq.true',
        'limit'     => max(count($person_ids) * 5, count($person_ids)),
    ));

    if (is_wp_error($membership_rows) || !is_array($membership_rows) || empty($membership_rows)) {
        return array();
    }

    $committee_ids = array();
    foreach ($membership_rows as $row) {
        $committee_id = isset($row['committee_id']) ? (int) $row['committee_id'] : 0;
        if ($committee_id > 0) {
            $committee_ids[] = $committee_id;
        }
    }

    $committee_ids = array_values(array_unique($committee_ids));
    if (empty($committee_ids)) {
        return array();
    }

    $committee_rows = pba_supabase_get('Committee', array(
        'select'       => 'committee_id,committee_name,status',
        'committee_id' => 'in.(' . implode(',', array_map('intval', $committee_ids)) . ')',
        'limit'        => count($committee_ids),
    ));

    if (is_wp_error($committee_rows) || !is_array($committee_rows)) {
        return array();
    }

    $committee_names = array();
    foreach ($committee_rows as $row) {
        $committee_id = isset($row['committee_id']) ? (int) $row['committee_id'] : 0;
        $committee_name = isset($row['committee_name']) ? trim((string) $row['committee_name']) : '';
        $status = isset($row['status']) ? trim((string) $row['status']) : '';

        if ($committee_id > 0 && $committee_name !== '' && ($status === '' || $status === 'Active')) {
            $committee_names[$committee_id] = $committee_name;
        }
    }

    $labels_by_person_id = array();
    foreach ($membership_rows as $row) {
        $person_id = isset($row['person_id']) ? (int) $row['person_id'] : 0;
        $committee_id = isset($row['committee_id']) ? (int) $row['committee_id'] : 0;

        if ($person_id < 1 || $committee_id < 1 || !isset($committee_names[$committee_id])) {
            continue;
        }

        $label = $committee_names[$committee_id];
        if (!empty($row['committee_role'])) {
            $label .= ' (' . trim((string) $row['committee_role']) . ')';
        }

        if (!isset($labels_by_person_id[$person_id])) {
            $labels_by_person_id[$person_id] = array();
        }

        $labels_by_person_id[$person_id][] = $label;
    }

    foreach ($labels_by_person_id as $person_id => $labels) {
        $labels = array_values(array_unique($labels));
        natcasesort($labels);
        $labels_by_person_id[$person_id] = array_values($labels);
    }

    return $labels_by_person_id;
}
