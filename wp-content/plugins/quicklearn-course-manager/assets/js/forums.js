/**
 * Course Forums JavaScript
 * Handles forum interactions, AJAX requests, and UI updates
 */

class QLCMForumManager {
    constructor(courseId) {
        this.courseId = courseId;
        this.currentPage = 1;
        this.currentSort = 'recent';
        this.currentSearch = '';
        this.isLoading = false;
    }
    
    init() {
        this.bindEvents();
        this.loadForumPosts();
    }
    
    bindEvents() {
        const $ = jQuery;
        
        // New topic button
        $('#new-topic-btn').on('click', () => {
            this.showNewTopicForm();
        });
        
        // Cancel new topic
        $('#cancel-topic').on('click', () => {
            this.hideNewTopicForm();
        });
        
        // Forum post form submission
        $('#forum-post-form').on('submit', (e) => {
            e.preventDefault();
            this.submitForumPost();
        });
        
        // Search functionality
        $('#forum-search-btn').on('click', () => {
            this.searchPosts();
        });
        
        $('#forum-search-input').on('keypress', (e) => {
            if (e.which === 13) {
                this.searchPosts();
            }
        });
        
        // Sort change
        $('#forum-sort').on('change', (e) => {
            this.currentSort = e.target.value;
            this.currentPage = 1;
            this.loadForumPosts();
        });
        
        // Delegate events for dynamically loaded content
        $(document).on('click', '.reply-btn', (e) => {
            const postId = $(e.target).data('post-id');
            this.showReplyForm(postId);
        });
        
        $(document).on('click', '.cancel-reply', () => {
            this.hideReplyForm();
        });
        
        $(document).on('submit', '.reply-form', (e) => {
            e.preventDefault();
            this.submitReply(e.target);
        });
        
        $(document).on('click', '.moderate-btn', (e) => {
            const postId = $(e.target).data('post-id');
            const action = $(e.target).data('action');
            this.moderatePost(postId, action);
        });
        
        $(document).on('click', '.load-more-replies', (e) => {
            const postId = $(e.target).data('post-id');
            this.loadMoreReplies(postId);
        });
        
        $(document).on('click', '.pagination-btn', (e) => {
            const page = $(e.target).data('page');
            this.loadPage(page);
        });
    }
    
    showNewTopicForm() {
        jQuery('#new-topic-form').slideDown();
        jQuery('#topic-title').focus();
    }
    
    hideNewTopicForm() {
        jQuery('#new-topic-form').slideUp();
        jQuery('#forum-post-form')[0].reset();
    }
    
