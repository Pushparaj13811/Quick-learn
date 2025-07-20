<?php
/**
 * Template for certificate verification page
 * 
 * @package QuickLearn
 */

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

        <div class="certificate-verification-page">
            <div class="verification-header">
                <h1 class="page-title"><?php esc_html_e('Certificate Verification', 'quicklearn'); ?></h1>
                <p class="page-description">
                    <?php esc_html_e('Enter a certificate verification code to verify its authenticity.', 'quicklearn'); ?>
                </p>
            </div>

            <div class="verification-form-container">
                <form id="certificate-verification-form" class="verification-form">
                    <div class="form-group">
                        <label for="verification-code">
                            <?php esc_html_e('Verification Code', 'quicklearn'); ?>
                        </label>
                        <input 
                            type="text" 
                            id="verification-code" 
                            name="verification_code" 
                            class="form-control" 
                            placeholder="<?php esc_attr_e('Enter verification code', 'quicklearn'); ?>"
                            required
                        >
                        <small class="form-help">
                            <?php esc_html_e('The verification code can be found on the certificate document.', 'quicklearn'); ?>
                        </small>
                    </div>
                    
                    <button type="submit" class="verify-btn">
                        <span class="verify-text"><?php esc_html_e('Verify Certificate', 'quicklearn'); ?></span>
                        <span class="verify-spinner" style="display: none;">
                            <span class="spinner"></span>
                            <?php esc_html_e('Verifying...', 'quicklearn'); ?>
                        </span>
                    </button>
                </form>

                <div id="verification-result" class="verification-result" style="display: none;">
                    <!-- Results will be populated via AJAX -->
                </div>
            </div>

            <div class="verification-info">
                <h2><?php esc_html_e('About Certificate Verification', 'quicklearn'); ?></h2>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-icon">🔒</div>
                        <h3><?php esc_html_e('Secure', 'quicklearn'); ?></h3>
                        <p><?php esc_html_e('All certificates are issued with unique verification codes that cannot be duplicated.', 'quicklearn'); ?></p>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">✅</div>
                        <h3><?php esc_html_e('Authentic', 'quicklearn'); ?></h3>
                        <p><?php esc_html_e('Verified certificates are guaranteed to be issued by our platform.', 'quicklearn'); ?></p>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">🌐</div>
                        <h3><?php esc_html_e('Global', 'quicklearn'); ?></h3>
                        <p><?php esc_html_e('Our certificates can be verified from anywhere in the world.', 'quicklearn'); ?></p>
                    </div>
                </div>
            </div>

            <?php if (function_exists('qlcm_get_recent_certificates')) : ?>
                <div class="recent-certificates">
                    <h2><?php esc_html_e('Recently Verified Certificates', 'quicklearn'); ?></h2>
                    <div class="certificates-list">
                        <?php
                        $recent_certificates = qlcm_get_recent_certificates(5);
                        if (!empty($recent_certificates)) :
                            foreach ($recent_certificates as $cert) :
                        ?>
                                <div class="certificate-item">
                                    <div class="cert-info">
                                        <h4><?php echo esc_html($cert['course_title']); ?></h4>
                                        <p class="cert-holder"><?php echo esc_html($cert['user_name']); ?></p>
                                        <p class="cert-date"><?php echo esc_html(date('F j, Y', strtotime($cert['issued_date']))); ?></p>
                                    </div>
                                    <div class="cert-badge">
                                        <span class="verified-badge"><?php esc_html_e('Verified', 'quicklearn'); ?></span>
                                    </div>
                                </div>
                        <?php
                            endforeach;
                        else :
                        ?>
                            <p class="no-certificates"><?php esc_html_e('No recent verifications available.', 'quicklearn'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </main><!-- #main -->
</div><!-- #primary -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('certificate-verification-form');
    const resultDiv = document.getElementById('verification-result');
    const verifyBtn = form.querySelector('.verify-btn');
    const verifyText = verifyBtn.querySelector('.verify-text');
    const verifySpinner = verifyBtn.querySelector('.verify-spinner');

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const verificationCode = document.getElementById('verification-code').value.trim();
        
        if (!verificationCode) {
            showError('<?php esc_js_e('Please enter a verification code.', 'quicklearn'); ?>');
            return;
        }

        // Show loading state
        verifyText.style.display = 'none';
        verifySpinner.style.display = 'inline-flex';
        verifyBtn.disabled = true;
        resultDiv.style.display = 'none';

        // Make AJAX request
        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'verify_certificate',
                verification_code: verificationCode,
                nonce: '<?php echo wp_create_nonce('verify_certificate_nonce'); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccess(data.data);
            } else {
                showError(data.data.message || '<?php esc_js_e('Verification failed. Please check the code and try again.', 'quicklearn'); ?>');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('<?php esc_js_e('An error occurred during verification. Please try again.', 'quicklearn'); ?>');
        })
        .finally(() => {
            // Reset button state
            verifyText.style.display = 'inline';
            verifySpinner.style.display = 'none';
            verifyBtn.disabled = false;
        });
    });

    function showSuccess(certificateData) {
        resultDiv.innerHTML = `
            <div class="verification-success">
                <div class="success-icon">✅</div>
                <h3><?php esc_js_e('Certificate Verified!', 'quicklearn'); ?></h3>
                <div class="certificate-details">
                    <div class="detail-row">
                        <span class="detail-label"><?php esc_js_e('Course:', 'quicklearn'); ?></span>
                        <span class="detail-value">${certificateData.course_title}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><?php esc_js_e('Student:', 'quicklearn'); ?></span>
                        <span class="detail-value">${certificateData.student_name}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><?php esc_js_e('Completion Date:', 'quicklearn'); ?></span>
                        <span class="detail-value">${certificateData.completion_date}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><?php esc_js_e('Issue Date:', 'quicklearn'); ?></span>
                        <span class="detail-value">${certificateData.issue_date}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><?php esc_js_e('Certificate ID:', 'quicklearn'); ?></span>
                        <span class="detail-value">${certificateData.certificate_id}</span>
                    </div>
                </div>
                <p class="verification-note">
                    <?php esc_js_e('This certificate has been verified as authentic and was issued by our platform.', 'quicklearn'); ?>
                </p>
            </div>
        `;
        resultDiv.style.display = 'block';
        resultDiv.scrollIntoView({ behavior: 'smooth' });
    }

    function showError(message) {
        resultDiv.innerHTML = `
            <div class="verification-error">
                <div class="error-icon">❌</div>
                <h3><?php esc_js_e('Verification Failed', 'quicklearn'); ?></h3>
                <p class="error-message">${message}</p>
                <div class="error-suggestions">
                    <h4><?php esc_js_e('Suggestions:', 'quicklearn'); ?></h4>
                    <ul>
                        <li><?php esc_js_e('Double-check the verification code for typos', 'quicklearn'); ?></li>
                        <li><?php esc_js_e('Ensure you\'re using the complete verification code', 'quicklearn'); ?></li>
                        <li><?php esc_js_e('Contact support if you believe this is an error', 'quicklearn'); ?></li>
                    </ul>
                </div>
            </div>
        `;
        resultDiv.style.display = 'block';
        resultDiv.scrollIntoView({ behavior: 'smooth' });
    }
});
</script>

