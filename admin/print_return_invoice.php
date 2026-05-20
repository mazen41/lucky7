<?php
session_start();
require_once '../config/database.php';
require_once 'users_functions.php';
require_once 'auth.php';

checkAdminAuth();

// 1. Get Invoice ID from URL
$return_invoice_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$return_invoice_id) {
    die("رقم فاتورة المرتجع غير صحيح.");
}

$database = new Database();
$db = $database->getConnection();

// 2. Fetch Return Invoice Data (and original invoice number)
try {
    $stmt = $db->prepare("
        SELECT 
            i.*, 
            orig.invoice_number as original_invoice_number,
            c.name as customer_name
        FROM invoices i
        LEFT JOIN invoices orig ON i.return_for_invoice_id = orig.id
        LEFT JOIN customers c ON i.customer_id = c.id
        WHERE i.id = ? AND i.is_return = 1
    ");
    $stmt->execute([$return_invoice_id]);
    $return_invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$return_invoice) {
        die("فاتورة المرتجع غير موجودة.");
    }

    // 3. Fetch Return Invoice Items
    $items_stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
    $items_stmt->execute([$return_invoice_id]);
    $return_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("خطأ في قاعدة البيانات: " . $e->getMessage());
}

// You can fetch these from a settings table if you have one
$salon_name = "MAI Salon";
$footer_text = "شكراً لتعاملكم معنا";

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طباعة فاتورة مرتجع #<?= htmlspecialchars($return_invoice['invoice_number']) ?></title>
    <style>
        body {
            font-family: 'monospace', 'Courier New', Courier;
            margin: 0;
            padding: 10px;
            background-color: #f4f4f4;
        }
        .receipt {
            width: 300px;
            margin: 20px auto;
            padding: 15px;
            background-color: #fff;
            border: 1px solid #ccc;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            padding-bottom: 10px;
            border-bottom: 1px dashed #000;
        }
        .header h2 {
            margin: 0;
            font-size: 1.2em;
        }
        .header p {
            margin: 3px 0;
            font-size: 0.8em;
        }
        .content table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8em;
        }
        .content th, .content td {
            padding: 5px 2px;
        }
        .content th {
            text-align: right;
            border-bottom: 1px solid #000;
        }
        .totals-section {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #000;
        }
        .totals-section table {
            width: 100%;
            font-size: 0.9em;
        }
        .totals-section td:last-child {
            text-align: left;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #000;
            font-size: 0.8em;
        }

        @media print {
            body {
                background-color: #fff;
            }
            .receipt {
                width: 100%;
                margin: 0;
                padding: 0;
                border: none;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>

    <div class="receipt">
        <div class="header">
            <h2><?= htmlspecialchars($salon_name) ?></h2>
            <p style="font-weight: bold; font-size: 1em;">فاتورة مرتجع</p>
            <p>رقم الفاتورة: <?= htmlspecialchars($return_invoice['invoice_number']) ?></p>
            <p>مرتجع من الفاتورة: #<?= htmlspecialchars($return_invoice['original_invoice_number']) ?></p>
            <p>التاريخ: <?= date('Y-m-d h:i A', strtotime($return_invoice['created_at'])) ?></p>
        </div>

        <div class="content">
            <table>
                <thead>
                    <tr>
                        <th>الصنف</th>
                        <th style="text-align: center;">الكمية</th>
                        <th style="text-align: left;">الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($return_items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                        <!-- Use abs() to show quantity as a positive number -->
                        <td style="text-align: center;"><?= abs($item['quantity']) ?></td>
                        <!-- Use abs() to show total as a positive number -->
                        <td style="text-align: left;"><?= number_format(abs($item['total_price']), 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="totals-section">
            <table>
                <tr>
                    <td>الإجمالي المسترجع:</td>
                    <!-- Use abs() to show total amount as a positive number -->
                    <td><?= number_format(abs($return_invoice['total_amount']), 2) ?> ر.س</td>
                </tr>
            </table>
        </div>
        
        <div class="footer">
            <p><?= htmlspecialchars($footer_text) ?></p>
        </div>
    </div>

    <script>
        // Automatically trigger the print dialog when the page loads
        window.onload = function() {
            window.print();
        };
    </script>

</body>
</html>