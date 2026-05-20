<?php
/**
 * Report Service for MAI Salon
 * Generates various reports for sales, inventory, appointments, etc.
 */

namespace App\Services;

class ReportService {
    private $db;
    
    /**
     * Constructor
     * 
     * @param object $db Database connection
     */
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Generate sales report
     * 
     * @param array $filters Report filters
     * @return array Report data
     */
    public function generateSalesReport($filters = []) {
        // Default filters
        $start_date = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $end_date = $filters['end_date'] ?? date('Y-m-d');
        $payment_method = $filters['payment_method'] ?? null;
        $specialist_id = $filters['specialist_id'] ?? null;
        $service_id = $filters['service_id'] ?? null;
        
        // Base query
        $query = "
            SELECT 
                DATE(i.created_at) as date,
                COUNT(i.id) as invoice_count,
                SUM(i.total_amount) as total_sales,
                SUM(i.tax_amount) as total_tax,
                SUM(i.discount_amount) as total_discount
            FROM invoices i
            WHERE i.status = 'paid'
            AND DATE(i.created_at) BETWEEN ? AND ?
        ";
        
        $params = [$start_date, $end_date];
        
        // Add payment method filter
        if ($payment_method) {
            $query .= " AND EXISTS (SELECT 1 FROM payments p WHERE p.invoice_id = i.id AND p.method = ?)";
            $params[] = $payment_method;
        }
        
        // Add specialist filter
        if ($specialist_id) {
            $query .= " AND EXISTS (SELECT 1 FROM invoice_items ii WHERE ii.invoice_id = i.id AND ii.specialist_id = ?)";
            $params[] = $specialist_id;
        }
        
        // Add service filter
        if ($service_id) {
            $query .= " AND EXISTS (SELECT 1 FROM invoice_items ii WHERE ii.invoice_id = i.id AND ii.item_type = 'service' AND ii.item_id = ?)";
            $params[] = $service_id;
        }
        
        // Group by date
        $query .= " GROUP BY DATE(i.created_at) ORDER BY DATE(i.created_at)";
        
        // Execute query
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $daily_data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Get summary data
        $summary_query = "
            SELECT 
                COUNT(i.id) as invoice_count,
                SUM(i.total_amount) as total_sales,
                AVG(i.total_amount) as average_sale,
                SUM(i.tax_amount) as total_tax,
                SUM(i.discount_amount) as total_discount
            FROM invoices i
            WHERE i.status = 'paid'
            AND DATE(i.created_at) BETWEEN ? AND ?
        ";
        
        $summary_params = [$start_date, $end_date];
        
        // Add payment method filter
        if ($payment_method) {
            $summary_query .= " AND EXISTS (SELECT 1 FROM payments p WHERE p.invoice_id = i.id AND p.method = ?)";
            $summary_params[] = $payment_method;
        }
        
        // Add specialist filter
        if ($specialist_id) {
            $summary_query .= " AND EXISTS (SELECT 1 FROM invoice_items ii WHERE ii.invoice_id = i.id AND ii.specialist_id = ?)";
            $summary_params[] = $specialist_id;
        }
        
        // Add service filter
        if ($service_id) {
            $summary_query .= " AND EXISTS (SELECT 1 FROM invoice_items ii WHERE ii.invoice_id = i.id AND ii.item_type = 'service' AND ii.item_id = ?)";
            $summary_params[] = $service_id;
        }
        
        // Execute summary query
        $summary_stmt = $this->db->prepare($summary_query);
        $summary_stmt->execute($summary_params);
        $summary = $summary_stmt->fetch(\PDO::FETCH_ASSOC);
        
        // Get payment method breakdown
        $payment_query = "
            SELECT 
                p.method,
                COUNT(p.id) as count,
                SUM(p.amount) as total
            FROM payments p
            JOIN invoices i ON p.invoice_id = i.id
            WHERE i.status = 'paid'
            AND DATE(i.created_at) BETWEEN ? AND ?
            GROUP BY p.method
        ";
        
        $payment_stmt = $this->db->prepare($payment_query);
        $payment_stmt->execute([$start_date, $end_date]);
        $payment_data = $payment_stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Return report data
        return [
            'daily_data' => $daily_data,
            'summary' => $summary,
            'payment_data' => $payment_data,
            'filters' => [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'payment_method' => $payment_method,
                'specialist_id' => $specialist_id,
                'service_id' => $service_id
            ]
        ];
    }
    
