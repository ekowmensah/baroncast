<?php

class FallbackSMSService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Send OTP using fallback method (file logging for development)
     */
    public function sendOTP($phoneNumber, $otp) {
        // For development/testing - log OTP to file instead of sending SMS
        $logMessage = date('Y-m-d H:i:s') . " - OTP for {$phoneNumber}: {$otp}\n";
        file_put_contents(__DIR__ . '/../logs/otp-fallback.log', $logMessage, FILE_APPEND | LOCK_EX);
        
        // Also log to database for admin visibility
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO system_logs (log_type, message, created_at) 
                VALUES ('otp_fallback', ?, NOW())
            ");
            $stmt->execute(["OTP sent to {$phoneNumber}: {$otp}"]);
        } catch (Exception $e) {
            // Ignore database logging errors
        }
        
        return [
            'success' => true,
            'message' => 'OTP logged successfully (fallback mode)',
            'fallback' => true
        ];
    }
    
    /**
     * Send SMS using fallback method
     */
    public function sendSMS($to, $message, $senderId = null) {
        $logMessage = date('Y-m-d H:i:s') . " - SMS to {$to} from {$senderId}: {$message}\n";
        file_put_contents(__DIR__ . '/../logs/sms-fallback.log', $logMessage, FILE_APPEND | LOCK_EX);
        
        return [
            'success' => true,
            'message' => 'SMS logged successfully (fallback mode)',
            'fallback' => true
        ];
    }
}
