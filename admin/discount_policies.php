<?php
    ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'auth.php';
require_once '../config/database.php';
require_once 'users_functions.php'; // Ensure permission functions are included

checkAdminAuth();

// PERMISSION CHECK: Main page access
requirePermission('discount_policies.view');

$admin = getCurrentAdmin();

// PERMISSION CHECK: Determine if the user has rights to manage policies
$can_manage_policies = hasPermission('discount_policies.manage');

$database = new Database();
$db = $database->getConnection();

// Layout configuration
$page_title = 'سياسات الخصم';
$page_subtitle = 'إدارة سياسات الخصم حسب الأدوار';

ob_start();
?>

<!-- Page Header -->
<div class="bg-white rounded-xl shadow mb-4 p-4">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div>
                <h1 class="text-lg font-bold text-mai-dark-brown">سياسات الخصم</h1>
                <p class="text-xs text-gray-500">إدارة سياسات الخصم حسب الأدوار</p>
            </div>
        </div>
        <a href="settings.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center">
            <i class="fas fa-arrow-left ml-2"></i>
            العودة للإعدادات
        </a>
    </div>
</div>

<!-- Main Content -->
<div class="container mx-auto px-4 pb-8">
    <!-- Dynamic Message Area -->
    <div id="message-container"></div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Add/Edit Discount Policy Form -->
        <div class="lg:col-span-1">
            <!-- PERMISSION CHECK: Show form only if user has manage permission -->
            <?php if ($can_manage_policies): ?>
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-bold text-mai-dark-brown mb-4">
                    <span id="policy-form-title">إضافة / تعديل سياسة</span>
                </h3>
                
                <form id="discount-policy-form" class="space-y-4" method="post" action="discount_policies_api.php">
                    <input type="hidden" name="action" value="update">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">الدور الوظيفي</label>
                        <select id="policy-role" name="role_name" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent">
                            <option value="">جاري تحميل الأدوار...</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">الحد الأقصى للخصم (%)</label>
                        <input type="number" id="policy-max-discount" name="max_discount_percent" 
                               min="0" max="100" step="0.01" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">الحد الأقصى للمبلغ (ريال)</label>
                        <input type="number" id="policy-max-amount" name="max_discount_amount" 
                               min="0" step="0.01"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-mai-brown focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">اتركه فارغاً لعدم وضع حد أقصى للمبلغ</p>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="policy-require-reason" name="reason_required"
                               class="h-4 w-4 rounded border-gray-300 text-mai-brown focus:ring-mai-brown">
                        <label for="policy-require-reason" class="mr-2 text-sm text-gray-700">يتطلب سبب الخصم</label>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="policy-active" name="is_active" checked
                               class="h-4 w-4 rounded border-gray-300 text-mai-brown focus:ring-mai-brown">
                        <label for="policy-active" class="mr-2 text-sm text-gray-700">سياسة نشطة</label>
                    </div>
                    
                    <input type="hidden" id="policy-role-ar" name="role_name_ar">

                    <div class="flex space-x-3 space-x-reverse pt-2">
                        <button type="submit" class="flex-1 bg-mai-brown text-white py-2 rounded-lg font-medium hover:bg-mai-dark-brown transition-colors flex justify-center items-center gap-2">
                            <i id="policy-submit-spinner" class="fas fa-spinner fa-spin hidden"></i>
                            <span id="policy-submit-text">حفظ</span>
                        </button>
                        <button type="button" onclick="resetPolicyForm()" class="flex-1 bg-gray-500 text-white py-2 rounded-lg font-medium hover:bg-gray-600 transition-colors">
                            إلغاء
                        </button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 text-center">
                 <i class="fas fa-lock text-3xl text-yellow-500 mb-3"></i>
                 <h3 class="font-bold text-yellow-800">صلاحية مطلوبة</h3>
                 <p class="text-sm text-yellow-700 mt-1">ليس لديك الصلاحية لإدارة سياسات الخصم.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Discount Policies List -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-lg">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-bold text-mai-dark-brown">سياسات الخصم الحالية</h3>
                        <button onclick="loadDiscountPolicies()" class="bg-mai-gold text-white px-4 py-2 rounded-lg hover:bg-yellow-600 transition-colors">
                            <i class="fas fa-sync-alt"></i>
                            <span class="mr-2">تحديث</span>
                        </button>
                    </div>
                </div>
                <div class="p-6">
                    <div id="discount-policies-container">
                        <p class="text-gray-500 text-center py-8">جاري تحميل سياسات الخصم...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// PERMISSION CHECK: Pass PHP variable to JavaScript
