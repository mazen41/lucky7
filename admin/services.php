<?php
// Add cache-busting headers
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once 'auth.php';
require_once '../config/database.php';
require_once 'users_functions.php'; // Required for permission checks

checkAdminAuth();
$admin = getCurrentAdmin();

// --- PERMISSION CHECK: Page Access ---
requirePermission('services.view');

$database = new Database();
$db = $database->getConnection();

// --- NEW: Fetch Internal Use Products for Recipe Dropdown ---
function getInternalUseProducts($db) {
    try {
        $stmt = $db->prepare("SELECT id, name_ar, unit FROM products WHERE is_active = 1 AND product_usage = 'internal_use' ORDER BY name_ar");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Get service categories
function getServiceCategories($db) {
    try {
        $stmt = $db->prepare("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order, name_ar");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Get services with categories
function getServicesWithCategories($db) {
    try {
        $stmt = $db->prepare("
            SELECT s.*, c.name_ar as category_name_ar, c.color as category_color
            FROM services s
            LEFT JOIN service_categories c ON s.category_id = c.id
            WHERE s.is_active = 1
            ORDER BY c.sort_order, s.sort_order, s.name_ar
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

$categories = getServiceCategories($db);
$services = getServicesWithCategories($db);
$internal_use_products = getInternalUseProducts($db); // Get ingredients data

$page_title = 'المنتجات';
$page_subtitle = 'إدارة وعرض البيانات';
$page_actions = '';

ob_start();
?>

    <style>
        .service-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
    </style>

    <!-- Main Content -->
    <div class="p-6 overflow-x-auto">
        <!-- Page Header -->
        <div class="bg-white rounded-xl shadow-md mb-6 p-6 fade-in">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">إدارة القائمة</h1>
                    <p class="text-sm text-gray-500">إضافة وتعديل الأصناف والمنتجات وتحديد مكوناتها</p>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="bg-white rounded-xl shadow-lg mb-6">
            <div class="border-b border-gray-200">
                <nav class="flex space-x-8 space-x-reverse px-6">
                    <button onclick="switchTab('services')" id="services-tab" class="tab-button py-4 px-1 border-b-2 font-medium text-sm focus:outline-none border-mai-brown text-mai-brown"><i class="fas fa-coffee ml-2"></i>المنتجات</button>
                    <button onclick="switchTab('categories')" id="categories-tab" class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm focus:outline-none"><i class="fas fa-tags ml-2"></i>التصنيفات</button>
                </nav>
            </div>
        </div>

        <!-- Services Tab Content -->
        <div id="services-content" class="tab-content" style="display: block;">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Add/Edit Service Form with Permission Check -->
                <?php if (hasPermission('services.create') || hasPermission('services.edit')): ?>
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-mai-dark-brown mb-4"><span id="service-form-title">إضافة منتج جديد</span></h2>
                        <form id="service-form" class="space-y-4">
                            <input type="hidden" id="service-id" name="id">
                            
                            <!-- Name Fields -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">اسم المنتج (عربي)</label>
                                <input type="text" id="service-name-ar" name="name_ar" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">اسم المنتج (إنجليزي)</label>
                                <input type="text" id="service-name-en" name="name_en" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                            </div>
                            
                            <!-- Category Selection -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">التصنيف</label>
                                <select id="service-category-id" name="category_id" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                                    <option value="">اختر تصنيف</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name_ar']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p id="category-validation-error" class="text-red-500 text-xs mt-1 hidden">الرجاء اختيار تصنيف.</p>
                            </div>

                            <!-- Fixed Price Field (Range removed) -->
                            <div id="fixed-price-field">
                                <label class="block text-sm font-medium text-gray-700 mb-2">السعر (ج.م)</label>
                                <input type="number" id="price" name="price" step="0.01" min="0" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                            </div>

                            <!-- Offer Price Fields -->
                            <div class="border-t pt-4 mt-4 space-y-3">
                                <div class="flex items-center">
                                    <input type="checkbox" id="has-offer" name="has_offer" onchange="toggleOfferField()" class="rounded border-gray-300 text-mai-brown focus:ring-mai-brown">
                                    <label for="has-offer" class="mr-2 text-sm font-medium text-gray-700">تفعيل خصم لهذا المنتج؟</label>
                                </div>
                                <div id="offer-price-field" style="display: none;">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">سعر العرض (ج.م)</label>
                                    <input type="number" id="offer-price" name="offer_price" step="0.01" min="0" class="w-full p-3 border border-red-300 rounded-lg focus:ring-red-500 focus:border-red-500" placeholder="السعر بعد الخصم">
                                </div>
                            </div>

                            <!-- Sort Order -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">ترتيب العرض</label>
                                <input type="number" id="service-sort-order" name="sort_order" min="0" value="0" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                            </div>

                            <!-- --- NEW: RECIPE MANAGEMENT SECTION --- -->
                            <div class="border-t pt-4 mt-4">
                                <h3 class="text-md font-semibold text-gray-800">مكونات المنتج (الوصفة)</h3>
                                <p class="text-xs text-gray-500 mt-1 mb-3">حدد المكونات التي سيتم خصمها من المخزون تلقائياً عند بيع هذا المنتج.</p>
                                <div id="ingredients-list" class="space-y-2">
                                    <!-- Ingredient rows will be added here by JavaScript -->
                                </div>
                                <button type="button" onclick="addIngredientRow()" class="mt-2 w-full text-sm bg-blue-50 text-blue-700 hover:bg-blue-100 font-semibold py-2 px-4 rounded-lg">
                                    <i class="fas fa-plus mr-2"></i>إضافة مكون
                                </button>
                            </div>
                            <!-- --- END NEW SECTION --- -->

                            <div class="flex items-center">
                                <input type="checkbox" id="service-active" name="is_active" checked class="rounded border-gray-300 text-mai-brown focus:ring-mai-brown">
                                <label for="service-active" class="mr-2 text-sm text-gray-700">منتج متاح</label>
                            </div>

                            <div class="flex space-x-3 space-x-reverse pt-4 border-t">
                                <button type="submit" class="flex-1 bg-mai-brown text-white py-3 rounded-lg font-medium hover:bg-mai-dark-brown"><span id="service-submit-text">إضافة المنتج</span></button>
                                <button type="button" onclick="resetServiceForm()" class="flex-1 bg-gray-500 text-white py-3 rounded-lg font-medium hover:bg-gray-600">إلغاء</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Services List -->
                <div class="<?= (hasPermission('services.create') || hasPermission('services.edit')) ? 'lg:col-span-2' : 'lg:col-span-3' ?>">
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <!-- Filters -->
                        <div class="mb-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <input type="text" id="service-search" placeholder="ابحث عن منتج..." class="w-full p-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                                <select id="category-filter" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                                    <option value="all">كل التصنيفات</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name_ar']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Services Grid -->
                        <div id="services-grid" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php if (empty($services)): ?>
                                <p class="text-gray-500 text-center py-8 col-span-2">لا توجد منتجات مضافة حتى الآن</p>
                            <?php else: ?>
                                <?php foreach ($services as $service): ?>
                                    <div class="service-card bg-white border border-gray-200 rounded-xl p-5" data-service-id="<?= $service['id']; ?>" data-category-id="<?= $service['category_id']; ?>" data-service-name="<?= strtolower(htmlspecialchars($service['name_ar'])); ?>">
                                        <div class="flex justify-between items-start">
                                            <div class="flex-1">
                                                <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-white" style="background-color: <?= $service['category_color'] ?? '#8B4513'; ?>">
                                                    <?= htmlspecialchars($service['category_name_ar'] ?? 'غير مصنف'); ?>
                                                </span>
                                                <h4 class="text-lg font-bold text-mai-dark-brown mt-2 mb-1"><?= htmlspecialchars($service['name_ar'] ?? ''); ?></h4>
                                            </div>
                                            <div class="flex flex-col items-end space-y-2">
                                                <?php if (hasPermission('services.edit')): ?><button onclick="editService(<?= htmlspecialchars(json_encode($service)); ?>)" class="text-mai-brown hover:text-mai-dark-brown"><i class="fas fa-edit"></i></button><?php endif; ?>
                                                <?php if (hasPermission('services.delete')): ?><button onclick="deleteService(<?= $service['id']; ?>)" class="text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></button><?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="border-t border-gray-200 mt-4 pt-4">
                                            <div class="flex justify-between items-center text-sm text-mai-brown">
                                                <div class="price-info">
                                                    <?php if (isset($service['offer_price']) && $service['offer_price'] > 0): ?>
                                                        <span class="font-bold text-red-600 text-lg"><?= number_format($service['offer_price'], 2) ?> ج.م</span>
                                                        <del class="text-gray-400 ml-2"><?= number_format($service['price'], 2) ?> ج.م</del>
                                                    <?php else: ?>
                                                        <span class="font-bold text-lg"><?= number_format($service['price'] ?? 0, 2) ?> ج.م</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <p id="no-results-message" class="text-gray-500 text-center py-8 col-span-2 hidden">لا توجد منتجات تطابق بحثك.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Categories Tab Content -->
        <div id="categories-content" class="tab-content" style="display: none;">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Add/Edit Category Form with Permission Check -->
                <?php if (hasPermission('service_categories.create') || hasPermission('service_categories.edit')): ?>
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h2 class="text-xl font-bold text-mai-dark-brown mb-4"><span id="category-form-title">إضافة تصنيف جديد</span></h2>
                        <form id="category-form" class="space-y-4">
                            <input type="hidden" id="category-id" name="id">
                            <div><label class="block text-sm font-medium text-gray-700 mb-2">اسم التصنيف (عربي)</label><input type="text" id="category-name-ar" name="name_ar" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-2">اسم التصنيف (إنجليزي)</label><input type="text" id="category-name-en" name="name_en" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-2">وصف التصنيف</label><textarea id="category-description" name="description_ar" rows="3" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"></textarea></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-2">لون التصنيف</label><input type="color" id="category-color" name="color" value="#8B4513" class="w-full h-12 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"></div>
                            <div><label class="block text-sm font-medium text-gray-700 mb-2">ترتيب العرض</label><input type="number" id="category-sort-order" name="sort_order" min="0" value="0" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"></div>
                            <div class="flex items-center"><input type="checkbox" id="category-active" name="is_active" checked class="rounded border-gray-300 text-mai-brown focus:ring-mai-brown"><label for="category-active" class="mr-2 text-sm text-gray-700">تصنيف نشط</label></div>
                            <div class="flex space-x-3 space-x-reverse"><button type="submit" class="flex-1 bg-mai-brown text-white py-3 rounded-lg font-medium hover:bg-mai-dark-brown"><span id="category-submit-text">إضافة التصنيف</span></button><button type="button" onclick="resetCategoryForm()" class="flex-1 bg-gray-500 text-white py-3 rounded-lg font-medium hover:bg-gray-600">إلغاء</button></div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Categories List -->
                <div class="<?= (hasPermission('service_categories.create') || hasPermission('service_categories.edit')) ? 'lg:col-span-2' : 'lg:col-span-3' ?>">
                    <div class="bg-white rounded-xl shadow-lg">
                        <div class="p-6 border-b border-gray-200"><h2 class="text-xl font-bold text-mai-dark-brown">قائمة التصنيفات</h2></div>
                        <div class="p-6"><div id="categories-list"><?php if (empty($categories)): ?><p class="text-gray-500 text-center py-8">لا توجد تصنيفات مضافة حتى الآن</p><?php else: ?><div class="space-y-4"><?php foreach ($categories as $category): ?><div class="border border-gray-200 rounded-lg p-4 category-item" data-category-id="<?php echo $category['id']; ?>"><div class="flex justify-between items-start"><div class="flex-1"><h4 class="text-lg font-bold text-mai-dark-brown mb-2 flex items-center"><span class="w-4 h-4 rounded-full mr-2" style="background-color: <?php echo $category['color']; ?>"></span><?php echo htmlspecialchars($category['name_ar']); ?><?php if (!$category['is_active']): ?><span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded-full mr-2">غير نشط</span><?php endif; ?></h4><?php if (isset($category['description']) && $category['description']): ?><p class="text-gray-600 mb-3"><?php echo htmlspecialchars($category['description']); ?></p><?php endif; ?><div class="flex items-center space-x-4 space-x-reverse text-sm text-mai-brown"><span><i class="fas fa-sort-numeric-down ml-1"></i> ترتيب: <?php echo $category['sort_order']; ?></span><?php if (isset($category['name_en']) && $category['name_en']): ?><span><i class="fas fa-language ml-1"></i> <?php echo htmlspecialchars($category['name_en']); ?></span><?php endif; ?></div></div><div class="flex space-x-2 space-x-reverse"><?php if (hasPermission('service_categories.edit')): ?><button onclick="editCategory(<?php echo htmlspecialchars(json_encode($category), ENT_QUOTES, 'UTF-8'); ?>)" class="text-mai-brown hover:text-mai-dark-brown"><i class="fas fa-edit"></i></button><?php endif; ?><?php if (hasPermission('service_categories.delete')): ?><button onclick="deleteCategory(<?php echo $category['id']; ?>)" class="text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></button><?php endif; ?></div></div></div><?php endforeach; ?></div><?php endif; ?></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- NEW: Make ingredients data available to JS ---
        const availableIngredients = <?php echo json_encode($internal_use_products); ?>;

        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
            document.querySelectorAll('.tab-button').forEach(b => {
                b.classList.remove('border-mai-brown', 'text-mai-brown');
                b.classList.add('border-transparent', 'text-gray-500');
            });
            document.getElementById(tabName + '-content').style.display = 'block';
            const btn = document.getElementById(tabName + '-tab');
            btn.classList.remove('border-transparent', 'text-gray-500');
            btn.classList.add('border-mai-brown', 'text-mai-brown');
        }

        // --- FILTERING LOGIC ---
        const searchInput = document.getElementById('service-search');
        const categoryFilter = document.getElementById('category-filter');
        const servicesGrid = document.getElementById('services-grid');
        const serviceCards = servicesGrid.querySelectorAll('.service-card');
        const noResultsMessage = document.getElementById('no-results-message');

        function filterServices() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedCategory = categoryFilter.value;
            let visibleCount = 0;

            serviceCards.forEach(card => {
                const serviceName = card.dataset.serviceName;
                const categoryId = card.dataset.categoryId;

                const nameMatch = serviceName.includes(searchTerm);
                const categoryMatch = selectedCategory === 'all' || selectedCategory === categoryId;

                if (nameMatch && categoryMatch) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (visibleCount === 0) {
                noResultsMessage.style.display = 'block';
            } else {
                noResultsMessage.style.display = 'none';
            }
        }

        searchInput.addEventListener('input', filterServices);
        categoryFilter.addEventListener('change', filterServices);

        function toggleOfferField() {
            const hasOffer = document.getElementById('has-offer').checked;
            const offerField = document.getElementById('offer-price-field');
            const offerInput = document.getElementById('offer-price');
            offerField.style.display = hasOffer ? 'block' : 'none';
            offerInput.required = hasOffer;
            if (!hasOffer) {
                offerInput.value = '';
            }
        }

        document.getElementById('service-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const categoryIdInput = document.getElementById('service-category-id');
            if (!categoryIdInput.value) {
                document.getElementById('category-validation-error').classList.remove('hidden');
                showMessage('الرجاء اختيار تصنيف للمنتج.', 'error');
                return;
            }

            const formData = new FormData(this);
            const serviceId = document.getElementById('service-id').value;
            formData.set('is_active', formData.has('is_active') ? '1' : '0');
            formData.append('action', serviceId ? 'update' : 'create');

            // Force fixed price type logic for Café
            formData.append('price_type', 'fixed');

            if (!formData.has('has_offer')) {
                formData.set('offer_price', '');
            }

            fetch('services_api.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showMessage('تم حفظ المنتج بنجاح', 'success');
                        location.reload();
                    } else {
                        showMessage('خطأ: ' + data.message, 'error');
                    }
                }).catch(() => showMessage('حدث خطأ أثناء حفظ المنتج', 'error'));
        });

        function editService(service) {
            <?php if (!hasPermission('services.edit')) { echo "showMessage('ليس لديك صلاحية لتعديل المنتجات.', 'error'); return;"; } ?>
            
            resetServiceForm(); // Clear the form first

            // Populate basic fields
            document.getElementById('service-id').value = service.id;
            document.getElementById('service-name-ar').value = service.name_ar || '';
            document.getElementById('service-name-en').value = service.name_en || '';
            document.getElementById('service-category-id').value = service.category_id || '';
            document.getElementById('service-sort-order').value = service.sort_order || 0;
            document.getElementById('service-active').checked = service.is_active == 1;
            document.getElementById('price').value = service.price || '';
            
            const hasOffer = parseFloat(service.offer_price) > 0;
            document.getElementById('has-offer').checked = hasOffer;
            document.getElementById('offer-price').value = hasOffer ? service.offer_price : '';
            toggleOfferField();

            // --- NEW: Fetch and populate recipe ---
            fetch(`services_api.php?action=get_with_recipe&id=${service.id}`)
                .then(res => res.json())
                .then(data => {
                    if(data.success && data.service.recipe) {
                        data.service.recipe.forEach(ing => {
                            addIngredientRow(ing.ingredient_product_id, ing.quantity_consumed);
                        });
                    }
                }).catch(err => console.error('Failed to fetch recipe:', err));

            document.getElementById('service-form-title').textContent = 'تعديل المنتج';
            document.getElementById('service-submit-text').textContent = 'تحديث المنتج';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function deleteService(serviceId) {
            if (!confirm('هل أنت متأكد من حذف هذا المنتج؟')) return;
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', serviceId);

            fetch('services_api.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showMessage('تم حذف المنتج بنجاح', 'success');
                        location.reload();
                    } else {
                        showMessage('خطأ: ' + data.message, 'error');
                    }
                }).catch(() => showMessage('حدث خطأ أثناء حذف المنتج', 'error'));
        }

        function resetServiceForm() {
            document.getElementById('service-form').reset();
            document.getElementById('service-id').value = '';
            
            document.getElementById('service-category-id').value = '';
            document.getElementById('category-validation-error').classList.add('hidden');
            document.getElementById('ingredients-list').innerHTML = ''; // Clear recipe

            document.getElementById('service-form-title').textContent = 'إضافة منتج جديد';
            document.getElementById('service-submit-text').textContent = 'إضافة المنتج';
            document.getElementById('has-offer').checked = false;
            document.getElementById('offer-price-field').style.display = 'none';
        }

        // --- NEW: RECIPE UI JAVASCRIPT ---
        function addIngredientRow(selectedId = '', quantity = '') {
            const container = document.getElementById('ingredients-list');
            
            const ingredientOptions = availableIngredients.map(ing => 
                `<option value="${ing.id}" ${ing.id == selectedId ? 'selected' : ''}>${ing.name_ar} (${ing.unit})</option>`
            ).join('');

            const row = document.createElement('div');
            row.className = 'flex items-center gap-2 p-2 bg-gray-50 border rounded-lg';
            row.innerHTML = `
                <select name="ingredients[]" class="flex-grow border-gray-300 rounded-lg text-sm p-2" required>
                    <option value="">-- اختر المكون --</option>
                    ${ingredientOptions}
                </select>
                <input type="number" name="quantities[]" step="0.01" placeholder="الكمية" value="${quantity}" class="w-24 border-gray-300 rounded-lg text-sm p-2" required>
                <button type="button" onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-700 p-2 text-sm">
                    <i class="fas fa-times"></i>
                </button>
            `;
            container.appendChild(row);
        }

        // Category JS (kept mostly same)
        document.getElementById('category-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const categoryId = document.getElementById('category-id').value;
            formData.append('action', categoryId ? 'update' : 'create');
            if (categoryId) formData.append('id', categoryId);

            fetch('service_categories_api.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showMessage('تم حفظ التصنيف بنجاح', 'success');
                        location.reload();
                    } else {
                        showMessage('خطأ: ' + data.message, 'error');
                    }
                }).catch(() => showMessage('حدث خطأ أثناء حفظ التصنيف', 'error'));
        });

        function editCategory(category) {
            <?php if (!hasPermission('service_categories.edit')) { echo "showMessage('ليس لديك صلاحية لتعديل التصنيفات.', 'error'); return;"; } ?>
            document.getElementById('category-id').value = category.id;
            document.getElementById('category-name-ar').value = category.name_ar || '';
            document.getElementById('category-name-en').value = category.name_en || '';
            document.getElementById('category-description').value = category.description_ar || category.description || '';
            document.getElementById('category-color').value = category.color || '#8B4513';
            document.getElementById('category-sort-order').value = category.sort_order || 0;
            document.getElementById('category-active').checked = category.is_active == 1;
            document.getElementById('category-form-title').textContent = 'تعديل التصنيف';
            document.getElementById('category-submit-text').textContent = 'تحديث التصنيف';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function deleteCategory(categoryId) {
            if (!confirm('هل أنت متأكد من حذف هذا التصنيف؟ سيؤثر هذا على المنتجات المرتبطة به.')) return;
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', categoryId);

            fetch('service_categories_api.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showMessage('تم حذف التصنيف بنجاح', 'success');
                        location.reload();
                    } else {
                        showMessage('خطأ: ' + data.message, 'error');
                    }
                }).catch(() => showMessage('حدث خطأ أثناء حذف التصنيف', 'error'));
        }

        function resetCategoryForm() {
            document.getElementById('category-form').reset();
            document.getElementById('category-id').value = '';
            document.getElementById('category-color').value = '#8B4513';
            document.getElementById('category-active').checked = true;
            document.getElementById('category-form-title').textContent = 'إضافة تصنيف جديد';
            document.getElementById('category-submit-text').textContent = 'إضافة التصنيف';
        }

        function showMessage(message, type) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg ${ type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }`;
            messageDiv.innerHTML = `<i class="fas ${ type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} ml-2"></i> ${message}`;
            document.body.appendChild(messageDiv);
            setTimeout(() => messageDiv.remove(), 3000);
        }
    </script>

<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>