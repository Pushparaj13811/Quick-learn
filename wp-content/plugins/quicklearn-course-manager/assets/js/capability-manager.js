/**
 * Capability Manager JavaScript
 * 
 * @package QuickLearn_Course_Manager
 */

(function($) {
    'use strict';
    
    /**
     * Capability Manager Object
     */
    var CapabilityManager = {
        
        /**
         * Initialize the capability manager
         */
        init: function() {
            this.bindEvents();
            this.initializeTabs();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Tab switching
            $('.qlcm-role-tabs .nav-tab').on('click', this.switchTab.bind(this));
            
            // Save capabilities button
            $('.qlcm-save-capabilities').on('click', this.saveCapabilities.bind(this));
            
            // Reset capabilities button
            $('.qlcm-reset-capabilities').on('click', this.resetCapabilities.bind(this));
            
            // Custom capability form
            $('#qlcm-custom-capability-form').on('submit', this.createCustomCapability.bind(this));
            
            // Capability checkbox changes
            $(document).on('change', '.qlcm-capability-item input[type="checkbox"]', this.handleCapabilityChange.bind(this));
            
            // Select all/none functionality
            this.addSelectAllButtons();
        },
        
        /**
         * Initialize tab functionality
         */
        initializeTabs: function() {
            // Show first tab by default
            $('.qlcm-role-panel').first().addClass('active');
            $('.nav-tab').first().addClass('nav-tab-active');
        },
        
        /**
         * Switch between role tabs
         */
        switchTab: function(e) {
            e.preventDefault();
            
            var $tab = $(e.target);
            var targetRole = $tab.data('role');
            
            // Update tab states
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Update panel states
            $('.qlcm-role-panel').removeClass('active');
            $('#role-' + targetRole).addClass('active');
        },
        
        /**
         * Save capabilities for a role
         */
        saveCapabilities: function(e) {
            e.preventDefault();
            
            var $button = $(e.target);
            var role = $button.data('role');
            var $form = $('.qlcm-capabilities-form[data-role="' + role + '"]');
            var capabilities = [];
            
            // Collect selected capabilities
            $form.find('input[type="checkbox"]:checked').each(function() {
                capabilities.push($(this).val());
            });
            
            // Update button state
            var originalText = $button.text();
            $button.prop('disabled', true)
                   .text(qlcm_capabilities.i18n.saving)
                   .addClass('saving');
            
            // Make AJAX request
            this.makeAjaxRequest('qlcm_update_role_capabilities', {
                role: role,
                capabilities: capabilities
            }, function(response) {
                if (response.success) {
                    CapabilityManager.showMessage(response.data.message, 'success');
                    $button.addClass('saved').text(qlcm_capabilities.i18n.saved);
                    
                    // Reset button after 2 seconds
                    setTimeout(function() {
                        $button.removeClass('saved saving')
                               .prop('disabled', false)
                               .text(originalText);
                    }, 2000);
                } else {
                    CapabilityManager.showMessage(response.data.message || qlcm_capabilities.i18n.error, 'error');
                    $button.removeClass('saving')
                           .prop('disabled', false)
                           .text(originalText);
                }
            });
        },
        
        /**
         * Reset capabilities for a role
         */
        resetCapabilities: function(e) {
            e.preventDefault();
            
            if (!confirm(qlcm_capabilities.i18n.confirm_reset)) {
                return;
            }
            
            var $button = $(e.target);
            var role = $button.data('role');
            var originalText = $button.text();
            
            $button.prop('disabled', true)
                   .text(qlcm_capabilities.i18n.loading)
                   .addClass('saving');
            
            this.makeAjaxRequest('qlcm_reset_role_capabilities', {
                role: role
            }, function(response) {
                if (response.success) {
                    CapabilityManager.showMessage(response.data.message, 'success');
                    // Reload page to show reset capabilities
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    CapabilityManager.showMessage(response.data.message || qlcm_capabilities.i18n.error, 'error');
                    $button.removeClass('saving')
                           .prop('disabled', false)
                           .text(originalText);
                }
            });
        },
        
        /**
         * Create custom capability
         */
        createCustomCapability: function(e) {
            e.preventDefault();
            
            var $form = $(e.target);
            var formData = {};
            
            // Collect form data
            $form.serializeArray().forEach(function(item) {
                if (item.name === 'assign_to_roles[]') {
                    if (!formData.assign_to_roles) {
                        formData.assign_to_roles = [];
                    }
                    formData.assign_to_roles.push(item.value);
                } else {
                    formData[item.name] = item.value;
                }
            });
            
            // Validate required fields
            if (!formData.capability_name || !formData.capability_label) {
                this.showMessage('Capability name and label are required', 'error');
                return;
            }
            
            // Validate capability name format
            if (!/^[a-z0-9_]+$/.test(formData.capability_name)) {
                this.showMessage('Capability name can only contain lowercase letters, numbers, and underscores', 'error');
                return;
            }
            
            if (!confirm(qlcm_capabilities.i18n.confirm_create)) {
                return;
            }
            
            var $submitButton = $form.find('button[type="submit"]');
            var originalText = $submitButton.text();
            
            $submitButton.prop('disabled', true)
                         .text(qlcm_capabilities.i18n.loading);
            
            this.makeAjaxRequest('qlcm_create_custom_capability', formData, function(response) {
                if (response.success) {
                    CapabilityManager.showMessage(response.data.message, 'success');
                    $form[0].reset();
                    
                    // Reload page to show new capability
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    CapabilityManager.showMessage(response.data.message || qlcm_capabilities.i18n.error, 'error');
                }
                
                $submitButton.prop('disabled', false)
                             .text(originalText);
            });
        },
        
        /**
         * Handle capability checkbox changes
         */
        handleCapabilityChange: function(e) {
            var $checkbox = $(e.target);
            var $item = $checkbox.closest('.qlcm-capability-item');
            var $group = $item.closest('.qlcm-capability-group');
            
            // Update group header with count
            this.updateGroupCount($group);
            
            // Add visual feedback
            if ($checkbox.is(':checked')) {
                $item.addClass('selected');
            } else {
                $item.removeClass('selected');
            }
            
            // Enable save button
            var $form = $item.closest('.qlcm-capabilities-form');
            var $saveButton = $('.qlcm-save-capabilities[data-role="' + $form.data('role') + '"]');
            $saveButton.removeClass('saved');
        },
        
        /**
         * Update capability count in group header
         */
        updateGroupCount: function($group) {
            var total = $group.find('input[type="checkbox"]').length;
            var selected = $group.find('input[type="checkbox"]:checked').length;
            
            var $header = $group.find('h4');
            var headerText = $header.text().replace(/ \(\d+\/\d+\)$/, '');
            $header.text(headerText + ' (' + selected + '/' + total + ')');
        },
        
        /**
         * Add select all/none buttons to capability groups
         */
        addSelectAllButtons: function() {
            $('.qlcm-capability-group').each(function() {
                var $group = $(this);
                var $header = $group.find('h4');
                
                if ($header.find('.qlcm-group-actions').length === 0) {
                    var $actions = $('<div class="qlcm-group-actions" style="float: right; font-size: 12px; font-weight: normal;"></div>');
                    
                    var $selectAll = $('<a href="#" class="qlcm-select-all" style="margin-right: 10px;">Select All</a>');
                    var $selectNone = $('<a href="#" class="qlcm-select-none">Select None</a>');
                    
                    $actions.append($selectAll).append($selectNone);
                    $header.append($actions);
                    
                    // Bind events
                    $selectAll.on('click', function(e) {
                        e.preventDefault();
                        $group.find('input[type="checkbox"]').prop('checked', true).trigger('change');
                    });
                    
                    $selectNone.on('click', function(e) {
                        e.preventDefault();
                        $group.find('input[type="checkbox"]').prop('checked', false).trigger('change');
                    });
                }
                
                // Update initial count
                CapabilityManager.updateGroupCount($group);
            });
        },
        
        /**
         * Make AJAX request
         */
        makeAjaxRequest: function(action, data, callback) {
            var requestData = $.extend({
                action: action,
                nonce: qlcm_capabilities.nonce
            }, data);
            
            $.post(qlcm_capabilities.ajax_url, requestData, callback)
                .fail(function() {
                    CapabilityManager.showMessage(qlcm_capabilities.i18n.error, 'error');
                });
        },
        
        /**
         * Show message to user
         */
        showMessage: function(message, type) {
            type = type || 'info';
            
            // Remove existing messages
            $('.qlcm-message').remove();
            
            var $message = $('<div>', {
                class: 'qlcm-message ' + type,
                text: message
            });
            
            $('.qlcm-capability-management').prepend($message);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $message.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Scroll to message
            $('html, body').animate({
                scrollTop: $message.offset().top - 50
            }, 300);
        },
        
        /**
         * Filter capabilities by search term
         */
        filterCapabilities: function(searchTerm) {
            searchTerm = searchTerm.toLowerCase();
            
            $('.qlcm-capability-item').each(function() {
                var $item = $(this);
                var capabilityText = $item.find('.qlcm-capability-label').text().toLowerCase();
                
                if (capabilityText.includes(searchTerm) || searchTerm === '') {
                    $item.show();
                } else {
                    $item.hide();
                }
            });
            
            // Hide empty groups
            $('.qlcm-capability-group').each(function() {
                var $group = $(this);
                var visibleItems = $group.find('.qlcm-capability-item:visible').length;
                
                if (visibleItems === 0 && searchTerm !== '') {
                    $group.hide();
                } else {
                    $group.show();
                }
            });
        }
    };
    
    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        if ($('.qlcm-capability-management').length > 0) {
            CapabilityManager.init();
            
            // Add search functionality
            var $searchBox = $('<div class="qlcm-search-box" style="margin-bottom: 20px;"><input type="text" placeholder="Search capabilities..." class="regular-text" /></div>');
            $('.qlcm-role-tabs').before($searchBox);
            
            $searchBox.find('input').on('input', function() {
                CapabilityManager.filterCapabilities($(this).val());
            });
        }
    });
    
})(jQuery);