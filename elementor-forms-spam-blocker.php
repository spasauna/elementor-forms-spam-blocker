<?php
/**
 * Plugin Name: Elementor Forms Spam Blocker
 * Plugin URI: https://example.com/elementor-forms-spam-blocker
 * Description: Detect and block spam submissions in Elementor Forms based on keyword blocklist.
 * Version: 1.8.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: efsb
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('EFSB_VERSION', '1.8.0');
define('EFSB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EFSB_PLUGIN_URL', plugin_dir_url(__FILE__));

class Elementor_Forms_Spam_Blocker {

    private static $instance = null;

    private $options;

    private $is_spam = false;

    private $default_keywords = array(
        'backlink',
        'link building',
        'link-building',
        'buy links',
        'seo services',
        'guest post',
        'guest posting',
        'link exchange',
        'paid links',
        'dofollow links'
    );

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->options = get_option('efsb_options', $this->get_default_options());
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // REJECT MODE: Hook into validation with high priority to stop form completely
        add_action('elementor_pro/forms/validation', array($this, 'validate_form_submission'), 5, 2);
        
        // SILENT MODE: Hook very early into new_record to set spam flag
        add_action('elementor_pro/forms/new_record', array($this, 'check_spam_for_silent_mode'), 1, 2);
        
        // SILENT MODE: Remove email action before it runs
        add_action('elementor_pro/forms/new_record', array($this, 'remove_email_actions'), 2, 2);
        
        // SILENT MODE: Block emails using MULTIPLE methods
        // Method 1: pre_wp_mail filter (WordPress 5.9+)
        add_filter('pre_wp_mail', array($this, 'block_wp_mail'), 1, 2);
        
        // Method 2: wp_mail filter - modify the email to prevent sending
        add_filter('wp_mail', array($this, 'filter_wp_mail'), 1, 1);
        
        // Method 3: phpmailer_init - clear recipients as last resort
        add_action('phpmailer_init', array($this, 'block_phpmailer'), 99999);
        
        // SILENT MODE: Mark submission for update and do it on shutdown
        add_action('elementor_pro/forms/new_record', array($this, 'mark_submission_for_update'), 999, 2);
        
        // Clear spam flag at end of request
        add_action('shutdown', array($this, 'clear_spam_flag'));
    }

    private function get_default_options() {
        return array(
            'keywords' => $this->default_keywords,
            'mode' => 'reject',
            'fields_to_scan' => array('subject', 'message'),
            'error_message' => __('Your message could not be sent. Please try again later.', 'efsb'),
        );
    }

    public function add_admin_menu() {
        add_options_page(
            __('Elementor Forms Spam Blocker', 'efsb'),
            __('Forms Spam Blocker', 'efsb'),
            'manage_options',
            'efsb-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('efsb_options_group', 'efsb_options', array($this, 'sanitize_options'));

        add_settings_section(
            'efsb_main_section',
            __('Spam Detection Settings', 'efsb'),
            array($this, 'render_section_intro'),
            'efsb-settings'
        );

        add_settings_field(
            'efsb_mode',
            __('Detection Mode', 'efsb'),
            array($this, 'render_mode_field'),
            'efsb-settings',
            'efsb_main_section'
        );

        add_settings_field(
            'efsb_fields',
            __('Fields to Scan', 'efsb'),
            array($this, 'render_fields_field'),
            'efsb-settings',
            'efsb_main_section'
        );

        add_settings_field(
            'efsb_keywords',
            __('Blocked Keywords', 'efsb'),
            array($this, 'render_keywords_field'),
            'efsb-settings',
            'efsb_main_section'
        );

        add_settings_field(
            'efsb_error_message',
            __('Rejection Message', 'efsb'),
            array($this, 'render_error_message_field'),
            'efsb-settings',
            'efsb_main_section'
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ('settings_page_efsb-settings' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'efsb-admin-styles',
            EFSB_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            EFSB_VERSION
        );

        wp_enqueue_script(
            'efsb-admin-script',
            EFSB_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            EFSB_VERSION,
            true
        );

        wp_localize_script('efsb-admin-script', 'efsbAdmin', array(
            'confirmDelete' => __('Are you sure you want to remove this keyword?', 'efsb'),
            'emptyKeyword' => __('Please enter a keyword.', 'efsb'),
        ));
    }

    public function render_section_intro() {
        echo '<p>' . esc_html__('Configure how spam submissions are detected and handled in your Elementor Forms.', 'efsb') . '</p>';
    }

    public function render_mode_field() {
        $mode = isset($this->options['mode']) ? $this->options['mode'] : 'reject';
        ?>
        <fieldset>
            <label>
                <input type="radio" name="efsb_options[mode]" value="reject" <?php checked($mode, 'reject'); ?>>
                <strong><?php esc_html_e('Reject', 'efsb'); ?></strong>
                <span class="description"><?php esc_html_e('— Show an error message and prevent submission entirely.', 'efsb'); ?></span>
            </label>
            <br><br>
            <label>
                <input type="radio" name="efsb_options[mode]" value="silent" <?php checked($mode, 'silent'); ?>>
                <strong><?php esc_html_e('Silent', 'efsb'); ?></strong>
                <span class="description"><?php esc_html_e('— Accept submission (shown as success) but do not send any email notifications.', 'efsb'); ?></span>
            </label>
        </fieldset>
        <?php
    }

    public function render_fields_field() {
        $fields = isset($this->options['fields_to_scan']) ? $this->options['fields_to_scan'] : array();
        $fields_string = implode(', ', $fields);
        ?>
        <input type="text" 
               name="efsb_options[fields_to_scan]" 
               value="<?php echo esc_attr($fields_string); ?>" 
               class="regular-text"
               placeholder="subject, message">
        <p class="description">
            <?php esc_html_e('Enter the field IDs from your Elementor form, separated by commas. These fields will be scanned for spam keywords.', 'efsb'); ?>
            <br>
            <?php esc_html_e('You can find field IDs in Elementor: Edit form → Click on field → Advanced tab → ID field.', 'efsb'); ?>
        </p>
        <?php
    }

    public function render_keywords_field() {
        $keywords = isset($this->options['keywords']) ? $this->options['keywords'] : array();
        ?>
        <div id="efsb-keywords-container">
            <div id="efsb-keywords-list">
                <?php foreach ($keywords as $index => $keyword) : ?>
                    <div class="efsb-keyword-item">
                        <input type="text" 
                               name="efsb_options[keywords][]" 
                               value="<?php echo esc_attr($keyword); ?>" 
                               class="regular-text efsb-keyword-input">
                        <button type="button" class="button efsb-remove-keyword" title="<?php esc_attr_e('Remove', 'efsb'); ?>">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="efsb-add-keyword-wrapper">
                <input type="text" id="efsb-new-keyword" class="regular-text" placeholder="<?php esc_attr_e('Enter new keyword...', 'efsb'); ?>">
                <button type="button" id="efsb-add-keyword" class="button button-secondary">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e('Add Keyword', 'efsb'); ?>
                </button>
            </div>
        </div>
        <p class="description">
            <?php esc_html_e('Keywords are matched as exact words (case-insensitive). For example, "backlink" will match "I offer backlink services" but not "backlinks".', 'efsb'); ?>
        </p>
        <?php
    }

    public function render_error_message_field() {
        $message = isset($this->options['error_message']) ? $this->options['error_message'] : '';
        ?>
        <input type="text" 
               name="efsb_options[error_message]" 
               value="<?php echo esc_attr($message); ?>" 
               class="large-text">
        <p class="description">
            <?php esc_html_e('This message is shown when a submission is rejected (only in Reject mode).', 'efsb'); ?>
        </p>
        <?php
    }

    public function sanitize_options($input) {
        $sanitized = array();

        // Mode
        $sanitized['mode'] = isset($input['mode']) && in_array($input['mode'], array('reject', 'silent')) 
            ? $input['mode'] 
            : 'reject';

        // Fields to scan
        if (isset($input['fields_to_scan'])) {
            $fields = array_map('trim', explode(',', $input['fields_to_scan']));
            $sanitized['fields_to_scan'] = array_filter(array_map('sanitize_text_field', $fields));
        } else {
            $sanitized['fields_to_scan'] = array();
        }

        // Keywords
        if (isset($input['keywords']) && is_array($input['keywords'])) {
            $sanitized['keywords'] = array_filter(array_map('sanitize_text_field', $input['keywords']));
            $sanitized['keywords'] = array_values($sanitized['keywords']);
        } else {
            $sanitized['keywords'] = array();
        }

        // Error message
        $sanitized['error_message'] = isset($input['error_message']) 
            ? sanitize_text_field($input['error_message']) 
            : __('Your message could not be sent. Please try again later.', 'efsb');

        return $sanitized;
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['settings-updated'])) {
            add_settings_error('efsb_messages', 'efsb_message', __('Settings saved.', 'efsb'), 'updated');
        }

        ?>
        <div class="wrap efsb-settings-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="efsb-header">
                <div class="efsb-header-icon">
                    <span class="dashicons dashicons-shield-alt"></span>
                </div>
                <div class="efsb-header-text">
                    <p><?php esc_html_e('Protect your Elementor Forms from spam submissions by blocking messages containing specific keywords.', 'efsb'); ?></p>
                </div>
            </div>

            <?php settings_errors('efsb_messages'); ?>

            <form action="options.php" method="post">
                <?php
                settings_fields('efsb_options_group');
                do_settings_sections('efsb-settings');
                submit_button(__('Save Settings', 'efsb'));
                ?>
            </form>

            <div class="efsb-info-box">
                <h3><span class="dashicons dashicons-info"></span> <?php esc_html_e('How it works', 'efsb'); ?></h3>
                <ul>
                    <li><?php esc_html_e('The plugin scans the specified form fields for any of the blocked keywords.', 'efsb'); ?></li>
                    <li><?php esc_html_e('Keywords are matched as complete words only (case-insensitive).', 'efsb'); ?></li>
                    <li><strong><?php esc_html_e('Reject mode:', 'efsb'); ?></strong> <?php esc_html_e('Shows an error and prevents the form from being submitted.', 'efsb'); ?></li>
                    <li><strong><?php esc_html_e('Silent mode:', 'efsb'); ?></strong> <?php esc_html_e('Accepts the submission but silently prevents all email notifications from being sent.', 'efsb'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Check if content contains any blocked keywords (exact word match, case-insensitive)
     */
    private function contains_blocked_keyword($content) {
        $keywords = isset($this->options['keywords']) ? $this->options['keywords'] : array();
        
        if (empty($keywords) || empty($content)) {
            return false;
        }

        $content_lower = mb_strtolower($content, 'UTF-8');

        foreach ($keywords as $keyword) {
            $keyword_lower = mb_strtolower(trim($keyword), 'UTF-8');
            
            if (empty($keyword_lower)) {
                continue;
            }

            // Build regex pattern for exact word match
            $pattern = '/(?<![a-zA-Z0-9])' . preg_quote($keyword_lower, '/') . '(?![a-zA-Z0-9])/ui';
            
            if (preg_match($pattern, $content_lower)) {
                return $keyword;
            }
        }

        return false;
    }

    /**
     * Get content from form fields - checks multiple field identifiers
     */
    private function get_scannable_content($record) {
        $fields_to_scan = isset($this->options['fields_to_scan']) ? $this->options['fields_to_scan'] : array();
        
        // Normalize field IDs to lowercase for comparison
        $fields_to_scan_lower = array_map('strtolower', $fields_to_scan);
        
        $content_parts = array();
        $form_data = $record->get('fields');

        foreach ($form_data as $field_key => $field) {
            $should_scan = false;
            
            // Check by array key (usually like 'field_abc123')
            if (in_array(strtolower($field_key), $fields_to_scan_lower)) {
                $should_scan = true;
            }
            
            // Check by field ID property
            if (isset($field['id']) && in_array(strtolower($field['id']), $fields_to_scan_lower)) {
                $should_scan = true;
            }
            
            // Check by field title/label (useful if user enters label instead of ID)
            if (isset($field['title']) && in_array(strtolower($field['title']), $fields_to_scan_lower)) {
                $should_scan = true;
            }
            
            // Check by field type for common types
            if (isset($field['type'])) {
                $type_lower = strtolower($field['type']);
                // If user specified 'message' or 'subject', also match textarea/text fields
                if (in_array('message', $fields_to_scan_lower) && $type_lower === 'textarea') {
                    $should_scan = true;
                }
            }

            if ($should_scan && !empty($field['value'])) {
                $content_parts[] = $field['value'];
            }
        }

        return implode(' ', $content_parts);
    }

    /**
     * VALIDATION PHASE: Check for spam early - runs BEFORE email actions
     * This is the key hook that runs before any form actions (like email) are processed
     */
    public function validate_form_submission($record, $ajax_handler) {
        // Refresh options
        $this->options = get_option('efsb_options', $this->get_default_options());
        
        $content = $this->get_scannable_content($record);
        $matched_keyword = $this->contains_blocked_keyword($content);

        if ($matched_keyword !== false) {
            // ALWAYS mark as spam first (for both modes) - this sets the flag BEFORE email actions run
            $this->mark_as_spam();
            
            if ($this->options['mode'] === 'reject') {
                // REJECT MODE: Stop the form completely
                $error_message = !empty($this->options['error_message']) 
                    ? $this->options['error_message'] 
                    : __('Your message could not be sent. Please try again later.', 'efsb');
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        '[EFSB] REJECT: Spam detected - Keyword: "%s"',
                        $matched_keyword
                    ));
                }
                
                wp_send_json_error(array(
                    'message' => $error_message,
                    'errors' => array(),
                    'data' => array()
                ));
                exit;
            } else {
                // SILENT MODE: Just log, form will continue but emails will be blocked
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        '[EFSB] SILENT: Spam detected during validation - Keyword: "%s" - Emails will be blocked',
                        $matched_keyword
                    ));
                }
            }
        }
    }

    /**
     * SILENT MODE: Backup check in new_record (in case validation didn't catch it)
     */
    public function check_spam_for_silent_mode($record, $ajax_handler) {
        // Skip if already marked as spam
        if ($this->is_spam_request()) {
            return;
        }
        
        // Refresh options
        $this->options = get_option('efsb_options', $this->get_default_options());
        
        // Only run in silent mode
        if ($this->options['mode'] !== 'silent') {
            return;
        }

        $content = $this->get_scannable_content($record);
        $matched_keyword = $this->contains_blocked_keyword($content);

        if ($matched_keyword !== false) {
            $this->mark_as_spam();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[EFSB] SILENT (backup): Spam detected in new_record - Keyword: "%s"',
                    $matched_keyword
                ));
            }
        }
    }

    /**
     * SILENT MODE: Remove email actions from Elementor's action queue
     */
    public function remove_email_actions($record, $ajax_handler) {
        if (!$this->is_spam) {
            return;
        }

        // Get the form's submit actions
        $form_settings = $record->get('form_settings');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[EFSB] Attempting to remove email actions from form');
        }

        // Method 1: Try to modify the record's meta to remove email actions
        // The record stores which actions should run
        try {
            // Get Elementor's module manager to access the form actions
            if (class_exists('\ElementorPro\Plugin')) {
                $module = \ElementorPro\Plugin::instance()->modules_manager->get_modules('forms');
                if ($module) {
                    // Remove email action handlers
                    remove_all_actions('elementor_pro/forms/process/email');
                    remove_all_actions('elementor_pro/forms/process/email2');
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[EFSB] Removed elementor_pro/forms/process/email actions');
                    }
                }
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[EFSB] Error removing actions: ' . $e->getMessage());
            }
        }
    }

    /**
     * Check if current request is spam (checks both class property and global)
     */
    private function is_spam_request() {
        $class_spam = $this->is_spam;
        $global_spam = isset($GLOBALS['efsb_is_spam']) && $GLOBALS['efsb_is_spam'];
        $transient_spam = get_transient('efsb_spam_' . $this->get_request_id());
        
        return $class_spam || $global_spam || $transient_spam;
    }

    /**
     * Get unique ID for current request
     */
    private function get_request_id() {
        if (!isset($GLOBALS['efsb_request_id'])) {
            $GLOBALS['efsb_request_id'] = md5($_SERVER['REQUEST_TIME_FLOAT'] . $_SERVER['REMOTE_ADDR']);
        }
        return $GLOBALS['efsb_request_id'];
    }

    /**
     * Mark current request as spam (stores in multiple places for reliability)
     */
    private function mark_as_spam() {
        $this->is_spam = true;
        $GLOBALS['efsb_is_spam'] = true;
        set_transient('efsb_spam_' . $this->get_request_id(), true, 60); // 60 second expiry
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[EFSB] Marked request as spam. Request ID: ' . $this->get_request_id());
        }
    }

    /**
     * Block wp_mail function when spam is detected (WordPress 5.9+)
     */
    public function block_wp_mail($return, $atts) {
        // Always log that this hook fired
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $to = isset($atts['to']) ? (is_array($atts['to']) ? implode(', ', $atts['to']) : $atts['to']) : 'unknown';
            error_log('[EFSB] pre_wp_mail hook fired. To: ' . $to . ' | is_spam: ' . ($this->is_spam_request() ? 'YES' : 'NO'));
        }
        
        if ($this->is_spam_request()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[EFSB] pre_wp_mail: BLOCKING email');
            }
            return true;
        }
        return $return;
    }

    /**
     * Filter wp_mail arguments to prevent email from being sent
     */
    public function filter_wp_mail($args) {
        // Always log that this hook fired
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $to = isset($args['to']) ? (is_array($args['to']) ? implode(', ', $args['to']) : $args['to']) : 'unknown';
            error_log('[EFSB] wp_mail filter fired. To: ' . $to . ' | is_spam: ' . ($this->is_spam_request() ? 'YES' : 'NO'));
        }
        
        if ($this->is_spam_request()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[EFSB] wp_mail filter: BLOCKING email');
            }
            $args['to'] = '';
            $args['subject'] = '';
            $args['message'] = '';
        }
        return $args;
    }

    /**
     * Block PHPMailer directly as last resort (runs very late)
     */
    public function block_phpmailer($phpmailer) {
        // Always log that this hook fired
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[EFSB] phpmailer_init hook fired. is_spam: ' . ($this->is_spam_request() ? 'YES' : 'NO'));
        }
        
        if ($this->is_spam_request()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[EFSB] phpmailer_init: BLOCKING - clearing all recipients');
            }
            $phpmailer->clearAllRecipients();
            $phpmailer->clearAttachments();
            $phpmailer->clearCustomHeaders();
            $phpmailer->clearReplyTos();
            $phpmailer->Subject = '';
            $phpmailer->Body = '';
            $phpmailer->AltBody = '';
        }
    }

    /**
     * Mark that we need to update a submission on shutdown
     */
    public function mark_submission_for_update($record, $ajax_handler) {
        if (!$this->is_spam_request()) {
            return;
        }
        
        // Store form ID to help identify the submission later
        $form_meta = $record->get('form_settings');
        $form_id = isset($form_meta['id']) ? $form_meta['id'] : '';
        
        $GLOBALS['efsb_update_submission'] = true;
        $GLOBALS['efsb_form_id'] = $form_id;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[EFSB] Marked for submission update. Form ID: ' . $form_id);
        }
        
        // Register shutdown handler to update after everything is done
        add_action('shutdown', array($this, 'update_submission_on_shutdown'), 1);
    }

    /**
     * Update the most recent submission on shutdown
     */
    public function update_submission_on_shutdown() {
        if (empty($GLOBALS['efsb_update_submission'])) {
            return;
        }
        
        global $wpdb;
        
        // Find the most recent submission
        $submissions_table = $wpdb->prefix . 'e_submissions';
        $values_table = $wpdb->prefix . 'e_submissions_values';
        $actions_table = $wpdb->prefix . 'e_submissions_actions_log';
        
        // Get the most recent submission ID
        $submission_id = $wpdb->get_var("SELECT id FROM $submissions_table ORDER BY id DESC LIMIT 1");
        
        if (!$submission_id) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[EFSB] No submission found to update');
            }
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[EFSB] Updating submission ID: ' . $submission_id);
        }
        
        // Add spam status field to submission values
        if ($wpdb->get_var("SHOW TABLES LIKE '$values_table'") === $values_table) {
            $result = $wpdb->insert(
                $values_table,
                array(
                    'submission_id' => $submission_id,
                    'key' => '⚠️ Spam Status',
                    'value' => 'Email blocked by Spam Blocker'
                ),
                array('%d', '%s', '%s')
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[EFSB] Inserted spam status field: ' . ($result ? 'success' : 'failed - ' . $wpdb->last_error));
            }
        }
        
        // Update the actions log
        if ($wpdb->get_var("SHOW TABLES LIKE '$actions_table'") === $actions_table) {
            $updated = $wpdb->update(
                $actions_table,
                array('log' => '⚠️ Blocked by Spam Blocker (email not sent)'),
                array('submission_id' => $submission_id, 'action_name' => 'email'),
                array('%s'),
                array('%d', '%s')
            );
            
            // Also try email2
            $wpdb->update(
                $actions_table,
                array('log' => '⚠️ Blocked by Spam Blocker (email not sent)'),
                array('submission_id' => $submission_id, 'action_name' => 'email2'),
                array('%s'),
                array('%d', '%s')
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[EFSB] Updated actions log: ' . $updated . ' rows');
            }
        }
    }

    /**
     * Clear spam flag at end of request
     */
    public function clear_spam_flag() {
        $this->is_spam = false;
        if (isset($GLOBALS['efsb_is_spam'])) {
            unset($GLOBALS['efsb_is_spam']);
        }
        // Clean up transient
        if (isset($GLOBALS['efsb_request_id'])) {
            delete_transient('efsb_spam_' . $GLOBALS['efsb_request_id']);
        }
    }
}

// Initialize the plugin
function efsb_init() {
    // Check if Elementor Pro is active
    if (!did_action('elementor_pro/init')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php esc_html_e('Elementor Forms Spam Blocker:', 'efsb'); ?></strong>
                    <?php esc_html_e('This plugin requires Elementor Pro with Forms widget to be installed and activated.', 'efsb'); ?>
                </p>
            </div>
            <?php
        });
        return;
    }

    Elementor_Forms_Spam_Blocker::get_instance();
}
add_action('init', 'efsb_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    $default_options = array(
        'keywords' => array(
            'backlink',
            'link building',
            'link-building',
            'buy links',
            'seo services',
            'guest post',
            'guest posting',
            'link exchange',
            'paid links',
            'dofollow links'
        ),
        'mode' => 'reject',
        'fields_to_scan' => array('subject', 'message'),
        'error_message' => __('Your message could not be sent. Please try again later.', 'efsb'),
    );

    if (!get_option('efsb_options')) {
        add_option('efsb_options', $default_options);
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Optionally remove options on deactivation
    // delete_option('efsb_options');
});
