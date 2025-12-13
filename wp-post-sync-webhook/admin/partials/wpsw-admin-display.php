<?php
/**
 * Admin display template.
 *
 * @since      1.0.0
 * @package    WP_Post_Sync_Webhook
 * @subpackage WP_Post_Sync_Webhook/admin/partials
 * @author     Prakash Tyata <developer@softart.com.np>
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$settings     = get_option( 'wpsw_settings', array() );
$endpoint_url = rest_url( 'post-sync/v1/receive' );

$defaults = array(
    'mode'          => 'sender',
    'receiver_url'  => '',
    'secret_key'    => '',
    'post_types'    => array( 'post' ),
    'sync_count'    => 0,
    'last_sync'     => '',
);

$settings = wp_parse_args( $settings, $defaults );
?>

<div class="wpsw-wrap">
    
    <!-- Header -->
    <div class="wpsw-header">
        <div>
            <h1><?php esc_html_e( '📡 Post Sync Webhook', 'wp-post-sync-webhook' ); ?></h1>
            <p style="margin: 8px 0 0 0; opacity: 0.9;">
                <?php esc_html_e( 'Sync posts seamlessly between WordPress sites', 'wp-post-sync-webhook' ); ?>
            </p>
        </div>
        <span class="wpsw-version">v<?php echo esc_html( WPSW_VERSION ); ?></span>
    </div>
    
    <!-- Stats Cards -->
    <div class="wpsw-cards">
        <div class="wpsw-card wpsw-status-card">
            <div class="wpsw-card-icon">
                <?php
                if ( 'sender' === $settings['mode'] ) {
                    echo '📤';
                } elseif ( 'receiver' === $settings['mode'] ) {
                    echo '📥';
                } else {
                    echo '🔄';
                }
                ?>
            </div>
            <div class="wpsw-card-value"><?php echo esc_html( ucfirst( $settings['mode'] ) ); ?></div>
            <div class="wpsw-card-label"><?php esc_html_e( 'Current Mode', 'wp-post-sync-webhook' ); ?></div>
        </div>
        
        <div class="wpsw-card wpsw-status-card">
            <div class="wpsw-card-icon">📊</div>
            <div class="wpsw-card-value"><?php echo intval( $settings['sync_count'] ); ?></div>
            <div class="wpsw-card-label"><?php esc_html_e( 'Total Syncs', 'wp-post-sync-webhook' ); ?></div>
        </div>
        
        <div class="wpsw-card wpsw-status-card">
            <div class="wpsw-card-icon">🕐</div>
            <div class="wpsw-card-value">
                <?php
                if ( $settings['last_sync'] ) {
                    /* translators: %s: human readable time difference */
                    printf( esc_html__( '%s ago', 'wp-post-sync-webhook' ), esc_html( human_time_diff( strtotime( $settings['last_sync'] ) ) ) );
                } else {
                    esc_html_e( 'Never', 'wp-post-sync-webhook' );
                }
                ?>
            </div>
            <div class="wpsw-card-label"><?php esc_html_e( 'Last Sync', 'wp-post-sync-webhook' ); ?></div>
        </div>
        
        <div class="wpsw-card wpsw-status-card">
            <div class="wpsw-card-icon"><?php echo ! empty( $settings['secret_key'] ) ? '🔒' : '🔓'; ?></div>
            <div class="wpsw-card-value">
                <?php echo ! empty( $settings['secret_key'] ) ? esc_html__( 'Configured', 'wp-post-sync-webhook' ) : esc_html__( 'Not Set', 'wp-post-sync-webhook' ); ?>
            </div>
            <div class="wpsw-card-label"><?php esc_html_e( 'Security', 'wp-post-sync-webhook' ); ?></div>
        </div>
    </div>
    
    <form method="post" action="options.php">
        <?php settings_fields( 'wpsw_settings_group' ); ?>
        
        <!-- Mode Selection -->
        <div class="wpsw-panel">
            <div class="wpsw-panel-header">
                <span class="dashicons dashicons-randomize"></span>
                <h2><?php esc_html_e( 'Sync Mode', 'wp-post-sync-webhook' ); ?></h2>
            </div>
            <div class="wpsw-panel-body">
                <div class="wpsw-mode-options">
                    <label class="wpsw-mode-option <?php echo 'sender' === $settings['mode'] ? 'active' : ''; ?>">
                        <input type="radio" name="wpsw_settings[mode]" value="sender" <?php checked( $settings['mode'], 'sender' ); ?>>
                        <div class="wpsw-mode-icon">📤</div>
                        <div class="wpsw-mode-title"><?php esc_html_e( 'Sender', 'wp-post-sync-webhook' ); ?></div>
                        <div class="wpsw-mode-desc"><?php esc_html_e( 'Send posts to another site', 'wp-post-sync-webhook' ); ?></div>
                    </label>
                    
                    <label class="wpsw-mode-option <?php echo 'receiver' === $settings['mode'] ? 'active' : ''; ?>">
                        <input type="radio" name="wpsw_settings[mode]" value="receiver" <?php checked( $settings['mode'], 'receiver' ); ?>>
                        <div class="wpsw-mode-icon">📥</div>
                        <div class="wpsw-mode-title"><?php esc_html_e( 'Receiver', 'wp-post-sync-webhook' ); ?></div>
                        <div class="wpsw-mode-desc"><?php esc_html_e( 'Receive posts from another site', 'wp-post-sync-webhook' ); ?></div>
                    </label>
                    
                    <label class="wpsw-mode-option <?php echo 'both' === $settings['mode'] ? 'active' : ''; ?>">
                        <input type="radio" name="wpsw_settings[mode]" value="both" <?php checked( $settings['mode'], 'both' ); ?>>
                        <div class="wpsw-mode-icon">🔄</div>
                        <div class="wpsw-mode-title"><?php esc_html_e( 'Both', 'wp-post-sync-webhook' ); ?></div>
                        <div class="wpsw-mode-desc"><?php esc_html_e( 'Send and receive posts', 'wp-post-sync-webhook' ); ?></div>
                    </label>
                </div>
            </div>
        </div>
        
        <!-- Connection Settings -->
        <div class="wpsw-panel">
            <div class="wpsw-panel-header">
                <span class="dashicons dashicons-admin-links"></span>
                <h2><?php esc_html_e( 'Connection Settings', 'wp-post-sync-webhook' ); ?></h2>
            </div>
            <div class="wpsw-panel-body">
                
                <div class="wpsw-form-group">
                    <label for="wpsw-receiver-url"><?php esc_html_e( 'Receiver Webhook URL', 'wp-post-sync-webhook' ); ?></label>
                    <input type="url" 
                           id="wpsw-receiver-url"
                           name="wpsw_settings[receiver_url]" 
                           value="<?php echo esc_attr( $settings['receiver_url'] ); ?>" 
                           placeholder="https://example.com/wp-json/post-sync/v1/receive"
                           class="wpsw-input">
                    <p class="wpsw-description">
                        <?php esc_html_e( 'Enter the receiving site\'s webhook endpoint. Required when this site is in Sender or Both mode.', 'wp-post-sync-webhook' ); ?>
                    </p>
                </div>
                
                <div class="wpsw-form-group">
                    <label for="wpsw-secret-key"><?php esc_html_e( 'Secret Key', 'wp-post-sync-webhook' ); ?></label>
                    <div class="wpsw-input-group">
                        <input type="text" 
                               id="wpsw-secret-key"
                               name="wpsw_settings[secret_key]" 
                               value="<?php echo esc_attr( $settings['secret_key'] ); ?>" 
                               placeholder="<?php esc_attr_e( 'Enter secret key', 'wp-post-sync-webhook' ); ?>"
                               class="wpsw-input">
                        <button type="button" class="wpsw-btn wpsw-btn-secondary" id="wpsw-generate-key">
                            🎲 <?php esc_html_e( 'Generate', 'wp-post-sync-webhook' ); ?>
                        </button>
                    </div>
                    <p class="wpsw-description">
                        <strong><?php esc_html_e( 'Important:', 'wp-post-sync-webhook' ); ?></strong>
                        <?php esc_html_e( 'This key must be identical on both sender and receiver sites.', 'wp-post-sync-webhook' ); ?>
                    </p>
                </div>
                
                <div class="wpsw-form-group">
                    <label><?php esc_html_e( 'This Site\'s Receiver Endpoint', 'wp-post-sync-webhook' ); ?></label>
                    <div class="wpsw-endpoint-box">
                        <span class="dashicons dashicons-location"></span>
                        <code id="wpsw-endpoint"><?php echo esc_html( $endpoint_url ); ?></code>
                        <button type="button" class="wpsw-btn wpsw-btn-secondary" id="wpsw-copy-endpoint" data-url="<?php echo esc_attr( $endpoint_url ); ?>">
                            📋 <?php esc_html_e( 'Copy', 'wp-post-sync-webhook' ); ?>
                        </button>
                    </div>
                    <p class="wpsw-description">
                        <?php esc_html_e( 'Share this URL with the sender site to receive posts here.', 'wp-post-sync-webhook' ); ?>
                    </p>
                </div>
                
            </div>
        </div>
        
        <!-- Post Types -->
        <div class="wpsw-panel">
            <div class="wpsw-panel-header">
                <span class="dashicons dashicons-admin-post"></span>
                <h2><?php esc_html_e( 'Post Types to Sync', 'wp-post-sync-webhook' ); ?></h2>
            </div>
            <div class="wpsw-panel-body">
                <div class="wpsw-checkbox-group">
                    <?php
                    $post_types = get_post_types( array( 'public' => true ), 'objects' );
                    foreach ( $post_types as $pt ) {
                        if ( 'attachment' === $pt->name ) {
                            continue;
                        }
                        $checked = in_array( $pt->name, $settings['post_types'], true ) ? 'checked' : '';
                        ?>
                        <label class="wpsw-checkbox-item">
                            <input type="checkbox" 
                                   name="wpsw_settings[post_types][]" 
                                   value="<?php echo esc_attr( $pt->name ); ?>" 
                                   <?php echo esc_attr( $checked ); ?>>
                            <span><?php echo esc_html( $pt->label ); ?></span>
                        </label>
                        <?php
                    }
                    ?>
                </div>
                <p class="wpsw-description" style="margin-top: 15px;">
                    <?php esc_html_e( 'Select which post types should be synced when published or updated.', 'wp-post-sync-webhook' ); ?>
                </p>
            </div>
        </div>
        
        <!-- Save Button -->
        <div class="wpsw-save-section">
            <button type="submit" class="wpsw-btn wpsw-btn-primary wpsw-btn-large">
                💾 <?php esc_html_e( 'Save Settings', 'wp-post-sync-webhook' ); ?>
            </button>
        </div>
        
    </form>
    
    <!-- Tools -->
    <div class="wpsw-panel">
        <div class="wpsw-panel-header">
            <span class="dashicons dashicons-admin-tools"></span>
            <h2><?php esc_html_e( 'Tools', 'wp-post-sync-webhook' ); ?></h2>
        </div>
        <div class="wpsw-panel-body">
            <div class="wpsw-tools-grid">
                
                <!-- Test Connection -->
                <div class="wpsw-tool-card">
                    <h3>🔌 <?php esc_html_e( 'Test Connection', 'wp-post-sync-webhook' ); ?></h3>
                    <p><?php esc_html_e( 'Verify the connection to the receiver site.', 'wp-post-sync-webhook' ); ?></p>
                    <button type="button" class="wpsw-btn wpsw-btn-secondary" id="wpsw-test-btn">
                        <?php esc_html_e( 'Test Connection', 'wp-post-sync-webhook' ); ?>
                    </button>
                    <div class="wpsw-result" id="wpsw-test-result"></div>
                </div>
                
                <!-- Manual Sync -->
                <div class="wpsw-tool-card">
                    <h3>📤 <?php esc_html_e( 'Manual Sync', 'wp-post-sync-webhook' ); ?></h3>
                    <p><?php esc_html_e( 'Manually sync a specific post by ID.', 'wp-post-sync-webhook' ); ?></p>
                    <div class="wpsw-sync-input">
                        <input type="number" id="wpsw-sync-post-id" placeholder="<?php esc_attr_e( 'Post ID', 'wp-post-sync-webhook' ); ?>" min="1">
                    </div>
                    <button type="button" class="wpsw-btn wpsw-btn-secondary" id="wpsw-sync-btn">
                        <?php esc_html_e( 'Sync Post', 'wp-post-sync-webhook' ); ?>
                    </button>
                    <div class="wpsw-result" id="wpsw-sync-result"></div>
                </div>
                
                <!-- Health Check -->
                <div class="wpsw-tool-card">
                    <h3>❤️ <?php esc_html_e( 'Health Check', 'wp-post-sync-webhook' ); ?></h3>
                    <p><?php esc_html_e( 'Check if the receiver endpoint is responding.', 'wp-post-sync-webhook' ); ?></p>
                    <button type="button" class="wpsw-btn wpsw-btn-secondary" id="wpsw-health-btn">
                        <?php esc_html_e( 'Check Health', 'wp-post-sync-webhook' ); ?>
                    </button>
                    <div class="wpsw-result" id="wpsw-health-result"></div>
                </div>
                
            </div>
        </div>
    </div>
    
    <!-- Help -->
    <div class="wpsw-panel">
        <div class="wpsw-panel-header">
            <span class="dashicons dashicons-editor-help"></span>
            <h2><?php esc_html_e( 'Quick Setup Guide', 'wp-post-sync-webhook' ); ?></h2>
        </div>
        <div class="wpsw-panel-body">
            <div class="wpsw-help-grid">
                <div class="wpsw-help-section">
                    <h4>📤 <?php esc_html_e( 'Sender Site Setup', 'wp-post-sync-webhook' ); ?></h4>
                    <ol>
                        <li><?php esc_html_e( 'Set mode to "Sender"', 'wp-post-sync-webhook' ); ?></li>
                        <li><?php esc_html_e( 'Enter the receiver\'s webhook URL', 'wp-post-sync-webhook' ); ?></li>
                        <li><?php esc_html_e( 'Copy the secret key to the receiver site', 'wp-post-sync-webhook' ); ?></li>
                        <li><?php esc_html_e( 'Select post types to sync', 'wp-post-sync-webhook' ); ?></li>
                        <li><?php esc_html_e( 'Save settings', 'wp-post-sync-webhook' ); ?></li>
                    </ol>
                </div>
                <div class="wpsw-help-section">
                    <h4>📥 <?php esc_html_e( 'Receiver Site Setup', 'wp-post-sync-webhook' ); ?></h4>
                    <ol>
                        <li><?php esc_html_e( 'Set mode to "Receiver"', 'wp-post-sync-webhook' ); ?></li>
                        <li><?php esc_html_e( 'Paste the same secret key from sender', 'wp-post-sync-webhook' ); ?></li>
                        <li><?php esc_html_e( 'Copy "This Site\'s Receiver Endpoint"', 'wp-post-sync-webhook' ); ?></li>
                        <li><?php esc_html_e( 'Give that URL to the sender site', 'wp-post-sync-webhook' ); ?></li>
                        <li><?php esc_html_e( 'Save settings', 'wp-post-sync-webhook' ); ?></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="wpsw-footer">
        <p>
            <?php
            printf(
                /* translators: %s: developer website link */
                esc_html__( 'Developed by %s', 'wp-post-sync-webhook' ),
                '<a href="https://softart.com.np" target="_blank">Prakash Tyata</a>'
            );
            ?>
            | 
            <a href="https://softart.com.np/support" target="_blank"><?php esc_html_e( 'Support', 'wp-post-sync-webhook' ); ?></a>
            | 
            <a href="https://softart.com.np/docs/wp-post-sync-webhook" target="_blank"><?php esc_html_e( 'Documentation', 'wp-post-sync-webhook' ); ?></a>
        </p>
    </div>
    
</div>