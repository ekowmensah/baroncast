/**
 * Hubtel-Only Vote Submission JavaScript
 * Handles OTP verification, USSD payments, and payment status checking
 */

document.addEventListener('DOMContentLoaded', function() {
    const voteForm = document.getElementById('voteForm');
    const loadingState = document.getElementById('loadingState');
    const errorMessage = document.getElementById('errorMessage');
    
    let currentTransactionRef = null;
    let paymentCheckInterval = null;
    let otpTimer = null;
    let otpTimeRemaining = 300; // 5 minutes

    // Form submission handler
    voteForm.addEventListener('submit', function(e) {
        e.preventDefault();
        submitVote();
    });

    function submitVote() {
        const formData = new FormData(voteForm);
        
        showLoading('Submitting your vote...');
        hideError();

        fetch('actions/hubtel-vote-submit.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data.success) {
                currentTransactionRef = data.transaction_ref;
                
                if (data.step === 'otp_sent') {
                    showOTPModal(data);
                } else if (data.step === 'ussd_generated') {
                    showUSSDModal(data);
                }
            } else {
                showError(data.message || 'Vote submission failed');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Vote submission error:', error);
            showError('Network error. Please check your connection and try again.');
        });
    }

    function showOTPModal(data) {
        const modal = createModal('otp-modal', 'Verify Your Phone Number');
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-mobile-alt"></i> Verify Your Phone Number</h3>
                </div>
                <div class="modal-body">
                    <p>We've sent a 6-digit verification code to your phone number.</p>
                    <p>Please enter the code below:</p>
                    
                    <div class="otp-input-group">
                        <input type="text" id="otpCode" class="otp-input" placeholder="Enter 6-digit OTP" maxlength="6">
                    </div>
                    
                    <div class="otp-timer">
                        <i class="fas fa-clock"></i>
                        <span id="otpTimeRemaining">05:00</span> remaining
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-primary" onclick="verifyOTP()">
                            <i class="fas fa-check"></i> Verify OTP
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resendOTP()">
                            <i class="fas fa-redo"></i> Resend OTP
                        </button>
                        <button type="button" class="btn btn-outline" onclick="closeModal('otp-modal')">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        modal.classList.add('show');
        
        // Start OTP timer
        startOTPTimer();
        
        // Focus on OTP input
        document.getElementById('otpCode').focus();
    }

    function showUSSDModal(data) {
        const modal = createModal('ussd-modal', 'Complete Payment via USSD');
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-mobile-alt"></i> Complete Payment via USSD</h3>
                </div>
                <div class="modal-body">
                    <div class="ussd-code-display">
                        <h4>Dial this USSD code:</h4>
                        <div class="ussd-code">
                            <span id="ussdCode">${data.ussd_code}</span>
                            <button type="button" class="copy-btn" onclick="copyUSSDCode()">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>
                    
                    <div class="ussd-instructions">
                        <h5>Instructions:</h5>
                        <ol>
                            ${data.instructions.map(instruction => `<li>${instruction}</li>`).join('')}
                        </ol>
                    </div>
                    
                    <div class="payment-status" id="paymentStatus">
                        <i class="fas fa-clock"></i> Waiting for payment confirmation...
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-outline" onclick="closeModal('ussd-modal')">
                            Cancel Payment
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        modal.classList.add('show');
        
        // Start payment status checking
        startPaymentStatusCheck();
    }

    function verifyOTP() {
        const otpCode = document.getElementById('otpCode').value.trim();
        
        if (!otpCode || otpCode.length !== 6) {
            showError('Please enter a valid 6-digit OTP code');
            return;
        }

        showLoading('Verifying OTP...');

        fetch('actions/hubtel-verify-otp.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                transaction_ref: currentTransactionRef,
                otp_code: otpCode
            })
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data.success) {
                closeModal('otp-modal');
                stopOTPTimer();
                
                if (data.step === 'payment_initiated') {
                    showPaymentProcessingModal(data);
                }
            } else {
                showError(data.message || 'OTP verification failed');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('OTP verification error:', error);
            showError('Network error during OTP verification');
        });
    }

    function showPaymentProcessingModal(data) {
        const modal = createModal('payment-modal', 'Processing Payment');
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-credit-card"></i> Processing Payment</h3>
                </div>
                <div class="modal-body">
                    <div class="payment-info">
                        <p><strong>Amount:</strong> GHS ${data.amount}</p>
                        <p>Please approve the payment request on your mobile money app.</p>
                    </div>
                    
                    <div class="payment-status" id="paymentStatus">
                        <i class="fas fa-spinner fa-spin"></i> Processing payment...
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-outline" onclick="closeModal('payment-modal')">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        modal.classList.add('show');
        
        // Start payment status checking
        startPaymentStatusCheck();
    }

    function startPaymentStatusCheck() {
        paymentCheckInterval = setInterval(() => {
            checkPaymentStatus();
        }, 5000); // Check every 5 seconds
    }

    function checkPaymentStatus() {
        fetch('actions/hubtel-payment-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                transaction_ref: currentTransactionRef
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updatePaymentStatus(data.status, data.message);
                
                if (data.status === 'completed') {
                    clearInterval(paymentCheckInterval);
                    showSuccessMessage();
                } else if (data.status === 'failed' || data.status === 'cancelled') {
                    clearInterval(paymentCheckInterval);
                    showError(data.message);
                }
            }
        })
        .catch(error => {
            console.error('Payment status check error:', error);
        });
    }

    function updatePaymentStatus(status, message) {
        const statusElement = document.getElementById('paymentStatus');
        if (!statusElement) return;
        
        statusElement.className = 'payment-status ' + status;
        
        const icons = {
            pending: 'fas fa-clock',
            processing: 'fas fa-spinner fa-spin',
            completed: 'fas fa-check-circle',
            failed: 'fas fa-times-circle',
            cancelled: 'fas fa-ban'
        };
        
        const icon = icons[status] || 'fas fa-clock';
        statusElement.innerHTML = `<i class="${icon}"></i> ${message}`;
    }

    function startOTPTimer() {
        otpTimeRemaining = 300; // 5 minutes
        updateOTPTimer();
        
        otpTimer = setInterval(() => {
            otpTimeRemaining--;
            updateOTPTimer();
            
            if (otpTimeRemaining <= 0) {
                stopOTPTimer();
                showError('OTP has expired. Please request a new one.');
            }
        }, 1000);
    }

    function updateOTPTimer() {
        const timerElement = document.getElementById('otpTimeRemaining');
        if (timerElement) {
            const minutes = Math.floor(otpTimeRemaining / 60);
            const seconds = otpTimeRemaining % 60;
            timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
    }

    function stopOTPTimer() {
        if (otpTimer) {
            clearInterval(otpTimer);
            otpTimer = null;
        }
    }

    function resendOTP() {
        showLoading('Resending OTP...');
        
        // Re-submit the form to get new OTP
        const formData = new FormData(voteForm);
        
        fetch('actions/hubtel-vote-submit.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data.success && data.step === 'otp_sent') {
                stopOTPTimer();
                startOTPTimer();
                showError('New OTP sent to your phone', 'success');
            } else {
                showError('Failed to resend OTP. Please try again.');
            }
        })
        .catch(error => {
            hideLoading();
            showError('Network error while resending OTP');
        });
    }

    function copyUSSDCode() {
        const ussdCode = document.getElementById('ussdCode').textContent;
        navigator.clipboard.writeText(ussdCode).then(() => {
            showError('USSD code copied to clipboard!', 'success');
        }).catch(() => {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = ussdCode;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showError('USSD code copied to clipboard!', 'success');
        });
    }

    function createModal(id, title) {
        const modal = document.createElement('div');
        modal.id = id;
        modal.className = 'modal';
        return modal;
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.remove();
            }, 300);
        }
        
        // Clean up timers and intervals
        if (modalId === 'otp-modal') {
            stopOTPTimer();
        } else if (modalId === 'payment-modal' || modalId === 'ussd-modal') {
            if (paymentCheckInterval) {
                clearInterval(paymentCheckInterval);
            }
        }
    }

    function showSuccessMessage() {
        closeModal('payment-modal');
        closeModal('ussd-modal');
        
        const modal = createModal('success-modal', 'Vote Successful');
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header success">
                    <h3><i class="fas fa-check-circle"></i> Vote Successful!</h3>
                </div>
                <div class="modal-body">
                    <p>Your vote has been successfully recorded and payment confirmed.</p>
                    <p>Thank you for participating!</p>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-primary" onclick="window.location.reload()">
                            <i class="fas fa-vote-yea"></i> Vote Again
                        </button>
                        <button type="button" class="btn btn-outline" onclick="window.location.href='events.php'">
                            <i class="fas fa-arrow-left"></i> Back to Events
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        modal.classList.add('show');
    }

    function showLoading(message = 'Processing...') {
        loadingState.querySelector('p').textContent = message;
        loadingState.style.display = 'flex';
        voteForm.style.display = 'none';
    }

    function hideLoading() {
        loadingState.style.display = 'none';
        voteForm.style.display = 'block';
    }

    function showError(message, type = 'error') {
        errorMessage.textContent = message;
        errorMessage.className = 'error-message show ' + type;
        
        setTimeout(() => {
            hideError();
        }, 5000);
    }

    function hideError() {
        errorMessage.classList.remove('show');
    }

    // Global functions for modal actions
    window.verifyOTP = verifyOTP;
    window.resendOTP = resendOTP;
    window.copyUSSDCode = copyUSSDCode;
    window.closeModal = closeModal;
});