const canManage = <?php echo json_encode($can_manage_policies); ?>;
const C_HIDDEN = 'hidden';

document.addEventListener('DOMContentLoaded', function() {
    // Only populate dropdown if the user can manage policies
    if (canManage) {
        populateRolesDropdown();
    }
    loadDiscountPolicies();
});

// Fetch roles from the API and populate the dropdown menu
function populateRolesDropdown() {
    const roleSelect = document.getElementById('policy-role');
    fetch('discount_policies_api.php?action=get_all_roles')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.roles) {
            roleSelect.innerHTML = '<option value="">اختر الدور</option>';
            data.roles.forEach(role => {
                roleSelect.innerHTML += `<option value="${role.role_name}">${role.role_name_ar}</option>`;
            });
        } else {
            roleSelect.innerHTML = '<option value="">فشل في تحميل الأدوار</option>';
        }
    })
    .catch(error => {
        roleSelect.innerHTML = '<option value="">خطأ في تحميل الأدوار</option>';
    });
}

// Load and display all discount policies from the API
function loadDiscountPolicies() {
    const container = document.getElementById('discount-policies-container');
    container.innerHTML = '<p class="text-gray-500 text-center py-8">جاري التحميل...</p>';
    
    fetch('discount_policies_api.php?action=get_all')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderDiscountPolicies(data.policies);
        } else {
            container.innerHTML = `<p class="text-red-500 text-center py-8">فشل في تحميل السياسات: ${data.message}</p>`;
        }
    })
    .catch(error => {
        container.innerHTML = '<p class="text-red-500 text-center py-8">حدث خطأ في تحميل سياسات الخصم</p>';
    });
}