    /**
     * Generate inventory report
     * 
     * @param array $filters Report filters
     * @return array Report data
     */
    public function generateInventoryReport($filters = []) {
        // Default filters
        $category_id = $filters['category_id'] ?? null;
        $low_stock_only = $filters['low_stock_only'] ?? false;
        
        // Base query
        $query = "
            SELECT 
                p.id,
                p.name_ar,
                p.sku,
                p.stock_quantity,
                p.min_stock_level,
                p.purchase_price,
                p.selling_price,
                (p.selling_price - p.purchase_price) as profit_margin,
                pc.name_ar as category_name
            FROM products p
            LEFT JOIN product_categories pc ON p.category_id = pc.id
            WHERE p.is_active = 1
        ";
        
        $params = [];
        
        // Add category filter
        if ($category_id) {
            $query .= " AND p.category_id = ?";
            $params[] = $category_id;
        }
        
        // Add low stock filter
        if ($low_stock_only) {
            $query .= " AND p.stock_quantity <= p.min_stock_level";
        }
        
        // Order by stock level ratio
        $query .= " ORDER BY (p.stock_quantity / p.min_stock_level) ASC";
        
        // Execute query
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Get summary data
        $summary_query = "
            SELECT 
                COUNT(p.id) as product_count,
                SUM(p.stock_quantity) as total_stock,
                SUM(p.stock_quantity * p.purchase_price) as total_inventory_value,
                COUNT(CASE WHEN p.stock_quantity <= p.min_stock_level THEN 1 END) as low_stock_count
            FROM products p
            WHERE p.is_active = 1
        ";
        
        $summary_params = [];
        
        // Add category filter
        if ($category_id) {
            $summary_query .= " AND p.category_id = ?";
            $summary_params[] = $category_id;
        }
        
        // Execute summary query
        $summary_stmt = $this->db->prepare($summary_query);
        $summary_stmt->execute($summary_params);
        $summary = $summary_stmt->fetch(\PDO::FETCH_ASSOC);
        
        // Return report data
        return [
            'products' => $products,
            'summary' => $summary,
            'filters' => [
                'category_id' => $category_id,
                'low_stock_only' => $low_stock_only
            ]
        ];
    }
    
    /**
     * Generate appointments report
     * 
     * @param array $filters Report filters
     * @return array Report data
     */
    public function generateAppointmentsReport($filters = []) {
        // Default filters
        $start_date = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $end_date = $filters['end_date'] ?? date('Y-m-d');
        $specialist_id = $filters['specialist_id'] ?? null;
        $status = $filters['status'] ?? null;
        
        // Base query
        $query = "
            SELECT 
                a.id,
                a.customer_id,
                c.name as customer_name,
                c.phone as customer_phone,
                a.service_id,
                s.title_ar as service_name,
                a.specialist_id,
                t.name_ar as specialist_name,
                a.start_at,
                a.end_at,
                a.status,
                a.source
            FROM appointments a
            LEFT JOIN customers c ON a.customer_id = c.id
            LEFT JOIN services s ON a.service_id = s.id
            LEFT JOIN team_members t ON a.specialist_id = t.id
            WHERE DATE(a.start_at) BETWEEN ? AND ?
        ";
        
        $params = [$start_date, $end_date];
        
        // Add specialist filter
        if ($specialist_id) {
            $query .= " AND a.specialist_id = ?";
            $params[] = $specialist_id;
        }
        
        // Add status filter
        if ($status) {
            $query .= " AND a.status = ?";
            $params[] = $status;
        }
        
        // Order by date
        $query .= " ORDER BY a.start_at";
        
        // Execute query
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $appointments = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Get summary data
        $summary_query = "
            SELECT 
                COUNT(a.id) as appointment_count,
                COUNT(CASE WHEN a.status = 'confirmed' THEN 1 END) as confirmed_count,
                COUNT(CASE WHEN a.status = 'done' THEN 1 END) as done_count,
                COUNT(CASE WHEN a.status = 'no_show' THEN 1 END) as no_show_count,
                COUNT(CASE WHEN a.status = 'cancelled' THEN 1 END) as cancelled_count,
                COUNT(CASE WHEN a.source = 'public' THEN 1 END) as public_count
            FROM appointments a
            WHERE DATE(a.start_at) BETWEEN ? AND ?
        ";
        
        $summary_params = [$start_date, $end_date];
        
        // Add specialist filter
        if ($specialist_id) {
            $summary_query .= " AND a.specialist_id = ?";
            $summary_params[] = $specialist_id;
        }
        
        // Add status filter
        if ($status) {
            $summary_query .= " AND a.status = ?";
            $summary_params[] = $status;
        }
        
        // Execute summary query
        $summary_stmt = $this->db->prepare($summary_query);
        $summary_stmt->execute($summary_params);
        $summary = $summary_stmt->fetch(\PDO::FETCH_ASSOC);
        
        // Return report data
        return [
            'appointments' => $appointments,
            'summary' => $summary,
            'filters' => [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'specialist_id' => $specialist_id,
                'status' => $status
            ]
        ];
    }
    
