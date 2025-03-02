<?php
/**
 * Plugin Name: Gravity Forms API Integration
 * Description: Send Gravity Forms submissions to a RESTful API and save data in a file
 * Version: 1.0.0
 * Author: Aqsa Mumtaz
 * Text Domain: gf-api-integration
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class GF_API_Integration {
    // File path for storing form submissions
    private $storage_file;
    
    // API endpoint URL
    private $api_endpoint = 'https://webhook.site/b0628ebd-b3eb-4347-abe7-9f2400ac14d0';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Set up the storage file path in the wp-content/uploads directory
        $upload_dir = wp_upload_dir();
        $this->storage_file = $upload_dir['basedir'] . '/gf_submissions.json';
        
        // Initialize the file if it doesn't exist
        if (!file_exists($this->storage_file)) {
            file_put_contents($this->storage_file, json_encode([]));
        }
        
        // Add hooks
        add_action('gform_after_submission', array($this, 'process_form_submission'), 10, 2);
        
        // Add admin menu
        add_action('admin_menu', array($this, 'register_admin_menu'));
        
        // Add settings link on plugin page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }
    
    /**
     * Process form submission
     * 
     * @param array $entry The entry that was just created
     * @param array $form The form object
     */
    public function process_form_submission($entry, $form) {
        // Prepare form data
        $form_data = $this->prepare_form_data($entry, $form);
        
        // Send to API
        $api_response = $this->send_to_api($form_data);
        
        // Log the submission and API response
        $this->log_submission($form_data, $api_response);
    }
    
    /**
     * Prepare form data for API submission
     * 
     * @param array $entry The entry that was just created
     * @param array $form The form object
     * @return array Prepared data
     */
    private function prepare_form_data($entry, $form) {
        $data = array(
            'form_id' => $form['id'],
            'form_title' => $form['title'],
            'date_created' => $entry['date_created'],
            'ip' => $entry['ip'],
            'fields' => array(),
        );
        
        // Add all form fields, including inspecting each entry key
        foreach ($entry as $key => $value) {
            // Skip non-field entries (metadata)
            if (!is_numeric($key)) {
                continue;
            }
            
            // Skip empty values
            if (empty($value)) {
                continue;
            }
            
            // Find the field object to get the label
            $field_label = "Field $key";
            foreach ($form['fields'] as $field) {
                if ($field->id == $key) {
                    $field_label = $field->label;
                    break;
                }
            }
            
            // For complex fields like checkboxes, we need special handling
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            
            // Add field to data array
            $data['fields'][] = array(
                'id' => $key,
                'label' => $field_label,
                'value' => $value,
            );
        }
        
        return $data;
    }
    
    /**
     * Send data to the mock API
     * 
     * @param array $data The form data to send
     * @return array Response from the API
     */
    private function send_to_api($data) {
        $response = array(
            'success' => false,
            'message' => '',
            'data' => null,
        );
        
        // Prepare the API request
        $args = array(
            'body' => json_encode($data),
            'timeout' => 45,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
        );
        
        // Send the request to the API
        $api_response = wp_remote_post($this->api_endpoint, $args);
        
        // Check for errors
        if (is_wp_error($api_response)) {
            $response['message'] = $api_response->get_error_message();
        } else {
            $response_code = wp_remote_retrieve_response_code($api_response);
            $response_body = wp_remote_retrieve_body($api_response);
            
            if ($response_code >= 200 && $response_code < 300) {
                $response['success'] = true;
                $response['message'] = 'Successfully sent data to API';
                $response['data'] = json_decode($response_body, true);
            } else {
                $response['message'] = 'API returned error: ' . $response_code;
                $response['data'] = $response_body;
            }
        }
        
        return $response;
    }
    
    /**
     * Log the submission to a file
     * 
     * @param array $form_data The form data
     * @param array $api_response The API response
     */
    private function log_submission($form_data, $api_response) {
        // Get existing submissions
        $submissions = json_decode(file_get_contents($this->storage_file), true);
        
        // Add new submission
        $submissions[] = array(
            'timestamp' => current_time('mysql'),
            'form_data' => $form_data,
            'api_response' => $api_response,
        );
        
        // Save updated submissions
        file_put_contents($this->storage_file, json_encode($submissions, JSON_PRETTY_PRINT));
    }
    
    /**
     * Register admin menu
     */
    public function register_admin_menu() {
        add_submenu_page(
            'gform_settings',
            'API Integration Settings',
            'API Integration',
            'manage_options',
            'gf-api-integration',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Render settings page
     */
    public function settings_page() {
        // Get the submissions
        $submissions = json_decode(file_get_contents($this->storage_file), true);
        
        // Display the settings page
        ?>
        <div class="wrap">
            <h1>Gravity Forms API Integration Settings</h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('gf-api-integration'); ?>
                <?php do_settings_sections('gf-api-integration'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">API Endpoint</th>
                        <td>
                            <input type="text" name="gf_api_endpoint" value="<?php echo esc_attr($this->api_endpoint); ?>" class="regular-text" />
                            <p class="description">The endpoint URL for the API</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <h2>Recent Submissions</h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Form</th>
                        <th>API Status</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Display the last 10 submissions (most recent first)
                    $submissions = array_reverse($submissions);
                    $submissions = array_slice($submissions, 0, 10);
                    
                    foreach ($submissions as $submission) {
                        $status_class = $submission['api_response']['success'] ? 'updated' : 'error';
                        $status_text = $submission['api_response']['success'] ? 'Success' : 'Error';
                        ?>
                        <tr>
                            <td><?php echo esc_html($submission['timestamp']); ?></td>
                            <td><?php echo esc_html($submission['form_data']['form_title']); ?> (ID: <?php echo esc_html($submission['form_data']['form_id']); ?>)</td>
                            <td><span class="<?php echo $status_class; ?>"><?php echo esc_html($status_text); ?></span></td>
                            <td>
                                <button type="button" class="button view-details-button" data-submission='<?php echo esc_attr(json_encode($submission)); ?>'>View Details</button>
                            </td>
                        </tr>
                        <?php
                    }
                    
                    if (empty($submissions)) {
                        echo '<tr><td colspan="4">No submissions yet.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
            
            <!-- Modal for displaying submission details -->
            <div id="submission-details-modal" style="display: none;">
                <div id="submission-details"></div>
            </div>
            
            <script>
                jQuery(document).ready(function($) {
                    $('.view-details-button').on('click', function() {
                        var submission = $(this).data('submission');
                        var formattedDetails = '<pre>' + JSON.stringify(submission, null, 2) + '</pre>';
                        
                        $('#submission-details').html(formattedDetails);
                        $('#submission-details-modal').dialog({
                            title: 'Submission Details',
                            width: 800,
                            height: 500,
                            modal: true
                        });
                    });
                });
            </script>
        </div>
        <?php
    }
    
    /**
     * Add settings link to plugin page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="admin.php?page=gf-api-integration">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Initialize the plugin
$gf_api_integration = new GF_API_Integration();