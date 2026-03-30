<?php
/**
 * Plugin Name: PBA Supabase Connector
 * Description: Reads household data from Supabase REST API and exposes it via shortcode.
 * Version: 0.2.0
 * Author: PBA
 */

if (!defined('ABSPATH')) {
    exit;
}

class PBA_Supabase_Connector
{
    public static function init(): void
    {
        add_shortcode('supabase_households', [self::class, 'render_households_shortcode']);
    }

    private static function get_config(): array
    {
        if (!defined('SUPABASE_URL') || !SUPABASE_URL) {
            throw new RuntimeException('SUPABASE_URL is not defined in wp-config.php');
        }

        if (!defined('SUPABASE_API_KEY') || !SUPABASE_API_KEY) {
            throw new RuntimeException('SUPABASE_API_KEY is not defined in wp-config.php');
        }

        return [
            'url' => rtrim(SUPABASE_URL, '/'),
            'key' => SUPABASE_API_KEY,
        ];
    }

    private static function get_headers(): array
    {
        $config = self::get_config();

        return [
            'apikey'       => $config['key'],
            'Authorization'=> 'Bearer ' . $config['key'],
            'Accept'       => 'application/json',
        ];
    }

    private static function build_rest_url(string $table, array $params = []): string
    {
        $config = self::get_config();

        $base = $config['url'] . '/rest/v1/' . rawurlencode($table);

        if (!empty($params)) {
            $base .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        return $base;
    }

    public static function get_households(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        $url = self::build_rest_url('Household', [
            'select' => 'household_id,street_address,household_status',
            'order'  => 'street_address.asc',
            'limit'  => $limit,
        ]);

        $response = wp_remote_get($url, [
            'headers' => self::get_headers(),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException('HTTP request failed: ' . $response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = wp_remote_retrieve_body($response);

        if ($status < 200 || $status >= 300) {
            $message = 'Supabase API returned HTTP ' . $status;

            $json = json_decode($body, true);
            if (is_array($json) && !empty($json['message'])) {
                $message .= ': ' . $json['message'];
            }

            throw new RuntimeException($message);
        }

        $data = json_decode($body, true);

        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON returned from Supabase API');
        }

        return $data;
    }

    public static function render_households_shortcode(array $atts = []): string
    {
        $atts = shortcode_atts([
            'limit' => 25,
        ], $atts, 'supabase_households');

        $limit = max(1, min(200, (int) $atts['limit']));

        try {
            $rows = self::get_households($limit);
        } catch (Throwable $e) {
            if (current_user_can('manage_options')) {
                return '<div class="notice notice-error"><p>Supabase query failed: ' .
                    esc_html($e->getMessage()) .
                    '</p></div>';
            }

            return '<p>Unable to load household data right now.</p>';
        }

        if (!$rows) {
            return '<p>No households found.</p>';
        }

        ob_start();
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Street Address</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo esc_html((string) ($row['household_id'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($row['street_address'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($row['household_status'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
}

PBA_Supabase_Connector::init();