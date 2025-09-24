<?php
/**
 * Site Settings Helper
 * Provides functions to fetch dynamic site settings from database
 */

require_once __DIR__ . '/database.php';

class SiteSettings {
    private static $settings = null;
    private static $db = null;
    
    /**
     * Get database connection
     */
    private static function getDatabase() {
        if (self::$db === null) {
            $database = new Database();
            self::$db = $database->getConnection();
        }
        return self::$db;
    }
    
    /**
     * Fetch all site settings from database
     */
    private static function fetchSettings() {
        if (self::$settings === null) {
            try {
                $db = self::getDatabase();
                $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
                $settings = [];
                
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
                
                self::$settings = $settings;
            } catch (Exception $e) {
                // Fallback to default settings if database error
                self::$settings = self::getDefaultSettings();
            }
        }
        
        return self::$settings;
    }
    
    /**
     * Get default settings (fallback)
     */
    private static function getDefaultSettings() {
        return [
            'site_name' => 'E-Cast Voting Platform',
            'site_description' => 'Secure and transparent online voting platform',
            'site_logo' => '',
            'admin_email' => 'admin@example.com',
            'timezone' => 'Africa/Accra',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i:s'
        ];
    }
    
    /**
     * Get a specific setting value
     */
    public static function get($key, $default = null) {
        $settings = self::fetchSettings();
        return $settings[$key] ?? $default;
    }
    
    /**
     * Get site name
     */
    public static function getSiteName() {
        return self::get('site_name', 'E-Cast Voting Platform');
    }
    
    /**
     * Get site description
     */
    public static function getSiteDescription() {
        return self::get('site_description', 'Secure and transparent online voting platform');
    }
    
    /**
     * Get site logo path
     */
    public static function getSiteLogo() {
        return self::get('site_logo', '');
    }
    
    /**
     * Get currency symbol for display
     */
    public static function getCurrencySymbol() {
        return self::get('currency_symbol', 'GHS');
    }
    
    /**
     * Get payment currency code
     */
    public static function getPaymentCurrency() {
        return self::get('payment_currency', 'GHS');
    }
    
    /**
     * Get currency name
     */
    public static function getCurrencyName() {
        return self::get('currency_name', 'Ghana Cedis');
    }
    
    /**
     * Check if site has a custom logo
     */
    public static function hasCustomLogo() {
        $logo = self::getSiteLogo();
        return !empty($logo) && file_exists(__DIR__ . '/../' . $logo);
    }
    
    /**
     * Get logo HTML for display
     */
    public static function getLogoHtml($class = '', $alt = null, $from_subdirectory = false) {
        if (self::hasCustomLogo()) {
            $logo_path = self::getSiteLogo();
            
            // Adjust path if called from subdirectory (like voter/)
            if ($from_subdirectory && substr($logo_path, 0, 3) !== '../') {
                $logo_path = '../' . $logo_path;
            }
            
            $alt_text = $alt ?? self::getSiteName();
            $logo_class = $class;
            
            // Add consistent logo sizing if no specific class is provided
            if (empty($class) || $class === 'brand-logo') {
                $logo_class = 'brand-logo';
                $style = 'height: 40px; width: auto; max-height: 40px; object-fit: contain;';
                return '<img src="' . htmlspecialchars($logo_path) . '" alt="' . htmlspecialchars($alt_text) . '" class="' . htmlspecialchars($logo_class) . '" style="' . $style . '">';
            }
            
            return '<img src="' . htmlspecialchars($logo_path) . '" alt="' . htmlspecialchars($alt_text) . '" class="' . htmlspecialchars($logo_class) . '">';
        }
        return '';
    }
    
    /**
     * Get brand HTML (logo + site name)
     */
    public static function getBrandHtml($show_text = true, $logo_class = '', $text_class = '', $from_subdirectory = false) {
        $html = '';
        
        if (self::hasCustomLogo()) {
            // For custom logo, only show the logo (no text to avoid duplication)
            $html .= self::getLogoHtml($logo_class, null, $from_subdirectory);
        } else {
            // Default icon + text
            $html .= '<i class="fas fa-vote-yea"></i>';
            if ($show_text) {
                $html .= '<span class="' . htmlspecialchars($text_class) . '">' . htmlspecialchars(self::getSiteName()) . '</span>';
            }
        }
        
        return $html;
    }
    
    /**
     * Get logo-only HTML for menu/footer
     */
    public static function getLogoOnlyHtml($logo_class = '', $from_subdirectory = false) {
        if (self::hasCustomLogo()) {
            return self::getLogoHtml($logo_class, null, $from_subdirectory);
        } else {
            // Default icon only
            return '<i class="fas fa-vote-yea"></i>';
        }
    }
    
    /**
     * Get admin email
     */
    public static function getAdminEmail() {
        return self::get('admin_email', 'admin@example.com');
    }
    
    /**
     * Get timezone
     */
    public static function getTimezone() {
        return self::get('timezone', 'Africa/Accra');
    }
    
    /**
     * Clear cached settings (useful after updates)
     */
    public static function clearCache() {
        self::$settings = null;
    }
}
