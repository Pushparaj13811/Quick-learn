/**
 * Course Q&A JavaScript
 * Handles Q&A interactions, voting, and UI updates
 */

class QLCMQAManager {
    constructor(courseId) {
        this.courseId = courseId;
        this.currentPage = 1;
        this.currentFilter = 'all';
        this.currentSort = 'recent';
        this.isLoading = false;
    }
    
    init() {
        this.bindEvents();
        this.loadQAContent();
    }
    
    bindEvents() {
        const $ = jQuery;
        
        // Ask question button
        $('#ask-question-btn').on('click', () => {
            this.showQuestionForm();
        });
        
        // Cancel question
        $('#cancel-question').on('click', () => {
            this.hideQuestionForm();
        });
        
        // Question form submission
        $('#question-form').on('submit', (e) => {
            e.preventDefault();
            this.submitQuestion();
        });
        
        // Filter buttons
        $('.filter-btn').on('click', (e) => {
            const filter = $(e.target).data('filter');
            this.setFilter(filter);
        });
        
        // Sort change
        $('#qa-sort').on('change', (e) => {
            this.currentSort = e.target.value;
            this.currentPage = 1;
            this.loadQAContent();
        });
        
        // Delegate events for dynamically loaded content
        $(document).on('click', '.answer-btn', (e) => {
            const questionId = $(e.target).data('question-id');
            this.showAnswerForm(questionId);
        });
        
        $(document).on('click', '.cancel-answer', () => {
            this.hideAnswerForm();
        });
        
        $(document).on('submit', '.answer-form', (e) => {
            e.preventDefault();
            this.submitAnswer(e.target);
        });
        
        $(document).on('click', '.vote-btn', (e) => {
            const answerId = $(e.target).data('answer-id');
            const voteType = $(e.target).data('vote-type');
            this.voteAnswer(answerId, voteType);
        });
        
        $(document).on('click', '.helpful-btn', (e) => {
            const answerId = $(e.target).data('answer-id');
            const isHelpful = $(e.target).hasClass('mark-helpful') ? 1 : 0;
            this.markAnswerHelpful(answerId, isHelpful);
        });
        
        $(document).on('click', '.load-more-answers', (e) => {
            const questionId = $(e.target).data('question-id');
            this.loadMoreAnswers(questionId);
        });
        
        $(document).on('click', '.qa-pagination-btn', (e) => {
            const page = $(e.target).data('page');
            this.loadPage(page);
        });
    }
    
    showQuestionForm() {
        jQuery('#ask-question-form').slideDown();
        jQuery('#question-title').focus();
    }
    
    hideQuestionForm() {
        jQuery('#ask-question-form').slideUp();
        jQuery('#question-form')[0].reset();
    }
    
    submitQuestion() {
        if (this.isLoading) return;
        
        const $ = jQuery;
        const formData = new FormData($('#question-form')[0]);
        formData.append('action', 'submit_question');
        formData.append('nonce', qlcm_qa_ajax.nonce);
        
        this.setLoading(true);
        
        $.ajax({
            url: qlcm_qa_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: (response) => {
                if (response.success) {
                    this.hideQuestionForm();
                    this.showMessage('Question posted successfully!', 'success');
                    this.loadQAContent(); // Reload to show new question
                } else {
                    this.showMessage(response.data || 'Failed to post question.', 'error');
                }
            },
            error: () => {
                this.showMessage('Network error. Please try again.', 'error');
            },
            complete: () => {
                this.setLoading(false);
            }
        });
    }
    
    setFilter(filter) {
        const $ = jQuery;
        $('.filter-btn').removeClass('active');
        $(`.filter-btn[data-filter="${filter}"]`).addClass('active');
        
        this.currentFilter = filter;
        this.currentPage = 1;
        this.loadQAContent();
    }
    
    loadQAContent() {
        if (this.isLoading) return;
        
        const $ = jQuery;
        this.setLoading(true);
        
        $.ajax({
            url: qlcm_qa_ajax.ajax_url,
            type: 'GET',
            data: {
                action: 'load_qa_content',
                course_id: this.courseId,
                page: this.currentPage,
                filter: this.currentFilter,
                sort: this.currentSort
            },
            success: (response) => {
                if (response.success) {
                    this.renderQAContent(response.data.questions);
                    this.renderPagination(response.data);
                } else {
                    this.showMessage('Failed to load questions.', 'error');
                }
            },
            error: () => {
                this.showMessage('Network error. Please try again.', 'error');
            },
            complete: () => {
                this.setLoading(false);
            }
        });
    }
    
    renderQAContent(questions) {
        const $ = jQuery;
        const container = $('#qa-content-list');
        
        if (questions.length === 0) {
            container.html('<div class="no-questions">No questions found. Be the first to ask!</div>');
            return;
        }
        
        let html = '';
        questions.forEach(question => {
            html += this.renderQuestion(question);
        });
        
        container.html(html);
    }
    
