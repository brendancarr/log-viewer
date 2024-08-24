<?php
/*
Plugin Name: Advanced Log Viewer
Description: Adds a menu option to view and clear error log files with auto-refresh
Version: 1.2
Author: Brendan @ Infinus
*/

// Add menu item
function add_log_viewer_menu() {
    add_menu_page(
        'Log Viewer',
        'Log Viewer',
        'manage_options',
        'log-viewer',
        'display_log_viewer',
        'dashicons-text'
    );
}
add_action('admin_menu', 'add_log_viewer_menu');

// Get log files
function get_log_files() {
    $log_dir = dirname(ABSPATH) . '/logs/';
    $log_files = glob($log_dir . '*.error.log');
    return array_map('basename', $log_files);
}

// Display log viewer page
function display_log_viewer() {
    $log_files = get_log_files();
    $selected_log = isset($_GET['log']) ? $_GET['log'] : (empty($log_files) ? '' : $log_files[0]);
    $log_file = dirname(ABSPATH) . '/logs/' . $selected_log;
    
    // Handle clear log action
    if (isset($_POST['clear_log']) && check_admin_referer('clear_log_action')) {
        file_put_contents($log_file, '');
        echo '<div class="notice notice-success"><p>Log file cleared successfully.</p></div>';
    }
    
    $log_content = file_exists($log_file) ? file_get_contents($log_file) : 'Log file not found.';
    ?>
    <div class="wrap">
        <h1>Log Viewer</h1>
        <p>This is a list of error logs in the root of your user directory: <?php echo ABSPATH; ?></p>
        <form method="get" action="">
            <input type="hidden" name="page" value="log-viewer">
            <select name="log" onchange="this.form.submit()">
                <?php foreach ($log_files as $file): ?>
                    <option value="<?php echo esc_attr($file); ?>" <?php selected($file, $selected_log); ?>><?php echo esc_html($file); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <br>
        <form method="post" action="">
            <?php wp_nonce_field('clear_log_action'); ?>
            <textarea id="log-content" style="width: 100%; height: 600px; font-family: monospace;" readonly><?php echo esc_textarea($log_content); ?></textarea>
            <p>
                <input type="submit" name="clear_log" class="button button-secondary" value="Clear Log" onclick="return confirm('Are you sure you want to clear the log?');" />
                <label><input type="checkbox" id="auto-refresh" checked> Auto-refresh</label>
            </p>
        </form>
    </div>
    <script>
    jQuery(document).ready(function($) {
        function refreshLog() {
            $.ajax({
                url: ajaxurl,
                data: {
                    action: 'refresh_log',
                    log: '<?php echo esc_js($selected_log); ?>',
                    _ajax_nonce: '<?php echo wp_create_nonce('refresh_log_nonce'); ?>'
                },
                success: function(response) {
                    $('#log-content').val(response);
                }
            });
        }

        var refreshInterval;
        $('#auto-refresh').change(function() {
            if ($(this).is(':checked')) {
                refreshInterval = setInterval(refreshLog, 5000); // Refresh every 5 seconds
            } else {
                clearInterval(refreshInterval);
            }
        }).change();
    });
    </script>
    <?php
}

// AJAX handler for refreshing log content
function ajax_refresh_log() {
    check_ajax_referer('refresh_log_nonce');
    $log_file = dirname(ABSPATH) . '/logs/' . $_GET['log'];
    echo file_exists($log_file) ? file_get_contents($log_file) : 'Log file not found.';
    wp_die();
}
add_action('wp_ajax_refresh_log', 'ajax_refresh_log');