<?php
/**
 * Create Default PWA Icon
 * Generates a default PWA icon if none is uploaded
 */

require_once 'generate-pwa-icons.php';

function createDefaultPWAIcon() {
    $iconSizes = [72, 96, 128, 144, 152, 192, 384, 512];
    $iconsDir = '../../assets/icons/';
    
    // Create icons directory if it doesn't exist
    if (!is_dir($iconsDir)) {
        mkdir($iconsDir, 0755, true);
    }
    
    // Generate default icons for each size
    foreach ($iconSizes as $size) {
        $iconPath = $iconsDir . "icon-{$size}x{$size}.png";
        
        // Skip if icon already exists
        if (file_exists($iconPath)) {
            continue;
        }
        
        // Create new image
        $img = imagecreatetruecolor($size, $size);
        
        // Set colors
        $bgColor = imagecolorallocate($img, 0, 123, 255); // Bootstrap primary blue
        $textColor = imagecolorallocate($img, 255, 255, 255); // White
        
        // Fill background
        imagefill($img, 0, 0, $bgColor);
        
        // Add text
        $fontSize = max(12, $size / 8);
        $text = 'E-Cast';
        
        // Calculate text position (center)
        $textBox = imagettfbbox($fontSize, 0, __DIR__ . '/../../assets/fonts/arial.ttf', $text);
        if (!$textBox) {
            // Fallback to imagestring if TTF font not available
            $textWidth = strlen($text) * imagefontwidth(5);
            $textHeight = imagefontheight(5);
            $x = ($size - $textWidth) / 2;
            $y = ($size - $textHeight) / 2;
            imagestring($img, 5, $x, $y, $text, $textColor);
        } else {
            $textWidth = $textBox[4] - $textBox[0];
            $textHeight = $textBox[1] - $textBox[5];
            $x = ($size - $textWidth) / 2;
            $y = ($size + $textHeight) / 2;
            imagettftext($img, $fontSize, 0, $x, $y, $textColor, __DIR__ . '/../../assets/fonts/arial.ttf', $text);
        }
        
        // Save as PNG
        imagepng($img, $iconPath);
        imagedestroy($img);
    }
    
    return true;
}

// Create default icons if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    createDefaultPWAIcon();
    echo "Default PWA icons created successfully!";
}
?>