    renderQuestion(question) {
        const timeAgo = this.timeAgo(new Date(question.created_date));
        const answeredBadge = question.is_answered ? '<span class="answered-badge">✓ Answered</span>' : '<span class="unanswered-badge">Unanswered</span>';
        
        let answersHtml = '';
        if (question.answers && question.answers.length > 0) {
            answersHtml = '<div class="question-answers">';
            question.answers.forEach(answer => {
                answersHtml += this.renderAnswer(answer);
            });
            if (question.answer_count > question.answers.length) {
                answersHtml += `<button class="load-more-answers" data-question-id="${question.id}">
                    Load ${question.answer_count - question.answers.length} more answers
                </button>`;
            }
            answersHtml += '</div>';
        }
        
        return `
            <div class="qa-question" data-question-id="${question.id}">
                <div class="question-header">
                    <div class="question-author">
                        <img src="${question.avatar_url}" alt="${question.display_name}" class="avatar" />
                        <div class="author-info">
                            <strong>${question.display_name}</strong>
                            <span class="question-date">${timeAgo}</span>
                        </div>
                    </div>
                    <div class="question-meta">
                        ${answeredBadge}
                        <span class="answer-count">${question.answer_count} answers</span>
                        <span class="view-count">${question.view_count} views</span>
                    </div>
                </div>
                
                <div class="question-content">
                    <h4 class="question-title">${question.title}</h4>
                    <div class="question-text">${question.content}</div>
                </div>
                
                <div class="question-actions">
                    <button class="answer-btn" data-question-id="${question.id}">Answer</button>
                </div>
                
                ${answersHtml}
                
                <div class="answer-form-container" id="answer-form-${question.id}" style="display: none;">
                    <form class="answer-form">
                        <input type="hidden" name="question_id" value="${question.id}" />
                        <textarea name="content" placeholder="Write your answer..." required rows="4"></textarea>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Post Answer</button>
                            <button type="button" class="btn btn-secondary cancel-answer">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
    }
    
    renderAnswer(answer) {
        const timeAgo = this.timeAgo(new Date(answer.created_date));
        const helpfulBadge = answer.is_helpful ? '<span class="helpful-badge">✓ Helpful</span>' : '';
        const userVote = answer.user_vote;
        const canMarkHelpful = qlcm_qa_ajax.can_moderate || false; // This would be set in localization
        
        const upvoteClass = userVote === 'upvote' ? 'voted' : '';
        const downvoteClass = userVote === 'downvote' ? 'voted' : '';
        
        let moderationButtons = '';
        if (canMarkHelpful) {
            if (answer.is_helpful) {
                moderationButtons = `<button class="helpful-btn unmark-helpful" data-answer-id="${answer.id}">Unmark Helpful</button>`;
            } else {
                moderationButtons = `<button class="helpful-btn mark-helpful" data-answer-id="${answer.id}">Mark Helpful</button>`;
            }
        }
        
        return `
            <div class="qa-answer ${answer.is_helpful ? 'helpful-answer' : ''}" data-answer-id="${answer.id}">
                <div class="answer-voting">
                    <button class="vote-btn upvote ${upvoteClass}" data-answer-id="${answer.id}" data-vote-type="upvote">
                        ▲
                    </button>
                    <span class="vote-score">${answer.vote_score}</span>
                    <button class="vote-btn downvote ${downvoteClass}" data-answer-id="${answer.id}" data-vote-type="downvote">
                        ▼
                    </button>
                </div>
                
                <div class="answer-content">
                    <div class="answer-header">
                        <img src="${answer.avatar_url}" alt="${answer.display_name}" class="avatar small" />
                        <div class="answer-author">
                            <strong>${answer.display_name}</strong>
                            <span class="answer-date">${timeAgo}</span>
                            ${helpfulBadge}
                        </div>
                    </div>
                    <div class="answer-text">${answer.content}</div>
                    <div class="answer-actions">
                        ${moderationButtons}
                    </div>
                </div>
            </div>
        `;
    }
    
    showAnswerForm(questionId) {
        const $ = jQuery;
        $('.answer-form-container').hide(); // Hide other answer forms
        $(`#answer-form-${questionId}`).slideDown();
        $(`#answer-form-${questionId} textarea`).focus();
    }
    
    hideAnswerForm() {
        jQuery('.answer-form-container').slideUp();
        jQuery('.answer-form textarea').val('');
    }
    
    submitAnswer(form) {
        if (this.isLoading) return;
        
        const $ = jQuery;
        const formData = new FormData(form);
        formData.append('action', 'submit_answer');
        formData.append('nonce', qlcm_qa_ajax.nonce);
        
        this.setLoading(true);
        
        $.ajax({
            url: qlcm_qa_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: (response) => {
                if (response.success) {
                    this.hideAnswerForm();
                    this.showMessage('Answer posted successfully!', 'success');
                    this.loadQAContent(); // Reload to show new answer
                } else {
                    this.showMessage(response.data || 'Failed to post answer.', 'error');
                }
            },
            error: () => {
                this.showMessage('Network error. Please try again.', 'error');
            },
            complete: () => {
                this.setLoading(false);
            }
        });
    }
    
