<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Require Composer autoloader
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
}

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;

// -----------------------------------------------------------------------------
// --- Configuration ---
// -----------------------------------------------------------------------------
$urlToLink = 'https://lucky7cafe.kesug.com/menu.pdf';
$logoPath = null; // or 'path/to/logo.png'
$labelText = 'Scan Me!';

try {
    // Build the QR code
    $result = Builder::builder() // <-- correct method for v4+
        ->writer(new PngWriter())
        ->data($urlToLink)
        ->encoding(new Encoding('UTF-8'))
        ->errorCorrectionLevel(new ErrorCorrectionLevelLow())
        ->size(300)
        ->margin(10)
        ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
        ->foregroundColor(new Color(0, 0, 0))
        ->backgroundColor(new Color(255, 255, 255))
        // Optional label
        ->label($labelText ? Label::create($labelText)->setTextColor(new Color(0,0,0)) : null)
        // Optional logo
        ->logo($logoPath && file_exists($logoPath) ? Logo::create($logoPath)->setResizeToWidth(50) : null)
        ->build();

    // Output to browser
    header('Content-Type: '.$result->getMimeType());
    echo $result->getString();

} catch (Exception $e) {
    header("Content-Type: text/plain");
    echo 'Error generating QR code: ' . $e->getMessage();
}
?>
