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

    if (
        (function_exists('pba_current_person_has_role') && pba_current_person_has_role('PBABoardMember')) ||
        (function_exists('pba_current_person_has_role') && pba_current_person_has_role('PBAAdmin'))
    ) {
        $cards[] = array(
            'title' => 'Household Map',
            'description' => 'Browse PBA households on a neighborhood map.',
            'url' => home_url('/household-map/'),
        );
    }

    if (function_exists('pba_current_person_can_view_member_resources') && pba_current_person_can_view_member_resources()) {
        $cards[] = array(
            'title' => 'Member Resources',
            'description' => 'View documents and resources shared with all members by the Board and committees.',
            'url' => home_url('/member-resources/'),
        );
    }

    if (pba_current_user_has_house_admin_access()) {
        $cards[] = array(
            'title' => 'My Household',
            'description' => 'Manage my household invitations and member status.',
            'url' => home_url('/my-household/'),
        );
    }

    if (
        (function_exists('pba_current_person_has_role') && pba_current_person_has_role('pba_board_member')) ||
        (function_exists('pba_current_person_has_role') && pba_current_person_has_role('pba_admin')) ||
        (function_exists('pba_current_person_has_role') && pba_current_person_has_role('PBABoardMember')) ||
        (function_exists('pba_current_person_has_role') && pba_current_person_has_role('PBAAdmin'))
    ) {
        $cards[] = array(
            'title' => 'Board Documents',
            'description' => 'Access agendas, minutes, and board working documents.',
            'url' => home_url('/board-documents/'),
        );
    }

    if (
        (function_exists('pba_current_person_has_role') && pba_current_person_has_role('pba_committee_member')) ||
        (function_exists('pba_current_person_has_role') && pba_current_person_has_role('pba_admin')) ||
        (function_exists('pba_current_person_has_role') && pba_current_person_has_role('PBACommitteeMember')) ||
        (function_exists('pba_current_person_has_role') && pba_current_person_has_role('PBAAdmin'))
    ) {
        $cards[] = array(
            'title' => 'Committee Documents',
            'description' => 'Access folders and documents for your committee work.',
            'url' => home_url('/committee-documents/'),
        );
    }

    if (
        pba_current_user_has_pba_admin_access()
        || (function_exists('pba_current_person_has_role') && pba_current_person_has_role('pba_admin'))
        || (function_exists('pba_current_person_has_role') && pba_current_person_has_role('PBAAdmin'))
    ) {
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

        $cards[] = array(
            'title' => 'Audit Log',
            'description' => 'Review audited user actions and investigate changes across the application.',
            'url' => home_url('/audit-log/'),
        );
    }

    $welcome_title = 'Welcome' . ($first_name !== '' ? ', ' . $first_name : '');

    ob_start();

    if (function_exists('pba_shared_list_ui_render_styles')) {
        echo pba_shared_list_ui_render_styles();
    }
    ?>
    <div class="pba-page-wrap pba-member-home-wrap">
        <?php
        if (function_exists('pba_shared_render_page_hero')) {
            echo pba_shared_render_page_hero('Member Home', $welcome_title, 'Select a section below to get started.');
        } else {
            ?>
            <div class="pba-page-hero">
                <div class="pba-page-eyebrow">Member Home</div>
                <h2 class="pba-page-title"><?php echo esc_html($welcome_title); ?></h2>
                <p class="pba-page-intro">Select a section below to get started.</p>
            </div>
            <?php
        }
        ?>

        <div class="pba-home-card-grid">
            <?php foreach ($cards as $card) : ?>
                <div class="pba-section pba-home-card">
                    <h3 class="pba-home-card-title"><?php echo esc_html($card['title']); ?></h3>
                    <p class="pba-home-card-text"><?php echo esc_html($card['description']); ?></p>
                    <div class="pba-home-card-actions">
                        <a class="pba-btn" href="<?php echo esc_url($card['url']); ?>">Open</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}