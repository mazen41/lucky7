<?php
/**
 * HR Management - Geolocation Settings (Free & Open-Source Version)
 * Uses Leaflet.js and OpenStreetMap. No API keys or billing required.
 */
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Helper functions (update_setting, get_setting) remain the same.
function update_setting($db, $key, $value) {
    $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$key, $value]);
}

function get_setting($db, $key, $default = '') {
    $stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetchColumn();
    return $result !== false ? $result : $default;
}

// Form handling remains the same.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
        $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);
        $radius = filter_input(INPUT_POST, 'radius', FILTER_VALIDATE_INT);

        if ($latitude === false || $longitude === false || $radius === false || $radius <= 0) {
            throw new Exception("البيانات المدخلة غير صالحة. يرجى التأكد من أن الإحداثيات والمسافة أرقام صحيحة.");
        }

        update_setting($db, 'work_location_lat', $latitude);
        update_setting($db, 'work_location_lng', $longitude);
        update_setting($db, 'work_location_radius_meters', $radius);

        $_SESSION['message'] = "تم تحديث إعدادات الموقع بنجاح.";

    } catch (Exception $e) {
        $_SESSION['error'] = "حدث خطأ: " . $e->getMessage();
    }
    header('Location: /admin/settings.php');
    exit;
}

// Data fetching remains the same.
$current_lat = get_setting($db, 'work_location_lat', '24.7136'); // Default to Riyadh
$current_lng = get_setting($db, 'work_location_lng', '46.6753');
$current_radius = get_setting($db, 'work_location_radius_meters', '200');

$page_title = 'إعدادات موقع العمل';
ob_start();
?>

<!-- === DEPENDENCIES (Free & Open Source) === -->
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<!-- Leaflet Geosearch Plugin CSS (for the search bar) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet-geosearch@3.11.0/dist/geosearch.css" />
<!-- Font Awesome for Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<!-- Custom Styles to ensure everything looks right -->
<style>
    #notification {
        position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
        padding: 15px 25px; border-radius: 8px; color: white;
        z-index: 10001; /* High z-index to appear on top */
        display: none; box-shadow: 0 4px 15px rgba(0,0,0,0.2); font-size: 16px;
    }
    #notification.success { background-color: #28a745; }
    #notification.error { background-color: #dc3545; }
    #notification.info { background-color: #17a2b8; }

    /* Ensure the search bar is visible and styled correctly */
    .leaflet-geosearch-bar {
        z-index: 1000; /* Ensure search is above map tiles */
    }
    .leaflet-geosearch-bar form input {
        height: 40px;
        border-radius: 5px;
        border: 1px solid #ccc;
    }
    #map {
        z-index: 1; /* Keep map below UI controls */
    }
</style>

<!-- === HTML BODY === -->
<div id="notification"></div>

<div class="container mx-auto p-6">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
            <h1 class="text-2xl font-bold text-mai-dark-brown"><?= $page_title ?></h1>
            <a href="/admin/settings.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 text-sm">
                <i class="fas fa-arrow-left mr-2"></i>العودة للوحة التحكم
            </a>
        </div>
        
        <!-- Session Messages -->
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Map Section -->
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <label class="font-bold text-lg text-gray-700">تحديد الموقع على الخريطة</label>
                        <button type="button" id="locate-me" class="bg-blue-500 text-white px-3 py-1 rounded-lg hover:bg-blue-600 text-sm flex items-center">
                            <i id="locate-icon" class="fas fa-location-arrow mr-2"></i>
                            <span id="locate-text">حدد موقعي</span>
                        </button>
                    </div>
                    <p class="text-sm text-gray-500">ابحث عن عنوان، أو انقر على الموقع، أو اسحب العلامة لتحديث الإحداثيات.</p>
                    <div id="map" class="w-full h-96 rounded-lg border-2 border-gray-300"></div>
                </div>

                <!-- Settings Inputs Section -->
                <div class="space-y-6 bg-gray-50 p-6 rounded-lg">
                     <div>
                        <label for="latitude_input" class="block text-sm font-medium text-gray-700">خط العرض (Latitude)</label>
                        <input type="text" id="latitude_input" name="latitude" value="<?= htmlspecialchars($current_lat) ?>" readonly class="mt-1 block w-full px-3 py-2 bg-gray-200 border border-gray-300 rounded-md">
                    </div>
                     <div>
                        <label for="longitude_input" class="block text-sm font-medium text-gray-700">خط الطول (Longitude)</label>
                        <input type="text" id="longitude_input" name="longitude" value="<?= htmlspecialchars($current_lng) ?>" readonly class="mt-1 block w-full px-3 py-2 bg-gray-200 border border-gray-300 rounded-md">
                    </div>
                     <div>
                        <label for="radius_input" class="block text-sm font-medium text-gray-700">نطاق الحضور المسموح به (بالمتر)</label>
                        <input type="number" id="radius_input" name="radius" min="10" value="<?= htmlspecialchars($current_radius) ?>" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md">
                        <p class="mt-2 text-xs text-gray-500">هذه هي المسافة القصوى التي يمكن للموظف تسجيل الحضور من خلالها.</p>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-5 border-t mt-6 flex justify-end">
                <button type="submit" class="bg-mai-brown text-white py-2 px-6 rounded-lg hover:bg-mai-dark-brown font-semibold">
                    <i class="fas fa-save mr-2"></i>حفظ الإعدادات
                </button>
            </div>
        </form>
    </div>
