<?php
// settings_functions.php

function getSettings($db) {
    $settings = [];
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE 'settings'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            throw new Exception("Settings table does not exist");
        }
        
        $stmt = $db->prepare("SELECT `key`, `value`, `type` FROM settings");
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $value = $row['value'];
            switch ($row['type']) {
                case 'boolean':
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'integer':
                    $value = (int) $value;
                    break;
                case 'decimal':
                    $value = (float) $value;
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            $settings[$row['key']] = $value;
        }
    } catch (Exception $e) {
        // Return defaults if settings table doesn't exist or on error
        return [
            'company_name' => 'MAI SALON',
            'company_address' => 'الرياض، المملكة العربية السعودية',
            'company_phone' => '+966XXXXXXX',
            'company_email' => 'info@maisalon.com',
            'company_twitter' => '',
            'company_instagram' => '',
            'tax_enabled' => true,
            'tax_inclusive' => true,
            'tax_rate' => 15.00,
            'tax_name' => 'ضريبة القيمة المضافة',
            'pos_require_open_shift_for_cash' => true,
            'pos_auto_print_receipt' => true,
            'pos_default_payment_method' => 'cash',
            'loyalty_enabled' => true,
            'loyalty_discount_priority' => 'tier_first',
            'loyalty_points_per_riyal' => 1,
            'loyalty_riyal_per_point' => 0.01
        ];
    }
    return $settings;
}