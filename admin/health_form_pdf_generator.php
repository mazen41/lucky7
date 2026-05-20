<?php
/**
 * Health Form PDF Generator
 */

class HealthFormPDFGenerator {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function generatePDF($response_id) {
        try {
            // Get response data
            $response_stmt = $this->db->prepare("
                SELECT hr.*, hft.title, hft.description, c.name as customer_name, c.email, c.phone
                FROM health_form_responses hr 
                JOIN health_form_templates hft ON hr.template_id = hft.id 
                LEFT JOIN customers c ON hr.customer_id = c.id
                WHERE hr.id = ?
            ");
            $response_stmt->execute([$response_id]);
            $response = $response_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$response) {
                throw new Exception('Response not found');
            }
            
            // Get questions and answers
            $qa_stmt = $this->db->prepare("
                SELECT q.*, a.answer_value
                FROM health_form_questions q
                LEFT JOIN health_form_answers a ON q.id = a.question_id AND a.response_id = ?
                WHERE q.template_id = ?
                ORDER BY q.sort_order ASC
            ");
            $qa_stmt->execute([$response_id, $response['template_id']]);
            $questions_answers = $qa_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Generate HTML content
            $html = $this->generateHTMLContent($response, $questions_answers);
            
            // Create PDF directory if not exists
            $pdf_dir = '../storage/health_forms/';
            if (!is_dir($pdf_dir)) {
                mkdir($pdf_dir, 0755, true);
            }
            
            // Generate PDF filename
            $filename = 'health_form_' . $response_id . '_' . date('Y-m-d_H-i-s') . '.pdf';
            $pdf_path = $pdf_dir . $filename;
            
            // For now, save as HTML (can be converted to PDF with libraries like TCPDF, mPDF, or wkhtmltopdf)
            file_put_contents($pdf_path . '.html', $html);
            
            // Return relative path for database storage
            return 'storage/health_forms/' . $filename . '.html';
            
        } catch (Exception $e) {
            error_log('PDF Generation Error: ' . $e->getMessage());
            return false;
        }
    }
    
    private function generateHTMLContent($response, $questions_answers) {
        $html = '<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>نموذج التاريخ الصحي - ' . htmlspecialchars($response['customer_name']) . '</title>
    <style>
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
            direction: rtl;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #8B4513;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #8B4513;
            margin-bottom: 10px;
        }
        .form-title {
            font-size: 20px;
            color: #654321;
            margin-bottom: 5px;
        }
        .customer-info {
            background-color: #F5E6D3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .question-block {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #fafafa;
        }
        .question-label {
            font-weight: bold;
            color: #8B4513;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .answer-text {
            background-color: white;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #e0e0e0;
            min-height: 20px;
        }
        .signature-box {
            border: 2px dashed #8B4513;
            padding: 20px;
            text-align: center;
            background-color: white;
            border-radius: 5px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .generated-date {
            text-align: left;
            font-size: 10px;
            color: #999;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">MAI Salon</div>
        <div class="form-title">' . htmlspecialchars($response['title']) . '</div>
        <div style="font-size: 12px; color: #666;">نموذج التاريخ الصحي</div>
    </div>
    
    <div class="customer-info">
        <div class="info-row">
            <strong>اسم العميل:</strong>
            <span>' . htmlspecialchars($response['customer_name']) . '</span>
        </div>';
        
        if ($response['email']) {
            $html .= '<div class="info-row">
                <strong>البريد الإلكتروني:</strong>
                <span>' . htmlspecialchars($response['email']) . '</span>
            </div>';
        }
        
        if ($response['phone']) {
            $html .= '<div class="info-row">
                <strong>رقم الهاتف:</strong>
                <span>' . htmlspecialchars($response['phone']) . '</span>
            </div>';
        }
        
        $html .= '<div class="info-row">
            <strong>تاريخ الإكمال:</strong>
            <span>' . date('Y-m-d H:i', strtotime($response['completed_at'])) . '</span>
        </div>
    </div>';
    
    // Add questions and answers
    foreach ($questions_answers as $qa) {
        $html .= '<div class="question-block">
            <div class="question-label">' . htmlspecialchars($qa['label']);
            
        if ($qa['required']) {
            $html .= ' <span style="color: red;">*</span>';
        }
        
        $html .= '</div>';
        
        $answer = $qa['answer_value'] ?? 'لم يتم الإجابة';
        
        if ($qa['type'] === 'signature') {
            if ($answer && $answer !== 'لم يتم الإجابة') {
                $html .= '<div class="signature-box">
                    <img src="' . htmlspecialchars($answer) . '" alt="التوقيع" style="max-width: 300px; max-height: 100px;">
                </div>';
            } else {
                $html .= '<div class="signature-box">لم يتم التوقيع</div>';
            }
        } else {
            $html .= '<div class="answer-text">' . htmlspecialchars($answer) . '</div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '
    <div class="footer">
        <div>MAI Salon - نموذج التاريخ الصحي</div>
        <div>جميع المعلومات سرية ومحمية وفقاً لمعايير الخصوصية الطبية</div>
        <div class="generated-date">تم إنشاء هذا التقرير في: ' . date('Y-m-d H:i:s') . '</div>
    </div>
</body>
</html>';
        
        return $html;
    }
}
?>
