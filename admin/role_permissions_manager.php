<?php

require_once 'auth.php';
require_once '../app/Core/UserManager.php';

// Check authentication and permissions
checkAdminAuth();
$admin = getCurrentAdmin();

if (!hasPermission('users.manage_roles')) {
    header('Location: dashboard.php');
    exit();
}

$userManager = new UserManager();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_role_permissions':
                $roleId = (int)($_POST['role_id'] ?? 0);
                $permissions = $_POST['permissions'] ?? [];
                
                if (!$roleId) {
                    throw new Exception('معرف الدور مطلوب');
                }
                
                // Clear existing permissions
                $userManager->clearRolePermissions($roleId);
                
                // Add new permissions
                foreach ($permissions as $permissionId) {
                    $userManager->grantRolePermission($roleId, (int)$permissionId, $admin['id']);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'تم تحديث صلاحيات الدور بنجاح'
                ]);
                break;
                
            case 'create_role':
                $roleName = trim($_POST['role_name'] ?? '');
                $roleNameAr = trim($_POST['role_name_ar'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($roleName) || empty($roleNameAr)) {
                    throw new Exception('اسم الدور مطلوب باللغتين');
                }
                
                $roleId = $userManager->createRole($roleName, $roleNameAr, $description, $admin['id']);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'تم إنشاء الدور بنجاح',
                    'role_id' => $roleId
                ]);
                break;
                
            default:
                throw new Exception('إجراء غير مدعوم');
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit();
}

// Get all roles and permissions
$roles = $userManager->getRoles();
$permissions = $userManager->getAllPermissions();

// Group permissions by module for better organization
$groupedPermissions = [];
foreach ($permissions as $permission) {
    $groupedPermissions[$permission['module']][] = $permission;
}

// Define sidebar menu structure with permissions
$sidebarMenus = [
    'dashboard' => [
        'name_ar' => 'لوحة التحكم',
        'icon' => 'fas fa-tachometer-alt',
        'permissions' => ['dashboard.view']
    ],
    'pos' => [
        'name_ar' => 'نقطة البيع (POS)',
        'icon' => 'fas fa-cash-register',
        'permissions' => ['pos.view', 'pos.create_invoice', 'pos.edit_invoice', 'pos.delete_invoice']
    ],
    'bookings' => [
        'name_ar' => 'التقويم والحجوزات',
        'icon' => 'fas fa-calendar-alt',
        'permissions' => ['bookings.view', 'bookings.create', 'bookings.edit', 'bookings.delete']
    ],
    'clients' => [
        'name_ar' => 'إدارة العملاء (CRM)',
        'icon' => 'fas fa-users',
        'permissions' => ['clients.view', 'clients.create', 'clients.edit', 'clients.delete']
    ],
    'inventory' => [
        'name_ar' => 'المخزون والمنتجات',
        'icon' => 'fas fa-boxes',
        'permissions' => ['inventory.view', 'inventory.create', 'inventory.edit', 'inventory.delete']
    ],
    'hr' => [
        'name_ar' => 'الموارد البشرية (HR)',
        'icon' => 'fas fa-user-tie',
        'permissions' => ['hr.view', 'hr.create', 'hr.edit', 'hr.delete']
    ],
    'loyalty' => [
        'name_ar' => 'نظام الولاء (Loyalty)',
        'icon' => 'fas fa-award',
        'permissions' => ['loyalty.view', 'loyalty.manage']
    ],
    'marketing' => [
        'name_ar' => 'الحملات التسويقية',
        'icon' => 'fas fa-bullhorn',
        'permissions' => ['marketing.view', 'marketing.create', 'marketing.edit', 'marketing.delete']
    ],
    'reports' => [
        'name_ar' => 'التقارير (Reports)',
        'icon' => 'fas fa-chart-bar',
        'permissions' => ['reports.view', 'reports.financial', 'reports.sales']
    ],
    'settings' => [
        'name_ar' => 'الإعدادات (Settings)',
        'icon' => 'fas fa-cog',
        'permissions' => ['settings.view', 'settings.general', 'settings.taxes']
    ],
    'users' => [
        'name_ar' => 'إدارة المستخدمين والصلاحيات',
        'icon' => 'fas fa-user-shield',
        'permissions' => ['users.view', 'users.create', 'users.edit', 'users.delete']
    ],
    'content' => [
        'name_ar' => 'إدارة المحتوى',
        'icon' => 'fas fa-edit',
        'permissions' => ['content.view', 'content.services', 'content.team', 'content.reviews']
    ]
];


// Layout configuration
$page_title = 'إدارة صلاحيات الأدوار';
$page_subtitle = 'إدارة وعرض البيانات';
$page_actions = '';

// Get current admin info
$admin = [
    'id' => $_SESSION['admin_id'] ?? 1,
    'username' => $_SESSION['admin_username'] ?? 'admin'
];

