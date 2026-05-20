<?php

require_once 'auth.php';
require_once 'users_functions.php';
require_once '../config/database.php';

checkAdminAuth();

// --- PERMISSION CHECK: Page Access ---
requirePermission('inventory.view');

$admin = getCurrentAdmin();

require_once 'SimpleProductManager.php';
$productManager = new SimpleProductManager();

// AJAX request handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'An unknown error occurred.'];

    try {
        switch ($_POST['action']) {
            case 'add_product':
                if (!hasPermission('inventory.create')) throw new Exception('ليس لديك الصلاحية لإضافة منتجات.');

                $productData = [
                    'sku' => $_POST['sku'],
                    'name_en' => $_POST['name_en'],
                    'name_ar' => $_POST['name_ar'],
                    'category_id' => $_POST['category_id'] ?: null,
                    'product_usage' => $_POST['product_usage'],
                    'qty' => ($_POST['stock_quantity'] ?? 0),
                    'min_qty' => ($_POST['min_stock_level'] ?? 0),
                    'unit' => $_POST['unit'],
                    'purchase_price' => $_POST['purchase_price'] ?: null,
                    'sale_price' => $_POST['selling_price'] ?: null,
                    'cost_price' => $_POST['purchase_price'] ?: null,
                    // --- Recipe data ---
                    'ingredients' => $_POST['ingredients'] ?? [],
                    'quantities' => $_POST['quantities'] ?? [],
                ];
                $productId = $productManager->createProduct($productData, $admin['id']);
                $response = ['success' => true, 'message' => 'تم إضافة المنتج بنجاح', 'product_id' => $productId];
                break;
                
            case 'update_product':
                if (!hasPermission('inventory.edit')) throw new Exception('ليس لديك الصلاحية لتعديل المنتجات.');

                $productId = (int) $_POST['product_id'];
                 $productData = [
                    'name_ar' => $_POST['name_ar'],
                    'name_en' => $_POST['name_en'],
                    'category_id' => $_POST['category_id'] ?: null,
                    'sku' => $_POST['sku'],
                    'selling_price' => $_POST['selling_price'],
                    'purchase_price' => $_POST['purchase_price'],
                    'cost_price' => $_POST['cost_price'],
                    'min_stock_level' => $_POST['min_stock_level'],
                    'unit' => $_POST['unit'],
                    'product_usage' => $_POST['product_usage'],
                    // --- Recipe data ---
                    'ingredients' => $_POST['ingredients'] ?? [],
                    'quantities' => $_POST['quantities'] ?? [],
                ];
                $productManager->updateProduct($productId, $productData, $admin['id']);
                $response = ['success' => true, 'message' => 'تم تحديث المنتج بنجاح'];
                break;

            case 'get_product_details':
                if (!hasPermission('inventory.view')) throw new Exception('ليس لديك الصلاحية لعرض تفاصيل المنتجات.');
                
                $product = $productManager->getProductById((int) $_POST['product_id']);
                if ($product) {
                    $response = ['success' => true, 'data' => $product];
                } else {
                    throw new Exception('لم يتم العثور على المنتج');
                }
                break;

            case 'adjust_stock':
                if (!hasPermission('inventory.adjust_stock')) throw new Exception('ليس لديك الصلاحية لتعديل المخزون.');
                
                $productId = (int) $_POST['product_id'];
                $quantity = (float) $_POST['quantity'];
                $type = $_POST['type'];
                $notes = $_POST['notes'] ?? 'Manual Adjustment';
                $quantityChange = ($type === 'add') ? $quantity : -$quantity;
                $movementType = ($type === 'add') ? 'purchase' : 'adjustment';
                $newStock = $productManager->adjustStock($productId, $quantityChange, $movementType, $admin['id'], null, null, $notes);
                $response = ['success' => true, 'message' => 'تم تعديل المخزون بنجاح', 'new_stock' => $newStock];
                break;

            case 'delete_product':
                if (!hasPermission('inventory.delete')) throw new Exception('ليس لديك الصلاحية لحذف المنتجات.');
                
                $productId = (int) $_POST['product_id'];
                $productManager->deleteProduct($productId);
                $response = ['success' => true, 'message' => 'تم حذف المنتج بنجاح'];
                break;

            case 'get_all_products':
                if (!hasPermission('inventory.view_reports')) throw new Exception('ليس لديك الصلاحية لتصدير التقارير.');
                
                $all_products = $productManager->getProducts([], 10000, 0);
                $response = ['success' => true, 'data' => $all_products];
                break;

            default:
                throw new Exception('إجراء غير مدعوم');
        }
    } catch (Exception $e) {
        http_response_code(400);
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit();
}


