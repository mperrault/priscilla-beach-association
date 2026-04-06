<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'pba_register_meeting_info_shortcode');

function pba_register_meeting_info_shortcode() {
    add_shortcode('pba_meeting_info', 'pba_render_meeting_info_shortcode');
}

function pba_render_meeting_info_shortcode() {
    ob_start();
    ?>
    <style>
        .pba-meeting-info {
            max-width: 1000px;
            margin: 0 auto;
        }

        .pba-meeting-section-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
            margin-top: 24px;
        }

        .pba-meeting-section-item {
            border: 1px solid #d7d7d7;
            border-radius: 8px;
            padding: 18px;
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
        }

        .pba-meeting-section-item h3 {
            margin: 0 0 10px;
            font-size: 20px;
        }

        .pba-meeting-section-item p {
            margin: 0 0 14px;
            color: #555;
        }

        .pba-meeting-section-item a {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 4px;
            background: #0d3b66;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            margin-top: auto;
            align-self: flex-start;
        }

        .pba-meeting-section-item a:hover {
            background: #0b3154;
            color: #fff;
        }

        .pba-meeting-placeholder {
            color: #666;
            font-style: italic;
            margin-top: auto;
        }
    </style>

    <div class="pba-meeting-info">
        <!-- h2>Meeting Information</h2 -->
        <p>
            This page provides important information and materials for upcoming Association meetings.
            Members can use this page to find notices, agendas, approved minutes, and meeting-related forms.
        </p>

        <p>
            Additional meeting details and documents will be added here over time as they become available.
        </p>

        <div class="pba-meeting-section-list">
            <div class="pba-meeting-section-item">
                <h3>Upcoming Meetings</h3>
                <p>
                    Information about the next Annual Meeting, Special Meetings, and other important member meetings
                    will be posted here.
                </p>
                <div class="pba-meeting-placeholder">
                    Upcoming meeting details are not currently available.
                </div>
            </div>

            <div class="pba-meeting-section-item">
                <h3>Meeting Agenda</h3>
                <p>
                    Review the agenda for the next posted meeting when available.
                </p>
                <div class="pba-meeting-placeholder">
                    The current meeting agenda is not currently available.
                </div>
            </div>

            <div class="pba-meeting-section-item">
                <h3>Approved Minutes</h3>
                <p>
                    Approved meeting minutes will be posted here for member reference.
                </p>
                <div class="pba-meeting-placeholder">
                    Approved meeting minutes are not currently available.
                </div>
            </div>

            <div class="pba-meeting-section-item">
                <h3>Proxy Form</h3>
                <p>
                    If proxy voting materials are needed for an upcoming meeting, they will be posted here.
                </p>
                <div class="pba-meeting-placeholder">
                    A proxy form is not currently available.
                </div>
            </div>
        </div>
    </div>
    <?php

    return ob_get_clean();
}