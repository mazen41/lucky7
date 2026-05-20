<?php
/**
 * MAI Salon - Responsive User Management System
 * Fully responsive user management system with mobile support
 */

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once 'auth.php';

// Check admin authentication
checkAdminAuth();

// Initialize database
$database = new Database();
$db = $database->getConnection();

// Set page title and start output buffering for the responsive layout
$page_title = 'إدارة المستخدمين';
ob_start();
?>

<!-- User Management Container -->
<div class="users-container">
    <!-- Header with Stats -->
    <?php include 'includes/users_header.php'; ?>
    
    <!-- Users Table -->
    <?php include 'includes/users_table.php'; ?>
    
    <!-- Roles Section -->
    <?php include 'includes/users_roles.php'; ?>
</div>

<!-- New User Modal (hidden by default) -->
<div id="new-user-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl mx-4 max-h-screen overflow-y-auto">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center sticky top-0 bg-white">
            <h3 class="text-lg font-bold text-mai-dark-brown">إضافة مستخدم جديد</h3>
            <button id="close-user-modal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <form id="new-user-form">
                <!-- Basic Information -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">المعلومات الأساسية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الاسم الأول*</label>
                            <input type="text" name="first_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">اسم العائلة*</label>
                            <input type="text" name="last_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">اسم المستخدم*</label>
                            <input type="text" name="username" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني*</label>
                            <input type="email" name="email" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                    </div>
                </div>
                
                <!-- Password -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">كلمة المرور</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور*</label>
                            <input type="password" name="password" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">تأكيد كلمة المرور*</label>
                            <input type="password" name="confirm_password" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="flex items-center">
                            <input type="checkbox" id="require-password-change" name="require_password_change" class="ml-2">
                            <label for="require-password-change" class="text-sm">يتطلب تغيير كلمة المرور عند تسجيل الدخول الأول</label>
                        </div>
                    </div>
                </div>
                
                <!-- Role and Permissions -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">الدور والصلاحيات</h4>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">الدور*</label>
                        <select name="role" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                            <option value="">اختر الدور...</option>
                            <option value="1">Super Admin</option>
                            <option value="2">Admin</option>
                            <option value="3">Manager</option>
                            <option value="4">Partner</option>
                            <option value="5">Receptionist</option>
                            <option value="6">Specialist</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">صلاحيات إضافية</label>
                        <p class="text-xs text-gray-500 mb-2">يمكنك تحديد صلاحيات إضافية للمستخدم بعد إنشائه من خلال صفحة إدارة الصلاحيات</p>
                    </div>
                </div>
                
                <!-- Additional Information -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">معلومات إضافية</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                            <input type="tel" name="phone" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">المسمى الوظيفي</label>
                            <input type="text" name="job_title" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                    </div>
                </div>
                
                <!-- Account Status -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">حالة الحساب</h4>
                    <div class="flex items-center">
                        <input type="checkbox" id="active-status" name="active_status" checked class="ml-2">
                        <label for="active-status" class="text-sm">حساب نشط</label>
                    </div>
                </div>
            </form>
        </div>
        <div class="p-4 border-t border-gray-200 flex justify-end sticky bottom-0 bg-white">
            <button id="cancel-user-btn" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg ml-2">
                إلغاء
            </button>
            <button id="save-user-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg">
                حفظ المستخدم
            </button>
        </div>
    </div>
</div>

<!-- New Role Modal (hidden by default) -->
<div id="new-role-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl mx-4 max-h-screen overflow-y-auto">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center sticky top-0 bg-white">
            <h3 class="text-lg font-bold text-mai-dark-brown">إضافة دور جديد</h3>
            <button id="close-role-modal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4">
            <form id="new-role-form">
                <!-- Basic Information -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">المعلومات الأساسية</h4>
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">اسم الدور*</label>
                            <input type="text" name="role_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الوصف</label>
                            <textarea name="role_description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-mai-brown"></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Permissions -->
                <div class="mb-6">
                    <h4 class="font-medium text-mai-dark-brown mb-3 pb-2 border-b border-gray-200">الصلاحيات</h4>
                    
                    <!-- Dashboard Permissions -->
                    <div class="mb-4">
                        <h5 class="text-sm font-medium text-gray-700 mb-2">لوحة التحكم</h5>
                        <div class="space-y-2 pr-4">
                            <div class="flex items-center">
                                <input type="checkbox" id="perm-dashboard-view" name="permissions[]" value="dashboard.view" class="ml-2">
                                <label for="perm-dashboard-view" class="text-sm">عرض لوحة التحكم</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="perm-dashboard-stats" name="permissions[]" value="dashboard.stats" class="ml-2">
                                <label for="perm-dashboard-stats" class="text-sm">عرض الإحصائيات</label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Users Permissions -->
                    <div class="mb-4">
                        <h5 class="text-sm font-medium text-gray-700 mb-2">المستخدمين</h5>
                        <div class="space-y-2 pr-4">
                            <div class="flex items-center">
                                <input type="checkbox" id="perm-users-view" name="permissions[]" value="users.view" class="ml-2">
                                <label for="perm-users-view" class="text-sm">عرض المستخدمين</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="perm-users-create" name="permissions[]" value="users.create" class="ml-2">
                                <label for="perm-users-create" class="text-sm">إنشاء مستخدمين</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="perm-users-edit" name="permissions[]" value="users.edit" class="ml-2">
                                <label for="perm-users-edit" class="text-sm">تعديل المستخدمين</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="perm-users-delete" name="permissions[]" value="users.delete" class="ml-2">
                                <label for="perm-users-delete" class="text-sm">حذف المستخدمين</label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Clients Permissions -->
                    <div class="mb-4">
                        <h5 class="text-sm font-medium text-gray-700 mb-2">العملاء</h5>
                        <div class="space-y-2 pr-4">
                            <div class="flex items-center">
                                <input type="checkbox" id="perm-clients-view" name="permissions[]" value="clients.view" class="ml-2">
                                <label for="perm-clients-view" class="text-sm">عرض العملاء</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="perm-clients-create" name="permissions[]" value="clients.create" class="ml-2">
                                <label for="perm-clients-create" class="text-sm">إنشاء عملاء</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="perm-clients-edit" name="permissions[]" value="clients.edit" class="ml-2">
                                <label for="perm-clients-edit" class="text-sm">تعديل العملاء</label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" id="perm-clients-delete" name="permissions[]" value="clients.delete" class="ml-2">
                                <label for="perm-clients-delete" class="text-sm">حذف العملاء</label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- More permission groups would be added here -->
                </div>
            </form>
        </div>
        <div class="p-4 border-t border-gray-200 flex justify-end sticky bottom-0 bg-white">
            <button id="cancel-role-btn" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg ml-2">
                إلغاء
            </button>
            <button id="save-role-btn" class="bg-mai-brown hover:bg-mai-dark-brown text-white px-4 py-2 rounded-lg">
                حفظ الدور
            </button>
        </div>
    </div>
