<?php
/**
 * ZATCA QR Code Service for MAI Salon
 * Implements Saudi e-invoicing requirements
 * 
 * Based on ZATCA specifications:
 * - TLV encoding for QR code data
 * - Required fields: Seller Name, VAT Number, Timestamp, Invoice Total, VAT Amount
 */

namespace App\Services;

class ZatcaService {
    /**
     * Generate ZATCA compliant QR code data
     * 
     * @param array $invoice Invoice data with required fields
     * @return string Base64 encoded QR code data
     */
    public static function generateQRCodeData($invoice) {
        // Required fields according to ZATCA
        $seller_name = $invoice['seller_name'] ?? 'MAI Salon';
        $vat_number = $invoice['vat_number'] ?? '123456789012345'; // 15 digits VAT number
        $timestamp = $invoice['timestamp'] ?? date('Y-m-d\TH:i:s\Z');
        $invoice_total = $invoice['invoice_total'] ?? 0;
        $vat_amount = $invoice['vat_amount'] ?? 0;
        
        // TLV encoding (Tag, Length, Value)
        $tlv_data = '';
        
        // Tag 1: Seller Name
        $tlv_data .= self::encodeTLV(1, $seller_name);
        
        // Tag 2: VAT Number
        $tlv_data .= self::encodeTLV(2, $vat_number);
        
        // Tag 3: Timestamp
        $tlv_data .= self::encodeTLV(3, $timestamp);
        
        // Tag 4: Invoice Total
        $tlv_data .= self::encodeTLV(4, number_format($invoice_total, 2, '.', ''));
        
        // Tag 5: VAT Amount
        $tlv_data .= self::encodeTLV(5, number_format($vat_amount, 2, '.', ''));
        
        // Base64 encode the TLV data
        return base64_encode($tlv_data);
    }
    
    /**
     * Encode a value using TLV format
     * 
     * @param int $tag Tag number
     * @param string $value Value to encode
     * @return string Binary TLV data
     */
    private static function encodeTLV($tag, $value) {
        $value_bytes = self::stringToBytes($value);
        $length = count($value_bytes);
        
        $result = pack('C', $tag); // Tag (1 byte)
        $result .= pack('C', $length); // Length (1 byte)
        
        // Value (variable length)
        foreach ($value_bytes as $byte) {
            $result .= pack('C', $byte);
        }
        
        return $result;
    }
    
    /**
     * Convert string to array of bytes
     * 
     * @param string $string Input string
     * @return array Array of bytes
     */
    private static function stringToBytes($string) {
        $bytes = [];
        for ($i = 0; $i < strlen($string); $i++) {
            $bytes[] = ord($string[$i]);
        }
        return $bytes;
    }
    
    /**
     * Generate HTML for QR code display
     * 
     * @param string $qr_data Base64 encoded QR code data
     * @return string HTML with QR code image
     */
    public static function generateQRCodeHTML($qr_data) {
        // Use Google Charts API for QR code generation
        $qr_url = "https://chart.googleapis.com/chart?cht=qr&chs=200x200&chl=" . urlencode($qr_data);
        
        return '<img src="' . $qr_url . '" alt="ZATCA QR Code" class="qr-code">';
    }
    
    /**
     * Generate XML for e-invoice
     * 
     * @param array $invoice Invoice data
     * @return string XML string
     */
    public static function generateInvoiceXML($invoice) {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        
        // Root element
        $root = $dom->createElement('Invoice');
        $root->setAttribute('xmlns', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $dom->appendChild($root);
        
        // Invoice header
        $header = $dom->createElement('InvoiceHeader');
        $header->appendChild($dom->createElement('ID', $invoice['invoice_number']));
        $header->appendChild($dom->createElement('IssueDate', date('Y-m-d', strtotime($invoice['created_at']))));
        $header->appendChild($dom->createElement('IssueTime', date('H:i:s', strtotime($invoice['created_at']))));
        $root->appendChild($header);
        
        // Seller information
        $seller = $dom->createElement('SellerParty');
        $seller->appendChild($dom->createElement('Name', $invoice['seller_name'] ?? 'MAI Salon'));
        $seller->appendChild($dom->createElement('VATNumber', $invoice['vat_number'] ?? '123456789012345'));
        $root->appendChild($seller);
        
        // Customer information
        if (!empty($invoice['customer_name'])) {
            $customer = $dom->createElement('CustomerParty');
            $customer->appendChild($dom->createElement('Name', $invoice['customer_name']));
            if (!empty($invoice['customer_phone'])) {
                $customer->appendChild($dom->createElement('Phone', $invoice['customer_phone']));
            }
            $root->appendChild($customer);
        }
        
        // Invoice lines
        $lines = $dom->createElement('InvoiceLines');
        
        if (!empty($invoice['items'])) {
            foreach ($invoice['items'] as $index => $item) {
                $line = $dom->createElement('InvoiceLine');
                $line->appendChild($dom->createElement('ID', $index + 1));
                $line->appendChild($dom->createElement('Name', $item['name']));
                $line->appendChild($dom->createElement('Quantity', $item['quantity']));
                $line->appendChild($dom->createElement('UnitPrice', number_format($item['unit_price'], 2, '.', '')));
                $line->appendChild($dom->createElement('LineTotal', number_format($item['total_price'], 2, '.', '')));
                $lines->appendChild($line);
            }
        }
        
        $root->appendChild($lines);
        
        // Invoice totals
        $totals = $dom->createElement('InvoiceTotals');
        $totals->appendChild($dom->createElement('TaxExclusiveAmount', number_format($invoice['subtotal'] ?? 0, 2, '.', '')));
        $totals->appendChild($dom->createElement('TaxAmount', number_format($invoice['tax_amount'] ?? 0, 2, '.', '')));
        $totals->appendChild($dom->createElement('TaxInclusiveAmount', number_format($invoice['total_amount'] ?? 0, 2, '.', '')));
        $root->appendChild($totals);
        
        // QR code data
        $qr_data = self::generateQRCodeData($invoice);
        $qr_element = $dom->createElement('QRCode', $qr_data);
        $root->appendChild($qr_element);
        
        return $dom->saveXML();
    }
}