// Get data for page load
try {
    $db = (new Database())->getConnection();
    $stmt_cat = $db->query("SELECT * FROM product_categories WHERE is_active = 1 ORDER BY name_ar");
    $categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
    
    $low_stock_sale = $productManager->getLowStockProducts('direct_sale');
    $low_stock_internal = $productManager->getLowStockProducts('internal_use');

    // Fetch internal use products for the recipe modals
    $internal_use_products = $productManager->getInternalUseProducts();

    $products = $productManager->getProducts([], 100, 0);
} catch (Exception $e) {
    $error = 'خطأ في تحميل البيانات: ' . $e->getMessage();
    $categories = $low_stock_sale = $low_stock_internal = $products = $internal_use_products = [];
}

$page_title = 'المخزون والمنتجات';
ob_start();
?>
<style>
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideInUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .fade-in { animation: fadeIn 0.5s ease-in-out; }
    .slide-in-up { animation: slideInUp 0.5s ease-in-out; }
    .product-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
    .product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
</style>

<!-- Page Header -->
<div class="bg-white rounded-xl shadow-md mb-6 p-6 fade-in">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">المخزون والمنتجات</h1>
            <p class="text-sm text-gray-500">إدارة المنتجات والمخزون والوصفات</p>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="space-y-8 p-1 md:p-4">
    <!-- Low Stock Alerts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-red-50 border-l-4 border-red-500 rounded-r-lg p-5 shadow-sm slide-in-up">
            <h2 class="text-lg font-bold text-red-800 mb-3 flex items-center"><i class="fas fa-cash-register mr-2"></i>تنبيهات نقص منتجات البيع (<?= count($low_stock_sale); ?>)</h2>
            <?php if (empty($low_stock_sale)): ?>
                <p class="text-sm text-gray-600">لا توجد منتجات بيع وصلت للحد الأدنى.</p>
            <?php else: ?>
                <div class="space-y-2 max-h-48 overflow-y-auto pr-2">
                <?php foreach ($low_stock_sale as $p): ?>
                    <div class="bg-white p-3 rounded-lg border text-sm flex justify-between items-center">
                        <span><?= htmlspecialchars($p['name_ar']); ?></span>
                        <span class="font-bold text-red-600"><?= $p['stock_quantity']; ?> / <span class="text-gray-400"><?= $p['min_stock_level']; ?></span></span>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="bg-yellow-50 border-l-4 border-yellow-500 rounded-r-lg p-5 shadow-sm slide-in-up" style="animation-delay: 0.1s;">
             <h2 class="text-lg font-bold text-yellow-800 mb-3 flex items-center"><i class="fas fa-cut mr-2"></i>تنبيهات نقص مواد الاستخدام (<?= count($low_stock_internal); ?>)</h2>
             <?php if (empty($low_stock_internal)): ?>
                <p class="text-sm text-gray-600">لا توجد مواد استخدام وصلت للحد الأدنى.</p>
            <?php else: ?>
                <div class="space-y-2 max-h-48 overflow-y-auto pr-2">
                <?php foreach ($low_stock_internal as $p): ?>
                     <div class="bg-white p-3 rounded-lg border text-sm flex justify-between items-center">
                        <span><?= htmlspecialchars($p['name_ar']); ?></span>
                        <span class="font-bold text-yellow-600"><?= $p['stock_quantity']; ?> / <span class="text-gray-400"><?= $p['min_stock_level']; ?></span></span>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-wrap gap-4 items-center bg-white p-4 rounded-xl shadow-md">
        <?php if (hasPermission('inventory.create')): ?>
        <button onclick="openAddProductModal()" class="flex-grow sm:flex-grow-0 bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors duration-300"><i class="fas fa-plus mr-2"></i>إضافة منتج</button>
        <?php endif; ?>
        <a href="inventory_management.php" class="flex-grow sm:flex-grow-0 bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition-colors duration-300"><i class="fas fa-tasks mr-2"></i>إدارة الجرد اليدوي</a>
        <?php if (hasPermission('inventory.view_reports')): ?>
        <button onclick="generateReport(this)" class="flex-grow sm:flex-grow-0 bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition-colors duration-300"><i class="fas fa-file-csv mr-2"></i>تقرير المخزون (CSV)</button>
        <?php endif; ?>
    </div>

    <!-- Products Grid -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-bold mb-4">قائمة المنتجات</h2>
        <div id="products-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php foreach ($products as $product): ?>
                <div id="product-card-<?= $product['id']; ?>" class="product-card bg-gray-50 rounded-lg border border-gray-200 overflow-hidden flex flex-col transition-all duration-300">
                    <div class="p-4 flex-grow">
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-semibold px-2 py-1 rounded-full <?= $product['product_usage'] == 'internal_use' ? 'bg-yellow-200 text-yellow-800' : 'bg-green-200 text-green-800'; ?>">
                                <?= $product['product_usage'] == 'internal_use' ? 'مكون داخلي' : 'بيع مباشر'; ?>
                            </span>
                            <p class="text-sm text-gray-500"><?= htmlspecialchars($product['sku']); ?></p>
                        </div>
                        <h3 class="font-bold text-lg mt-2 text-gray-800"><?= htmlspecialchars($product['name_ar']); ?></h3>
                        <p class="text-sm text-gray-600"><?= htmlspecialchars($product['name_en']); ?></p>
                        <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($product['category_name_ar'] ?? 'غير مصنف'); ?></p>
                    </div>
                    <div class="bg-white p-4 border-t border-gray-200">
                        <div class="flex justify-between items-center text-sm mb-3">
                            <span class="text-gray-600">المخزون:</span>
                            <span class="font-bold text-lg stock-quantity <?= ($product['stock_quantity'] <= $product['min_stock_level']) ? 'text-red-600' : 'text-gray-800'; ?>">
                                <!-- This part will now be dynamically formatted by JS -->
                                <?= $product['stock_quantity']; ?> <span class="text-xs text-gray-500"><?= $product['unit']; ?></span>
                            </span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-gray-600">سعر البيع:</span>
                             <span class="font-semibold text-green-600"><?= $product['selling_price'] ? number_format($product['selling_price'], 2) . ' ريال' : '---'; ?></span>
                        </div>
                    </div>
                    <div class="bg-gray-100 p-2 flex justify-around">
                        <button onclick="viewProduct(<?= $product['id']; ?>)" title="عرض التفاصيل" class="text-gray-500 hover:text-green-600 p-2"><i class="fas fa-eye"></i></button>
                        <?php if (hasPermission('inventory.edit')): ?>
                        <button onclick="editProduct(<?= $product['id']; ?>)" title="تعديل المنتج" class="text-gray-500 hover:text-blue-600 p-2"><i class="fas fa-edit"></i></button>
                        <?php endif; ?>
                        <?php if (hasPermission('inventory.adjust_stock')): ?>
                        <button onclick="stockMovement(<?= $product['id']; ?>)" title="حركة مخزون" class="text-gray-500 hover:text-purple-600 p-2"><i class="fas fa-exchange-alt"></i></button>
                        <?php endif; ?>
                        <?php if (hasPermission('inventory.delete')): ?>
                        <button onclick="deleteProduct(<?= $product['id']; ?>)" title="حذف المنتج" class="text-gray-500 hover:text-red-600 p-2"><i class="fas fa-trash-alt"></i></button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modals Included Here -->
