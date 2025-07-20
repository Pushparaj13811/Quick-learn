/**
 * Course Ratings and Reviews JavaScript
 * QuickLearn Course Manager Plugin
 */

(function ($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function () {
        initRatingForm();
        initLoadMoreReviews();
    });

    /**
     * Initialize rating form functionality
     */
    function initRatingForm() {
        const $form = $('#qlcm-rating-form');

        if (!$form.length) {
            return;
        }

        // Handle form submission
        $form.on('submit', function (e) {
            e.preventDefault();
            submitRating($form);
        });

        // Handle star rating interaction
        const $ratingInputs = $form.find('.qlcm-rating-input');

        $ratingInputs.on('mouseover', 'label', function () {
            const $label = $(this);
            const rating = $label.attr('for').replace('rating-', '');

            // Highlight stars on hover
            $ratingInputs.find('label').each(function () {
                const labelRating = $(this).attr('for').replace('rating-', '');
                if (labelRating <= rating) {
                    $(this).addClass('qlcm-star-hover');
                } else {
                    $(this).removeClass('qlcm-star-hover');
                }
            });
        });

        $ratingInputs.on('mouseleave', function () {
            // Remove hover effects
            $(this).find('label').removeClass('qlcm-star-hover');
        });

        // Handle star selection
        $ratingInputs.on('change', 'input[type=\"radio\"]', function () {
            const rating = $(this).val();
            updateStarDisplay($ratingInputs, rating);
        });

        // Initialize star display based on existing selection
        const $checkedInput = $ratingInputs.find('input[type=\"radio\"]:checked');
        if ($checkedInput.length) {
            updateStarDisplay($ratingInputs, $checkedInput.val());
        }
    }

    /**
     * Update star display based on selected rating
     */
    function updateStarDisplay($container, rating) {
        $container.find('label').each(function () {
            const labelRating = $(this).attr('for').replace('rating-', '');
            if (labelRating <= rating) {
                $(this).addClass('qlcm-star-selected');
            } else {
                $(this).removeClass('qlcm-star-selected');
            }
        });
    }

    /**
     * Submit rating form via AJAX
     */
    function submitRating($form) {
        const $submitButton = $form.find('.qlcm-submit-rating');
        const originalText = $submitButton.text();

        // Check if rating is selected
        const rating = $form.find('input[name=\"rating\"]:checked').val();
        if (!rating) {
            showMessage(__('Please select a rating'), 'error');
            return;
        }

        // Disable form and show loading state
        $form.addClass('qlcm-loading');
        $submitButton.prop('disabled', true).text(qlcm_ratings.i18n.submitting);

        // Prepare form data
        const formData = {
            action: 'submit_course_rating',
            nonce: $form.find('input[name=\"nonce\"]').val(),
            course_id: $form.find('input[name=\"course_id\"]').val(),
            rating: rating,
            review_title: $form.find('input[name=\"review_title\"]').val(),
            review_content: $form.find('textarea[name=\"review_content\"]').val()
        };

        // Submit via AJAX
        $.ajax({
            url: qlcm_ratings.ajax_url,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showMessage(response.data.message, 'success');

                    // Update rating display if provided
                    if (response.data.rating_data) {
                        updateRatingDisplay(response.data.rating_data);
                    }

                    // Optionally reload the page to show the new review
                    setTimeout(function () {
                        location.reload();
                    }, 2000);

                } else {
                    showMessage(response.data.message || qlcm_ratings.i18n.error, 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('Rating submission error:', error);
                showMessage(qlcm_ratings.i18n.error, 'error');
            },
            complete: function () {
                // Re-enable form
                $form.removeClass('qlcm-loading');
                $submitButton.prop('disabled', false).text(originalText);
            }
        });
    }

    /**
     * Initialize load more reviews functionality
     */
    function initLoadMoreReviews() {
        $(document).on('click', '.qlcm-load-more-reviews', function (e) {
            e.preventDefault();

            const $link = $(this);
            const courseId = $link.data('course-id');
            const $reviewsList = $('.qlcm-reviews-list');
            const currentCount = $reviewsList.find('.qlcm-review-item').length;

            // Show loading state
            $link.text(__('Loading...')).addClass('qlcm-loading');

            // Load more reviews
            $.ajax({
                url: qlcm_ratings.ajax_url,
                type: 'POST',
                data: {
                    action: 'load_more_reviews',
                    nonce: qlcm_ratings.nonce,
                    course_id: courseId,
                    offset: currentCount
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success && response.data.reviews) {
                        // Append new reviews
                        $reviewsList.append(response.data.reviews);

                        // Update or hide the load more link
                        if (response.data.has_more) {
                            $link.text(sprintf(__('Show %d more reviews'), response.data.remaining));
                        } else {
                            $link.parent().hide();
                        }
                    } else {
                        $link.parent().hide();
                    }
                },
                error: function () {
                    showMessage(__('Failed to load more reviews'), 'error');
                },
                complete: function () {
                    $link.removeClass('qlcm-loading');
                }
            });
        });
    }

    /**
     * Update rating display in the summary section
     */
    function updateRatingDisplay(ratingData) {
        const $summary = $('.qlcm-course-rating-summary');

        if ($summary.length && ratingData.count > 0) {
            // Update average rating
            $summary.find('.qlcm-rating-average').text(ratingData.average.toFixed(1));

            // Update review count
            const reviewText = ratingData.count === 1 ?
                sprintf(__('%s review'), ratingData.count) :
                sprintf(__('%s reviews'), ratingData.count);
            $summary.find('.qlcm-rating-count').text('(' + reviewText + ')');

            // Update stars (this would require more complex logic to update the star display)
            // For now, we'll just reload the page to show updated ratings
        }
    }

    /**
     * Show success/error message
     */
    function showMessage(message, type) {
        // Remove existing messages
        $('.qlcm-message').remove();

        // Create new message
        const $message = $('<div class=\"qlcm-message qlcm-message-' + type + '\">' + message + '</div>');

        // Insert message
        const $form = $('#qlcm-rating-form');
        if ($form.length) {
            $form.before($message);
        } else {
            $('.qlcm-course-reviews').prepend($message);
        }

        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(function () {
                $message.fadeOut(function () {
                    $(this).remove();
                });
            }, 5000);
        }

        // Scroll to message
        $('html, body').animate({
            scrollTop: $message.offset().top - 100
        }, 500);
    }

    /**
     * Simple translation function (fallback)
     */
    function __(text) {
        return qlcm_ratings.i18n[text] || text;
    }

    /**
     * Simple sprintf function (fallback)
     */
    function sprintf(format, ...args) {
        return format.replace(/%[sd]/g, function () {
            return args.shift();
        });
    }

})(jQuery);