</div>

<!-- User Management JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add cache-busting query parameter to prevent browser caching issues
    const cacheBuster = '?v=' + new Date().getTime();
    
    // New user modal
    const newUserBtn = document.getElementById('new-user-btn');
    const newUserModal = document.getElementById('new-user-modal');
    const closeUserModal = document.getElementById('close-user-modal');
    const cancelUserBtn = document.getElementById('cancel-user-btn');
    
    newUserBtn.addEventListener('click', function() {
        newUserModal.classList.remove('hidden');
    });
    
    function closeUserModal() {
        newUserModal.classList.add('hidden');
    }
    
    closeUserModal.addEventListener('click', closeUserModal);
    cancelUserBtn.addEventListener('click', closeUserModal);
    
    // Close modal when clicking outside
    newUserModal.addEventListener('click', function(event) {
        if (event.target === newUserModal) {
            closeUserModal();
        }
    });
    
    // New role modal
    const newRoleBtn = document.getElementById('new-role-btn');
    const newRoleModal = document.getElementById('new-role-modal');
    const closeRoleModal = document.getElementById('close-role-modal');
    const cancelRoleBtn = document.getElementById('cancel-role-btn');
    
    newRoleBtn.addEventListener('click', function() {
        newRoleModal.classList.remove('hidden');
    });
    
    function closeRoleModal() {
        newRoleModal.classList.add('hidden');
    }
    
    closeRoleModal.addEventListener('click', closeRoleModal);
    cancelRoleBtn.addEventListener('click', closeRoleModal);
    
    // Close modal when clicking outside
    newRoleModal.addEventListener('click', function(event) {
        if (event.target === newRoleModal) {
            closeRoleModal();
        }
    });
    
    // Roles and permissions buttons
    const rolesBtn = document.getElementById('roles-btn');
    const permissionsBtn = document.getElementById('permissions-btn');
    const auditLogBtn = document.getElementById('audit-log-btn');
    
    rolesBtn.addEventListener('click', function() {
        window.location.href = 'roles.php' + cacheBuster;
    });
    
    permissionsBtn.addEventListener('click', function() {
        window.location.href = 'permissions.php' + cacheBuster;
    });
    
    auditLogBtn.addEventListener('click', function() {
        window.location.href = 'audit_log.php' + cacheBuster;
    });
    
    // Save user form
    const saveUserBtn = document.getElementById('save-user-btn');
    const newUserForm = document.getElementById('new-user-form');
    
    saveUserBtn.addEventListener('click', function() {
        if (newUserForm.checkValidity()) {
            // This would typically submit the form via AJAX
            // For demo purposes, we'll just show an alert
            alert('تم حفظ المستخدم بنجاح');
            closeUserModal();
        } else {
            // Trigger HTML5 validation
            const firstInvalidField = newUserForm.querySelector(':invalid');
            if (firstInvalidField) {
                firstInvalidField.focus();
            }
        }
    });
    
    // Save role form
    const saveRoleBtn = document.getElementById('save-role-btn');
    const newRoleForm = document.getElementById('new-role-form');
    
    saveRoleBtn.addEventListener('click', function() {
        if (newRoleForm.checkValidity()) {
            // This would typically submit the form via AJAX
            // For demo purposes, we'll just show an alert
            alert('تم حفظ الدور بنجاح');
            closeRoleModal();
        } else {
            // Trigger HTML5 validation
            const firstInvalidField = newRoleForm.querySelector(':invalid');
            if (firstInvalidField) {
                firstInvalidField.focus();
            }
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include 'includes/responsive_layout.php';
?>
