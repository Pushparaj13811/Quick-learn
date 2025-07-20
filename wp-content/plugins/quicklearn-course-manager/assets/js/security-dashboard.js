/**
 * Security Dashboard JavaScript
 * 
 * @package QuickLearn_Course_Manager
 */

(function($) {
    'use strict';
    
    /**
     * Security Dashboard Object
     */
    var SecurityDashboard = {
        
        /**
         * Initialize the dashboard
         */
        init: function() {
            this.bindEvents();
            this.loadSecurityChart();
            this.startAutoRefresh();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Refresh statistics button
            $('#refresh-stats').on('click', this.refreshStats.bind(this));
            
            // Clear logs button
            $('#clear-logs').on('click', this.clearLogs.bind(this));
            
            // Export logs button
            $('#export-logs').on('click', this.exportLogs.bind(this));
            
            // Unblock IP buttons
            $(document).on('click', '.qlcm-unblock-ip', this.unblockIP.bind(this));
            
            // Block IP buttons
            $(document).on('click', '.qlcm-block-ip', this.blockIP.bind(this));
        },
        
        /**
         * Load security events chart
         */
        loadSecurityChart: function() {
            var ctx = document.getElementById('security-events-chart');
            if (!ctx) return;
            
            // Get chart data via AJAX
            this.makeAjaxRequest('qlcm_get_security_stats', {}, function(response) {
                if (response.success && response.data.events_24h) {
                    SecurityDashboard.renderChart(ctx, response.data.events_24h);
                }
            });
        },
        
        /**
         * Render the security events chart
         */
        renderChart: function(ctx, data) {
            // Prepare data for Chart.js
            var labels = [];
            var datasets = {};
            var colors = {
                'login_failed': '#dc3232',
                'rate_limit_exceeded': '#ff9800',
                'nonce_verification_failed': '#f44336',
                'ajax_request': '#2196f3',
                'login_success': '#4caf50'
            };
            
            // Process data
            data.forEach(function(item) {
                if (!datasets[item.event_type]) {
                    datasets[item.event_type] = {
                        label: item.event_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
                        data: [],
                        backgroundColor: colors[item.event_type] || '#666666',
                        borderColor: colors[item.event_type] || '#666666',
                        borderWidth: 1
                    };
                }
                datasets[item.event_type].data.push(item.count);
                
                if (labels.indexOf(item.event_type) === -1) {
                    labels.push(item.event_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()));
                }
            });
            
            // Convert datasets object to array
            var datasetsArray = Object.values(datasets);
            
            // Create chart
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Events Count',
                        data: data.map(item => item.count),
                        backgroundColor: data.map(item => colors[item.event_type] || '#666666'),
                        borderColor: data.map(item => colors[item.event_type] || '#666666'),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Security Events (Last 24 Hours)'
                        }
                    }
                }
            });
        },
        
        /**
         * Refresh statistics
         */
        refreshStats: function(e) {
            e.preventDefault();
            
            var $button = $(e.target);
            var originalText = $button.text();
            
            $button.prop('disabled', true).text(qlcm_security.i18n.loading);
            
            this.makeAjaxRequest('qlcm_get_security_stats', {}, function(response) {
                if (response.success) {
                    // Update dashboard numbers
                    SecurityDashboard.updateDashboardNumbers(response.data);
                    
                    // Show success message
                    SecurityDashboard.showNotice('Statistics refreshed successfully!', 'success');
                } else {
                    SecurityDashboard.showNotice(response.data.message || qlcm_security.i18n.error, 'error');
                }
                
                $button.prop('disabled', false).text(originalText);
            });
        },
        
        /**
         * Update dashboard numbers
         */
        updateDashboardNumbers: function(data) {
            // Update event counts
            var totalEvents = 0;
            var failedLogins = 0;
            var rateLimitHits = 0;
            
            if (data.events_24h) {
                data.events_24h.forEach(function(event) {
                    totalEvents += parseInt(event.count);
                    
                    if (event.event_type === 'login_failed') {
                        failedLogins = parseInt(event.count);
                    } else if (event.event_type === 'rate_limit_exceeded') {
                        rateLimitHits = parseInt(event.count);
                    }
                });
            }
            
            $('#total-events-24h').text(totalEvents);
            $('#failed-logins-24h').text(failedLogins);
            $('#rate-limit-hits').text(rateLimitHits);
            $('#blocked-ips').text(data.blocked_ips || 0);
        },
        
        /**
         * Clear old logs
         */
        clearLogs: function(e) {
            e.preventDefault();
            
            if (!confirm(qlcm_security.i18n.confirm_clear)) {
                return;
            }
            
            var $button = $(e.target);
            var originalText = $button.text();
            
            $button.prop('disabled', true).text(qlcm_security.i18n.loading);
            
            this.makeAjaxRequest('qlcm_clear_security_logs', {}, function(response) {
                if (response.success) {
                    SecurityDashboard.showNotice(response.data.message, 'success');
                    // Refresh the page to show updated logs
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    SecurityDashboard.showNotice(response.data.message || qlcm_security.i18n.error, 'error');
                }
                
                $button.prop('disabled', false).text(originalText);
            });
        },
        
        /**
         * Export security logs
         */
        exportLogs: function(e) {
            e.preventDefault();
            
            // Create a form to download the logs
            var form = $('<form>', {
                method: 'POST',
                action: ajaxurl
            });
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'qlcm_export_security_logs'
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'nonce',
                value: qlcm_security.nonce
            }));
            
            $('body').append(form);
            form.submit();
            form.remove();
            
            this.showNotice('Security logs export initiated...', 'info');
        },
        
        /**
         * Unblock IP address
         */
        unblockIP: function(e) {
            e.preventDefault();
            
            if (!confirm(qlcm_security.i18n.confirm_unblock)) {
                return;
            }
            
            var $button = $(e.target);
            var ip = $button.data('ip');
            var originalText = $button.text();
            
            $button.prop('disabled', true).text(qlcm_security.i18n.loading);
            
            this.makeAjaxRequest('qlcm_unblock_ip', { ip: ip }, function(response) {
                if (response.success) {
                    SecurityDashboard.showNotice(response.data.message, 'success');
                    
                    // Update button state
                    $button.removeClass('qlcm-unblock-ip')
                           .addClass('qlcm-block-ip')
                           .text('Block');
                    
                    // Update status indicator
                    $button.closest('tr').find('.qlcm-status-blocked')
                           .removeClass('qlcm-status-blocked')
                           .addClass('qlcm-status-active')
                           .text('Active');
                } else {
                    SecurityDashboard.showNotice(response.data.message || qlcm_security.i18n.error, 'error');
                }
                
                $button.prop('disabled', false);
                if (!response.success) {
                    $button.text(originalText);
                }
            });
        },
        
        /**
         * Block IP address
         */
        blockIP: function(e) {
            e.preventDefault();
            
            var $button = $(e.target);
            var ip = $button.data('ip');
            
            // For now, just show a message - actual blocking would require additional implementation
            this.showNotice('IP blocking feature coming soon. Use server-level blocking for now.', 'info');
        },
        
        /**
         * Start auto-refresh for dashboard
         */
        startAutoRefresh: function() {
            // Refresh statistics every 5 minutes
            setInterval(function() {
                SecurityDashboard.refreshStats({ preventDefault: function() {} });
            }, 300000); // 5 minutes
        },
        
        /**
         * Make AJAX request
         */
        makeAjaxRequest: function(action, data, callback) {
            var requestData = $.extend({
                action: action,
                nonce: qlcm_security.nonce
            }, data);
            
            $.post(qlcm_security.ajax_url, requestData, callback)
                .fail(function() {
                    SecurityDashboard.showNotice(qlcm_security.i18n.error, 'error');
                });
        },
        
        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div>', {
                class: 'notice notice-' + type + ' is-dismissible qlcm-temp-notice',
                html: '<p>' + message + '</p>'
            });
            
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Make dismissible
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        }
    };
    
    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        if ($('.qlcm-security-dashboard').length > 0) {
            SecurityDashboard.init();
        }
    });
    
})(jQuery);