<?php include '_inventory_modals.php'; ?>

<script>
    // --- NEW: JS helper to format stock for display ---
    function formatStockForDisplay(quantity, unit) {
        const qty = parseFloat(quantity);
        if (isNaN(qty)) return `0 ${unit}`;

        switch (unit.toUpperCase()) {
            case 'KILOGRAM':
                return `${(qty / 1000).toFixed(3)} Kg`;
            case 'LITER':
                return `${(qty / 1000).toFixed(3)} Ltr`;
            case 'GRAM':
                return `${qty} g`;
            case 'MILLILITER':
                return `${qty} ml`;
            default:
                return `${qty} ${unit}`;
        }
    }

    // --- Recipe Data ---
    const availableIngredients = <?php echo json_encode($internal_use_products); ?>;

    function sendAjaxRequest(formData, successCallback, errorCallback) {
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(response => response.json().then(data => ({ ok: response.ok, data })))
            .then(({ ok, data }) => {
                if (ok && data.success) {
                    if(successCallback) successCallback(data);
                } else {
                    const message = data.message || 'An unknown error occurred.';
                    if (errorCallback) errorCallback(data);
                    else alert('خطأ: ' + message);
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                alert('A network or server error occurred.');
            });
    }

    function openModal(id) { 
        document.getElementById(id).classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }
    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
    
    function openAddProductModal() { 
        const form = document.getElementById('addProductForm');
        form.reset();
        form.querySelector('.ingredients-list').innerHTML = '';
        toggleRecipeFields(form, 'direct_sale');
        openModal('addProductModal'); 
    }
    
    function editProduct(id) {
        const formData = new FormData();
        formData.append('action', 'get_product_details');
        formData.append('product_id', id);

        sendAjaxRequest(formData, (data) => {
            const form = document.getElementById('editProductForm');
            const product = data.data;
            
            form.querySelector('[name="product_id"]').value = product.id;
            form.querySelector('[name="name_ar"]').value = product.name_ar;
            form.querySelector('[name="name_en"]').value = product.name_en || '';
            form.querySelector('[name="category_id"]').value = product.category_id || '';
            form.querySelector('[name="sku"]').value = product.sku || '';
            form.querySelector('[name="selling_price"]').value = product.selling_price || '';
            form.querySelector('[name="purchase_price"]').value = product.purchase_price || '';
            form.querySelector('[name="cost_price"]').value = product.cost_price || '';
            form.querySelector('[name="min_stock_level"]').value = product.min_stock_level || '';
            form.querySelector('[name="unit"]').value = product.unit || 'Piece';
            
            const usageRadio = form.querySelector(`[name="product_usage"][value="${product.product_usage}"]`);
            if(usageRadio) usageRadio.checked = true;

            const ingredientsContainer = form.querySelector('.ingredients-list');
            ingredientsContainer.innerHTML = ''; 
            if (product.recipe && product.recipe.length > 0) {
                product.recipe.forEach(ing => {
                    addIngredientRow(ingredientsContainer, ing.ingredient_product_id, ing.quantity_consumed);
                });
            }
            toggleRecipeFields(form, product.product_usage);

            openModal('editProductModal');
        });
    }

    // --- MODIFIED: To display formatted stock ---
    function viewProduct(id) {
        const formData = new FormData();
        formData.append('action', 'get_product_details');
        formData.append('product_id', id);

        sendAjaxRequest(formData, (data) => {
            const product = data.data;
            document.getElementById('viewProductName').textContent = product.name_ar;
            
            const displayStock = formatStockForDisplay(product.stock_quantity, product.unit);
            
            document.getElementById('viewProductData').innerHTML = `
                <li class="flex justify-between py-2 border-b"><strong>الاسم الإنجليزي:</strong> <span>${product.name_en || 'لا يوجد'}</span></li>
                <li class="flex justify-between py-2 border-b"><strong>رمز المنتج (SKU):</strong> <span>${product.sku || 'N/A'}</span></li>
                <li class="flex justify-between py-2 border-b"><strong>استخدام المنتج:</strong> <span class="px-2 py-1 rounded-full text-xs ${product.product_usage === 'internal_use' ? 'bg-yellow-200 text-yellow-800' : 'bg-green-200 text-green-800'}">${product.product_usage === 'internal_use' ? 'مكون داخلي' : 'بيع مباشر'}</span></li>
                <li class="flex justify-between py-2 border-b"><strong>المخزون الحالي:</strong> <span>${displayStock}</span></li>
                <li class="flex justify-between py-2 border-b"><strong>سعر البيع:</strong> <span class="font-semibold text-green-600">${product.selling_price || '---'} ريال</span></li>
            `;
            
            const recipeContainer = document.getElementById('viewProductRecipe');
            recipeContainer.innerHTML = '';
            if (product.recipe && product.recipe.length > 0) {
                let recipeHtml = '<h4 class="font-bold mt-4 border-t pt-2">الوصفة:</h4><ul class="list-disc pl-5 mt-2 space-y-1">';
                product.recipe.forEach(ing => {
                    recipeHtml += `<li>${ing.ingredient_name}: ${ing.quantity_consumed} ${ing.ingredient_unit}</li>`;
                });
                recipeHtml += '</ul>';
                recipeContainer.innerHTML = recipeHtml;
            }
            
            openModal('viewProductModal');
        });
    }

    // --- MODIFIED: To display formatted stock ---
    function stockMovement(id) {
        const formData = new FormData();
        formData.append('action', 'get_product_details');
        formData.append('product_id', id);
        sendAjaxRequest(formData, (data) => {
            const product = data.data;
            const form = document.getElementById('stockMovementForm');
            form.reset();
            document.getElementById('stockMovementProductName').textContent = product.name_ar;
            
            const displayStock = formatStockForDisplay(product.stock_quantity, product.unit);
            document.getElementById('currentStock').textContent = displayStock;
            
            form.querySelector('[name="product_id"]').value = product.id;
            openModal('stockMovementModal');
        });
    }

    function deleteProduct(id) {
        if (confirm('هل أنت متأكد من رغبتك في حذف هذا المنتج؟')) {
            const formData = new FormData();
            formData.append('action', 'delete_product');
            formData.append('product_id', id);
            sendAjaxRequest(formData, (data) => {
                alert(data.message);
                const card = document.getElementById('product-card-' + id);
                if (card) {
                    card.style.opacity = '0';
                    setTimeout(() => card.remove(), 500);
                }
            });
        }
    }

    // --- Recipe UI Functions ---
    function toggleRecipeFields(form, usageType) {
        const recipeSection = form.querySelector('.recipe-fields');
        const sellingPriceInput = form.querySelector('[name="selling_price"]');
        const unitSelect = form.querySelector('[name="unit"]');

        if (usageType === 'direct_sale') {
            if(recipeSection) recipeSection.style.display = 'block';
            if(sellingPriceInput) sellingPriceInput.required = true;
            if(unitSelect) unitSelect.value = 'Piece';
        } else { 
            if(recipeSection) recipeSection.style.display = 'none';
            if(sellingPriceInput) {
                sellingPriceInput.required = false;
                sellingPriceInput.value = '';
            }
        }
    }

    function addIngredientRow(container, selectedId = '', quantity = '') {
        if (container.children.length >= 3) {
            alert('يمكنك إضافة 3 مكونات كحد أقصى.');
            return;
        }

        const ingredientOptions = availableIngredients.map(ing => 
            `<option value="${ing.id}" ${ing.id == selectedId ? 'selected' : ''}>${ing.name_ar} (${ing.unit})</option>`
        ).join('');

        const row = document.createElement('div');
        row.className = 'flex items-center gap-3 p-2 bg-white border rounded mb-2';
        row.innerHTML = `
            <select name="ingredients[]" class="flex-grow border-gray-300 rounded-lg text-sm" required>
                <option value="">-- اختر المكون --</option>
                ${ingredientOptions}
            </select>
            <input type="number" name="quantities[]" step="0.01" placeholder="الكمية" value="${quantity}" class="w-24 border-gray-300 rounded-lg text-sm" required>
            <button type="button" onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-700 p-2">
                <i class="fas fa-trash-alt"></i>
            </button>
        `;
        container.appendChild(row);
    }

    function generateReport(button) {
        button.innerHTML = 'جاري التجهيز...';
        button.disabled = true;

        const formData = new FormData();
        formData.append('action', 'get_all_products');

        sendAjaxRequest(formData, (data) => {
            const products = data.data;
            let csvContent = "data:text/csv;charset=utf-8,\uFEFF";
            csvContent += "SKU,Name (AR),Name (EN),Category,Usage,Stock Qty,Unit,Sale Price,Cost Price\r\n";

            products.forEach(p => {
                let row = [ p.sku, `"${p.name_ar}"`, `"${p.name_en}"`, `"${p.category_name_ar}"`, p.product_usage, p.stock_quantity, p.unit, p.selling_price, p.cost_price ].join(',');
                csvContent += row + "\r\n";
            });

            const link = document.createElement("a");
            link.setAttribute("href", encodeURI(csvContent));
            link.setAttribute("download", "inventory_report.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            button.innerHTML = '<i class="fas fa-file-csv mr-2"></i>تقرير المخزون (CSV)';
            button.disabled = false;
        }, () => {
             button.innerHTML = '<i class="fas fa-file-csv mr-2"></i>تقرير المخزون (CSV)';
             button.disabled = false;
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('addProductForm').addEventListener('submit', function (e) {
            e.preventDefault();
            sendAjaxRequest(new FormData(this), (data) => { alert(data.message); location.reload(); });
        });
        
        document.getElementById('editProductForm').addEventListener('submit', function (e) {
            e.preventDefault();
            sendAjaxRequest(new FormData(this), (data) => { alert(data.message); location.reload(); });
        });

        // --- MODIFIED: Update the card with formatted stock after adjustment ---
        document.getElementById('stockMovementForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            const productId = formData.get('product_id');
            sendAjaxRequest(formData, (data) => {
                alert(data.message);
                
                const productCard = document.getElementById(`product-card-${productId}`);
                if (!productCard) return;

                const unitSpan = productCard.querySelector('.stock-quantity span');
                const unit = unitSpan ? unitSpan.innerText.trim() : '';
                
                const newDisplayStock = formatStockForDisplay(data.new_stock, unit);
                
                const stockCell = productCard.querySelector('.stock-quantity');
                if(stockCell) {
                    const parts = newDisplayStock.split(' ');
                    stockCell.innerHTML = `${parts[0]} <span class="text-xs text-gray-500">${parts[1] || ''}</span>`;
                }

                closeModal('stockMovementModal');
            });
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === "Escape") {
                document.querySelectorAll('.modal-container').forEach(m => closeModal(m.id));
            }
        });
    });
</script>

<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>