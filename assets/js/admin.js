/**
 * Content Freshness Monitor - Admin JavaScript
 */

(function($) {
    'use strict';

    var CFM = {
        init: function() {
            this.bindEvents();
            this.createLiveRegion();
        },

        /**
         * Create ARIA live region for screen reader announcements
         */
        createLiveRegion: function() {
            if ($('#cfm-live-region').length === 0) {
                $('body').append('<div id="cfm-live-region" class="screen-reader-text" aria-live="polite" aria-atomic="true"></div>');
            }
        },

        /**
         * Announce message to screen readers
         */
        announce: function(message) {
            var $region = $('#cfm-live-region');
            $region.text('');
            setTimeout(function() {
                $region.text(message);
            }, 100);
        },

        bindEvents: function() {
            // Mark single post as reviewed
            $(document).on('click', '.cfm-mark-reviewed', this.markReviewed);

            // Bulk mark as reviewed
            $(document).on('click', '.cfm-bulk-review', this.bulkMarkReviewed);

            // Select all checkboxes
            $(document).on('click', '.cfm-select-all', this.selectAll);
            $(document).on('change', '.cfm-check-all', this.checkAll);
            $(document).on('change', '.cfm-post-check', this.updateBulkButton);

            // Send test email
            $(document).on('click', '#cfm-send-test-email', this.sendTestEmail);
        },

        markReviewed: function(e) {
            e.preventDefault();

            var $button = $(this);
            var postId = $button.data('post-id');
            var $row = $button.closest('tr');
            var postTitle = $row.find('.cfm-col-title strong a').text();

            $button.prop('disabled', true).attr('aria-disabled', 'true').text(cfmAjax.marking || 'Marking...');

            $.ajax({
                url: cfmAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cfm_mark_reviewed',
                    nonce: cfmAjax.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        CFM.announce(postTitle + ' marked as reviewed');
                        $row.fadeOut(400, function() {
                            $(this).remove();
                            CFM.updateStats();
                        });
                    } else {
                        CFM.announce('Error: ' + (response.data.message || 'Could not mark as reviewed'));
                        alert(response.data.message || 'Error marking as reviewed');
                        $button.prop('disabled', false).attr('aria-disabled', 'false').text('Mark Reviewed');
                    }
                },
                error: function() {
                    CFM.announce('Error: Could not mark as reviewed. Please try again.');
                    alert('An error occurred. Please try again.');
                    $button.prop('disabled', false).attr('aria-disabled', 'false').text('Mark Reviewed');
                }
            });
        },

        bulkMarkReviewed: function(e) {
            e.preventDefault();

            var $button = $(this);
            var postIds = [];

            $('.cfm-post-check:checked').each(function() {
                postIds.push($(this).val());
            });

            if (postIds.length === 0) {
                return;
            }

            $button.prop('disabled', true).attr('aria-disabled', 'true').text(cfmAjax.marking || 'Marking...');

            $.ajax({
                url: cfmAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cfm_bulk_mark_reviewed',
                    nonce: cfmAjax.nonce,
                    post_ids: postIds
                },
                success: function(response) {
                    if (response.success) {
                        CFM.announce(postIds.length + ' posts marked as reviewed');
                        postIds.forEach(function(id) {
                            $('tr[data-post-id="' + id + '"]').fadeOut(400, function() {
                                $(this).remove();
                            });
                        });

                        setTimeout(function() {
                            CFM.updateStats();
                        }, 500);

                        $button.text('Mark Selected as Reviewed');
                        CFM.updateBulkButton();
                    } else {
                        CFM.announce('Error: ' + (response.data.message || 'Could not mark posts as reviewed'));
                        alert(response.data.message || 'Error marking as reviewed');
                        $button.prop('disabled', false).attr('aria-disabled', 'false').text('Mark Selected as Reviewed');
                    }
                },
                error: function() {
                    CFM.announce('Error: Could not mark posts as reviewed. Please try again.');
                    alert('An error occurred. Please try again.');
                    $button.prop('disabled', false).attr('aria-disabled', 'false').text('Mark Selected as Reviewed');
                }
            });
        },

        selectAll: function(e) {
            e.preventDefault();
            var $button = $(this);
            var $checkboxes = $('.cfm-post-check');
            var allChecked = $checkboxes.filter(':checked').length === $checkboxes.length;

            if (allChecked) {
                $checkboxes.prop('checked', false);
                $button.attr('aria-pressed', 'false');
                CFM.announce('All posts deselected');
            } else {
                $checkboxes.prop('checked', true);
                $button.attr('aria-pressed', 'true');
                CFM.announce($checkboxes.length + ' posts selected');
            }
            CFM.updateBulkButton();
        },

        checkAll: function() {
            var isChecked = $(this).prop('checked');
            var $checkboxes = $('.cfm-post-check');
            $checkboxes.prop('checked', isChecked);
            $('.cfm-select-all').attr('aria-pressed', isChecked ? 'true' : 'false');
            CFM.announce(isChecked ? $checkboxes.length + ' posts selected' : 'All posts deselected');
            CFM.updateBulkButton();
        },

        updateBulkButton: function() {
            var checkedCount = $('.cfm-post-check:checked').length;
            var $bulkButton = $('.cfm-bulk-review');

            if (checkedCount > 0) {
                $bulkButton.prop('disabled', false).attr('aria-disabled', 'false');
                $bulkButton.text('Mark ' + checkedCount + ' as Reviewed');
            } else {
                $bulkButton.prop('disabled', true).attr('aria-disabled', 'true');
                $bulkButton.text('Mark Selected as Reviewed');
            }
        },

        updateStats: function() {
            // Update the remaining count in stats
            var remaining = $('.cfm-table tbody tr:visible').length;
            var $staleCard = $('.cfm-stat-stale .cfm-stat-number');

            if ($staleCard.length) {
                var currentStale = parseInt($staleCard.text(), 10);
                var newStale = Math.max(0, currentStale - 1);
                $staleCard.text(newStale);

                // Update fresh count
                var $freshCard = $('.cfm-stat-fresh .cfm-stat-number');
                if ($freshCard.length) {
                    var currentFresh = parseInt($freshCard.text(), 10);
                    $freshCard.text(currentFresh + 1);
                }

                // Update percentage
                var $percentCard = $('.cfm-stat-card:last .cfm-stat-number');
                if ($percentCard.length) {
                    var total = parseInt($('.cfm-stat-card:first .cfm-stat-number').text(), 10);
                    if (total > 0) {
                        var newPercent = Math.round((newStale / total) * 100);
                        $percentCard.text(newPercent + '%');
                    }
                }
            }

            // Show success message if no stale content left
            if (remaining === 0) {
                CFM.announce('Great news! All your content is now fresh.');
                $('.cfm-table, .cfm-bulk-actions, .cfm-pagination').fadeOut(400, function() {
                    $(this).remove();
                });

                var successHtml = '<div class="cfm-no-stale" role="status">' +
                    '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>' +
                    '<p>Great news! All your content is fresh.</p>' +
                    '</div>';

                $('.cfm-threshold-note').after(successHtml);
            }
        },

        sendTestEmail: function(e) {
            e.preventDefault();

            var $button = $(this);
            var $result = $('#cfm-test-email-result');

            $button.prop('disabled', true);
            $result.text('Sending...').css('color', '#666');

            $.ajax({
                url: cfmAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cfm_send_test_email',
                    nonce: cfmAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.text(response.data.message).css('color', '#46b450');
                    } else {
                        $result.text(response.data.message || 'Failed to send email').css('color', '#dc3232');
                    }
                    $button.prop('disabled', false);
                },
                error: function() {
                    $result.text('An error occurred. Please try again.').css('color', '#dc3232');
                    $button.prop('disabled', false);
                }
            });
        }
    };

    // Trends Chart functionality
    var CFMTrends = {
        chart: null,

        init: function() {
            if ($('#cfm-trends-chart').length && typeof Chart !== 'undefined') {
                this.loadTrends(30);
                this.bindEvents();
            }
        },

        bindEvents: function() {
            $(document).on('click', '.cfm-trends-range', function(e) {
                e.preventDefault();
                var $button = $(this);
                var days = $button.data('days');

                $('.cfm-trends-range').removeClass('active').attr('aria-pressed', 'false');
                $button.addClass('active').attr('aria-pressed', 'true');

                CFMTrends.loadTrends(days);
            });
        },

        loadTrends: function(days) {
            var $container = $('.cfm-chart-container');
            $container.addClass('cfm-loading');

            $.ajax({
                url: cfmAjax.ajaxUrl,
                type: 'GET',
                data: {
                    action: 'cfm_get_trends',
                    nonce: cfmAjax.nonce,
                    days: days
                },
                success: function(response) {
                    $container.removeClass('cfm-loading');
                    if (response.success && response.data.history.length > 0) {
                        CFMTrends.renderChart(response.data);
                    }
                },
                error: function() {
                    $container.removeClass('cfm-loading');
                }
            });
        },

        renderChart: function(data) {
            var ctx = document.getElementById('cfm-trends-chart');
            if (!ctx) return;

            var labels = data.labels;
            var freshData = data.history.map(function(item) { return item.fresh; });
            var staleData = data.history.map(function(item) { return item.stale; });

            if (this.chart) {
                this.chart.destroy();
            }

            this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: cfmAjax.freshLabel || 'Fresh Content',
                            data: freshData,
                            borderColor: '#46b450',
                            backgroundColor: 'rgba(70, 180, 80, 0.1)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        },
                        {
                            label: cfmAjax.staleLabel || 'Stale Content',
                            data: staleData,
                            borderColor: '#dc3232',
                            backgroundColor: 'rgba(220, 50, 50, 0.1)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#1d2327',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            padding: 12,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y + ' posts';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                callback: function(value) {
                                    if (Math.floor(value) === value) {
                                        return value;
                                    }
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
    };

    $(document).ready(function() {
        CFM.init();
        CFMTrends.init();
    });

})(jQuery);
