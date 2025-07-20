/**
 * Multimedia Admin JavaScript for QuickLearn Course Manager
 * 
 * Handles video and audio integration in the admin interface
 * Also handles drag-and-drop functionality for course modules and lessons
 */

(function($) {
    'use strict';
    
    let mediaFrame;
    
    $(document).ready(function() {
        initMultimediaAdmin();
        initModulesAdmin();
    });
    
    /**
     * Initialize multimedia admin functionality
     */
    function initMultimediaAdmin() {
        // Video type change handler
        $('#qlcm_video_type').on('change', handleVideoTypeChange);
        
        // Video upload button
        $('#qlcm_video_upload_button').on('click', handleVideoUpload);
        $('#qlcm_video_remove_button').on('click', handleVideoRemove);
        
        // Audio file management
        $('#qlcm_add_audio_file').on('click', handleAddAudioFile);
        $(document).on('click', '.qlcm-remove-audio-file', handleRemoveAudioFile);
        
        // URL validation buttons
        $('#qlcm_validate_youtube').on('click', function() {
            validateVideoUrl('youtube');
        });
        
        $('#qlcm_validate_vimeo').on('click', function() {
            validateVideoUrl('vimeo');
        });
        
        // Auto-validate URLs on input
        $('#qlcm_youtube_url').on('blur', function() {
            if ($(this).val()) {
                validateVideoUrl('youtube');
            }
        });
        
        $('#qlcm_vimeo_url').on('blur', function() {
            if ($(this).val()) {
                validateVideoUrl('vimeo');
            }
        });
        
        // Lesson type change handler
        $('#qlcm_lesson_type').on('change', handleLessonTypeChange);
        
        // Course selection change for lesson assignment
        $('#qlcm_lesson_course_id').on('change', handleCourseSelectionChange);
    }
    
    /**
     * Initialize modules admin functionality
     */
    function initModulesAdmin() {
        // Initialize sortable for modules
        if ($('#qlcm-modules-sortable').length) {
            $('#qlcm-modules-sortable').sortable({
                handle: '.qlcm-drag-handle',
                placeholder: 'qlcm-sortable-placeholder',
                update: function(event, ui) {
                    updateModuleOrder();
                }
            });
        }
        
        // Initialize sortable for lessons within modules
        $('.qlcm-lessons-sortable').each(function() {
            $(this).sortable({
                handle: '.qlcm-drag-handle',
                placeholder: 'qlcm-sortable-placeholder',
                update: function(event, ui) {
                    updateLessonOrder($(this).data('module-id'));
                }
            });
        });
        
        // Toggle module content
        $(document).on('click', '.qlcm-module-header', function(e) {
            if (!$(e.target).hasClass('qlcm-drag-handle') && !$(e.target).closest('.qlcm-module-actions').length) {
                $(this).siblings('.qlcm-lessons-container').slideToggle();
            }
        });
    }
    
    /**
     * Handle lesson type change
     */
    function handleLessonTypeChange() {
        const selectedType = $(this).val();
        
        // Hide all conditional rows
        $('.qlcm-lesson-video, .qlcm-lesson-audio, .qlcm-lesson-quiz').hide();
        
        // Show relevant rows based on selection
        switch (selectedType) {
            case 'video':
                $('.qlcm-lesson-video').show();
                break;
            case 'audio':
                $('.qlcm-lesson-audio').show();
                break;
            case 'quiz':
                $('.qlcm-lesson-quiz').show();
                break;
            case 'mixed':
                $('.qlcm-lesson-video, .qlcm-lesson-audio').show();
                break;
        }
    }
    
    /**
     * Handle course selection change for lesson assignment
     */
    function handleCourseSelectionChange() {
        const courseId = $(this).val();
        const moduleContainer = $('#qlcm_module_selection');
        
        if (!courseId) {
            moduleContainer.empty();
            return;
        }
        
        // Show loading
        moduleContainer.html('<p>' + (qlcm_modules_admin.strings.loading || 'Loading...') + '</p>');
        
        // Fetch modules for selected course
        $.ajax({
            url: qlcm_modules_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'qlcm_get_course_modules',
                nonce: qlcm_modules_admin.nonce,
                course_id: courseId
            },
            success: function(response) {
                if (response.success) {
                    moduleContainer.html(response.data.html);
                } else {
                    moduleContainer.html('<p>' + (response.data || 'Error loading modules') + '</p>');
                }
            },
            error: function() {
                moduleContainer.html('<p>Error loading modules</p>');
            }
        });
    }
    
    /**
     * Update module order after drag and drop
     */
    function updateModuleOrder() {
        const moduleIds = [];
        
        $('#qlcm-modules-sortable .qlcm-module-item').each(function() {
            moduleIds.push($(this).data('module-id'));
        });
        
        if (moduleIds.length === 0) {
            return;
        }
        
        // Show saving indicator
        showSavingIndicator();
        
        $.ajax({
            url: qlcm_modules_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'qlcm_reorder_modules',
                nonce: qlcm_modules_admin.nonce,
                module_ids: moduleIds
            },
            success: function(response) {
                if (response.success) {
                    showSavedIndicator();
                } else {
                    showErrorIndicator(response.data || qlcm_modules_admin.strings.error);
                }
            },
            error: function() {
                showErrorIndicator(qlcm_modules_admin.strings.error);
            }
        });
    }
    
    /**
     * Update lesson order after drag and drop
     */
    function updateLessonOrder(moduleId) {
        const lessonIds = [];
        
        $('.qlcm-lessons-sortable[data-module-id="' + moduleId + '"] .qlcm-lesson-item').each(function() {
            lessonIds.push($(this).data('lesson-id'));
        });
        
        if (lessonIds.length === 0) {
            return;
        }
        
        // Show saving indicator
        showSavingIndicator();
        
        $.ajax({
            url: qlcm_modules_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'qlcm_reorder_lessons',
                nonce: qlcm_modules_admin.nonce,
                lesson_ids: lessonIds
            },
            success: function(response) {
                if (response.success) {
                    showSavedIndicator();
                } else {
                    showErrorIndicator(response.data || qlcm_modules_admin.strings.error);
                }
            },
            error: function() {
                showErrorIndicator(qlcm_modules_admin.strings.error);
            }
        });
    }
    
    /**
     * Show saving indicator
     */
    function showSavingIndicator() {
        if ($('#qlcm-save-indicator').length === 0) {
            $('body').append('<div id="qlcm-save-indicator" class="qlcm-save-indicator">' + qlcm_modules_admin.strings.saving + '</div>');
        }
        $('#qlcm-save-indicator').show();
    }
    
    /**
     * Show saved indicator
     */
    function showSavedIndicator() {
        $('#qlcm-save-indicator').text(qlcm_modules_admin.strings.saved).delay(2000).fadeOut();
    }
    
    /**
     * Show error indicator
     */
    function showErrorIndicator(message) {
        $('#qlcm-save-indicator').text(message).addClass('error').delay(3000).fadeOut(function() {
            $(this).removeClass('error');
        });
    }
    
    /**
     * Handle video type change
     */
    function handleVideoTypeChange() {
        const selectedType = $(this).val();
        
        // Hide all video type rows
        $('#qlcm_youtube_row, #qlcm_vimeo_row, #qlcm_video_upload_row, #qlcm_video_options_row').hide();
        
        // Show relevant row based on selection
        switch (selectedType) {
            case 'youtube':
                $('#qlcm_youtube_row, #qlcm_video_options_row').show();
                break;
            case 'vimeo':
                $('#qlcm_vimeo_row, #qlcm_video_options_row').show();
                break;
            case 'upload':
                $('#qlcm_video_upload_row, #qlcm_video_options_row').show();
                break;
            case 'none':
            default:
                // All rows already hidden
                break;
        }
    }
    
    /**
     * Handle video upload
     */
    function handleVideoUpload() {
        // Create media frame if it doesn't exist
        if (mediaFrame) {
            mediaFrame.open();
            return;
        }
        
        mediaFrame = wp.media({
            title: qlcm_multimedia.strings.select_video,
            button: {
                text: qlcm_multimedia.strings.select_video
            },
            library: {
                type: ['video']
            },
            multiple: false
        });
        
        // Handle selection
        mediaFrame.on('select', function() {
            const attachment = mediaFrame.state().get('selection').first().toJSON();
            
            $('#qlcm_video_upload_id').val(attachment.id);
            
            let previewHtml = '';
            if (attachment.sizes && attachment.sizes.thumbnail) {
                previewHtml += '<img src="' + attachment.sizes.thumbnail.url + '" alt="' + attachment.title + '" />';
            }
            previewHtml += '<p>' + attachment.title + '</p>';
            
            $('#qlcm_video_upload_preview').html(previewHtml);
            $('#qlcm_video_remove_button').show();
        });
        
        mediaFrame.open();
    }
    
    /**
     * Handle video removal
     */
    function handleVideoRemove() {
        $('#qlcm_video_upload_id').val('');
        $('#qlcm_video_upload_preview').empty();
        $(this).hide();
    }
    
    /**
     * Handle adding audio file
     */
    function handleAddAudioFile() {
        const audioFrame = wp.media({
            title: qlcm_multimedia.strings.select_audio,
            button: {
                text: qlcm_multimedia.strings.select_audio
            },
            library: {
                type: ['audio']
            },
            multiple: true
        });
        
        audioFrame.on('select', function() {
            const attachments = audioFrame.state().get('selection').toJSON();
            
            attachments.forEach(function(attachment) {
                addAudioFileToList(attachment);
            });
        });
        
        audioFrame.open();
    }
    
    /**
     * Add audio file to the list
     */
    function addAudioFileToList(attachment) {
        const container = $('#qlcm_audio_files_container');
        const index = container.children().length;
        
        let thumbnailHtml = '';
        if (attachment.sizes && attachment.sizes.thumbnail) {
            thumbnailHtml = '<img src="' + attachment.sizes.thumbnail.url + '" alt="' + attachment.title + '" style="width: 50px; height: 50px; margin-right: 10px;" />';
        }
        
        const itemHtml = `
            <div class="qlcm-audio-file-item" data-index="${index}">
                <input type="hidden" name="qlcm_audio_files[]" value="${attachment.id}" />
                ${thumbnailHtml}
                <span>${attachment.title}</span>
                <button type="button" class="button qlcm-remove-audio-file">${qlcm_multimedia.strings.remove}</button>
            </div>
        `;
        
        container.append(itemHtml);
    }
    
    /**
     * Handle removing audio file
     */
    function handleRemoveAudioFile() {
        $(this).closest('.qlcm-audio-file-item').remove();
    }
    
    /**
     * Validate video URL
     */
    function validateVideoUrl(type) {
        const urlInput = type === 'youtube' ? $('#qlcm_youtube_url') : $('#qlcm_vimeo_url');
        const previewDiv = type === 'youtube' ? $('#qlcm_youtube_preview') : $('#qlcm_vimeo_preview');
        const validateButton = type === 'youtube' ? $('#qlcm_validate_youtube') : $('#qlcm_validate_vimeo');
        
        const url = urlInput.val().trim();
        
        if (!url) {
            previewDiv.empty();
            return;
        }
        
        // Show loading state
        validateButton.prop('disabled', true).text(qlcm_multimedia.strings.validating);
        previewDiv.html('<div class="qlcm-loading">Validating...</div>');
        
        // Make AJAX request
        $.ajax({
            url: qlcm_multimedia.ajax_url,
            type: 'POST',
            data: {
                action: 'qlcm_validate_video_url',
                nonce: qlcm_multimedia.nonce,
                url: url,
                type: type
            },
            success: function(response) {
                if (response.success) {
                    showVideoPreview(previewDiv, response.data, type);
                    showValidationMessage(previewDiv, qlcm_multimedia.strings.valid_url, 'success');
                } else {
                    showValidationMessage(previewDiv, response.data || qlcm_multimedia.strings.invalid_url, 'error');
                }
            },
            error: function() {
                showValidationMessage(previewDiv, qlcm_multimedia.strings.invalid_url, 'error');
            },
            complete: function() {
                validateButton.prop('disabled', false).text(qlcm_multimedia.strings.validate || 'Validate');
            }
        });
    }
    
    /**
     * Show video preview
     */
    function showVideoPreview(container, data, type) {
        let previewHtml = '<div class="qlcm-video-preview">';
        
        if (data.thumbnail) {
            previewHtml += '<img src="' + data.thumbnail + '" alt="Video thumbnail" style="max-width: 200px; height: auto;" />';
        }
        
        if (data.title) {
            previewHtml += '<p><strong>' + data.title + '</strong></p>';
        }
        
        previewHtml += '<p>Video ID: ' + data.video_id + '</p>';
        previewHtml += '</div>';
        
        container.html(previewHtml);
    }
    
    /**
     * Show validation message
     */
    function showValidationMessage(container, message, type) {
        const className = type === 'success' ? 'notice-success' : 'notice-error';
        const messageHtml = '<div class="notice ' + className + ' inline"><p>' + message + '</p></div>';
        
        if (type === 'error') {
            container.html(messageHtml);
        } else {
            container.append(messageHtml);
        }
    }
    
})(jQuery);