// Render the list of policies with appropriate styling and actions
function renderDiscountPolicies(policies) {
    const container = document.getElementById('discount-policies-container');
    if (policies.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-8">لا توجد أدوار لعرضها.</p>';
        return;
    }
    
    let html = '<div class="space-y-4">';
    policies.forEach(policy => {
        const isPolicySet = policy.max_discount_percent !== null;
        
        html += `<div class="${isPolicySet ? 'bg-gray-50' : 'bg-yellow-50 border border-dashed border-yellow-300'} rounded-lg p-4">`;
        html += `
            <div class="flex justify-between items-start mb-2">
                <div>
                    <h3 class="font-bold ${isPolicySet ? 'text-mai-dark-brown' : 'text-gray-600'}">${policy.role_name_ar}</h3>
                    <p class="text-sm text-gray-500">${policy.role_name}</p>
                </div>
                <div class="flex items-center space-x-2 space-x-reverse">`;

        // PERMISSION CHECK: Show edit/add buttons only if user has manage permission
        if (canManage) {
            if (isPolicySet) {
                html += `<span class="px-2 py-1 rounded text-xs ${policy.is_active == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                            ${policy.is_active == 1 ? 'نشط' : 'غير نشط'}
                         </span>
                         <button onclick="editDiscountPolicy('${policy.role_name}')" class="text-mai-brown hover:text-mai-dark-brown" title="تعديل السياسة">
                             <i class="fas fa-edit"></i>
                         </button>`;
            } else {
                html += `<span class="px-2 py-1 rounded text-xs bg-gray-200 text-gray-700">لم يتم التعيين</span>
                         <button onclick="editDiscountPolicy('${policy.role_name}')" class="text-green-600 hover:text-green-800" title="إضافة سياسة">
                             <i class="fas fa-plus-circle fa-lg"></i>
                         </button>`;
            }
        }
        
        html += `</div></div>`;

        if (isPolicySet) {
            html += `
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm pt-2">
                    <div><span class="text-gray-600">أقصى نسبة:</span> <span class="font-bold text-mai-brown">${policy.max_discount_percent}%</span></div>
                    <div><span class="text-gray-600">أقصى مبلغ:</span> <span class="font-bold text-mai-brown">${policy.max_discount_amount || 'لا يوجد'} ر.س</span></div>
                    <div><span class="text-gray-600">يتطلب سبب:</span> <span class="font-bold ${policy.reason_required == 1 ? 'text-red-600' : 'text-green-600'}">${policy.reason_required == 1 ? 'نعم' : 'لا'}</span></div>
                </div>`;
        }
        html += `</div>`;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

// PERMISSION CHECK: Attach submit listener only if the form exists (user has manage permission)
const policyForm = document.getElementById('discount-policy-form');
if (policyForm) {
    policyForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const submitText = document.getElementById('policy-submit-text');
        const submitSpinner = document.getElementById('policy-submit-spinner');

        const roleSelect = document.getElementById('policy-role');
        const selectedText = roleSelect.options[roleSelect.selectedIndex].text;
        formData.set('role_name_ar', selectedText);
        
        if (this.dataset.editingRole) {
            formData.set('role_name', this.dataset.editingRole);
        }

        submitBtn.disabled = true;
        submitText.classList.add(C_HIDDEN);
        submitSpinner.classList.remove(C_HIDDEN);
        
        fetch('discount_policies_api.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('تم حفظ السياسة بنجاح', 'success');
                resetPolicyForm();
                loadDiscountPolicies();
            } else {
                showMessage('خطأ: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showMessage('حدث خطأ في الاتصال بالخادم', 'error');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitText.classList.remove(C_HIDDEN);
            submitSpinner.classList.add(C_HIDDEN);
        });
    });
}


// Fetch policy data and populate the form for editing or adding
function editDiscountPolicy(roleName) {
    const formEl = document.getElementById('discount-policy-form');

    fetch(`discount_policies_api.php?action=get_policy_for_role&role=${roleName}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.policy) {
            const policy = data.policy;
            document.getElementById('policy-role').value = policy.role_name;
            document.getElementById('policy-role').disabled = true;
            document.getElementById('policy-max-discount').value = policy.max_discount_percent;
            document.getElementById('policy-max-amount').value = policy.max_discount_amount || '';
            document.getElementById('policy-require-reason').checked = policy.reason_required == 1;
            document.getElementById('policy-active').checked = policy.is_active == 1;
            
            document.getElementById('policy-form-title').textContent = `تعديل سياسة لـ: ${policy.role_name_ar}`;
            document.getElementById('policy-submit-text').textContent = 'تحديث السياسة';
            
            formEl.dataset.editingRole = roleName;

            formEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            formEl.parentElement.classList.add('ring-2', 'ring-mai-brown');
            setTimeout(() => {
                 formEl.parentElement.classList.remove('ring-2', 'ring-mai-brown');
            }, 2000);

        } else {
            showMessage('خطأ في تحميل بيانات السياسة: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showMessage('حدث خطأ في تحميل بيانات السياسة', 'error');
    });
}

// Reset the form to its initial state for adding a new policy
function resetPolicyForm() {
    const form = document.getElementById('discount-policy-form');
    if(form) { // check if form exists
        form.reset();
        document.getElementById('policy-role').disabled = false;
        document.getElementById('policy-active').checked = true;
        document.getElementById('policy-form-title').textContent = 'إضافة / تعديل سياسة';
        document.getElementById('policy-submit-text').textContent = 'حفظ';
        delete form.dataset.editingRole;
    }
}

// Utility function to show dynamic success or error messages
function showMessage(message, type) {
    const container = document.getElementById('message-container');
    const messageDiv = document.createElement('div');
    messageDiv.className = `border px-4 py-3 rounded mb-4 text-center transition-opacity duration-500 ${
        type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'
    }`;
    messageDiv.textContent = message;
    
    container.innerHTML = '';
    container.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.style.opacity = '0';
        setTimeout(() => messageDiv.remove(), 500);
    }, 5000);
}
</script>

<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>