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

$settings     = WPSW_Settings::get_settings();
$endpoint_url = rest_url( 'post-sync/v1/receive' );
$mode         = $settings['mode'];
$rules        = $settings['sync_rules'] ?? WPSW_Settings::get_default_rules();
$receivers    = $settings['receivers'] ?? array();
$active_tab   = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';
$valid_tabs   = array( 'dashboard', 'connections', 'sync-rules', 'tools' );
if ( ! in_array( $active_tab, $valid_tabs, true ) ) {
    $active_tab = 'dashboard';
}
$receiver_count = count( array_filter( $receivers, function ( $r ) {
    return ! empty( $r['enabled'] );
} ) );
$total_synced = intval( $settings['sync_count'] ?? 0 );
?>

<div class="wpsw-wrap">

    <!-- HEADER -->
    <div class="wpsw-header">
        <div class="wpsw-header-left">
            <h1><?php esc_html_e( 'WP Post Sync Webhook', 'softart-post-sync-webhook' ); ?></h1>
            <p class="wpsw-header-subtitle">
                <?php esc_html_e( 'Sync posts, pages, and Elementor-built content between WordPress sites.', 'softart-post-sync-webhook' ); ?>
            </p>
        </div>
        <div class="wpsw-header-right">
            <span class="wpsw-version">v<?php echo esc_html( WPSW_VERSION ); ?></span>
            <span class="wpsw-mode-badge wpsw-mode-badge-<?php echo esc_attr( $mode ); ?>" id="wpsw-current-mode-badge">
                <?php
                $mode_labels = array(
                    'sender'   => '📤 ' . esc_html__( 'Sender', 'softart-post-sync-webhook' ),
                    'receiver' => '📥 ' . esc_html__( 'Receiver', 'softart-post-sync-webhook' ),
                    'both'     => '🔄 ' . esc_html__( 'Two-Way', 'softart-post-sync-webhook' ),
                );
                echo $mode_labels[ $mode ] ?? $mode_labels['sender'];
                ?>
            </span>
        </div>
    </div>

    <!-- TAB NAVIGATION -->
    <nav class="wpsw-tabs" role="tablist">
        <a href="?page=wp-post-sync-webhook&tab=dashboard"
           class="wpsw-tab <?php echo 'dashboard' === $active_tab ? 'active' : ''; ?>"
           role="tab">
            <span class="dashicons dashicons-dashboard"></span>
            <?php esc_html_e( 'Dashboard', 'softart-post-sync-webhook' ); ?>
        </a>
        <a href="?page=wp-post-sync-webhook&tab=connections"
           class="wpsw-tab <?php echo 'connections' === $active_tab ? 'active' : ''; ?>"
           role="tab">
            <span class="dashicons dashicons-admin-links"></span>
            <?php esc_html_e( 'Connections', 'softart-post-sync-webhook' ); ?>
            <span class="wpsw-tab-badge"><?php echo $receiver_count; ?></span>
        </a>
        <a href="?page=wp-post-sync-webhook&tab=sync-rules"
           class="wpsw-tab <?php echo 'sync-rules' === $active_tab ? 'active' : ''; ?>"
           role="tab">
            <span class="dashicons dashicons-randomize"></span>
            <?php esc_html_e( 'Sync Rules', 'softart-post-sync-webhook' ); ?>
        </a>
        <a href="?page=wp-post-sync-webhook&tab=tools"
           class="wpsw-tab <?php echo 'tools' === $active_tab ? 'active' : ''; ?>"
           role="tab">
            <span class="dashicons dashicons-admin-tools"></span>
            <?php esc_html_e( 'Tools', 'softart-post-sync-webhook' ); ?>
        </a>
    </nav>

    <!-- ═══════════════════════════════════════
         TAB: DASHBOARD
    ═══════════════════════════════════════ -->
    <?php if ( 'dashboard' === $active_tab ) : ?>
    <div class="wpsw-tab-content">

        <div class="wpsw-cards">
            <div class="wpsw-card wpsw-status-card">
                <span class="wpsw-card-icon">
                    <?php echo 'sender' === $mode ? '📤' : ( 'receiver' === $mode ? '📥' : '🔄' ); ?>
                </span>
                <div class="wpsw-card-value"><?php echo esc_html( ucfirst( $mode ) ); ?></div>
                <div class="wpsw-card-label"><?php esc_html_e( 'Mode', 'softart-post-sync-webhook' ); ?></div>
            </div>
            <div class="wpsw-card wpsw-status-card">
                <span class="wpsw-card-icon">📡</span>
                <div class="wpsw-card-value"><?php echo $receiver_count; ?></div>
                <div class="wpsw-card-label"><?php esc_html_e( 'Active Receivers', 'softart-post-sync-webhook' ); ?></div>
            </div>
            <div class="wpsw-card wpsw-status-card">
                <span class="wpsw-card-icon">📊</span>
                <div class="wpsw-card-value"><?php echo $total_synced; ?></div>
                <div class="wpsw-card-label"><?php esc_html_e( 'Total Synced', 'softart-post-sync-webhook' ); ?></div>
            </div>
            <div class="wpsw-card wpsw-status-card">
                <span class="wpsw-card-icon">🕐</span>
                <div class="wpsw-card-value">
                    <?php
                    if ( ! empty( $settings['last_sync'] ) ) {
                        printf(
                            esc_html__( '%s ago', 'softart-post-sync-webhook' ),
                            esc_html( human_time_diff( strtotime( $settings['last_sync'] ) ) )
                        );
                    } else {
                        esc_html_e( 'Never', 'softart-post-sync-webhook' );
                    }
                    ?>
                </div>
                <div class="wpsw-card-label"><?php esc_html_e( 'Last Activity', 'softart-post-sync-webhook' ); ?></div>
            </div>
        </div>

        <!-- Receiver Status List -->
        <div class="wpsw-panel">
            <div class="wpsw-panel-header">
                <span class="dashicons dashicons-sos"></span>
                <h2><?php esc_html_e( 'Receiver Status', 'softart-post-sync-webhook' ); ?></h2>
            </div>
            <div class="wpsw-panel-body">
                <?php if ( empty( $receivers ) ) : ?>
                    <p class="wpsw-empty-state">
                        <?php esc_html_e( 'No receivers configured. Go to Connections tab to add one.', 'softart-post-sync-webhook' ); ?>
                    </p>
                <?php else : ?>
                    <div class="wpsw-receiver-status-list">
                        <?php foreach ( $receivers as $recv ) : ?>
                            <div class="wpsw-receiver-status-item">
                                <span class="wpsw-status-dot wpsw-status-<?php echo ! empty( $recv['enabled'] ) ? 'ok' : 'disabled'; ?>"></span>
                                <div class="wpsw-receiver-status-info">
                                    <strong><?php echo esc_html( $recv['name'] ?: $recv['url'] ); ?></strong>
                                    <span class="wpsw-receiver-status-url"><?php echo esc_url( $recv['url'] ); ?></span>
                                </div>
                                <div class="wpsw-receiver-status-meta">
                                    <span class="wpsw-rstat"><?php echo intval( $recv['sync_count'] ?? 0 ); ?> <?php esc_html_e( 'synced', 'softart-post-sync-webhook' ); ?></span>
                                    <span class="wpsw-rstat">
                                        <?php if ( ! empty( $recv['last_sync'] ) ) : ?>
                                            <?php echo esc_html( human_time_diff( strtotime( $recv['last_sync'] ) ) ); ?> ago
                                        <?php else : ?>
                                            <?php esc_html_e( 'Never', 'softart-post-sync-webhook' ); ?>
                                        <?php endif; ?>
                                    </span>
                                    <span class="wpsw-status-badge wpsw-status-<?php echo esc_attr( $recv['last_status'] ?? 'pending' ); ?>">
                                        <?php echo esc_html( ucfirst( $recv['last_status'] ?? 'pending' ) ); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Endpoint URL (for receiver/both modes) -->
        <?php if ( in_array( $mode, array( 'receiver', 'both' ), true ) ) : ?>
        <div class="wpsw-panel">
            <div class="wpsw-panel-header">
                <span class="dashicons dashicons-location"></span>
                <h2><?php esc_html_e( 'Your Webhook Endpoint', 'softart-post-sync-webhook' ); ?></h2>
            </div>
            <div class="wpsw-panel-body">
                <div class="wpsw-endpoint-box" id="wpsw-endpoint-card">
                    <span class="dashicons dashicons-rest-api"></span>
                    <code id="wpsw-endpoint-code"><?php echo esc_html( $endpoint_url ); ?></code>
                    <button type="button" class="wpsw-btn wpsw-btn-success" id="wpsw-copy-endpoint" data-url="<?php echo esc_attr( $endpoint_url ); ?>">
                        <?php esc_html_e( 'Copy Endpoint URL', 'softart-post-sync-webhook' ); ?>
                    </button>
                </div>
                <div class="wpsw-endpoint-hint">
                    <span class="dashicons dashicons-info"></span>
                    <div>
                        <strong><?php esc_html_e( 'Receiver Site Action:', 'softart-post-sync-webhook' ); ?></strong>
                        <?php esc_html_e( 'Copy this URL and paste it into the "Receiver Webhook URL" field on your Sender site(s).', 'softart-post-sync-webhook' ); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════
         TAB: CONNECTIONS
    ═══════════════════════════════════════ -->
    <?php if ( 'connections' === $active_tab ) : ?>
    <form method="post" action="options.php" id="wpsw-settings-form">
        <?php settings_fields( 'wpsw_settings_group' ); ?>
        <div class="wpsw-tab-content">

            <!-- Mode Selection -->
            <div class="wpsw-panel">
                <div class="wpsw-panel-header">
                    <span class="dashicons dashicons-randomize"></span>
                    <h2><?php esc_html_e( 'Site Role', 'softart-post-sync-webhook' ); ?></h2>
                </div>
                <div class="wpsw-panel-body">
                    <div class="wpsw-mode-options">
                        <label class="wpsw-mode-option <?php echo 'sender' === $mode ? 'active' : ''; ?>" data-mode="sender">
                            <input type="radio" name="wpsw_settings[mode]" value="sender" <?php checked( $mode, 'sender' ); ?>>
                            <div class="wpsw-mode-icon">📤</div>
                            <div class="wpsw-mode-title"><?php esc_html_e( 'Sender', 'softart-post-sync-webhook' ); ?></div>
                            <div class="wpsw-mode-desc"><?php esc_html_e( 'Push posts to other sites.', 'softart-post-sync-webhook' ); ?></div>
                        </label>
                        <label class="wpsw-mode-option <?php echo 'receiver' === $mode ? 'active' : ''; ?>" data-mode="receiver">
                            <input type="radio" name="wpsw_settings[mode]" value="receiver" <?php checked( $mode, 'receiver' ); ?>>
                            <div class="wpsw-mode-icon">📥</div>
                            <div class="wpsw-mode-title"><?php esc_html_e( 'Receiver', 'softart-post-sync-webhook' ); ?></div>
                            <div class="wpsw-mode-desc"><?php esc_html_e( 'Accept posts from other sites.', 'softart-post-sync-webhook' ); ?></div>
                        </label>
                        <label class="wpsw-mode-option <?php echo 'both' === $mode ? 'active' : ''; ?>" data-mode="both">
                            <input type="radio" name="wpsw_settings[mode]" value="both" <?php checked( $mode, 'both' ); ?>>
                            <div class="wpsw-mode-icon">🔄</div>
                            <div class="wpsw-mode-title"><?php esc_html_e( 'Two-Way', 'softart-post-sync-webhook' ); ?></div>
                            <div class="wpsw-mode-desc"><?php esc_html_e( 'Send and receive.', 'softart-post-sync-webhook' ); ?></div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Receiver Sites -->
            <div class="wpsw-panel">
                <div class="wpsw-panel-header">
                    <span class="dashicons dashicons-admin-links"></span>
                    <h2><?php esc_html_e( 'Receiver Sites', 'softart-post-sync-webhook' ); ?></h2>
                    <div class="wpsw-header-actions">
                        <button type="button" class="wpsw-btn wpsw-btn-primary wpsw-btn-sm" id="wpsw-add-receiver">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php esc_html_e( 'Add Receiver', 'softart-post-sync-webhook' ); ?>
                        </button>
                    </div>
                </div>
                <div class="wpsw-panel-body">
                    <div id="wpsw-receivers-container" class="wpsw-receivers-repeater">
                        <?php if ( empty( $receivers ) ) : ?>
                            <div class="wpsw-empty-receivers">
                                <p><?php esc_html_e( 'No receiver sites configured. Click "Add Receiver" to get started.', 'softart-post-sync-webhook' ); ?></p>
                            </div>
                        <?php endif; ?>

                        <?php foreach ( $receivers as $index => $recv ) : ?>
                            <div class="wpsw-receiver-card" data-index="<?php echo $index; ?>">
                                <div class="wpsw-receiver-card-header">
                                    <div class="wpsw-receiver-drag-handle">
                                        <span class="dashicons dashicons-move"></span>
                                    </div>
                                    <div class="wpsw-receiver-card-title">
                                        <input type="text"
                                               name="wpsw_settings[receivers][<?php echo $index; ?>][name]"
                                               value="<?php echo esc_attr( $recv['name'] ); ?>"
                                               class="wpsw-receiver-name-input"
                                               placeholder="<?php esc_attr_e( 'Receiver name...', 'softart-post-sync-webhook' ); ?>">
                                    </div>
                                    <label class="wpsw-toggle">
                                        <input type="checkbox"
                                               name="wpsw_settings[receivers][<?php echo $index; ?>][enabled]"
                                               value="1"
                                               <?php checked( ! empty( $recv['enabled'] ) ); ?>>
                                        <span class="wpsw-toggle-slider"></span>
                                    </label>
                                    <button type="button" class="wpsw-btn-icon wpsw-receiver-toggle" title="<?php esc_attr_e( 'Expand/Collapse', 'softart-post-sync-webhook' ); ?>">
                                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                                    </button>
                                    <button type="button" class="wpsw-btn-icon wpsw-btn-danger wpsw-remove-receiver" title="<?php esc_attr_e( 'Remove', 'softart-post-sync-webhook' ); ?>">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                                <div class="wpsw-receiver-card-body">
                                    <input type="hidden"
                                           name="wpsw_settings[receivers][<?php echo $index; ?>][id]"
                                           value="<?php echo esc_attr( $recv['id'] ); ?>">

                                    <div class="wpsw-field-row">
                                        <label>
                                            <?php esc_html_e( 'Webhook URL', 'softart-post-sync-webhook' ); ?>
                                            <span class="wpsw-help-tip" tabindex="0" aria-label="help">
                                                <span class="dashicons dashicons-editor-help"></span>
                                                <span class="wpsw-tooltip wpsw-tooltip-wide">
                                                    <strong><?php esc_html_e( 'Where to get this:', 'softart-post-sync-webhook' ); ?></strong>
                                                    <?php esc_html_e( 'This is the receive endpoint on the destination (receiver) site. On that site, open Post Sync Webhook &rarr; Connections and copy the "Your Webhook Endpoint" URL, then paste it here.', 'softart-post-sync-webhook' ); ?>
                                                </span>
                                            </span>
                                        </label>
                                        <div class="wpsw-field-input-group">
                                            <input type="url"
                                                   name="wpsw_settings[receivers][<?php echo $index; ?>][url]"
                                                   value="<?php echo esc_attr( $recv['url'] ); ?>"
                                                   class="wpsw-input wpsw-input-full wpsw-receiver-url"
                                                   placeholder="https://example.com/wp-json/post-sync/v1/receive">
                                            <span class="wpsw-url-format-check"></span>
                                        </div>
                                    </div>

                                    <div class="wpsw-field-row">
                                        <label>
                                            <?php esc_html_e( 'Secret Key', 'softart-post-sync-webhook' ); ?>
                                            <span class="wpsw-help-tip" tabindex="0" aria-label="help">
                                                <span class="dashicons dashicons-editor-help"></span>
                                                <span class="wpsw-tooltip">
                                                    <strong><?php esc_html_e( 'Where to get / put this:', 'softart-post-sync-webhook' ); ?></strong>
                                                    <?php esc_html_e( 'A shared password between both sites. Use the "Secret Key" shown on the receiver site (Connections tab) and paste the exact same key here, OR generate one here and paste it on the receiver site. Both sides must match.', 'softart-post-sync-webhook' ); ?>
                                                </span>
                                            </span>
                                        </label>
                                        <div class="wpsw-field-input-group wpsw-key-group">
                                            <input type="password"
                                                   name="wpsw_settings[receivers][<?php echo $index; ?>][secret_key]"
                                                   value="<?php echo esc_attr( $recv['secret_key'] ); ?>"
                                                   class="wpsw-input wpsw-input-full wpsw-secret-key"
                                                   placeholder="<?php esc_attr_e( 'Enter or generate key...', 'softart-post-sync-webhook' ); ?>">
                                            <button type="button" class="wpsw-btn-icon wpsw-toggle-key-visibility" title="<?php esc_attr_e( 'Show/Hide', 'softart-post-sync-webhook' ); ?>">
                                                <span class="dashicons dashicons-visibility"></span>
                                            </button>
                                            <button type="button" class="wpsw-btn wpsw-btn-secondary wpsw-generate-key" title="<?php esc_attr_e( 'Generate Key', 'softart-post-sync-webhook' ); ?>">
                                                <span class="dashicons dashicons-update"></span>
                                            </button>
                                            <button type="button" class="wpsw-btn wpsw-btn-secondary wpsw-copy-key" title="<?php esc_attr_e( 'Copy Key', 'softart-post-sync-webhook' ); ?>">
                                                <span class="dashicons dashicons-clipboard"></span>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="wpsw-field-row">
                                        <label><?php esc_html_e( 'Post Types', 'softart-post-sync-webhook' ); ?></label>
                                        <div class="wpsw-checkbox-group wpsw-pt-checkboxes">
                                            <?php
                                            $post_types = get_post_types( array( 'public' => true ), 'objects' );
                                            $selected   = $recv['post_types'] ?? array( 'post' );
                                            foreach ( $post_types as $pt ) {
                                                if ( 'attachment' === $pt->name ) {
                                                    continue;
                                                }
                                                $checked = in_array( $pt->name, $selected, true ) ? 'checked' : '';
                                                ?>
                                                <label class="wpsw-checkbox-item <?php echo $checked ? 'checked' : ''; ?>">
                                                    <input type="checkbox"
                                                           name="wpsw_settings[receivers][<?php echo $index; ?>][post_types][]"
                                                           value="<?php echo esc_attr( $pt->name ); ?>"
                                                           <?php echo $checked; ?>>
                                                    <span class="wpsw-pt-label"><?php echo esc_html( $pt->label ); ?></span>
                                                </label>
                                                <?php
                                            }
                                            ?>
                                        </div>
                                    </div>

                                    <div class="wpsw-receiver-card-actions">
                                        <button type="button" class="wpsw-btn wpsw-btn-secondary wpsw-test-connection" data-receiver-id="<?php echo esc_attr( $recv['id'] ); ?>">
                                            <span class="dashicons dashicons-sos"></span>
                                            <?php esc_html_e( 'Test Connection', 'softart-post-sync-webhook' ); ?>
                                        </button>
                                        <div class="wpsw-receiver-result"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Endpoint URL & Secret Key -->
            <?php if ( in_array( $mode, array( 'receiver', 'both' ), true ) ) : ?>
            <div class="wpsw-panel">
                <div class="wpsw-panel-header">
                    <span class="dashicons dashicons-location"></span>
                    <h2><?php esc_html_e( 'Your Webhook Endpoint &amp; Secret Key', 'softart-post-sync-webhook' ); ?></h2>
                </div>
                <div class="wpsw-panel-body">

                    <div class="wpsw-endpoint-box" id="wpsw-endpoint-card">
                        <span class="dashicons dashicons-rest-api"></span>
                        <code id="wpsw-endpoint-code"><?php echo esc_html( $endpoint_url ); ?></code>
                        <button type="button" class="wpsw-btn wpsw-btn-success" id="wpsw-copy-endpoint" data-url="<?php echo esc_attr( $endpoint_url ); ?>">
                            <?php esc_html_e( 'Copy Endpoint URL', 'softart-post-sync-webhook' ); ?>
                        </button>
                    </div>

                    <div class="wpsw-receiver-key-box">
                        <div class="wpsw-receiver-key-label">
                            <span class="dashicons dashicons-lock"></span>
                            <span>
                                <strong><?php esc_html_e( 'Your Secret Key', 'softart-post-sync-webhook' ); ?></strong>
                                <em><?php esc_html_e( "Share this with your Sender site(s) - they must paste the exact same key in the receiver's \"Secret Key\" field.", 'softart-post-sync-webhook' ); ?></em>
                            </span>
                        </div>
                        <div class="wpsw-field-input-group wpsw-key-group">
                            <input type="text"
                                   name="wpsw_settings[secret_key]"
                                   value="<?php echo esc_attr( $settings['secret_key'] ?? '' ); ?>"
                                   class="wpsw-input wpsw-input-full wpsw-secret-key wpsw-receiver-secret"
                                   placeholder="<?php esc_attr_e( 'Generate or enter a key...', 'softart-post-sync-webhook' ); ?>">
                            <button type="button" class="wpsw-btn-icon wpsw-toggle-key-visibility" title="<?php esc_attr_e( 'Show/Hide', 'softart-post-sync-webhook' ); ?>">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                            <button type="button" class="wpsw-btn wpsw-btn-secondary wpsw-generate-key" title="<?php esc_attr_e( 'Generate Key', 'softart-post-sync-webhook' ); ?>">
                                <span class="dashicons dashicons-update"></span>
                            </button>
                            <button type="button" class="wpsw-btn wpsw-btn-secondary wpsw-copy-key" title="<?php esc_attr_e( 'Copy Key', 'softart-post-sync-webhook' ); ?>">
                                <span class="dashicons dashicons-clipboard"></span>
                            </button>
                        </div>
                        <p class="wpsw-description"><?php esc_html_e( 'Save Settings after changing the key. Both the sender and receiver must use the same secret key for syncs to authenticate.', 'softart-post-sync-webhook' ); ?></p>
                    </div>

                </div>
            </div>
            <?php endif; ?>

            <div class="wpsw-save-section">
                <button type="submit" class="wpsw-btn wpsw-btn-primary wpsw-btn-large">
                    <?php esc_html_e( 'Save Settings', 'softart-post-sync-webhook' ); ?>
                </button>
            </div>

        </div>
    </form>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════
         TAB: SYNC RULES
    ═══════════════════════════════════════ -->
    <?php if ( 'sync-rules' === $active_tab ) : ?>
    <form method="post" action="options.php" id="wpsw-settings-form">
        <?php settings_fields( 'wpsw_settings_group' ); ?>
        <input type="hidden" name="wpsw_settings[mode]" value="<?php echo esc_attr( $mode ); ?>">
        <input type="hidden" name="wpsw_settings[secret_key]" value="<?php echo esc_attr( $settings['secret_key'] ?? '' ); ?>">
        <?php foreach ( $receivers as $index => $recv ) : ?>
            <input type="hidden" name="wpsw_settings[receivers][<?php echo $index; ?>][id]" value="<?php echo esc_attr( $recv['id'] ); ?>">
            <input type="hidden" name="wpsw_settings[receivers][<?php echo $index; ?>][name]" value="<?php echo esc_attr( $recv['name'] ); ?>">
            <input type="hidden" name="wpsw_settings[receivers][<?php echo $index; ?>][url]" value="<?php echo esc_attr( $recv['url'] ); ?>">
            <input type="hidden" name="wpsw_settings[receivers][<?php echo $index; ?>][secret_key]" value="<?php echo esc_attr( $recv['secret_key'] ); ?>">
            <?php if ( ! empty( $recv['enabled'] ) ) : ?>
                <input type="hidden" name="wpsw_settings[receivers][<?php echo $index; ?>][enabled]" value="1">
            <?php endif; ?>
            <?php foreach ( $recv['post_types'] ?? array( 'post' ) as $pt ) : ?>
                <input type="hidden" name="wpsw_settings[receivers][<?php echo $index; ?>][post_types][]" value="<?php echo esc_attr( $pt ); ?>">
            <?php endforeach; ?>
        <?php endforeach; ?>
        <div class="wpsw-tab-content">

            <div class="wpsw-panel">
                <div class="wpsw-panel-header">
                    <span class="dashicons dashicons-edit"></span>
                    <h2><?php esc_html_e( 'Content Fields', 'softart-post-sync-webhook' ); ?></h2>
                </div>
                <div class="wpsw-panel-body">
                    <div class="wpsw-checkbox-group wpsw-rules-group">
                        <label class="wpsw-checkbox-item <?php echo ! empty( $rules['sync_title'] ) ? 'checked' : ''; ?>">
                            <input type="checkbox" name="wpsw_settings[sync_rules][sync_title]" value="1" <?php checked( ! empty( $rules['sync_title'] ) ); ?>>
                            <span class="wpsw-pt-label"><?php esc_html_e( 'Title', 'softart-post-sync-webhook' ); ?></span>
                        </label>
                        <label class="wpsw-checkbox-item <?php echo ! empty( $rules['sync_content'] ) ? 'checked' : ''; ?>">
                            <input type="checkbox" name="wpsw_settings[sync_rules][sync_content]" value="1" <?php checked( ! empty( $rules['sync_content'] ) ); ?>>
                            <span class="wpsw-pt-label"><?php esc_html_e( 'Content', 'softart-post-sync-webhook' ); ?></span>
                        </label>
                        <label class="wpsw-checkbox-item <?php echo ! empty( $rules['sync_excerpt'] ) ? 'checked' : ''; ?>">
                            <input type="checkbox" name="wpsw_settings[sync_rules][sync_excerpt]" value="1" <?php checked( ! empty( $rules['sync_excerpt'] ) ); ?>>
                            <span class="wpsw-pt-label"><?php esc_html_e( 'Excerpt', 'softart-post-sync-webhook' ); ?></span>
                        </label>
                        <label class="wpsw-checkbox-item <?php echo ! empty( $rules['sync_slug'] ) ? 'checked' : ''; ?>">
                            <input type="checkbox" name="wpsw_settings[sync_rules][sync_slug]" value="1" <?php checked( ! empty( $rules['sync_slug'] ) ); ?>>
                            <span class="wpsw-pt-label"><?php esc_html_e( 'Slug', 'softart-post-sync-webhook' ); ?></span>
                        </label>
                        <label class="wpsw-checkbox-item <?php echo ! empty( $rules['sync_status'] ) ? 'checked' : ''; ?>">
                            <input type="checkbox" name="wpsw_settings[sync_rules][sync_status]" value="1" <?php checked( ! empty( $rules['sync_status'] ) ); ?>>
                            <span class="wpsw-pt-label"><?php esc_html_e( 'Status', 'softart-post-sync-webhook' ); ?></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="wpsw-panel">
                <div class="wpsw-panel-header">
                    <span class="dashicons dashicons-category"></span>
                    <h2><?php esc_html_e( 'Taxonomies & Media', 'softart-post-sync-webhook' ); ?></h2>
                </div>
                <div class="wpsw-panel-body">
                    <div class="wpsw-checkbox-group wpsw-rules-group">
                        <label class="wpsw-checkbox-item <?php echo ! empty( $rules['sync_categories'] ) ? 'checked' : ''; ?>">
                            <input type="checkbox" name="wpsw_settings[sync_rules][sync_categories]" value="1" <?php checked( ! empty( $rules['sync_categories'] ) ); ?>>
                            <span class="wpsw-pt-label"><?php esc_html_e( 'Categories', 'softart-post-sync-webhook' ); ?></span>
                        </label>
                        <label class="wpsw-checkbox-item <?php echo ! empty( $rules['sync_tags'] ) ? 'checked' : ''; ?>">
                            <input type="checkbox" name="wpsw_settings[sync_rules][sync_tags]" value="1" <?php checked( ! empty( $rules['sync_tags'] ) ); ?>>
                            <span class="wpsw-pt-label"><?php esc_html_e( 'Tags', 'softart-post-sync-webhook' ); ?></span>
                        </label>
                        <label class="wpsw-checkbox-item <?php echo ! empty( $rules['sync_featured_img'] ) ? 'checked' : ''; ?>">
                            <input type="checkbox" name="wpsw_settings[sync_rules][sync_featured_img]" value="1" <?php checked( ! empty( $rules['sync_featured_img'] ) ); ?>>
                            <span class="wpsw-pt-label"><?php esc_html_e( 'Featured Image', 'softart-post-sync-webhook' ); ?></span>
                        </label>
                        <label class="wpsw-checkbox-item <?php echo ! empty( $rules['sync_content_imgs'] ) ? 'checked' : ''; ?>">
                            <input type="checkbox" name="wpsw_settings[sync_rules][sync_content_imgs]" value="1" <?php checked( ! empty( $rules['sync_content_imgs'] ) ); ?>>
                            <span class="wpsw-pt-label"><?php esc_html_e( 'Content Images', 'softart-post-sync-webhook' ); ?></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="wpsw-panel">
                <div class="wpsw-panel-header">
                    <span class="dashicons dashicons-code"></span>
                    <h2><?php esc_html_e( 'Meta & Builder Data', 'softart-post-sync-webhook' ); ?></h2>
                </div>
                <div class="wpsw-panel-body">
                    <div class="wpsw-checkbox-group wpsw-rules-group">
                        <label class="wpsw-checkbox-item <?php echo ! empty( $rules['sync_meta'] ) ? 'checked' : ''; ?>">
                            <input type="checkbox" name="wpsw_settings[sync_rules][sync_meta]" value="1" <?php checked( ! empty( $rules['sync_meta'] ) ); ?>>
                            <span class="wpsw-pt-label"><?php esc_html_e( 'Custom Fields (Post Meta)', 'softart-post-sync-webhook' ); ?></span>
                        </label>
                        <label class="wpsw-checkbox-item <?php echo ! empty( $rules['sync_builder_data'] ) ? 'checked' : ''; ?>">
                            <input type="checkbox" name="wpsw_settings[sync_rules][sync_builder_data]" value="1" <?php checked( ! empty( $rules['sync_builder_data'] ) ); ?>>
                            <span class="wpsw-pt-label"><?php esc_html_e( 'Page Builder Data (Elementor/Divi)', 'softart-post-sync-webhook' ); ?></span>
                        </label>
                    </div>

                    <div class="wpsw-field-row" style="margin-top:16px;">
                        <label for="wpsw-meta-exclude"><?php esc_html_e( 'Meta Keys to Exclude', 'softart-post-sync-webhook' ); ?></label>
                        <input type="text"
                               id="wpsw-meta-exclude"
                               name="wpsw_settings[sync_rules][meta_exclude]"
                               value="<?php echo esc_attr( $rules['meta_exclude'] ?? '' ); ?>"
                               class="wpsw-input wpsw-input-full"
                               placeholder="_edit_lock, _edit_last, _thumbnail_id">
                        <p class="wpsw-description"><?php esc_html_e( 'Comma-separated list of meta keys to exclude from sync.', 'softart-post-sync-webhook' ); ?></p>
                    </div>

                    <div class="wpsw-field-row" style="margin-top:16px;">
                        <label for="wpsw-default-status"><?php esc_html_e( 'Default Post Status', 'softart-post-sync-webhook' ); ?></label>
                        <select id="wpsw-default-status"
                                name="wpsw_settings[sync_rules][default_status]"
                                class="wpsw-select">
                            <option value="publish" <?php selected( $rules['default_status'] ?? '', 'publish' ); ?>><?php esc_html_e( 'Published', 'softart-post-sync-webhook' ); ?></option>
                            <option value="draft" <?php selected( $rules['default_status'] ?? '', 'draft' ); ?>><?php esc_html_e( 'Draft', 'softart-post-sync-webhook' ); ?></option>
                            <option value="pending" <?php selected( $rules['default_status'] ?? '', 'pending' ); ?>><?php esc_html_e( 'Pending Review', 'softart-post-sync-webhook' ); ?></option>
                        </select>
                        <p class="wpsw-description"><?php esc_html_e( 'Status for newly received posts when the sender status cannot be preserved.', 'softart-post-sync-webhook' ); ?></p>
                    </div>
                </div>
            </div>

            <div class="wpsw-save-section">
                <button type="submit" class="wpsw-btn wpsw-btn-primary wpsw-btn-large">
                    <?php esc_html_e( 'Save Sync Rules', 'softart-post-sync-webhook' ); ?>
                </button>
            </div>

        </div>
    </form>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════
         TAB: TOOLS
    ═══════════════════════════════════════ -->
    <?php if ( 'tools' === $active_tab ) : ?>
    <div class="wpsw-tab-content">

        <div class="wpsw-panel">
            <div class="wpsw-panel-header">
                <span class="dashicons dashicons-admin-tools"></span>
                <h2><?php esc_html_e( 'Manual Sync', 'softart-post-sync-webhook' ); ?></h2>
            </div>
            <div class="wpsw-panel-body">
                <div class="wpsw-tool-inline">

                    <div class="wpsw-field-row">
                        <label><?php esc_html_e( 'Post Type', 'softart-post-sync-webhook' ); ?></label>
                        <select id="wpsw-sync-post-type" class="wpsw-select">
                            <?php
                            $tool_post_types = get_post_types( array( 'public' => true ), 'objects' );
                            foreach ( $tool_post_types as $tpt ) {
                                if ( 'attachment' === $tpt->name ) {
                                    continue;
                                }
                                ?>
                                <option value="<?php echo esc_attr( $tpt->name ); ?>"><?php echo esc_html( $tpt->label ); ?></option>
                                <?php
                            }
                            ?>
                        </select>
                        <p class="wpsw-description"><?php esc_html_e( 'Select the post type. The full list is shown below — type to search, or scroll to browse. Add posts to the selection.', 'softart-post-sync-webhook' ); ?></p>
                    </div>

                    <div class="wpsw-field-row">
                        <label><?php esc_html_e( 'Select Posts', 'softart-post-sync-webhook' ); ?></label>
                        <div class="wpsw-multi-picker">
                            <div class="wpsw-multi-search">
                                <input type="text" id="wpsw-post-search" class="wpsw-input wpsw-input-full" placeholder="<?php esc_attr_e( 'Showing all — type to search...', 'softart-post-sync-webhook' ); ?>" autocomplete="off">
                                <span class="wpsw-multi-spinner wpsw-hidden"></span>
                            </div>
                            <div class="wpsw-multi-results" id="wpsw-post-search-results"></div>
                            <div class="wpsw-multi-selected">
                                <div class="wpsw-multi-selected-head">
                                    <strong><?php esc_html_e( 'Selected', 'softart-post-sync-webhook' ); ?></strong>
                                    <span class="wpsw-selected-count" id="wpsw-selected-count">0</span>
                                </div>
                                <div class="wpsw-selected-items" id="wpsw-selected-items"></div>
                            </div>
                        </div>
                    </div>

                    <div class="wpsw-field-row">
                        <label>
                            <?php esc_html_e( 'Or Enter Post/Page IDs Manually', 'softart-post-sync-webhook' ); ?>
                            <span class="wpsw-help-tip" tabindex="0" aria-label="help">
                                <span class="dashicons dashicons-editor-help"></span>
                                <span class="wpsw-tooltip">
                                    <strong><?php esc_html_e( 'Manual IDs:', 'softart-post-sync-webhook' ); ?></strong>
                                    <?php esc_html_e( 'Type one or more post/page IDs separated by commas (e.g. 12, 45, 78) then click "Add IDs" to include them in the selection.', 'softart-post-sync-webhook' ); ?>
                                </span>
                            </span>
                        </label>
                        <div class="wpsw-field-input-group">
                            <input type="text" id="wpsw-manual-ids" class="wpsw-input wpsw-input-full" placeholder="<?php esc_attr_e( 'e.g. 12, 45, 78', 'softart-post-sync-webhook' ); ?>" autocomplete="off">
                            <button type="button" class="wpsw-btn wpsw-btn-secondary" id="wpsw-add-ids-btn">
                                <span class="dashicons dashicons-plus-alt2"></span>
                                <?php esc_html_e( 'Add IDs', 'softart-post-sync-webhook' ); ?>
                            </button>
                        </div>
                    </div>

                    <div class="wpsw-tool-inline-fields">
                        <select id="wpsw-sync-receiver-id" class="wpsw-select">
                            <option value="all"><?php esc_html_e( 'All Receivers', 'softart-post-sync-webhook' ); ?></option>
                            <?php foreach ( $receivers as $recv ) : ?>
                                <?php if ( ! empty( $recv['enabled'] ) ) : ?>
                                    <option value="<?php echo esc_attr( $recv['id'] ); ?>"><?php echo esc_html( $recv['name'] ); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="wpsw-btn wpsw-btn-primary" id="wpsw-sync-btn">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e( 'Sync Selected', 'softart-post-sync-webhook' ); ?>
                        </button>
                    </div>

                    <div class="wpsw-result" id="wpsw-sync-result"></div>
                </div>
            </div>
        </div>

        <div class="wpsw-panel">
            <div class="wpsw-panel-header">
                <span class="dashicons dashicons-sos"></span>
                <h2><?php esc_html_e( 'Connection Test', 'softart-post-sync-webhook' ); ?></h2>
            </div>
            <div class="wpsw-panel-body">
                <div class="wpsw-tool-inline">
                    <div class="wpsw-tool-inline-fields">
                        <select id="wpsw-test-receiver-id" class="wpsw-select">
                            <?php foreach ( $receivers as $recv ) : ?>
                                <?php if ( ! empty( $recv['enabled'] ) ) : ?>
                                    <option value="<?php echo esc_attr( $recv['id'] ); ?>"><?php echo esc_html( $recv['name'] ); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="wpsw-btn wpsw-btn-secondary" id="wpsw-test-btn">
                            <?php esc_html_e( 'Test Connection', 'softart-post-sync-webhook' ); ?>
                        </button>
                    </div>
                    <div class="wpsw-result" id="wpsw-test-result"></div>
                </div>
            </div>
        </div>

        <div class="wpsw-panel">
            <div class="wpsw-panel-header">
                <span class="dashicons dashicons-media-document"></span>
                <h2><?php esc_html_e( 'Activity Log', 'softart-post-sync-webhook' ); ?></h2>
                <div class="wpsw-header-actions">
                    <button type="button" class="wpsw-btn wpsw-btn-secondary wpsw-btn-sm" id="wpsw-refresh-log">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e( 'Refresh', 'softart-post-sync-webhook' ); ?>
                    </button>
                    <button type="button" class="wpsw-btn wpsw-btn-secondary wpsw-btn-sm" id="wpsw-clear-log">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e( 'Clear', 'softart-post-sync-webhook' ); ?>
                    </button>
                </div>
            </div>
            <div class="wpsw-panel-body">
                <div class="wpsw-log-container" id="wpsw-log-container">
                    <p class="wpsw-empty-state"><?php esc_html_e( 'No log entries yet.', 'softart-post-sync-webhook' ); ?></p>
                </div>
            </div>
        </div>

    </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <div class="wpsw-footer">
        <span>
            <?php
            printf(
                esc_html__( 'Developed by %s', 'softart-post-sync-webhook' ),
                '<a href="https://softart.com.np" target="_blank" rel="noopener noreferrer">Prakash Tyata</a>'
            );
            ?>
        </span>
        <span class="wpsw-footer-sep">&middot;</span>
        <a href="https://softart.com.np/support" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e( 'Support', 'softart-post-sync-webhook' ); ?>
        </a>
        <span class="wpsw-footer-sep">&middot;</span>
        <a href="https://softart.com.np/docs/wp-post-sync-webhook" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e( 'Documentation', 'softart-post-sync-webhook' ); ?>
        </a>
    </div>

</div>