</div>

<!-- === JAVASCRIPT LIBRARIES (Free & Open Source) === -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/leaflet-geosearch@3.11.0/dist/geosearch.umd.js"></script>

<!-- === THE ROBUST MAP SCRIPT === -->
<!-- === THE ROBUST MAP SCRIPT (Corrected) === -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- 1. INITIALIZE VARIABLES ---
    const initialLat = <?= (float)$current_lat ?>;
    const initialLng = <?= (float)$current_lng ?>;
    const initialRadius = <?= (int)$current_radius ?>;

    const latInput = document.getElementById('latitude_input');
    const lngInput = document.getElementById('longitude_input');
    const radiusInput = document.getElementById('radius_input');
    const locateButton = document.getElementById('locate-me');
    const locateIcon = document.getElementById('locate-icon');
    const locateText = document.getElementById('locate-text');
    const notification = document.getElementById('notification');
    let notificationTimeout;

    // --- 2. SETUP THE MAP ---
    const map = L.map('map').setView([initialLat, initialLng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // --- 3. CREATE MAP ELEMENTS (MARKER & CIRCLE) ---
    const marker = L.marker([initialLat, initialLng], { draggable: true }).addTo(map);
    const circle = L.circle([initialLat, initialLng], {
        color: '#007bff', fillColor: '#007bff', fillOpacity: 0.2, radius: initialRadius
    }).addTo(map);

    // --- 4. SETUP THE FREE GEOSEARCH CONTROL (CORRECTED SYNTAX) ---
    // The library now uses its own global 'GeoSearch' object, not 'L.GeoSearch'
    const searchProvider = new GeoSearch.OpenStreetMapProvider();
    const searchControl = new GeoSearch.GeoSearchControl({
        provider: searchProvider,
        style: 'bar',
        showMarker: false,
        searchLabel: 'ابحث عن عنوان أو مكان',
        autoClose: true,
    });
    map.addControl(searchControl);

    // --- 5. HELPER FUNCTIONS ---
    function showNotification(message, type = 'error') {
        clearTimeout(notificationTimeout);
        notification.textContent = message;
        notification.className = type;
        notification.style.display = 'block';
        notificationTimeout = setTimeout(() => { notification.style.display = 'none'; }, 5000);
    }

    function updatePositionAndUI(latlng, zoomLevel = null) {
        latInput.value = latlng.lat.toFixed(6);
        lngInput.value = latlng.lng.toFixed(6);
        marker.setLatLng(latlng);
        circle.setLatLng(latlng);
        if (zoomLevel) {
            map.setView(latlng, zoomLevel);
        }
    }

    function setLocateButtonLoading(isLoading) {
        locateButton.disabled = isLoading;
        locateText.textContent = isLoading ? '...جاري التحديد' : 'حدد موقعي';
        locateIcon.className = isLoading ? 'fas fa-spinner fa-spin mr-2' : 'fas fa-location-arrow mr-2';
    }

    // --- 6. BIND EVENT LISTENERS ---

    // When a search result is selected (event is on the map, not the control)
    map.on('geosearch/showlocation', function (result) {
        const latlng = L.latLng(result.location.y, result.location.x);
        updatePositionAndUI(latlng, 15);
    });

    // When the map is clicked
    map.on('click', (e) => updatePositionAndUI(e.latlng));

    // When the marker is dragged and dropped
    marker.on('dragend', (e) => updatePositionAndUI(e.target.getLatLng()));

    // When the radius input is changed
    radiusInput.addEventListener('input', (e) => {
        const newRadius = parseInt(e.target.value, 10);
        if (!isNaN(newRadius) && newRadius > 0) {
            circle.setRadius(newRadius);
        }
    });

    // --- 7. "LOCATE ME" FUNCTIONALITY ---
    locateButton.addEventListener('click', () => {
        setLocateButtonLoading(true);
        map.locate({ setView: true, maxZoom: 16 });
    });

    // Handle successful location finding
    map.on('locationfound', (e) => {
        updatePositionAndUI(e.latlng);
        setLocateButtonLoading(false);
        showNotification("تم تحديد موقعك بنجاح!", 'success');
    });

    // Handle errors in location finding (like permission denied)
    map.on('locationerror', (e) => {
        setLocateButtonLoading(false);
        let message = "لا يمكن الوصول إلى موقعك. ";
        if (e.code === 1) { // PERMISSION_DENIED
            message += "يرجى التأكد من تفعيل خدمات الموقع والسماح للمتصفح بالوصول إليها.";
        } else {
            message += "يرجى المحاولة مرة أخرى.";
        }
        showNotification(message, 'error');
    });
});
</script>

<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>