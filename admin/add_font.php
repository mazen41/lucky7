<?php
// SCRIPT TO ADD A FONT TO TCPDF
// ------------------------------------

require_once(__DIR__ . '/../vendor/autoload.php');

// IMPORTANT: Path to the NotoSansArabic-Regular.ttf font file
// Place the 'NotoSansArabic-Regular.ttf' file in the same folder as this script.
$fontfile = __DIR__ . '/NotoSansArabic-Regular.ttf';

if (!file_exists($fontfile)) {
    die("ERROR: Font file not found! Please make sure 'NotoSansArabic-Regular.ttf' is in the same folder as this script.");
}

echo "Attempting to add font: " . $fontfile . "\n";

try {
    // This function converts and installs the font for TCPDF
    $fontname = TCPDF_FONTS::addTTFfont($fontfile, 'TrueTypeUnicode', '', 96);

    if ($fontname === false) {
        die("ERROR: Failed to add the font. Check file permissions and that the TCPDF fonts directory is writable.");
    }

    echo "--------------------------------------------------------\n";
    echo "SUCCESS!\n";
    echo "The font '" . $fontname . "' was successfully added.\n";
    echo "You can now use 'notosansarabic' as the font family in your PDF script.\n";
    echo "You can safely delete this 'add_font.php' script now.\n";
    echo "--------------------------------------------------------\n";

} catch (Exception $e) {
    echo "An exception occurred: " . $e->getMessage();
}
?>