<style>
.certificate-verification-page {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem;
}

.verification-header {
    text-align: center;
    margin-bottom: 3rem;
    padding-bottom: 2rem;
    border-bottom: 2px solid #e9ecef;
}

.verification-header .page-title {
    font-size: 2.5rem;
    color: #2c3e50;
    margin-bottom: 1rem;
}

.verification-header .page-description {
    font-size: 1.1rem;
    color: #6c757d;
    max-width: 600px;
    margin: 0 auto;
}

.verification-form-container {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 3rem;
}

.verification-form .form-group {
    margin-bottom: 1.5rem;
}

.verification-form label {
    display: block;
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
}

.verification-form .form-control {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.verification-form .form-control:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 3px rgba(0, 124, 186, 0.1);
}

.form-help {
    display: block;
    margin-top: 0.25rem;
    color: #6c757d;
    font-size: 0.875rem;
}

.verify-btn {
    width: 100%;
    padding: 1rem 2rem;
    background: #007cba;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.verify-btn:hover:not(:disabled) {
    background: #005a87;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 124, 186, 0.3);
}

.verify-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.verify-spinner {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.verification-result {
    margin-top: 2rem;
    padding: 2rem;
    border-radius: 8px;
    animation: fadeInUp 0.5s ease;
}

.verification-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    text-align: center;
}

.verification-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.success-icon,
.error-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.certificate-details {
    background: rgba(255, 255, 255, 0.5);
    border-radius: 6px;
    padding: 1.5rem;
    margin: 1.5rem 0;
    text-align: left;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 600;
}

.verification-info {
    margin-bottom: 3rem;
}

.verification-info h2 {
    text-align: center;
    color: #2c3e50;
    margin-bottom: 2rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
}

.info-item {
    text-align: center;
    padding: 1.5rem;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.info-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
}

.info-item h3 {
    color: #2c3e50;
    margin-bottom: 1rem;
}

.recent-certificates h2 {
    color: #2c3e50;
    margin-bottom: 1.5rem;
}

.certificate-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: #fff;
    border-radius: 8px;
    margin-bottom: 1rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.cert-info h4 {
    margin: 0 0 0.5rem 0;
    color: #2c3e50;
}

.cert-holder {
    margin: 0;
    font-weight: 600;
    color: #495057;
}

.cert-date {
    margin: 0;
    font-size: 0.9rem;
    color: #6c757d;
}

.verified-badge {
    background: #28a745;
    color: #fff;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

@media (max-width: 768px) {
    .certificate-verification-page {
        padding: 1rem;
    }
    
    .verification-header .page-title {
        font-size: 2rem;
    }
    
    .verification-form-container {
        padding: 1.5rem;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .certificate-item {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .detail-row {
        flex-direction: column;
        gap: 0.25rem;
    }
}
</style>

<?php get_footer(); ?>