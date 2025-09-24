<?php
/**
 * Generate PWA Icons for E-Cast Voting Platform
 * This script creates all required PWA icon sizes from a base logo
 */

// Create icons directory if it doesn't exist
$iconsDir = __DIR__ . '/assets/icons';
if (!is_dir($iconsDir)) {
    mkdir($iconsDir, 0755, true);
}

// Icon sizes required for PWA
$iconSizes = [72, 96, 128, 144, 152, 192, 384, 512];

// Base64 encoded SVG logo (E-Cast logo)
$svgLogo = '<?xml version="1.0" encoding="UTF-8"?>
<svg width="512" height="512" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#007bff;stop-opacity:1" />
      <stop offset="100%" style="stop-color:#0056b3;stop-opacity:1" />
    </linearGradient>
  </defs>
  
  <!-- Background Circle -->
  <circle cx="256" cy="256" r="240" fill="url(#grad1)" stroke="#ffffff" stroke-width="8"/>
  
  <!-- Ballot Box -->
  <rect x="180" y="200" width="152" height="120" rx="8" fill="#ffffff" stroke="#007bff" stroke-width="3"/>
  
  <!-- Ballot Slot -->
  <rect x="200" y="180" width="112" height="8" rx="4" fill="#007bff"/>
  
  <!-- Vote Paper -->
  <rect x="220" y="160" width="72" height="40" rx="4" fill="#ffffff" stroke="#007bff" stroke-width="2"/>
  
  <!-- Checkmark -->
  <path d="M240 175 L250 185 L270 165" stroke="#28a745" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
  
  <!-- Text "E-CAST" -->
  <text x="256" y="380" font-family="Arial, sans-serif" font-size="36" font-weight="bold" text-anchor="middle" fill="#ffffff">E-CAST</text>
  
  <!-- Decorative elements -->
  <circle cx="140" cy="140" r="8" fill="#ffffff" opacity="0.7"/>
  <circle cx="372" cy="140" r="8" fill="#ffffff" opacity="0.7"/>
  <circle cx="140" cy="372" r="8" fill="#ffffff" opacity="0.7"/>
  <circle cx="372" cy="372" r="8" fill="#ffffff" opacity="0.7"/>
</svg>';

// Function to create PNG from SVG
function createPngFromSvg($svgContent, $size, $outputPath) {
    // Save SVG to temporary file
    $tempSvg = tempnam(sys_get_temp_dir(), 'ecast_icon') . '.svg';
    file_put_contents($tempSvg, $svgContent);
    
    try {
        // Try using ImageMagick if available
        if (extension_loaded('imagick')) {
            $imagick = new Imagick();
            $imagick->setBackgroundColor(new ImagickPixel('transparent'));
            $imagick->readImageBlob($svgContent);
            $imagick->setImageFormat('png');
            $imagick->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1);
            $imagick->writeImage($outputPath);
            $imagick->clear();
            $imagick->destroy();
            unlink($tempSvg);
            return true;
        }
        
        // Fallback: Create simple colored PNG if ImageMagick not available
        $image = imagecreatetruecolor($size, $size);
        imagesavealpha($image, true);
        
        // Create gradient background
        $blue = imagecolorallocate($image, 0, 123, 255);
        $darkBlue = imagecolorallocate($image, 0, 86, 179);
        $white = imagecolorallocate($image, 255, 255, 255);
        
        // Fill with gradient (simplified)
        imagefill($image, 0, 0, $blue);
        
        // Add simple ballot box representation
        $boxSize = $size * 0.3;
        $boxX = ($size - $boxSize) / 2;
        $boxY = ($size - $boxSize) / 2;
        
        imagefilledrectangle($image, $boxX, $boxY, $boxX + $boxSize, $boxY + $boxSize, $white);
        imagerectangle($image, $boxX, $boxY, $boxX + $boxSize, $boxY + $boxSize, $darkBlue);
        
        // Add checkmark (simplified)
        $checkSize = $boxSize * 0.4;
        $checkX = $boxX + ($boxSize - $checkSize) / 2;
        $checkY = $boxY + ($boxSize - $checkSize) / 2;
        
        $green = imagecolorallocate($image, 40, 167, 69);
        imageline($image, $checkX, $checkY + $checkSize/2, $checkX + $checkSize/3, $checkY + $checkSize*0.7, $green);
        imageline($image, $checkX + $checkSize/3, $checkY + $checkSize*0.7, $checkX + $checkSize, $checkY + $checkSize*0.3, $green);
        
        imagepng($image, $outputPath);
        imagedestroy($image);
        unlink($tempSvg);
        return true;
        
    } catch (Exception $e) {
        unlink($tempSvg);
        return false;
    }
}

echo "Generating PWA Icons for E-Cast Voting Platform...\n\n";

$successCount = 0;
foreach ($iconSizes as $size) {
    $filename = "icon-{$size}x{$size}.png";
    $filepath = $iconsDir . '/' . $filename;
    
    echo "Creating {$filename}... ";
    
    if (createPngFromSvg($svgLogo, $size, $filepath)) {
        echo "✓ Success\n";
        $successCount++;
    } else {
        echo "✗ Failed\n";
    }
}

// Create favicon.ico (16x16 and 32x32)
echo "\nCreating favicon.ico... ";
if (createPngFromSvg($svgLogo, 32, __DIR__ . '/favicon.ico')) {
    echo "✓ Success\n";
} else {
    echo "✗ Failed\n";
}

// Create apple-touch-icon
echo "Creating apple-touch-icon.png... ";
if (createPngFromSvg($svgLogo, 180, __DIR__ . '/apple-touch-icon.png')) {
    echo "✓ Success\n";
} else {
    echo "✗ Failed\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "PWA Icon Generation Complete!\n";
echo "Generated {$successCount} out of " . count($iconSizes) . " icons\n";
echo "Icons saved to: {$iconsDir}\n";
echo "\nNext steps:\n";
echo "1. Check that all icon files were created successfully\n";
echo "2. Test PWA installation on mobile devices\n";
echo "3. Verify 'Add to Home Screen' functionality\n";
echo str_repeat("=", 50) . "\n";
?>
