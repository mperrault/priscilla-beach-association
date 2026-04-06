<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'pba_register_governing_documents_shortcode');

function pba_register_governing_documents_shortcode() {
    add_shortcode('pba_governing_documents', 'pba_render_governing_documents_shortcode');
}

function pba_render_governing_documents_shortcode() {
    ob_start();
    ?>
    <style>
        .pba-governing-docs {
            max-width: 1000px;
            margin: 0 auto;
        }

        .pba-governing-doc-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
            margin-top: 24px;
        }

        .pba-governing-doc-item {
            border: 1px solid #d7d7d7;
            border-radius: 8px;
            padding: 18px;
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
        }

        .pba-governing-doc-item h3 {
            margin: 0 0 10px;
            font-size: 20px;
        }

        .pba-governing-doc-item p {
            margin: 0 0 14px;
            color: #555;
        }

        .pba-governing-doc-item a {
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

        .pba-governing-doc-item a:hover {
            background: #0b3154;
            color: #fff;
        }
    </style>

    <div class="pba-governing-docs">
        <!-- h2>Governing Documents</h2 -->
        <p>
            This page provides access to key association reference documents for Priscilla Beach Association members.
            These documents describe important policies, agreements, and governing rules for use of association facilities
            and member responsibilities.
        </p>

        <p>
            Please review these documents carefully before using association facilities or submitting related requests.
            Additional governing materials may be added here over time.
        </p>

        <div class="pba-governing-doc-list">
            <div class="pba-governing-doc-item">
                <h3>Clubhouse Rental Agreement</h3>
                <p>
                    Review the rental terms, conditions, and responsibilities for use of the Association Club House.
                </p>
                <a href="<?php echo esc_url(home_url('/wp-content/uploads/2023/05/PBA_Rental_Agreement_2019.pdf')); ?>" target="_blank" rel="noopener">
                    View Rental Agreement
                </a>
            </div>

            <div class="pba-governing-doc-item">
                <h3>Clubhouse Liability Agreement</h3>
                <p>
                    Review the liability agreement associated with clubhouse use.
                </p>
                <a href="<?php echo esc_url(home_url('/wp-content/uploads/2023/05/LiabilityDocScan.pdf')); ?>" target="_blank" rel="noopener">
                    View Liability Agreement
                </a>
            </div>

            <div class="pba-governing-doc-item">
                <h3>Bylaws</h3>
                <p>
                    The Association Bylaws are not currently available on this page.
                    Please check back later or contact the Association for a copy.
                </p>
            </div>
        </div>
    </div>
    <?php

    return ob_get_clean();
}