/**
 * Admin JavaScript for QuickLearn Course Manager
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        initAdminFeatures();
        initColorPicker();
        initDataTables();
        initAjaxActions();
    });
    
    /**
     * Initialize admin features
     */
    function initAdminFeatures() {
        // Add confirmation dialogs for destructive actions
        $('.delete-enrollment, .delete-rating').on('click', function(e) {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
                return false;
            }
        });
        
        // Auto-refresh analytics every 5 minutes
        if ($('.qlcm-analytics-grid').length) {
            setInterval(refreshAnalytics, 300000); // 5 minutes
        }
        
        // Initialize tooltips
        initTooltips();
    }
    
    /**
     * Initialize color picker for admin settings
     */
    function initColorPicker() {
        if ($('.color-picker').length) {
            $('.color-picker').wpColorPicker();
        }
    }
    
    /**
     * Initialize data tables for better admin experience
     */
    function initDataTables() {
        // Add search and pagination to admin tables
        $('.wp-list-table').each(function() {
            const $table = $(this);
            
            // Add search functionality
            if ($table.find('tbody tr').length > 10) {
                addTableSearch($table);
            }
            
            // Add sorting functionality
            addTableSorting($table);
        });
    }
    
    /**
     * Add search functionality to tables
     */
    function addTableSearch($table) {
        const $searchContainer = $('<div class="table-search-container"></div>');
        const $searchInput = $('<input type="text" class="table-search" placeholder="Search..." />');
        
        $searchContainer.append($searchInput);
        $table.before($searchContainer);
        
        $searchInput.on('keyup', function() {
            const searchTerm = $(this).val().toLowerCase();
            
            $table.find('tbody tr').each(function() {
                const $row = $(this);
                const rowText = $row.text().toLowerCase();
                
                if (rowText.indexOf(searchTerm) === -1) {
                    $row.hide();
                } else {
                    $row.show();
                }
            });
        });
    }
    
    /**
     * Add sorting functionality to tables
     */
    function addTableSorting($table) {
        $table.find('thead th').each(function(index) {
            const $th = $(this);
            
            // Skip action columns
            if ($th.hasClass('column-actions') || $th.text().trim() === '') {
                return;
            }
            
            $th.addClass('sortable').css('cursor', 'pointer');
            
            $th.on('click', function() {
                sortTable($table, index, $th);
            });
        });
    }
    
    /**
     * Sort table by column
     */
    function sortTable($table, columnIndex, $header) {
        const $tbody = $table.find('tbody');
        const $rows = $tbody.find('tr').toArray();
        const isAscending = !$header.hasClass('sorted-asc');
        
        // Remove existing sort classes
        $table.find('thead th').removeClass('sorted-asc sorted-desc');
        
        // Add current sort class
        $header.addClass(isAscending ? 'sorted-asc' : 'sorted-desc');
        
        // Sort rows
        $rows.sort(function(a, b) {
            const aText = $(a).find('td').eq(columnIndex).text().trim();
            const bText = $(b).find('td').eq(columnIndex).text().trim();
            
            // Try to parse as numbers first
            const aNum = parseFloat(aText);
            const bNum = parseFloat(bText);
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return isAscending ? aNum - bNum : bNum - aNum;
            }
            
            // Sort as strings
            if (isAscending) {
                return aText.localeCompare(bText);
            } else {
                return bText.localeCompare(aText);
            }
        });
        
        // Reorder rows in DOM
        $tbody.empty().append($rows);
    }
    
    /**
     * Initialize AJAX actions
     */
    function initAjaxActions() {
        // Handle bulk actions
        $('.bulk-action-button').on('click', function(e) {
            e.preventDefault();
            
            const action = $(this).data('action');
            const $checkedItems = $('.bulk-checkbox:checked');
            
            if ($checkedItems.length === 0) {
                alert('Please select items to perform bulk action.');
                return;
            }
            
            if (!confirm('Are you sure you want to perform this action on ' + $checkedItems.length + ' items?')) {
                return;
            }
            
            performBulkAction(action, $checkedItems);
        });
        
        // Handle status changes
        $('.status-select').on('change', function() {
            const $select = $(this);
            const itemId = $select.data('item-id');
            const itemType = $select.data('item-type');
            const newStatus = $select.val();
            
            updateItemStatus(itemType, itemId, newStatus, $select);
        });
    }
    
    /**
     * Perform bulk action
     */
    function performBulkAction(action, $items) {
        const itemIds = [];
        
        $items.each(function() {
            itemIds.push($(this).val());
        });
        
        $.ajax({
            url: qlcm_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'qlcm_bulk_action',
                nonce: qlcm_admin.nonce,
                bulk_action: action,
                item_ids: itemIds
            },
            beforeSend: function() {
                $items.closest('tr').addClass('qlcm-loading');
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    
                    // Refresh the page or update the table
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice(response.data.message || 'Action failed', 'error');
                }
            },
            error: function() {
                showNotice('Network error occurred', 'error');
            },
            complete: function() {
                $items.closest('tr').removeClass('qlcm-loading');
            }
        });
    }
    
    /**
     * Update item status
     */
    function updateItemStatus(itemType, itemId, newStatus, $select) {
        const originalStatus = $select.data('original-status');
        
        $.ajax({
            url: qlcm_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'qlcm_update_status',
                nonce: qlcm_admin.nonce,
                item_type: itemType,
                item_id: itemId,
                new_status: newStatus
            },
            beforeSend: function() {
                $select.prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    showNotice(response.data.message, 'success');
                    $select.data('original-status', newStatus);
                } else {
                    showNotice(response.data.message || 'Status update failed', 'error');
                    $select.val(originalStatus); // Revert to original status
                }
            },
            error: function() {
                showNotice('Network error occurred', 'error');
                $select.val(originalStatus); // Revert to original status
            },
            complete: function() {
                $select.prop('disabled', false);
            }
        });
    }
    
    /**
     * Refresh analytics data
     */
    function refreshAnalytics() {
        const $analyticsGrid = $('.qlcm-analytics-grid');
        
        if (!$analyticsGrid.length) {
            return;
        }
        
        $.ajax({
            url: qlcm_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'qlcm_refresh_analytics',
                nonce: qlcm_admin.nonce
            },
            success: function(response) {
                if (response.success && response.data.analytics) {
                    updateAnalyticsDisplay(response.data.analytics);
                }
            },
            error: function() {
                console.log('Failed to refresh analytics');
            }
        });
    }
    
    /**
     * Update analytics display
     */
    function updateAnalyticsDisplay(analytics) {
        $('.qlcm-stat-card').each(function() {
            const $card = $(this);
            const statType = $card.data('stat-type');
            
            if (analytics[statType]) {
                $card.find('.stat-number').text(analytics[statType].value);
                
                if (analytics[statType].percentage) {
                    $card.find('.stat-percentage').text(analytics[statType].percentage);
                }
            }
        });
    }
    
    /**
     * Initialize tooltips
     */
    function initTooltips() {
        $('[data-tooltip]').each(function() {
            const $element = $(this);
            const tooltipText = $element.data('tooltip');
            
            $element.attr('title', tooltipText);
            
            // Add hover effects
            $element.on('mouseenter', function() {
                $(this).addClass('tooltip-hover');
            }).on('mouseleave', function() {
                $(this).removeClass('tooltip-hover');
            });
        });
    }
    
    /**
     * Show admin notice
     */
    function showNotice(message, type) {
        const $notice = $('<div class="notice notice-' + type + ' is-dismissible qlcm-notice"><p>' + message + '</p></div>');
        
        $('.wrap h1').after($notice);
        
        // Auto-dismiss success notices
        if (type === 'success') {
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
        
        // Add dismiss functionality
        $notice.on('click', '.notice-dismiss', function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        });
    }
    
    /**
     * Export data functionality
     */
    $('.export-data').on('click', function(e) {
        e.preventDefault();
        
        const exportType = $(this).data('export-type');
        const $button = $(this);
        const originalText = $button.text();
        
        $button.text('Exporting...').prop('disabled', true);
        
        $.ajax({
            url: qlcm_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'qlcm_export_data',
                nonce: qlcm_admin.nonce,
                export_type: exportType
            },
            success: function(response) {
                if (response.success) {
                    // Create download link
                    const $link = $('<a>').attr({
                        href: 'data:text/csv;charset=utf-8,' + encodeURIComponent(response.data.csv),
                        download: exportType + '_export_' + new Date().toISOString().split('T')[0] + '.csv'
                    });
                    
                    $link[0].click();
                    showNotice('Export completed successfully', 'success');
                } else {
                    showNotice(response.data.message || 'Export failed', 'error');
                }
            },
            error: function() {
                showNotice('Export failed due to network error', 'error');
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    /**
     * Form validation
     */
    $('form').on('submit', function(e) {
        const $form = $(this);
        let isValid = true;
        
        // Validate required fields
        $form.find('[required]').each(function() {
            const $field = $(this);
            
            if (!$field.val().trim()) {
                $field.addClass('error');
                isValid = false;
            } else {
                $field.removeClass('error');
            }
        });
        
        // Validate email fields
        $form.find('input[type="email"]').each(function() {
            const $field = $(this);
            const email = $field.val().trim();
            
            if (email && !isValidEmail(email)) {
                $field.addClass('error');
                isValid = false;
            } else {
                $field.removeClass('error');
            }
        });
        
        // Validate number fields
        $form.find('input[type="number"]').each(function() {
            const $field = $(this);
            const value = parseFloat($field.val());
            const min = parseFloat($field.attr('min'));
            const max = parseFloat($field.attr('max'));
            
            if (!isNaN(min) && value < min) {
                $field.addClass('error');
                isValid = false;
            } else if (!isNaN(max) && value > max) {
                $field.addClass('error');
                isValid = false;
            } else {
                $field.removeClass('error');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            showNotice('Please correct the highlighted fields', 'error');
            
            // Focus on first error field
            $form.find('.error').first().focus();
        }
    });
    
    /**
     * Validate email address
     */
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
})(jQuery);