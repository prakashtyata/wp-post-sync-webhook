/**
 * Admin JavaScript for WP Post Sync Webhook
 *
 * @since      1.0.0
 * @package    WP_Post_Sync_Webhook
 * @author     Prakash Tyata <developer@softart.com.np>
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // Mode selection
        $('.wpsw-mode-option input').on('change', function() {
            $('.wpsw-mode-option').removeClass('active');
            $(this).closest('.wpsw-mode-option').addClass('active');
        });

        // Generate secret key
        $('#wpsw-generate-key').on('click', function() {
            var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            var key = '';
            for (var i = 0; i < 32; i++) {
                key += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            $('#wpsw-secret-key').val(key);
        });

        // Copy endpoint URL
        $('#wpsw-copy-endpoint').on('click', function() {
            var url = $(this).data('url');
            var btn = $(this);
            
            navigator.clipboard.writeText(url).then(function() {
                var originalText = btn.html();
                btn.html('✓ ' + wpswAdmin.i18n.copied);
                setTimeout(function() {
                    btn.html(originalText);
                }, 2000);
            });
        });

        // Test connection
        $('#wpsw-test-btn').on('click', function() {
            var resultEl = $('#wpsw-test-result');
            resultEl.removeClass('success error').addClass('loading').text('⏳ ' + wpswAdmin.i18n.testing).show();

            $.ajax({
                url: wpswAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpsw_test_connection',
                    nonce: wpswAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        resultEl.removeClass('loading error').addClass('success').text('✓ ' + response.data);
                    } else {
                        resultEl.removeClass('loading success').addClass('error').text('✗ ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    resultEl.removeClass('loading success').addClass('error').text('✗ ' + error);
                }
            });
        });

        // Manual sync
        $('#wpsw-sync-btn').on('click', function() {
            var postId = $('#wpsw-sync-post-id').val();
            var resultEl = $('#wpsw-sync-result');

            if (!postId) {
                resultEl.removeClass('success loading').addClass('error').text('✗ ' + wpswAdmin.i18n.enterPostId).show();
                return;
            }

            resultEl.removeClass('success error').addClass('loading').text('⏳ ' + wpswAdmin.i18n.syncing).show();

            $.ajax({
                url: wpswAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpsw_sync_post',
                    nonce: wpswAdmin.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        resultEl.removeClass('loading error').addClass('success').text('✓ ' + response.data);
                    } else {
                        resultEl.removeClass('loading success').addClass('error').text('✗ ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    resultEl.removeClass('loading success').addClass('error').text('✗ ' + error);
                }
            });
        });

        // Health check
        $('#wpsw-health-btn').on('click', function() {
            var resultEl = $('#wpsw-health-result');
            var receiverUrl = $('#wpsw-receiver-url').val();

            if (!receiverUrl) {
                resultEl.removeClass('success loading').addClass('error').text('✗ ' + wpswAdmin.i18n.noReceiverUrl).show();
                return;
            }

            resultEl.removeClass('success error').addClass('loading').text('⏳ ' + wpswAdmin.i18n.checking).show();

            var healthUrl = receiverUrl.replace('/receive', '/health');

            $.ajax({
                url: healthUrl,
                type: 'GET',
                success: function(response) {
                    if (response.status === 'ok') {
                        resultEl.removeClass('loading error').addClass('success')
                            .text('✓ Healthy! Version: ' + response.version + ' | Mode: ' + response.mode);
                    } else {
                        resultEl.removeClass('loading success').addClass('error').text('✗ Unexpected response');
                    }
                },
                error: function() {
                    resultEl.removeClass('loading success').addClass('error').text('✗ Could not reach endpoint');
                }
            });
        });

    });

})(jQuery);