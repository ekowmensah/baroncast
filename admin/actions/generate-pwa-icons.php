<?php
/**
 * PWA Icon Generator
 * Generates multiple icon sizes from uploaded PWA icon
 */

// Database not needed for icon generation

class PWAIconGenerator {
    private $iconSizes = [72, 96, 128, 144, 152, 192, 384, 512];
    private $iconsDir = '../../assets/icons/';
    
    public function __construct() {
        // Create icons directory if it doesn't exist
        if (!is_dir($this->iconsDir)) {
            mkdir($this->iconsDir, 0755, true);
        }
    }
    
    public function generateIcons($sourceImage) {
        $results = [];
        
        // Get image info
        $imageInfo = getimagesize($sourceImage);
        if (!$imageInfo) {
            throw new Exception('Invalid image file');
        }
        
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $imageType = $imageInfo[2];
        
        // Create source image resource
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $sourceImg = imagecreatefromjpeg($sourceImage);
                break;
            case IMAGETYPE_PNG:
                $sourceImg = imagecreatefrompng($sourceImage);
                break;
            case IMAGETYPE_GIF:
                $sourceImg = imagecreatefromgif($sourceImage);
                break;
            default:
                throw new Exception('Unsupported image type');
        }
        
        if (!$sourceImg) {
            throw new Exception('Failed to create image resource');
        }
        
        // Generate icons for each size
        foreach ($this->iconSizes as $size) {
            $iconPath = $this->iconsDir . "icon-{$size}x{$size}.png";
            
            // Create new image
            $newImg = imagecreatetruecolor($size, $size);
            
            // Preserve transparency for PNG
            imagealphablending($newImg, false);
            imagesavealpha($newImg, true);
            $transparent = imagecolorallocatealpha($newImg, 255, 255, 255, 127);
            imagefill($newImg, 0, 0, $transparent);
            
            // Resize image
            imagecopyresampled(
                $newImg, $sourceImg,
                0, 0, 0, 0,
                $size, $size,
                $sourceWidth, $sourceHeight
            );
            
            // Save as PNG
            if (imagepng($newImg, $iconPath)) {
                $results[] = [
                    'size' => $size,
                    'path' => str_replace('../../', '', $iconPath),
                    'success' => true
                ];
            } else {
                $results[] = [
                    'size' => $size,
                    'path' => $iconPath,
                    'success' => false,
                    'error' => 'Failed to save icon'
                ];
            }
            
            imagedestroy($newImg);
        }
        
        imagedestroy($sourceImg);
        
        return $results;
    }
    
    public function updateManifest($siteName, $shortName, $description) {
        $manifestPath = '../../manifest.json';
        
        // Read current manifest
        $manifest = json_decode(file_get_contents($manifestPath), true);
        
        // Update with dynamic values
        $manifest['name'] = $siteName ?: 'E-Cast Voting Platform';
        $manifest['short_name'] = $shortName ?: 'E-Cast';
        $manifest['description'] = $description ?: 'Professional voting platform for events and awards';
        
        // Update icon paths to use generated icons
        $manifest['icons'] = [];
        foreach ($this->iconSizes as $size) {
            $manifest['icons'][] = [
                'src' => "assets/icons/icon-{$size}x{$size}.png",
                'sizes' => "{$size}x{$size}",
                'type' => 'image/png',
                'purpose' => 'maskable any'
            ];
        }
        
        // Save updated manifest
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));
        
        return true;
    }
    
    public function cleanupOldIcons() {
        $iconFiles = glob($this->iconsDir . 'icon-*.png');
        foreach ($iconFiles as $file) {
            unlink($file);
        }
    }
}
?>