ob_start();
?>


    <div class="flex min-h-screen">
        <?php  ?>
        
        <div class="flex-1 p-8">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-mai-dark-brown mb-2">إدارة صلاحيات الأدوار</h1>
                        <p class="text-gray-600">تحكم في صلاحيات كل دور للوصول إلى عناصر القائمة الجانبية</p>
                    </div>
                    <button @click="showCreateRoleModal = true" 
                            class="px-6 py-3 bg-mai-brown text-white rounded-lg hover:bg-mai-dark-brown transition-colors">
                        <i class="fas fa-plus ml-2"></i>
                        إنشاء دور جديد
                    </button>
                </div>
            </div>

            <!-- Roles Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                <?php foreach ($roles as $role): ?>
                <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-mai-gold bg-opacity-20 rounded-full flex items-center justify-center ml-3">
                                <i class="fas fa-user-tag text-mai-brown text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($role['name'] ?? $role['role_name_ar'] ?? 'دور غير محدد'); ?></h3>
                                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($role['description'] ?? 'لا يوجد وصف'); ?></p>
                            </div>
                        </div>
                        <?php if ($role['is_system_role']): ?>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                            <i class="fas fa-shield-alt ml-1"></i>
                            نظام
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="space-y-3">
                        <button @click="openRolePermissions(<?php echo $role['id']; ?>)" 
                                class="w-full px-4 py-3 bg-mai-brown text-white rounded-lg hover:bg-mai-dark-brown transition-colors">
                            <i class="fas fa-key ml-2"></i>
                            إدارة الصلاحيات
                        </button>
                        
                        <div class="text-xs text-gray-500 text-center">
                            المعرف: <?php echo $role['id']; ?>
                            <?php if (!empty($role['role_name'])): ?>
                            | الاسم التقني: <?php echo htmlspecialchars($role['role_name']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Role Permissions Modal -->
    <div x-show="showPermissionsModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl max-w-6xl w-full mx-4 max-h-screen overflow-y-auto">
            <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-mai-gold to-yellow-600">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center ml-4">
                            <i class="fas fa-key text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">إدارة صلاحيات الدور</h3>
                            <p class="text-yellow-100 text-sm mt-1" x-text="'تحديد الصلاحيات للدور: ' + selectedRoleName"></p>
                        </div>
                    </div>
                    <button @click="closePermissionsModal()" class="text-white hover:text-yellow-100 transition-colors">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <!-- Sidebar Menu Permissions -->
                <div class="mb-8">
                    <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-bars ml-2 text-mai-brown"></i>
                        صلاحيات القائمة الجانبية
                    </h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($sidebarMenus as $menuKey => $menu): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-center mb-3">
                                <i class="<?php echo $menu['icon']; ?> text-mai-brown ml-2"></i>
                                <h5 class="font-medium text-gray-900"><?php echo $menu['name_ar']; ?></h5>
                            </div>
                            
                            <div class="space-y-2">
                                <?php foreach ($menu['permissions'] as $permKey): ?>
                                    <?php 
                                    $permission = null;
                                    foreach ($permissions as $p) {
                                        if ($p['permission_key'] === $permKey) {
                                            $permission = $p;
                                            break;
                                        }
                                    }
                                    if ($permission):
                                    ?>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="checkbox" 
                                               :checked="selectedPermissions.includes(<?php echo $permission['id']; ?>)"
                                               @change="togglePermission(<?php echo $permission['id']; ?>)"
                                               class="ml-2 text-mai-brown focus:ring-mai-brown">
                                        <span class="text-sm text-gray-700"><?php echo htmlspecialchars($permission['permission_name_ar']); ?></span>
                                    </label>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- All Permissions by Module -->
                <div class="mb-6">
                    <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-shield-alt ml-2 text-mai-brown"></i>
                        جميع الصلاحيات حسب الوحدة
                    </h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($groupedPermissions as $module => $modulePermissions): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h5 class="font-medium text-gray-900 capitalize"><?php echo ucfirst($module); ?></h5>
                                <button @click="toggleModulePermissions('<?php echo $module; ?>')" 
                                        class="text-xs px-2 py-1 bg-mai-brown text-white rounded hover:bg-mai-dark-brown">
                                    تبديل الكل
                                </button>
                            </div>
                            
                            <div class="space-y-2 max-h-48 overflow-y-auto">
                                <?php foreach ($modulePermissions as $permission): ?>
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" 
                                           :checked="selectedPermissions.includes(<?php echo $permission['id']; ?>)"
                                           @change="togglePermission(<?php echo $permission['id']; ?>)"
                                           class="ml-2 text-mai-brown focus:ring-mai-brown">
                                    <div class="flex-1">
                                        <span class="text-sm text-gray-700"><?php echo htmlspecialchars($permission['permission_name_ar']); ?></span>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($permission['permission_key']); ?></div>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="p-6 border-t border-gray-200 bg-gray-50">
                <div class="flex justify-between items-center">
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-info-circle ml-1 text-mai-brown"></i>
                        عدد الصلاحيات المحددة: <span x-text="selectedPermissions.length"></span>
                    </div>
                    <div class="flex gap-3">
                        <button @click="saveRolePermissions()" :disabled="loading"
                                class="px-6 py-3 bg-mai-brown text-white rounded-lg hover:bg-mai-dark-brown transition-colors disabled:opacity-50">
                            <i class="fas fa-save ml-2" x-show="!loading"></i>
                            <i class="fas fa-spinner fa-spin ml-2" x-show="loading"></i>
                            حفظ الصلاحيات
                        </button>
                        <button @click="closePermissionsModal()" 
                                class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                            <i class="fas fa-times ml-2"></i>
                            إغلاق
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Role Modal -->
    <div x-show="showCreateRoleModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-bold text-mai-dark-brown">إنشاء دور جديد</h3>
            </div>
            
            <form @submit.prevent="createRole()" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">اسم الدور (بالإنجليزية)</label>
                    <input type="text" x-model="newRole.role_name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">اسم الدور (بالعربية)</label>
                    <input type="text" x-model="newRole.role_name_ar" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">الوصف</label>
                    <textarea x-model="newRole.description" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-mai-brown focus:border-mai-brown"></textarea>
                </div>
                
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" @click="showCreateRoleModal = false"
                            class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                        إلغاء
                    </button>
                    <button type="submit" :disabled="loading"
                            class="px-4 py-2 bg-mai-brown text-white rounded-lg hover:bg-mai-dark-brown disabled:opacity-50">
                        <i class="fas fa-plus ml-2" x-show="!loading"></i>
                        <i class="fas fa-spinner fa-spin ml-2" x-show="loading"></i>
                        إنشاء الدور
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function rolePermissionManager() {
            return {
                showPermissionsModal: false,
                showCreateRoleModal: false,
                loading: false,
                selectedRoleId: null,
                selectedRoleName: '',
                selectedPermissions: [],
                newRole: {
                    role_name: '',
                    role_name_ar: '',
                    description: ''
                },
                
                async openRolePermissions(roleId) {
                    this.selectedRoleId = roleId;
                    this.loading = true;
                    
                    try {
                        // Get role permissions
                        const response = await fetch('users_api.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=get_role_permissions&role_id=${roleId}`
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            this.selectedPermissions = data.permissions.map(p => p.id);
                            this.selectedRoleName = data.role_name;
                            this.showPermissionsModal = true;
                        }
                    } catch (error) {
                        console.error('Error loading permissions:', error);
                    } finally {
                        this.loading = false;
                    }
                },
                
                togglePermission(permissionId) {
                    const index = this.selectedPermissions.indexOf(permissionId);
                    if (index > -1) {
                        this.selectedPermissions.splice(index, 1);
                    } else {
                        this.selectedPermissions.push(permissionId);
                    }
                },
                
                toggleModulePermissions(module) {
                    const modulePermissions = <?php echo json_encode($groupedPermissions); ?>[module];
                    const modulePermissionIds = modulePermissions.map(p => p.id);
                    
                    const allSelected = modulePermissionIds.every(id => this.selectedPermissions.includes(id));
                    
                    if (allSelected) {
                        // Remove all module permissions
                        this.selectedPermissions = this.selectedPermissions.filter(id => !modulePermissionIds.includes(id));
                    } else {
                        // Add all module permissions
                        modulePermissionIds.forEach(id => {
                            if (!this.selectedPermissions.includes(id)) {
                                this.selectedPermissions.push(id);
                            }
                        });
                    }
                },
                
                async saveRolePermissions() {
                    this.loading = true;
                    
                    try {
                        const formData = new FormData();
                        formData.append('action', 'update_role_permissions');
                        formData.append('role_id', this.selectedRoleId);
                        this.selectedPermissions.forEach(id => {
                            formData.append('permissions[]', id);
                        });
                        
                        const response = await fetch('role_permissions_manager.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            this.showAlert('تم حفظ الصلاحيات بنجاح', 'success');
                            this.closePermissionsModal();
                        } else {
                            this.showAlert(data.message, 'error');
                        }
                    } catch (error) {
                        this.showAlert('حدث خطأ أثناء حفظ الصلاحيات', 'error');
                    } finally {
                        this.loading = false;
                    }
                },
                
                async createRole() {
                    this.loading = true;
                    
                    try {
                        const formData = new FormData();
                        formData.append('action', 'create_role');
                        formData.append('role_name', this.newRole.role_name);
                        formData.append('role_name_ar', this.newRole.role_name_ar);
                        formData.append('description', this.newRole.description);
                        
                        const response = await fetch('role_permissions_manager.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            this.showAlert('تم إنشاء الدور بنجاح', 'success');
                            this.showCreateRoleModal = false;
                            this.newRole = { role_name: '', role_name_ar: '', description: '' };
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            this.showAlert(data.message, 'error');
                        }
                    } catch (error) {
                        this.showAlert('حدث خطأ أثناء إنشاء الدور', 'error');
                    } finally {
                        this.loading = false;
                    }
                },
                
                closePermissionsModal() {
                    this.showPermissionsModal = false;
                    this.selectedRoleId = null;
                    this.selectedRoleName = '';
                    this.selectedPermissions = [];
                },
                
                showAlert(message, type) {
                    // Simple alert for now - can be enhanced with toast notifications
                    alert(message);
                }
            }
        }
    </script>


<?php
$page_content = ob_get_clean();
include 'includes/admin_layout.php';
?>