    submitForumPost() {
        if (this.isLoading) return;
        
        const $ = jQuery;
        const formData = new FormData($('#forum-post-form')[0]);
        formData.append('action', 'submit_forum_post');
        formData.append('nonce', qlcm_ajax.nonce);
        
        this.setLoading(true);
        
        $.ajax({
            url: qlcm_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: (response) => {
                if (response.success) {
                    this.hideNewTopicForm();
                    this.showMessage('Discussion posted successfully!', 'success');
                    this.loadForumPosts(); // Reload to show new post
                } else {
                    this.showMessage(response.data || 'Failed to post discussion.', 'error');
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
    
    loadForumPosts() {
        if (this.isLoading) return;
        
        const $ = jQuery;
        this.setLoading(true);
        
        $.ajax({
            url: qlcm_ajax.ajax_url,
            type: 'GET',
            data: {
                action: 'load_forum_posts',
                course_id: this.courseId,
                page: this.currentPage,
                sort: this.currentSort,
                search: this.currentSearch
            },
            success: (response) => {
                if (response.success) {
                    this.renderForumPosts(response.data.posts);
                    this.renderPagination(response.data);
                } else {
                    this.showMessage('Failed to load discussions.', 'error');
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
    
    renderForumPosts(posts) {
        const $ = jQuery;
        const container = $('#forum-posts-list');
        
        if (posts.length === 0) {
            container.html('<div class="no-posts">No discussions found. Be the first to start a conversation!</div>');
            return;
        }
        
        let html = '';
        posts.forEach(post => {
            html += this.renderForumPost(post);
        });
        
        container.html(html);
    }
    
    renderForumPost(post) {
        const timeAgo = this.timeAgo(new Date(post.created_date));
        const pinnedBadge = post.is_pinned == 1 ? '<span class="pinned-badge">📌 Pinned</span>' : '';
        const moderationButtons = post.can_moderate ? this.renderModerationButtons(post) : '';
        
        let repliesHtml = '';
        if (post.replies && post.replies.length > 0) {
            repliesHtml = '<div class="post-replies">';
            post.replies.forEach(reply => {
                repliesHtml += this.renderReply(reply);
            });
            if (post.reply_count > post.replies.length) {
                repliesHtml += `<button class="load-more-replies" data-post-id="${post.id}">
                    Load ${post.reply_count - post.replies.length} more replies
                </button>`;
            }
            repliesHtml += '</div>';
        }
        
        return `
            <div class="forum-post" data-post-id="${post.id}">
                <div class="post-header">
                    <div class="post-author">
                        <img src="${post.avatar_url}" alt="${post.display_name}" class="avatar" />
                        <div class="author-info">
                            <strong>${post.display_name}</strong>
                            <span class="post-date">${timeAgo}</span>
                        </div>
                    </div>
                    <div class="post-meta">
                        ${pinnedBadge}
                        <span class="reply-count">${post.reply_count} replies</span>
                    </div>
                </div>
                
                <div class="post-content">
                    <h4 class="post-title">${post.title}</h4>
                    <div class="post-text">${post.content}</div>
                </div>
                
                <div class="post-actions">
                    <button class="reply-btn" data-post-id="${post.id}">Reply</button>
                    ${moderationButtons}
                </div>
                
                ${repliesHtml}
                
                <div class="reply-form-container" id="reply-form-${post.id}" style="display: none;">
                    <form class="reply-form">
                        <input type="hidden" name="course_id" value="${this.courseId}" />
                        <input type="hidden" name="parent_id" value="${post.id}" />
                        <textarea name="content" placeholder="Write your reply..." required rows="3"></textarea>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Post Reply</button>
                            <button type="button" class="btn btn-secondary cancel-reply">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
    }
    
    renderReply(reply) {
        const timeAgo = this.timeAgo(new Date(reply.created_date));
        
        return `
            <div class="forum-reply" data-reply-id="${reply.id}">
                <div class="reply-header">
                    <img src="${reply.avatar_url}" alt="${reply.display_name}" class="avatar small" />
                    <div class="reply-author">
                        <strong>${reply.display_name}</strong>
                        <span class="reply-date">${timeAgo}</span>
                    </div>
                </div>
                <div class="reply-content">${reply.content}</div>
            </div>
        `;
    }
    
    renderModerationButtons(post) {
        const pinAction = post.is_pinned == 1 ? 'unpin' : 'pin';
        const pinText = post.is_pinned == 1 ? 'Unpin' : 'Pin';
        const hideAction = post.status === 'hidden' ? 'show' : 'hide';
        const hideText = post.status === 'hidden' ? 'Show' : 'Hide';
        
        return `
            <div class="moderation-buttons">
                <button class="moderate-btn" data-post-id="${post.id}" data-action="${pinAction}">${pinText}</button>
                <button class="moderate-btn" data-post-id="${post.id}" data-action="${hideAction}">${hideText}</button>
            </div>
        `;
    }
    
    showReplyForm(postId) {
        const $ = jQuery;
        $('.reply-form-container').hide(); // Hide other reply forms
        $(`#reply-form-${postId}`).slideDown();
        $(`#reply-form-${postId} textarea`).focus();
    }
    
    hideReplyForm() {
        jQuery('.reply-form-container').slideUp();
        jQuery('.reply-form textarea').val('');
    }
    
    submitReply(form) {
        if (this.isLoading) return;
        
        const $ = jQuery;
        const formData = new FormData(form);
        formData.append('action', 'submit_forum_post');
        formData.append('nonce', qlcm_ajax.nonce);
        formData.append('title', ''); // Replies don't need titles
        
        this.setLoading(true);
        
        $.ajax({
            url: qlcm_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: (response) => {
                if (response.success) {
                    this.hideReplyForm();
                    this.showMessage('Reply posted successfully!', 'success');
                    this.loadForumPosts(); // Reload to show new reply
                } else {
                    this.showMessage(response.data || 'Failed to post reply.', 'error');
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
    
    moderatePost(postId, action) {
        if (this.isLoading) return;
        
        const $ = jQuery;
        this.setLoading(true);
        
        $.ajax({
            url: qlcm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'moderate_forum_post',
                post_id: postId,
                action_type: action,
                course_id: this.courseId,
                nonce: qlcm_ajax.nonce
            },
            success: (response) => {
                if (response.success) {
                    this.showMessage('Post moderated successfully!', 'success');
                    this.loadForumPosts(); // Reload to show changes
                } else {
                    this.showMessage(response.data || 'Failed to moderate post.', 'error');
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
    
    searchPosts() {
        const searchTerm = jQuery('#forum-search-input').val().trim();
        this.currentSearch = searchTerm;
        this.currentPage = 1;
        this.loadForumPosts();
    }
    
    loadMoreReplies(postId) {
        // This would load more replies for a specific post
        // Implementation would be similar to loadForumPosts but for replies
        console.log('Loading more replies for post:', postId);
    }
    
    loadPage(page) {
        this.currentPage = page;
        this.loadForumPosts();
    }
    
    renderPagination(data) {
        const $ = jQuery;
        const totalPages = Math.ceil(data.total / data.per_page);
        const currentPage = data.page;
        
        if (totalPages <= 1) {
            $('#forum-pagination').empty();
            return;
        }
        
        let html = '<div class="pagination">';
        
        // Previous button
        if (currentPage > 1) {
            html += `<button class="pagination-btn" data-page="${currentPage - 1}">Previous</button>`;
        }
        
        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === currentPage) {
                html += `<span class="current-page">${i}</span>`;
            } else if (i === 1 || i === totalPages || Math.abs(i - currentPage) <= 2) {
                html += `<button class="pagination-btn" data-page="${i}">${i}</button>`;
            } else if (i === currentPage - 3 || i === currentPage + 3) {
                html += '<span class="pagination-dots">...</span>';
            }
        }
        
        // Next button
        if (currentPage < totalPages) {
            html += `<button class="pagination-btn" data-page="${currentPage + 1}">Next</button>`;
        }
        
        html += '</div>';
        $('#forum-pagination').html(html);
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
        $('.forum-message').remove();
        
        // Add new message
        const messageHtml = `<div class="forum-message ${messageClass}">${message}</div>`;
        $('.qlcm-course-forum').prepend(messageHtml);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            $('.forum-message').fadeOut();
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
window.QLCMForumManager = QLCMForumManager;