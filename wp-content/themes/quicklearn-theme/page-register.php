<?php
/**
 * Template for custom registration page
 * 
 * @package QuickLearn
 */

// Redirect if user is already logged in
if (is_user_logged_in()) {
    wp_redirect(home_url());
    exit;
}

get_header(); ?>

<main id="primary" class="site-main">
    <div class="container">
        <div class="auth-container">
            <div class="auth-form-wrapper">
                <div class="auth-header">
                    <h1 class="auth-title"><?php _e('Join QuickLearn Academy', 'quicklearn'); ?></h1>
                    <p class="auth-subtitle"><?php _e('Start your learning journey today', 'quicklearn'); ?></p>
                </div>

                <?php if (get_option('users_can_register')) : ?>
                    <form id="registration-form" class="auth-form" method="post" action="<?php echo esc_url(site_url('wp-login.php?action=register', 'login_post')); ?>">
                        <div class="form-group">
                            <label for="user_login" class="form-label form-label--required"><?php _e('Username', 'quicklearn'); ?></label>
                            <input type="text" name="user_login" id="user_login" class="form-input" required 
                                   placeholder="<?php _e('Enter your username', 'quicklearn'); ?>" 
                                   value="<?php echo esc_attr(wp_unslash($_POST['user_login'] ?? '')); ?>">
                            <p class="form-help"><?php _e('Username cannot be changed later.', 'quicklearn'); ?></p>
                        </div>

                        <div class="form-group">
                            <label for="user_email" class="form-label form-label--required"><?php _e('Email Address', 'quicklearn'); ?></label>
                            <input type="email" name="user_email" id="user_email" class="form-input" required 
                                   placeholder="<?php _e('Enter your email address', 'quicklearn'); ?>"
                                   value="<?php echo esc_attr(wp_unslash($_POST['user_email'] ?? '')); ?>">
                            <p class="form-help"><?php _e('We\'ll send your password to this email.', 'quicklearn'); ?></p>
                        </div>

                        <div class="form-group">
                            <label for="first_name" class="form-label"><?php _e('First Name', 'quicklearn'); ?></label>
                            <input type="text" name="first_name" id="first_name" class="form-input" 
                                   placeholder="<?php _e('Enter your first name', 'quicklearn'); ?>"
                                   value="<?php echo esc_attr(wp_unslash($_POST['first_name'] ?? '')); ?>">
                        </div>

                        <div class="form-group">
                            <label for="last_name" class="form-label"><?php _e('Last Name', 'quicklearn'); ?></label>
                            <input type="text" name="last_name" id="last_name" class="form-input" 
                                   placeholder="<?php _e('Enter your last name', 'quicklearn'); ?>"
                                   value="<?php echo esc_attr(wp_unslash($_POST['last_name'] ?? '')); ?>">
                        </div>

                        <div class="form-group">
                            <label for="user_role" class="form-label"><?php _e('I want to join as', 'quicklearn'); ?></label>
                            <select name="user_role" id="user_role" class="form-select">
                                <option value="qlcm_student"><?php _e('Student - Learn new skills', 'quicklearn'); ?></option>
                                <option value="qlcm_instructor"><?php _e('Instructor - Teach and share knowledge', 'quicklearn'); ?></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" id="terms_agree" name="terms_agree" class="form-check-input" required>
                                <label for="terms_agree" class="form-check-label">
                                    <?php printf(
                                        __('I agree to the %s and %s', 'quicklearn'),
                                        '<a href="' . esc_url(get_page_link(get_option('quicklearn_terms-of-service_page_id'))) . '" target="_blank">' . __('Terms of Service', 'quicklearn') . '</a>',
                                        '<a href="' . esc_url(get_page_link(get_option('quicklearn_privacy-policy_page_id'))) . '" target="_blank">' . __('Privacy Policy', 'quicklearn') . '</a>'
                                    ); ?>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" id="newsletter_subscribe" name="newsletter_subscribe" class="form-check-input" checked>
                                <label for="newsletter_subscribe" class="form-check-label">
                                    <?php _e('Subscribe to our newsletter for course updates and learning tips', 'quicklearn'); ?>
                                </label>
                            </div>
                        </div>

                        <?php wp_nonce_field('quicklearn_register', 'quicklearn_register_nonce'); ?>
                        
                        <button type="submit" class="btn btn--primary btn--block btn--lg" id="register-btn">
                            <?php _e('Create My Account', 'quicklearn'); ?>
                        </button>
                    </form>

                    <div class="auth-footer">
                        <p class="auth-link">
                            <?php _e('Already have an account?', 'quicklearn'); ?>
                            <a href="<?php echo esc_url(wp_login_url()); ?>"><?php _e('Sign In', 'quicklearn'); ?></a>
                        </p>
                    </div>

                    <div class="auth-benefits">
                        <h3><?php _e('Why Join QuickLearn?', 'quicklearn'); ?></h3>
                        <div class="benefits-grid">
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <span class="dashicons dashicons-book"></span>
                                </div>
                                <h4><?php _e('Expert-Led Courses', 'quicklearn'); ?></h4>
                                <p><?php _e('Learn from industry professionals with real-world experience.', 'quicklearn'); ?></p>
                            </div>
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <span class="dashicons dashicons-awards"></span>
                                </div>
                                <h4><?php _e('Certificates', 'quicklearn'); ?></h4>
                                <p><?php _e('Earn certificates upon course completion to showcase your skills.', 'quicklearn'); ?></p>
                            </div>
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <span class="dashicons dashicons-groups"></span>
                                </div>
                                <h4><?php _e('Community', 'quicklearn'); ?></h4>
                                <p><?php _e('Connect with fellow learners and instructors worldwide.', 'quicklearn'); ?></p>
                            </div>
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <span class="dashicons dashicons-smartphone"></span>
                                </div>
                                <h4><?php _e('Learn Anywhere', 'quicklearn'); ?></h4>
                                <p><?php _e('Access courses on any device, anytime, anywhere.', 'quicklearn'); ?></p>
                            </div>
                        </div>
                    </div>

                <?php else : ?>
                    <div class="auth-disabled">
                        <div class="empty-state">
                            <div class="empty-state__icon">
                                <span class="dashicons dashicons-lock"></span>
                            </div>
                            <h3 class="empty-state__title"><?php _e('Registration is currently disabled', 'quicklearn'); ?></h3>
                            <p class="empty-state__message">
                                <?php _e('New user registration is not available at this time. Please contact the administrator for more information.', 'quicklearn'); ?>
                            </p>
                            <div class="empty-state__actions">
                                <a href="<?php echo esc_url(wp_login_url()); ?>" class="btn btn--primary">
                                    <?php _e('Sign In Instead', 'quicklearn'); ?>
                                </a>
                                <a href="<?php echo esc_url(get_page_link(get_option('quicklearn_contact_page_id'))); ?>" class="btn btn--secondary">
                                    <?php _e('Contact Us', 'quicklearn'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<style>
.auth-container {
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 0;
}

.auth-form-wrapper {
    max-width: 500px;
    width: 100%;
    background: var(--color-background);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xl);
    padding: 3rem;
    border: 1px solid var(--color-border);
}

