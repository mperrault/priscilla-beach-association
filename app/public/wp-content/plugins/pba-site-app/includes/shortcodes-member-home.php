<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'pba_register_member_home_shortcode');

function pba_register_member_home_shortcode() {
    add_shortcode('pba_member_home', 'pba_render_member_home_shortcode');
}

function pba_render_member_home_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access this page.</p>';
    }

    $person = pba_get_current_person_record();
    $role_names = pba_get_current_person_role_names();

    $first_name = '';
    if (is_array($person) && !empty($person['first_name'])) {
        $first_name = (string) $person['first_name'];
    } else {
        $first_name = pba_get_welcome_name();
    }

    $cards = array();

    $cards[] = array(
        'title' => 'Calendar',
        'description' => 'View upcoming association events and activities.',
        'url' => home_url('/calendar/'),
    );

    $cards[] = array(
        'title' => 'Member Directory',
        'description' => 'Browse the member directory.',
        'url' => home_url('/member-directory/'),
    );

    if (pba_current_user_has_house_admin_access()) {
        $cards[] = array(
            'title' => 'My Household',
            'description' => 'Manage my household invitations and member status.',
            'url' => home_url('/my-household/'),
        );
    }

    if (in_array('PBABoardMember', $role_names, true) || in_array('PBAAdmin', $role_names, true)) {
        $cards[] = array(
            'title' => 'Board Documents',
            'description' => 'Access agendas, minutes, and board working documents.',
            'url' => home_url('/board-documents/'),
        );
    }

    if (in_array('PBACommitteeMember', $role_names, true) || in_array('PBAAdmin', $role_names, true)) {
        $cards[] = array(
            'title' => 'Committee Documents',
            'description' => 'Access folders and documents for your committee work.',
            'url' => home_url('/committee-documents/'),
        );
    }

    if (in_array('PBAAdmin', $role_names, true)) {
        $cards[] = array(
            'title' => 'Members',
            'description' => 'Manage member records, roles, and committee assignments.',
            'url' => home_url('/members/'),
        );

        $cards[] = array(
            'title' => 'Committees',
            'description' => 'Manage committees and committee rosters.',
            'url' => home_url('/committees/'),
        );
    }

    ob_start();
    ?>
    <style>
        .pba-member-home-wrap {
            max-width: 1100px;
            margin: 0 auto;
        }

        .pba-member-home-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
            margin-top: 24px;
        }

        .pba-member-home-card {
            border: 1px solid #d7d7d7;
            border-radius: 8px;
            padding: 18px;
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .pba-member-home-card h3 {
            margin: 0 0 10px;
            font-size: 20px;
        }

        .pba-member-home-card p {
            margin: 0 0 16px;
            color: #555;
        }

        .pba-member-home-btn {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 4px;
            background: #0d3b66;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }

        .pba-member-home-btn:hover {
            background: #0b3154;
            color: #fff;
        }

        .pba-member-home-roles {
            margin-top: 10px;
            color: #666;
            font-size: 14px;
        }
    </style>

    <div class="pba-member-home-wrap">
        <h2>Welcome<?php echo $first_name !== '' ? ', ' . esc_html($first_name) : ''; ?></h2>
        <p>Select a section below to get started.</p>

        <?php if (!empty($role_names)) : ?>
            <div class="pba-member-home-roles">
                Your PBA roles: <?php echo esc_html(implode(', ', $role_names)); ?>
            </div>
        <?php endif; ?>

        <div class="pba-member-home-grid">
            <?php foreach ($cards as $card) : ?>
                <div class="pba-member-home-card">
                    <h3><?php echo esc_html($card['title']); ?></h3>
                    <p><?php echo esc_html($card['description']); ?></p>
                    <a class="pba-member-home-btn" href="<?php echo esc_url($card['url']); ?>">Open</a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}