    /**
     * Generate customers report
     * 
     * @param array $filters Report filters
     * @return array Report data
     */
    public function generateCustomersReport($filters = []) {
        // Default filters
        $segment_id = $filters['segment_id'] ?? null;
        $loyalty_tier_id = $filters['loyalty_tier_id'] ?? null;
        $min_spent = $filters['min_spent'] ?? null;
        
        // Base query
        $query = "
            SELECT 
                c.id,
                c.name,
                c.phone,
                c.email,
                c.total_spend,
                c.visit_count,
                c.last_visit,
                c.created_at,
                lt.name_ar as loyalty_tier_name,
                lw.points as loyalty_points
            FROM customers c
            LEFT JOIN loyalty_wallets lw ON c.id = lw.customer_id
            LEFT JOIN loyalty_tiers lt ON lw.tier_id = lt.id
            WHERE 1=1
        ";
        
        $params = [];
        
        // Add segment filter
        if ($segment_id) {
            $query .= " AND EXISTS (SELECT 1 FROM customer_segment_assignments csa WHERE csa.customer_id = c.id AND csa.segment_id = ?)";
            $params[] = $segment_id;
        }
        
        // Add loyalty tier filter
        if ($loyalty_tier_id) {
            $query .= " AND lw.tier_id = ?";
            $params[] = $loyalty_tier_id;
        }
        
        // Add min spent filter
        if ($min_spent) {
            $query .= " AND c.total_spend >= ?";
            $params[] = $min_spent;
        }
        
        // Order by total spend
        $query .= " ORDER BY c.total_spend DESC";
        
        // Execute query
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $customers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Get summary data
        $summary_query = "
            SELECT 
                COUNT(c.id) as customer_count,
                SUM(c.total_spend) as total_spent,
                AVG(c.total_spend) as average_spent,
                COUNT(CASE WHEN c.visit_count > 1 THEN 1 END) as returning_count,
                COUNT(CASE WHEN c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_count
            FROM customers c
            WHERE 1=1
        ";
        
        $summary_params = [];
        
        // Add segment filter
        if ($segment_id) {
            $summary_query .= " AND EXISTS (SELECT 1 FROM customer_segment_assignments csa WHERE csa.customer_id = c.id AND csa.segment_id = ?)";
            $summary_params[] = $segment_id;
        }
        
        // Add loyalty tier filter
        if ($loyalty_tier_id) {
            $summary_query .= " AND EXISTS (SELECT 1 FROM loyalty_wallets lw WHERE lw.customer_id = c.id AND lw.tier_id = ?)";
            $summary_params[] = $loyalty_tier_id;
        }
        
        // Add min spent filter
        if ($min_spent) {
            $summary_query .= " AND c.total_spend >= ?";
            $summary_params[] = $min_spent;
        }
        
        // Execute summary query
        $summary_stmt = $this->db->prepare($summary_query);
        $summary_stmt->execute($summary_params);
        $summary = $summary_stmt->fetch(\PDO::FETCH_ASSOC);
        
        // Return report data
        return [
            'customers' => $customers,
            'summary' => $summary,
            'filters' => [
                'segment_id' => $segment_id,
                'loyalty_tier_id' => $loyalty_tier_id,
                'min_spent' => $min_spent
            ]
        ];
    }
    
    /**
     * Export report to CSV
     * 
     * @param array $data Report data
     * @param string $type Report type
     * @return string CSV content
     */
    public function exportToCsv($data, $type) {
        $csv = '';
        $delimiter = ',';
        
        switch ($type) {
            case 'sales':
                // Headers
                $headers = ['التاريخ', 'عدد الفواتير', 'إجمالي المبيعات', 'إجمالي الضريبة', 'إجمالي الخصم'];
                $csv .= implode($delimiter, $headers) . "\n";
                
                // Data
                foreach ($data['daily_data'] as $row) {
                    $line = [
                        $row['date'],
                        $row['invoice_count'],
                        $row['total_sales'],
                        $row['total_tax'],
                        $row['total_discount']
                    ];
                    $csv .= implode($delimiter, $line) . "\n";
                }
                break;
                
            case 'inventory':
                // Headers
                $headers = ['المنتج', 'الرمز', 'الكمية', 'الحد الأدنى', 'سعر الشراء', 'سعر البيع', 'هامش الربح', 'التصنيف'];
                $csv .= implode($delimiter, $headers) . "\n";
                
                // Data
                foreach ($data['products'] as $row) {
                    $line = [
                        $row['name_ar'],
                        $row['sku'],
                        $row['stock_quantity'],
                        $row['min_stock_level'],
                        $row['purchase_price'],
                        $row['selling_price'],
                        $row['profit_margin'],
                        $row['category_name']
                    ];
                    $csv .= implode($delimiter, $line) . "\n";
                }
                break;
                
            case 'appointments':
                // Headers
                $headers = ['العميل', 'الهاتف', 'الخدمة', 'الأخصائية', 'التاريخ', 'الوقت', 'الحالة', 'المصدر'];
                $csv .= implode($delimiter, $headers) . "\n";
                
                // Data
                foreach ($data['appointments'] as $row) {
                    $line = [
                        $row['customer_name'],
                        $row['customer_phone'],
                        $row['service_name'],
                        $row['specialist_name'],
                        date('Y-m-d', strtotime($row['start_at'])),
                        date('H:i', strtotime($row['start_at'])),
                        $row['status'],
                        $row['source']
                    ];
                    $csv .= implode($delimiter, $line) . "\n";
                }
                break;
                
            case 'customers':
                // Headers
                $headers = ['العميل', 'الهاتف', 'البريد الإلكتروني', 'إجمالي الإنفاق', 'عدد الزيارات', 'آخر زيارة', 'تاريخ التسجيل', 'فئة الولاء', 'نقاط الولاء'];
                $csv .= implode($delimiter, $headers) . "\n";
                
                // Data
                foreach ($data['customers'] as $row) {
                    $line = [
                        $row['name'],
                        $row['phone'],
                        $row['email'],
                        $row['total_spend'],
                        $row['visit_count'],
                        $row['last_visit'],
                        $row['created_at'],
                        $row['loyalty_tier_name'],
                        $row['loyalty_points']
                    ];
                    $csv .= implode($delimiter, $line) . "\n";
                }
                break;
        }
        
        return $csv;
    }
    
    /**
     * Export report to PDF
     * 
     * @param array $data Report data
     * @param string $type Report type
     * @return string PDF content
     */
    public function exportToPdf($data, $type) {
        // This would typically use a PDF library like DOMPDF
        // For now, we'll return a placeholder
        return 'PDF export not implemented yet';
    }
}
