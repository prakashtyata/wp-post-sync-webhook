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

        /**
         * 1. Mode selection
         */
        function updateModeUI(mode) {
            $('.wpsw-mode-option').removeClass('active');
            $('.wpsw-mode-option[data-mode="' + mode + '"]').addClass('active');
        }

        $('.wpsw-mode-option input').on('change', function() {
            updateModeUI($(this).val());
        });

        var initialMode = $('.wpsw-mode-option input:checked').val() || 'sender';
        updateModeUI(initialMode);

        /**
         * 2. Receiver repeater - Add
         */
        var receiverIndex = $('#wpsw-receivers-container .wpsw-receiver-card').length;

        $('#wpsw-add-receiver').on('click', function() {
            var tpl = '<div class="wpsw-receiver-card expanded" data-index="' + receiverIndex + '">' +
                '<div class="wpsw-receiver-card-header">' +
                    '<div class="wpsw-receiver-drag-handle"><span class="dashicons dashicons-move"></span></div>' +
                    '<div class="wpsw-receiver-card-title">' +
                        '<input type="text" name="wpsw_settings[receivers][' + receiverIndex + '][name]" class="wpsw-receiver-name-input" placeholder="' + (wpswAdmin.i18n.receiverName || 'Receiver name...') + '">' +
                    '</div>' +
                    '<label class="wpsw-toggle">' +
                        '<input type="checkbox" name="wpsw_settings[receivers][' + receiverIndex + '][enabled]" value="1" checked>' +
                        '<span class="wpsw-toggle-slider"></span>' +
                    '</label>' +
                    '<button type="button" class="wpsw-btn-icon wpsw-receiver-toggle"><span class="dashicons dashicons-arrow-up-alt2"></span></button>' +
                    '<button type="button" class="wpsw-btn-icon wpsw-btn-danger wpsw-remove-receiver"><span class="dashicons dashicons-trash"></span></button>' +
                '</div>' +
                '<div class="wpsw-receiver-card-body" style="display:block;">' +
                    '<input type="hidden" name="wpsw_settings[receivers][' + receiverIndex + '][id]" value="rcv_' + Math.random().toString(36).substr(2, 8) + '">' +
                    '<div class="wpsw-field-row">' +
                        '<label>' + (wpswAdmin.i18n.webhookUrl || 'Webhook URL') +
                            '<span class="wpsw-help-tip" tabindex="0"><span class="dashicons dashicons-editor-help"></span>' +
                            '<span class="wpsw-tooltip wpsw-tooltip-wide"><strong>' + (wpswAdmin.i18n.webhookUrlTipTitle || 'Where to get this:') + '</strong>' +
                            (wpswAdmin.i18n.webhookUrlTip || 'This is the receive endpoint on the destination (receiver) site. On that site, open Post Sync Webhook &rarr; Connections and copy the "Your Webhook Endpoint" URL, then paste it here.') +
                            '</span></span>' +
                        '</label>' +
                        '<div class="wpsw-field-input-group">' +
                            '<input type="url" name="wpsw_settings[receivers][' + receiverIndex + '][url]" class="wpsw-input wpsw-input-full wpsw-receiver-url" placeholder="https://example.com/wp-json/post-sync/v1/receive">' +
                        '</div>' +
                    '</div>' +
                    '<div class="wpsw-field-row">' +
                        '<label>' + (wpswAdmin.i18n.secretKey || 'Secret Key') +
                            '<span class="wpsw-help-tip" tabindex="0"><span class="dashicons dashicons-editor-help"></span>' +
                            '<span class="wpsw-tooltip"><strong>' + (wpswAdmin.i18n.secretKeyTipTitle || 'Where to get / put this:') + '</strong>' +
                            (wpswAdmin.i18n.secretKeyTip || 'A shared password between both sites. Use the "Secret Key" shown on the receiver site (Connections tab) and paste the exact same key here, OR generate one here and paste it on the receiver site. Both sides must match.') +
                            '</span></span>' +
                        '</label>' +
                        '<div class="wpsw-field-input-group wpsw-key-group">' +
                            '<input type="password" name="wpsw_settings[receivers][' + receiverIndex + '][secret_key]" class="wpsw-input wpsw-input-full wpsw-secret-key" placeholder="' + (wpswAdmin.i18n.enterKey || 'Enter or generate key...') + '">' +
                            '<button type="button" class="wpsw-btn-icon wpsw-toggle-key-visibility"><span class="dashicons dashicons-visibility"></span></button>' +
                            '<button type="button" class="wpsw-btn wpsw-btn-secondary wpsw-generate-key"><span class="dashicons dashicons-update"></span></button>' +
                            '<button type="button" class="wpsw-btn wpsw-btn-secondary wpsw-copy-key"><span class="dashicons dashicons-clipboard"></span></button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="wpsw-field-row">' +
                        '<label>' + (wpswAdmin.i18n.postTypes || 'Post Types') + '</label>' +
                        '<div class="wpsw-checkbox-group wpsw-pt-checkboxes">' +
                            generatePostTypeCheckboxes(receiverIndex) +
                        '</div>' +
                    '</div>' +
                    '<div class="wpsw-receiver-card-actions">' +
                        '<button type="button" class="wpsw-btn wpsw-btn-secondary wpsw-test-connection" data-receiver-id="new">' +
                            '<span class="dashicons dashicons-sos"></span> ' + (wpswAdmin.i18n.testConnection || 'Test Connection') +
                        '</button>' +
                        '<div class="wpsw-receiver-result"></div>' +
                    '</div>' +
                '</div>' +
            '</div>';

            $('.wpsw-empty-receivers').remove();
            $('#wpsw-receivers-container').append(tpl);
            receiverIndex++;
        });

        function generatePostTypeCheckboxes(idx) {
            var postTypes = [];
            $('#wpsw-receivers-container .wpsw-receiver-card:first .wpsw-pt-checkboxes .wpsw-checkbox-item').each(function() {
                var input = $(this).find('input[type="checkbox"]');
                postTypes.push({
                    value: input.val(),
                    label: $(this).find('.wpsw-pt-label').text()
                });
            });

            if (postTypes.length === 0) {
                postTypes = [
                    { value: 'post', label: 'Post' },
                    { value: 'page', label: 'Page' }
                ];
            }

            var html = '';
            for (var i = 0; i < postTypes.length; i++) {
                var checked = postTypes[i].value === 'post' ? 'checked' : '';
                html += '<label class="wpsw-checkbox-item ' + checked + '">' +
                    '<input type="checkbox" name="wpsw_settings[receivers][' + idx + '][post_types][]" value="' + postTypes[i].value + '" ' + checked + '>' +
                    '<span class="wpsw-pt-label">' + postTypes[i].label + '</span>' +
                '</label>';
            }
            return html;
        }

        /**
         * 3. Receiver repeater - Remove
         */
        $(document).on('click', '.wpsw-remove-receiver', function() {
            if (confirm(wpswAdmin.i18n.confirmDelete || 'Remove this receiver site?')) {
                $(this).closest('.wpsw-receiver-card').fadeOut(200, function() {
                    $(this).remove();
                });
            }
        });

        /**
         * 4. Receiver repeater - Toggle expand/collapse
         */
        $(document).on('click', '.wpsw-receiver-toggle', function() {
            $(this).closest('.wpsw-receiver-card').toggleClass('expanded');
            var icon = $(this).find('.dashicons');
            if ($(this).closest('.wpsw-receiver-card').hasClass('expanded')) {
                icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            } else {
                icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
            }
        });

        /**
         * 5. Secret Key - Generate
         */
        $(document).on('click', '.wpsw-generate-key', function() {
            var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()-_=+';
            var key = '';
            var keyLength = 32;

            if (window.crypto && window.crypto.getRandomValues) {
                var randomBytes = new Uint32Array(keyLength);
                window.crypto.getRandomValues(randomBytes);
                for (var i = 0; i < keyLength; i++) {
                    key += chars.charAt(randomBytes[i] % chars.length);
                }
            } else {
                for (var j = 0; j < keyLength; j++) {
                    key += chars.charAt(Math.floor(Math.random() * chars.length));
                }
            }

            var input = $(this).closest('.wpsw-key-group').find('.wpsw-secret-key');
            input.attr('type', 'text').val(key);

            var btn = $(this);
            btn.addClass('wpsw-btn-flash');
            setTimeout(function() { btn.removeClass('wpsw-btn-flash'); }, 1500);
        });

        /**
         * 6. Secret Key - Copy
         */
        $(document).on('click', '.wpsw-copy-key', function() {
            var key = $(this).closest('.wpsw-key-group').find('.wpsw-secret-key').val();
            if (!key) {
                return;
            }
            copyToClipboard(key, $(this));
        });

        /**
         * 7. Secret Key - Toggle visibility
         */
        $(document).on('click', '.wpsw-toggle-key-visibility', function() {
            var input = $(this).closest('.wpsw-key-group').find('.wpsw-secret-key');
            var icon = $(this).find('.dashicons');
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                input.attr('type', 'password');
                icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        });

        /**
         * 8. Copy Endpoint URL
         */
        $(document).on('click', '#wpsw-copy-endpoint', function() {
            var url = $(this).data('url') || $('#wpsw-endpoint-code').text();
            copyToClipboard(url, $(this));
        });

        /**
         * 9. Test Connection (per receiver)
         */
        $(document).on('click', '.wpsw-test-connection', function() {
            var btn       = $(this);
            var receiverId = btn.data('receiver-id');
            var resultEl  = btn.closest('.wpsw-receiver-card-actions').find('.wpsw-receiver-result');

            resultEl.removeClass('success error').text(wpswAdmin.i18n.testing).show();

            $.ajax({
                url: wpswAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpsw_test_connection',
                    nonce: wpswAdmin.nonce,
                    receiver_id: receiverId
                },
                success: function(response) {
                    if (response.success) {
                        resultEl.removeClass('error').addClass('success').text('✓ ' + response.data);
                    } else {
                        resultEl.removeClass('success').addClass('error').text('✗ ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    resultEl.removeClass('success').addClass('error').text('✗ ' + error);
                }
            });
        });

        /**
         * 10. Test Connection (tools tab)
         */
        $('#wpsw-test-btn').on('click', function() {
            var receiverId = $('#wpsw-test-receiver-id').val();
            var resultEl = $('#wpsw-test-result');

            if (!receiverId) {
                return;
            }

            resultEl.removeClass('success error').addClass('loading')
                .html('<span class="wpsw-spinner"></span> ' + wpswAdmin.i18n.testing).show();

            $.ajax({
                url: wpswAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpsw_test_connection',
                    nonce: wpswAdmin.nonce,
                    receiver_id: receiverId
                },
                success: function(response) {
                    resultEl.removeClass('loading');
                    if (response.success) {
                        resultEl.addClass('success').html('✓ ' + response.data);
                    } else {
                        resultEl.addClass('error').html('✗ ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    resultEl.removeClass('loading').addClass('error').html('✗ ' + error);
                }
            });
        });

        /**
         * 11. Manual Sync - multi post/page picker (tools tab)
         */
        var selectedPosts = {}; // id -> title

        function renderSelected() {
            var ids = Object.keys(selectedPosts);
            var container = $('#wpsw-selected-items');
            var count = $('#wpsw-selected-count');
            container.empty();

            if (ids.length === 0) {
                container.html('<p class="wpsw-empty-state wpsw-multi-empty">' + (wpswAdmin.i18n.noPostsSelected || 'No posts selected yet.') + '</p>');
                count.text('0');
                return;
            }

            ids.sort(function(a, b) { return a - b; });
            for (var i = 0; i < ids.length; i++) {
                var id = ids[i];
                container.append(
                    '<div class="wpsw-selected-item" data-id="' + id + '">' +
                        '<span class="wpsw-selected-item-title">' + selectedPosts[id] + '</span>' +
                        '<span class="wpsw-selected-item-id">#' + id + '</span>' +
                        '<button type="button" class="wpsw-btn-icon wpsw-selected-remove" title="Remove"><span class="dashicons dashicons-no-alt"></span></button>' +
                    '</div>'
                );
            }
            count.text(ids.length);
        }

        $(document).on('click', '.wpsw-selected-remove', function() {
            var id = $(this).closest('.wpsw-selected-item').data('id');
            delete selectedPosts[id];
            renderSelected();
            loadPostSearch();
        });

        function loadPostSearch() {
            var postType = $('#wpsw-sync-post-type').val();
            var search = $('#wpsw-post-search').val();
            var exclude = Object.keys(selectedPosts).map(Number);
            var resultsEl = $('#wpsw-post-search-results');
            var spinner = $('.wpsw-multi-spinner');

            spinner.removeClass('wpsw-hidden');

            $.ajax({
                url: wpswAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpsw_search_posts',
                    nonce: wpswAdmin.nonce,
                    post_type: postType,
                    search: search,
                    exclude: exclude
                },
                success: function(response) {
                    spinner.addClass('wpsw-hidden');
                    resultsEl.empty();

                    if (response.success && response.data.length > 0) {
                        for (var i = 0; i < response.data.length; i++) {
                            var p = response.data[i];
                            resultsEl.append(
                                '<div class="wpsw-search-result-item" data-id="' + p.id + '">' +
                                    '<span class="wpsw-search-result-title">' + p.title + '</span>' +
                                    '<span class="wpsw-search-result-id">#' + p.id + '</span>' +
                                    '<button type="button" class="wpsw-btn wpsw-btn-secondary wpsw-btn-sm wpsw-search-add">' +
                                        '<span class="dashicons dashicons-plus-alt2"></span> ' + (wpswAdmin.i18n.add || 'Add') +
                                    '</button>' +
                                '</div>'
                            );
                        }
                    } else {
                        resultsEl.html('<p class="wpsw-empty-state">' + (wpswAdmin.i18n.noResults || 'No posts found.') + '</p>');
                    }
                },
                error: function() {
                    spinner.addClass('wpsw-hidden');
                    resultsEl.html('<p class="wpsw-empty-state">' + (wpswAdmin.i18n.searchFailed || 'Search failed.') + '</p>');
                }
            });
        }

        var searchTimer;
        $('#wpsw-post-search').on('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(loadPostSearch, 350);
        });

        $('#wpsw-sync-post-type').on('change', function() {
            $('#wpsw-post-search').val('');
            loadPostSearch();
        });

        $(document).on('click', '.wpsw-search-add', function() {
            var item = $(this).closest('.wpsw-search-result-item');
            var id = String(item.data('id'));
            var title = item.find('.wpsw-search-result-title').text();
            selectedPosts[id] = title;
            renderSelected();
            loadPostSearch();
        });

        function addManualIds() {
            var raw = $('#wpsw-manual-ids').val();
            var ids = raw.split(',').map(function(s) { return s.trim(); }).filter(function(s) { return /^\d+$/.test(s); }).map(Number);

            if (ids.length === 0) {
                alert(wpswAdmin.i18n.invalidIds || 'Please enter valid post/page IDs separated by commas.');
                return;
            }

            var resultEl = $('#wpsw-sync-result');
            resultEl.removeClass('success error').addClass('loading')
                .html('<span class="wpsw-spinner"></span> ' + (wpswAdmin.i18n.resolving || 'Resolving posts...')).show();

            $.ajax({
                url: wpswAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpsw_get_post_titles',
                    nonce: wpswAdmin.nonce,
                    post_ids: ids
                },
                success: function(response) {
                    resultEl.removeClass('loading').empty().hide();
                    if (response.success) {
                        var found = response.data;
                        var missing = [];
                        for (var i = 0; i < ids.length; i++) {
                            var id = ids[i];
                            if (found[id] !== undefined) {
                                selectedPosts[String(id)] = found[id];
                            } else {
                                missing.push(id);
                            }
                        }
                        renderSelected();
                        loadPostSearch();
                        if (missing.length > 0) {
                            resultEl.removeClass('success').addClass('error')
                                .html('✗ ' + (wpswAdmin.i18n.idsNotFound || 'IDs not found:') + ' #' + missing.join(', #')).show();
                        }
                        $('#wpsw-manual-ids').val('');
                    } else {
                        resultEl.removeClass('loading').addClass('error').html('✗ ' + response.data).show();
                    }
                },
                error: function(xhr, status, error) {
                    resultEl.removeClass('loading').addClass('error').html('✗ ' + error).show();
                }
            });
        }

        $('#wpsw-add-ids-btn').on('click', addManualIds);

        $('#wpsw-manual-ids').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addManualIds();
            }
        });

        renderSelected();
        loadPostSearch();

        $('#wpsw-sync-btn').on('click', function() {
            var postIds = Object.keys(selectedPosts).map(Number);
            var receiverId = $('#wpsw-sync-receiver-id').val();
            var resultEl = $('#wpsw-sync-result');

            if (postIds.length === 0) {
                resultEl.removeClass('success loading').addClass('error')
                    .html('✗ ' + (wpswAdmin.i18n.noPostsSelected || 'Please select at least one post.')).show();
                return;
            }

            resultEl.removeClass('success error').addClass('loading')
                .html('<span class="wpsw-spinner"></span> ' + wpswAdmin.i18n.syncing).show();

            var postData = {
                action: 'wpsw_sync_posts',
                nonce: wpswAdmin.nonce,
                post_ids: postIds,
                receiver_id: receiverId
            };

            $.ajax({
                url: wpswAdmin.ajaxUrl,
                type: 'POST',
                data: postData,
                success: function(response) {
                    resultEl.removeClass('loading');
                    if (response.success) {
                        var ok = 0, fail = 0;
                        var html = '<strong>✓ ' + (wpswAdmin.i18n.syncComplete || 'Sync Complete!') + '</strong><br>';
                        for (var i = 0; i < response.data.length; i++) {
                            var r = response.data[i];
                            var label = (r.title || '#' + r.id);
                            if (r.success) { ok++; html += '<span class="wpsw-sync-ok">✓ ' + label + '</span><br>'; }
                            else { fail++; html += '<span class="wpsw-sync-fail">✗ ' + label + '</span><br>'; }
                        }
                        html += '<div class="wpsw-sync-summary">' + ok + ' ' + (wpswAdmin.i18n.synced || 'synced') + ', ' + fail + ' ' + (wpswAdmin.i18n.failed || 'failed') + '</div>';
                        resultEl.addClass(fail > 0 ? 'error' : 'success').html(html);
                        if (ok > 0) { selectedPosts = {}; renderSelected(); loadPostSearch(); }
                    } else {
                        resultEl.addClass('error').html('✗ ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    resultEl.removeClass('loading').addClass('error').html('✗ ' + error);
                }
            });
        });

        /**
         * 12. Activity Log
         */
        function loadLog() {
            $.ajax({
                url: wpswAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpsw_get_log',
                    nonce: wpswAdmin.nonce
                },
                success: function(response) {
                    var container = $('#wpsw-log-container');
                    if (response.success && response.data && response.data.length > 0) {
                        var html = '';
                        for (var i = 0; i < response.data.length; i++) {
                            var entry = response.data[i];
                            var logClass = 'log-' + (entry.type || 'info');
                            html += '<div class="wpsw-log-entry ' + logClass + '">' +
                                '<span class="log-time">' + (entry.time || '') + '</span>' +
                                '<span class="log-type">[' + (entry.type || 'info').toUpperCase() + ']</span>' +
                                '<span class="log-message">' + (entry.message || '') + '</span>' +
                            '</div>';
                        }
                        container.html(html);
                        container.scrollTop(container[0].scrollHeight);
                    } else {
                        container.html('<p class="wpsw-empty-state">No log entries yet.</p>');
                    }
                }
            });
        }

        $('#wpsw-refresh-log').on('click', loadLog);

        $('#wpsw-clear-log').on('click', function() {
            $.ajax({
                url: wpswAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpsw_clear_log',
                    nonce: wpswAdmin.nonce
                },
                success: function() {
                    $('#wpsw-log-container').html('<p class="wpsw-empty-state">No log entries yet.</p>');
                }
            });
        });

        /**
         * 13. URL validation helper
         */
        $(document).on('input paste change', '.wpsw-receiver-url', function() {
            var val = $(this).val().trim();
            var checkEl = $(this).closest('.wpsw-field-input-group').find('.wpsw-url-format-check');

            if (!val) {
                checkEl.empty();
                return;
            }

            if (val.indexOf('post-sync/v1/receive') !== -1) {
                checkEl.html('<span style="color:#059669;font-weight:600;">✓ Valid endpoint</span>');
            } else {
                checkEl.html('<span style="color:#d97706;font-weight:500;">💡 ' + (wpswAdmin.i18n.invalidUrlTip || 'Should end with /wp-json/post-sync/v1/receive') + '</span>');
            }
        });

        /**
         * 14. Checkbox toggles
         */
        $(document).on('change', '.wpsw-checkbox-item input[type="checkbox"]', function() {
            if ($(this).is(':checked')) {
                $(this).closest('.wpsw-checkbox-item').addClass('checked');
            } else {
                $(this).closest('.wpsw-checkbox-item').removeClass('checked');
            }
        });

        /**
         * 15. Clipboard utility
         */
        function copyToClipboard(text, btn) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    flashBtn(btn, '✓');
                }).catch(function() {
                    fallbackCopy(text, btn);
                });
            } else {
                fallbackCopy(text, btn);
            }
        }

        function fallbackCopy(text, btn) {
            var temp = document.createElement('textarea');
            temp.value = text;
            temp.style.position = 'fixed';
            temp.style.left = '-9999px';
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
            flashBtn(btn, '✓');
        }

        function flashBtn(btn, icon) {
            var orig = btn.html();
            btn.html(icon + ' ' + (wpswAdmin.i18n.copied || 'Copied!'));
            btn.addClass('wpsw-btn-flash');
            setTimeout(function() {
                btn.html(orig).removeClass('wpsw-btn-flash');
            }, 2000);
        }

    });

})(jQuery);
