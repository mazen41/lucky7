<?php
// Force browser cache refresh and check current page state
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

echo "<h1>Cache Cleared - Testing Current State</h1>";

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Test segments query exactly as used in customer_segments.php
$segments_query = "
    SELECT cs.*, 
           COUNT(csa.customer_id) as actual_count,
           (SELECT COUNT(*) FROM customers c WHERE c.status = 'active') as total_customers
    FROM customer_segments cs
    LEFT JOIN customer_segment_assignments csa ON cs.id = csa.segment_id
    WHERE cs.is_active = 1
    GROUP BY cs.id
    ORDER BY cs.name_ar ASC
";

$segments_stmt = $db->prepare($segments_query);
$segments_stmt->execute();
$segments = $segments_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Segments Data (Count: " . count($segments) . ")</h2>";

if (empty($segments)) {
    echo "<div style='background: #fee; padding: 20px; border: 1px solid #fcc;'>";
    echo "<h3>❌ NO SEGMENTS FOUND</h3>";
    echo "<p>This explains why you see the circular graphics - the page is showing empty state</p>";
    echo "<p><strong>Solution:</strong> <a href='fix_database_structure.php'>Run Database Fix Script</a></p>";
    echo "</div>";
} else {
    echo "<div style='background: #efe; padding: 20px; border: 1px solid #cfc;'>";
    echo "<h3>✅ SEGMENTS FOUND</h3>";
    echo "<table border='1' style='width: 100%; border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name (Arabic)</th><th>Color</th><th>Icon</th><th>Customer Count</th></tr>";
    foreach ($segments as $segment) {
        echo "<tr>";
        echo "<td>" . $segment['id'] . "</td>";
        echo "<td>" . htmlspecialchars($segment['name_ar']) . "</td>";
        echo "<td style='background: " . $segment['color'] . ";'>" . $segment['color'] . "</td>";
        echo "<td><i class='fas fa-" . $segment['icon'] . "'></i> " . $segment['icon'] . "</td>";
        echo "<td>" . $segment['actual_count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
}

echo "<hr>";
echo "<h2>Direct Page Links (Cache Cleared)</h2>";
echo "<p><a href='customer_segments.php?v=" . time() . "'>Customer Segments (Fresh)</a></p>";
echo "<p><a href='clients.php?v=" . time() . "'>Clients Page (Fresh)</a></p>";
?>
