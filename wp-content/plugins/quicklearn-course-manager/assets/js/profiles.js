/**
 * User Profiles and Messaging JavaScript
 * Handles profile interactions and private messaging
 */

class QLCMProfileManager {
    constructor() {
        this.isLoading = false;
    }
    
    init() {
        this.bindEvents();
    }
    
    bindEvents() {
        const $ = jQuery;
        
        // Send message button
        $(document).on('click', '.send-message-btn', (e) => {
            const userId = $(e.target).data('user-id');
            this.showMessageModal(userId);
        });
        
        // Modal close buttons
        $(document).on('click', '.modal-close', () => {
            this.hideModal();
        });
        
        // Click outside modal to close
        $(document).on('click', '.modal', (e) => {
            if (e.target === e.currentTarget) {
                this.hideModal();
            }
        });
        
        // Private message form submission
        $(document).on('submit', '#private-message-form', (e) => {
            e.preventDefault();
            this.sendPrivateMessage();
        });
    }
    
    showMessageModal(userId) {
        jQuery('#message-modal').fadeIn();
        jQuery('#message-subject').focus();
    }
    
    hideModal() {
        jQuery('.modal').fadeOut();
        jQuery('form')[0]?.reset();
    }
    
    sendPrivateMessage() {
        if (this.isLoading) return;
        
        const $ = jQuery;
        const formData = new FormData($('#private-message-form')[0]);
        formData.append('action', 'send_private_message');
        formData.append('nonce', qlcm_profiles_ajax.nonce);
        
        this.setLoading(true);
        
        $.ajax({
            url: qlcm_profiles_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: (response) => {
                if (response.success) {
                    this.hideModal();
                    this.showMessage('Message sent successfully!', 'success');
                } else {
                    this.showMessage(response.data || 'Failed to send message.', 'error');
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
    
    setLoading(loading) {
        this.isLoading = loading;
        const $ = jQuery;
        
        if (loading) {
            $('button').prop('disabled', true);
        } else {
            $('button').prop('disabled', false);
        }
    }
    
    showMessage(message, type) {
        const $ = jQuery;
        const messageClass = type === 'success' ? 'success-message' : 'error-message';
        
        // Remove existing messages
        $('.profile-message').remove();
        
        // Add new message
        const messageHtml = `<div class="profile-message ${messageClass}">${message}</div>`;
        $('.qlcm-user-profile, .qlcm-user-messages').prepend(messageHtml);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            $('.profile-message').fadeOut();
        }, 5000);
    }
}

class QLCMMessagesManager {
    constructor() {
        this.currentFolder = 'inbox';
        this.currentMessage = null;
        this.isLoading = false;
    }
    
    init() {
        this.bindEvents();
        this.loadMessages();
    }
    
    bindEvents() {
        const $ = jQuery;
        
        // Folder navigation
        $(document).on('click', '.folder-btn', (e) => {
            const folder = $(e.target).data('folder');
            this.switchFolder(folder);
        });
        
        // Compose message button
        $(document).on('click', '.compose-message-btn', () => {
            this.showComposeModal();
        });
        
        // Message list item click
        $(document).on('click', '.message-item', (e) => {
            const messageId = $(e.target).closest('.message-item').data('message-id');
            this.viewMessage(messageId);
        });
        
        // Back to list
        $(document).on('click', '.back-to-list', () => {
            this.showMessagesList();
        });
        
        // Message actions
        $(document).on('click', '.reply-btn', () => {
            this.replyToMessage();
        });
        
        $(document).on('click', '.archive-btn', () => {
            this.archiveMessage();
        });
        
        $(document).on('click', '.delete-btn', () => {
            this.deleteMessage();
        });
        
        // Compose form submission
        $(document).on('submit', '#compose-message-form', (e) => {
            e.preventDefault();
            this.sendMessage();
        });
        
        // Recipient search
        $(document).on('input', '#recipient-search', (e) => {
            this.searchRecipients(e.target.value);
        });
        
        // Recipient selection
        $(document).on('click', '.recipient-result', (e) => {
            const userId = $(e.target).data('user-id');
            const userName = $(e.target).text();
            $('#recipient-id').val(userId);
            $('#recipient-search').val(userName);
            $('#recipient-search-results').empty();
        });
    }
    
    switchFolder(folder) {
        const $ = jQuery;
        $('.folder-btn').removeClass('active');
        $(`.folder-btn[data-folder="${folder}"]`).addClass('active');
        
        this.currentFolder = folder;
        this.loadMessages();
    }
    
    loadMessages() {
        if (this.isLoading) return;
        
        const $ = jQuery;
        this.setLoading(true);
        
        $.ajax({
            url: qlcm_profiles_ajax.ajax_url,
            type: 'GET',
            data: {
                action: 'load_messages',
                folder: this.currentFolder
            },
            success: (response) => {
                if (response.success) {
                    this.renderMessagesList(response.data);
                } else {
                    this.showMessage('Failed to load messages.', 'error');
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
    
    renderMessagesList(messages) {
        const $ = jQuery;
        const container = $('#messages-list');
        
        if (messages.length === 0) {
            container.html('<div class="no-messages">No messages found.</div>');
            return;
        }
        
        let html = '';
        messages.forEach(message => {
            const isUnread = message.status === 'unread';
            const sender = this.currentFolder === 'sent' ? message.recipient_name : message.sender_name;
            const timeAgo = this.timeAgo(new Date(message.created_date));
            
            html += `
                <div class="message-item ${isUnread ? 'unread' : ''}" data-message-id="${message.id}">
                    <div class="message-sender">${sender}</div>
                    <div class="message-subject">${message.subject}</div>
                    <div class="message-preview">${this.truncateText(message.content, 100)}</div>
                    <div class="message-date">${timeAgo}</div>
                </div>
            `;
        });
        
        container.html(html);
        this.updateUnreadCount();
    }
    
    viewMessage(messageId) {
        if (this.isLoading) return;
        
        const $ = jQuery;
        this.setLoading(true);
        
        // Find message in current list
        const messageData = this.findMessageById(messageId);
        if (!messageData) return;
        
        this.currentMessage = messageData;
        this.renderMessageDetail(messageData);
        this.showMessageDetail();
        
        // Mark as read if it's unread
        if (messageData.status === 'unread') {
            this.markMessageRead(messageId);
        }
        
        this.setLoading(false);
    }
    
    renderMessageDetail(message) {
        const $ = jQuery;
        const sender = this.currentFolder === 'sent' ? message.recipient_name : message.sender_name;
        const timeAgo = this.timeAgo(new Date(message.created_date));
        
        const html = `
            <div class="message-detail-header">
                <h3>${message.subject}</h3>
                <div class="message-meta">
                    <span class="message-from">From: ${sender}</span>
                    <span class="message-time">${timeAgo}</span>
                </div>
            </div>
            <div class="message-detail-content">
                ${message.content}
            </div>
        `;
        
        $('.message-content-area').html(html);
    }
    
    showMessageDetail() {
        jQuery('.messages-list').hide();
        jQuery('#message-detail').show();
    }
    
    showMessagesList() {
        jQuery('#message-detail').hide();
        jQuery('.messages-list').show();
    }
    
    showComposeModal() {
        jQuery('#compose-modal').fadeIn();
        jQuery('#recipient-search').focus();
    }
    
    sendMessage() {
        if (this.isLoading) return;
        
        const $ = jQuery;
        const formData = new FormData($('#compose-message-form')[0]);
        formData.append('action', 'send_private_message');
        formData.append('nonce', qlcm_profiles_ajax.nonce);
        
        this.setLoading(true);
        
        $.ajax({
            url: qlcm_profiles_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: (response) => {
                if (response.success) {
                    this.hideModal();
                    this.showMessage('Message sent successfully!', 'success');
                    if (this.currentFolder === 'sent') {
                        this.loadMessages();
                    }
                } else {
                    this.showMessage(response.data || 'Failed to send message.', 'error');
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
    
    searchRecipients(searchTerm) {
        if (searchTerm.length < 3) {
            jQuery('#recipient-search-results').empty();
            return;
        }
        
        const $ = jQuery;
        
        $.ajax({
            url: qlcm_profiles_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'search_users',
                search: searchTerm,
                nonce: qlcm_profiles_ajax.nonce
            },
            success: (response) => {
                if (response.success) {
                    let html = '<div class="recipient-results">';
                    response.data.forEach(user => {
                        html += `<div class="recipient-result" data-user-id="${user.ID}">${user.display_name} (${user.user_email})</div>`;
                    });
                    html += '</div>';
                    $('#recipient-search-results').html(html);
                }
            }
        });
    }
    
    markMessageRead(messageId) {
        const $ = jQuery;
        
        $.ajax({
            url: qlcm_profiles_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mark_message_read',
                message_id: messageId,
                nonce: qlcm_profiles_ajax.nonce
            }
        });
    }
    
    replyToMessage() {
        if (!this.currentMessage) return;
        
        const $ = jQuery;
        $('#recipient-id').val(this.currentMessage.sender_id);
        $('#recipient-search').val(this.currentMessage.sender_name);
        $('#compose-subject').val('Re: ' + this.currentMessage.subject);
        this.showComposeModal();
    }
    
    archiveMessage() {
        // Implementation for archiving messages
        console.log('Archive message:', this.currentMessage?.id);
    }
    
    deleteMessage() {
        // Implementation for deleting messages
        if (confirm('Are you sure you want to delete this message?')) {
            console.log('Delete message:', this.currentMessage?.id);
        }
    }
    
    findMessageById(messageId) {
        // This would typically come from the loaded messages data
        // For now, return a placeholder
        return {
            id: messageId,
            sender_name: 'User',
            recipient_name: 'Recipient',
            subject: 'Message Subject',
            content: 'Message content...',
            status: 'unread',
            created_date: new Date().toISOString()
        };
    }
    
    updateUnreadCount() {
        // Update unread message count in the UI
        const $ = jQuery;
        const unreadCount = $('.message-item.unread').length;
        $('#inbox-count').text(unreadCount > 0 ? `(${unreadCount})` : '');
    }
    
    hideModal() {
        jQuery('.modal').fadeOut();
        jQuery('form')[0]?.reset();
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
        $('.messages-message').remove();
        
        // Add new message
        const messageHtml = `<div class="messages-message ${messageClass}">${message}</div>`;
        $('.qlcm-user-messages').prepend(messageHtml);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            $('.messages-message').fadeOut();
        }, 5000);
    }
    
    truncateText(text, maxLength) {
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
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

// Make classes globally available
window.QLCMProfileManager = QLCMProfileManager;
window.QLCMMessagesManager = QLCMMessagesManager;