.auth-header {
    text-align: center;
    margin-bottom: 2rem;
}

.auth-title {
    font-size: var(--font-size-3xl);
    font-weight: var(--font-weight-bold);
    color: var(--color-text-primary);
    margin-bottom: 0.5rem;
}

.auth-subtitle {
    font-size: var(--font-size-lg);
    color: var(--color-text-muted);
    margin: 0;
}

.auth-form {
    margin-bottom: 2rem;
}

.auth-footer {
    text-align: center;
    padding-top: 2rem;
    border-top: 1px solid var(--color-border-light);
}

.auth-link {
    color: var(--color-text-muted);
    margin: 0;
}

.auth-link a {
    color: var(--color-primary);
    font-weight: var(--font-weight-semibold);
    text-decoration: none;
}

.auth-link a:hover {
    text-decoration: underline;
}

.auth-benefits {
    margin-top: 3rem;
    padding-top: 2rem;
    border-top: 1px solid var(--color-border-light);
}

.auth-benefits h3 {
    text-align: center;
    margin-bottom: 2rem;
    color: var(--color-text-primary);
}

.benefits-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

.benefit-item {
    text-align: center;
}

.benefit-icon {
    width: 50px;
    height: 50px;
    background: var(--color-primary-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
}

.benefit-icon .dashicons {
    font-size: 24px;
    color: var(--color-primary);
}

.benefit-item h4 {
    font-size: var(--font-size-base);
    font-weight: var(--font-weight-semibold);
    margin-bottom: 0.5rem;
    color: var(--color-text-primary);
}

.benefit-item p {
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
    margin: 0;
    line-height: var(--line-height-normal);
}

.auth-disabled {
    text-align: center;
}

@media (max-width: 767px) {
    .auth-form-wrapper {
        padding: 2rem;
        margin: 1rem;
    }
    
    .auth-title {
        font-size: var(--font-size-2xl);
    }
    
    .benefits-grid {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
}

/* Loading state for form submission */
.auth-form.loading {
    opacity: 0.6;
    pointer-events: none;
}

.auth-form.loading #register-btn {
    position: relative;
    color: transparent;
}

.auth-form.loading #register-btn::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to {
        transform: translate(-50%, -50%) rotate(360deg);
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registration-form');
    const submitBtn = document.getElementById('register-btn');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            // Add loading state
            form.classList.add('loading');
            submitBtn.disabled = true;
            
            // Basic client-side validation
            const username = document.getElementById('user_login').value.trim();
            const email = document.getElementById('user_email').value.trim();
            const termsAgree = document.getElementById('terms_agree').checked;
            
            if (!username || !email || !termsAgree) {
                e.preventDefault();
                form.classList.remove('loading');
                submitBtn.disabled = false;
                
                alert('<?php _e('Please fill in all required fields and agree to the terms.', 'quicklearn'); ?>');
                return false;
            }
            
            // Username validation
            if (username.length < 3) {
                e.preventDefault();
                form.classList.remove('loading');
                submitBtn.disabled = false;
                
                alert('<?php _e('Username must be at least 3 characters long.', 'quicklearn'); ?>');
                return false;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                form.classList.remove('loading');
                submitBtn.disabled = false;
                
                alert('<?php _e('Please enter a valid email address.', 'quicklearn'); ?>');
                return false;
            }
        });
    }
    
    // Real-time username validation
    const usernameInput = document.getElementById('user_login');
    if (usernameInput) {
        usernameInput.addEventListener('input', function() {
            const username = this.value.trim();
            const feedback = this.parentNode.querySelector('.username-feedback');
            
            if (feedback) {
                feedback.remove();
            }
            
            if (username.length > 0 && username.length < 3) {
                const feedbackEl = document.createElement('p');
                feedbackEl.className = 'form-error username-feedback';
                feedbackEl.innerHTML = '<span class="dashicons dashicons-warning"></span> <?php _e('Username must be at least 3 characters', 'quicklearn'); ?>';
                this.parentNode.appendChild(feedbackEl);
                this.classList.add('form-input--error');
            } else {
                this.classList.remove('form-input--error');
            }
        });
    }
});
</script>

<?php get_footer(); ?>