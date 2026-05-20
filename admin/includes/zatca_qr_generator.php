<?php
/**
 * ZATCA QR Code Generator (API-Based - More Reliable)
 * This version generates the QR code image using a public API,
 * removing the need for local library installations.
 */

class ZatcaQRGenerator {
    
    // ZATCA QR Code Tags
    const TAG_SELLER_NAME = 1;
    const TAG_VAT_NUMBER = 2;
    const TAG_TIMESTAMP = 3;
    const TAG_INVOICE_TOTAL = 4;
    const TAG_VAT_AMOUNT = 5;
    
    /**
     * The main function to generate the ZATCA QR code image URI.
     * It creates the required data string and then fetches the QR image from an online API.
     *
     * @param array $invoice_data Associative array with invoice details.
     * @return string A Base64 Data URI for the QR image (e.g., "data:image/png;base64,..."), or an empty string on failure.
     */
    public static function createQrCodeUri(array $invoice_data): string {
        try {
            // 1. Generate the complex TLV string required by ZATCA.
            $tlv_base64_string = self::generateTlvString($invoice_data);

            if (empty($tlv_base64_string)) {
                error_log('ZATCA QR Error: Failed to generate the TLV data string.');
                return '';
            }

            // 2. Use a reliable public API to generate the QR code image from our string.
            $api_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($tlv_base64_string);

            // 3. Fetch the image data from the API.
            // Using a context to set a timeout and prevent the whole page from freezing.
            $context = stream_context_create(['http' => ['timeout' => 5]]);
            $image_data = @file_get_contents($api_url, false, $context);

            if ($image_data === false || empty($image_data)) {
                error_log("ZATCA QR Error: Failed to fetch QR image from API: " . $api_url);
                return '';
            }

            // 4. Convert the image data to a Base64 Data URI to be used directly in HTML/JS.
            return 'data:image/png;base64,' . base64_encode($image_data);

        } catch (Exception $e) {
            error_log("ZATCA QR Generation Critical Failure: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Generates the ZATCA-compliant TLV (Tag-Length-Value) string and encodes it in Base64.
     * This is an internal helper function.
     *
     * @param array $invoice_data
     * @return string The Base64 encoded TLV string.
     */
    private static function generateTlvString(array $invoice_data): string {
        // Validate that we have all the required data.
        $required_fields = ['seller_name', 'vat_number', 'timestamp', 'invoice_total', 'vat_amount'];
        foreach ($required_fields as $field) {
            if (!isset($invoice_data[$field])) {
                error_log("ZATCA TLV Error: Missing required field '$field'.");
                return '';
            }
        }

        // Create each part of the TLV structure.
        $tlv_parts = [
            self::encodeTlvField(self::TAG_SELLER_NAME, (string)$invoice_data['seller_name']),
            self::encodeTlvField(self::TAG_VAT_NUMBER, (string)$invoice_data['vat_number']),
            self::encodeTlvField(self::TAG_TIMESTAMP, (string)$invoice_data['timestamp']),
            self::encodeTlvField(self::TAG_INVOICE_TOTAL, number_format((float)$invoice_data['invoice_total'], 2, '.', '')),
            self::encodeTlvField(self::TAG_VAT_AMOUNT, number_format((float)$invoice_data['vat_amount'], 2, '.', ''))
        ];
        
        // Join all the binary parts together.
        $tlv_binary_data = implode('', $tlv_parts);
        
        // Return the final string encoded in Base64.
        return base64_encode($tlv_binary_data);
    }
    
    /**
     * Encodes a single field into the TLV binary format.
     *
     * @param int $tag The field's tag number.
     * @param string $value The field's value.
     * @return string The binary representation of the TLV field.
     */
    private static function encodeTlvField(int $tag, string $value): string {
        $value_as_utf8 = mb_convert_encoding($value, 'UTF-8');
        $length = strlen($value_as_utf8);
        
        // Pack the Tag (1 byte), Length (1 byte), and the Value itself.
        return pack('C', $tag) . pack('C', $length) . $value_as_utf8;
    }
}
?>