    voteAnswer(answerId, voteType) {
        if (this.isLoading) return;
        
        const $ = jQuery;
        this.setLoading(true);
        
        $.ajax({
            url: qlcm_qa_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'vote_answer',
                answer_id: answerId,
                vote_type: voteType,
                nonce: qlcm_qa_ajax.nonce
            },
            success: (response) => {
                if (response.success) {
                    this.updateVoteDisplay(answerId, response.data);
                    this.showMessage('Vote recorded!', 'success');
                } else {
                    this.showMessage(response.data || 'Failed to record vote.', 'error');
                }
            },
            error: () => {
                this.showMessage('Network error. Please try again.', 'error');
            },
            complete: () => {
                this.setLoading(false);
            }
        });
    }
    
    updateVoteDisplay(answerId, voteData) {
        const $ = jQuery;
        const answerElement = $(`.qa-answer[data-answer-id="${answerId}"]`);
        
        // Update vote score
        answerElement.find('.vote-score').text(voteData.vote_score);
        
        // Update button states
        answerElement.find('.vote-btn').removeClass('voted');
        if (voteData.vote_action === 'added' || voteData.vote_action === 'updated') {
            // Determine which button should be active based on the last action
            // This is a simplified approach - in a real app you'd track the current user vote
            answerElement.find('.upvote').toggleClass('voted', voteData.upvotes > voteData.downvotes);
            answerElement.find('.downvote').toggleClass('voted', voteData.downvotes > voteData.upvotes);
        }
    }
    
    markAnswerHelpful(answerId, isHelpful) {
        if (this.isLoading) return;
        
        const $ = jQuery;
        this.setLoading(true);
        
        $.ajax({
            url: qlcm_qa_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mark_answer_helpful',
                answer_id: answerId,
                is_helpful: isHelpful,
                nonce: qlcm_qa_ajax.nonce
            },
            success: (response) => {
                if (response.success) {
                    this.showMessage('Answer marked successfully!', 'success');
                    this.loadQAContent(); // Reload to show changes
                } else {
                    this.showMessage(response.data || 'Failed to mark answer.', 'error');
                }
            },
            error: () => {
                this.showMessage('Network error. Please try again.', 'error');
            },
            complete: () => {
                this.setLoading(false);
            }
        });
    }
    
    loadMoreAnswers(questionId) {
        // This would load more answers for a specific question
        console.log('Loading more answers for question:', questionId);
    }
    
    loadPage(page) {
        this.currentPage = page;
        this.loadQAContent();
    }
    
    renderPagination(data) {
        const $ = jQuery;
        const totalPages = Math.ceil(data.total / data.per_page);
        const currentPage = data.page;
        
        if (totalPages <= 1) {
            $('#qa-pagination').empty();
            return;
        }
        
        let html = '<div class="pagination">';
        
        // Previous button
        if (currentPage > 1) {
            html += `<button class="qa-pagination-btn" data-page="${currentPage - 1}">Previous</button>`;
        }
        
        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === currentPage) {
                html += `<span class="current-page">${i}</span>`;
            } else if (i === 1 || i === totalPages || Math.abs(i - currentPage) <= 2) {
                html += `<button class="qa-pagination-btn" data-page="${i}">${i}</button>`;
            } else if (i === currentPage - 3 || i === currentPage + 3) {
                html += '<span class="pagination-dots">...</span>';
            }
        }
        
        // Next button
        if (currentPage < totalPages) {
            html += `<button class="qa-pagination-btn" data-page="${currentPage + 1}">Next</button>`;
        }
        
        html += '</div>';
        $('#qa-pagination').html(html);
    }
    
    setLoading(loading) {
        this.isLoading = loading;
        const $ = jQuery;
        
        if (loading) {
            $('.loading-spinner').show();
            $('button').prop('disabled', true);
        } else {
            $('.loading-spinner').hide();
            $('button').prop('disabled', false);
        }
    }
    
    showMessage(message, type) {
        const $ = jQuery;
        const messageClass = type === 'success' ? 'success-message' : 'error-message';
        
        // Remove existing messages
        $('.qa-message').remove();
        
        // Add new message
        const messageHtml = `<div class="qa-message ${messageClass}">${message}</div>`;
        $('.qlcm-course-qa').prepend(messageHtml);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            $('.qa-message').fadeOut();
        }, 5000);
    }
    
    timeAgo(date) {
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        if (diffInSeconds < 60) {
            return 'just now';
        } else if (diffInSeconds < 3600) {
            const minutes = Math.floor(diffInSeconds / 60);
            return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        } else if (diffInSeconds < 86400) {
            const hours = Math.floor(diffInSeconds / 3600);
            return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        } else if (diffInSeconds < 2592000) {
            const days = Math.floor(diffInSeconds / 86400);
            return `${days} day${days > 1 ? 's' : ''} ago`;
        } else {
            return date.toLocaleDateString();
        }
    }
}

// Make it globally available
window.QLCMQAManager = QLCMQAManager;