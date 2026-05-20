<?php
// This file contains the permission management modal components
// Include this in users.php to add permission management functionality
?>

<!-- User Permissions Modal -->
<div x-show="showPermissionsModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-6xl w-full mx-4 max-h-screen overflow-y-auto">
        <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-mai-brown to-mai-dark-brown">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center ml-4">
                        <i class="fas fa-user-shield text-white text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">إدارة الصلاحيات</h3>
                        <p class="text-mai-cream text-sm mt-1" x-text="'المستخدم: ' + permissionsUser.name"></p>
                    </div>
                </div>
                <button @click="closePermissionsModal()" class="text-white hover:text-mai-cream transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6">
            <!-- Loading State -->
            <div x-show="loadingPermissions" class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-mai-brown text-2xl mb-4"></i>
                <p class="text-gray-600">جاري تحميل الصلاحيات...</p>
            </div>
            
            <!-- Permissions Content -->
            <div x-show="!loadingPermissions">
                <!-- Role Info -->
                <div class="bg-gradient-to-r from-mai-cream to-mai-gold bg-opacity-20 rounded-xl p-6 mb-8">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-mai-brown rounded-full flex items-center justify-center ml-3">
                            <i class="fas fa-user-tag text-white"></i>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold text-mai-dark-brown">معلومات الدور الوظيفي</h4>
                            <p class="text-mai-brown font-medium" x-text="'الدور: ' + (permissionsUser.role_name || 'غير محدد')"></p>
                        </div>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-info-circle text-blue-600 ml-2"></i>
                            <p class="text-blue-800 text-sm">
                                يمكنك تخصيص الصلاحيات لهذا المستخدم بشكل فردي. الصلاحيات المفعلة ستتجاوز صلاحيات الدور الأساسي.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Permissions by Module -->
                <div class="space-y-6">
                    <template x-for="(permissions, module) in groupedPermissions" :key="module">
                        <div class="border border-gray-200 rounded-xl shadow-sm hover:shadow-md transition-shadow">
                            <div class="bg-gradient-to-r from-mai-brown to-mai-dark-brown px-6 py-4 rounded-t-xl">
                                <div class="flex items-center">
                                    <i class="fas fa-cog text-white ml-3"></i>
                                    <h5 class="text-lg font-bold text-white" x-text="getModuleNameAr(module)"></h5>
                                </div>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <template x-for="permission in permissions" :key="permission.id">
                                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition-colors">
                                            <div class="flex-1">
                                                <div class="flex items-center mb-2">
                                                    <i class="fas fa-key text-mai-brown text-sm ml-2"></i>
                                                    <div class="text-sm font-medium text-gray-900" x-text="permission.permission_name_ar"></div>
                                                </div>
                                                <div class="text-xs text-gray-500 bg-gray-200 px-2 py-1 rounded font-mono" x-text="permission.permission_key"></div>
                                            </div>
                                            <div class="flex items-center gap-3 mr-4">
                                                <!-- Role Permission Indicator -->
                                                <div x-show="hasRolePermission(permission.id)" 
                                                     class="flex items-center gap-1 text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full" 
                                                     title="صلاحية من الدور">
                                                    <i class="fas fa-check-circle"></i>
                                                    <span>من الدور</span>
                                                </div>
                                                
                                                <!-- User Override Toggle -->
                                                <label class="relative inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" 
                                                           :checked="getUserPermissionStatus(permission.id)"
                                                           @change="toggleUserPermission(permission.id, $event.target.checked)"
                                                           class="sr-only peer">
                                                    <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-mai-brown/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-mai-brown"></div>
                                                </label>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                
                <!-- Legend -->
                <div class="mt-8 p-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-200">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-info-circle text-blue-600 text-xl ml-3"></i>
                        <h6 class="text-lg font-bold text-blue-900">دليل الرموز والمعاني</h6>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="flex items-center gap-3 p-3 bg-white rounded-lg border border-blue-100">
                            <div class="flex items-center gap-1 text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full">
                                <i class="fas fa-check-circle"></i>
                                <span>من الدور</span>
                            </div>
                            <span class="text-blue-800 font-medium">صلاحية مكتسبة من الدور</span>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-white rounded-lg border border-blue-100">
                            <div class="w-14 h-7 bg-mai-brown rounded-full relative">
                                <div class="absolute top-[2px] right-[2px] bg-white rounded-full h-6 w-6"></div>
                            </div>
                            <span class="text-blue-800 font-medium">تخصيص فردي مفعل</span>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-white rounded-lg border border-blue-100">
                            <div class="w-14 h-7 bg-gray-200 rounded-full relative">
                                <div class="absolute top-[2px] left-[2px] bg-white rounded-full h-6 w-6"></div>
                            </div>
                            <span class="text-blue-800 font-medium">تخصيص فردي معطل</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="p-6 border-t border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-600">
                    <i class="fas fa-save ml-1 text-mai-brown"></i>
                    يتم حفظ التغييرات تلقائياً
                </div>
                <div class="flex gap-3">
                    <button @click="saveAllPermissions()" :disabled="loadingPermissions"
                            class="px-6 py-3 bg-mai-brown text-white rounded-lg hover:bg-mai-dark-brown transition-colors disabled:opacity-50 font-medium">
                        <i class="fas fa-save ml-2" x-show="!loadingPermissions"></i>
                        <i class="fas fa-spinner fa-spin ml-2" x-show="loadingPermissions"></i>
                        حفظ الصلاحيات
                    </button>
                    <button @click="closePermissionsModal()" 
                            class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors font-medium">
                        <i class="fas fa-times ml-2"></i>
                        إغلاق
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Roles Management Modal -->
<div x-show="showRolesModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-4xl w-full mx-4 max-h-screen overflow-y-auto">
        <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-mai-gold to-yellow-600">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center ml-4">
                        <i class="fas fa-user-tag text-white text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">إدارة الأدوار الوظيفية</h3>
                        <p class="text-yellow-100 text-sm mt-1">عرض وإدارة جميع الأدوار في النظام</p>
                    </div>
                </div>
                <button @click="closeRolesModal()" class="text-white hover:text-yellow-100 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6">
            <!-- Loading State -->
            <div x-show="loadingRoles" class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-mai-brown text-2xl mb-4"></i>
                <p class="text-gray-600">جاري تحميل الأدوار...</p>
            </div>
            
            <!-- Roles List -->
            <div x-show="!loadingRoles">
                <div class="space-y-4">
                    <template x-for="role in allRoles" :key="role.id">
                        <div class="border border-gray-200 rounded-xl p-6 hover:shadow-md transition-shadow bg-white">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center mb-3">
                                        <div class="w-10 h-10 bg-mai-gold bg-opacity-20 rounded-full flex items-center justify-center ml-3">
                                            <i class="fas fa-user-tag text-mai-brown"></i>
                                        </div>
                                        <div>
                                            <h4 class="text-lg font-bold text-gray-900" x-text="role.name || role.role_name_ar || 'دور غير محدد'"></h4>
                                            <p class="text-sm text-gray-600" x-text="role.description || 'لا يوجد وصف'"></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4 text-xs">
                                        <div class="flex items-center gap-1">
                                            <i class="fas fa-hashtag text-gray-400"></i>
                                            <span class="text-gray-500 bg-gray-100 px-2 py-1 rounded font-mono" x-text="'ID: ' + role.id"></span>
                                        </div>
                                        <div class="flex items-center gap-1" x-show="role.role_name">
                                            <i class="fas fa-code text-gray-400"></i>
                                            <span class="text-gray-500 bg-gray-100 px-2 py-1 rounded font-mono" x-text="role.role_name"></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span x-show="role.is_system_role" 
                                          class="px-3 py-1 text-xs bg-blue-100 text-blue-800 rounded-full font-medium">
                                        <i class="fas fa-shield-alt ml-1"></i>
                                        دور نظام
                                    </span>
                                    <button @click="openRolePermissionsModal(role)" 
                                            class="px-4 py-2 bg-mai-brown text-white rounded-lg hover:bg-mai-dark-brown transition-colors text-sm font-medium" 
                                            title="عرض صلاحيات الدور">
                                        <i class="fas fa-key ml-2"></i>
                                        عرض الصلاحيات
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                
                <div class="mt-6 p-4 bg-yellow-50 rounded-lg">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-info-circle text-yellow-600 mt-1"></i>
                        <div>
                            <h6 class="font-medium text-yellow-800">ملاحظة مهمة</h6>
                            <p class="text-sm text-yellow-700 mt-1">
                                أدوار النظام محمية ولا يمكن تعديلها أو حذفها. يمكنك فقط عرض صلاحياتها.
                                لإنشاء أدوار مخصصة، يرجى التواصل مع مطور النظام.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Role Permissions View Modal -->
<div x-show="showRolePermissionsModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-4xl w-full mx-4 max-h-screen overflow-y-auto">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-mai-dark-brown">صلاحيات الدور</h3>
                    <p class="text-gray-600 text-sm mt-1" x-text="'الدور: ' + (selectedRole?.role_name_ar || '')"></p>
                </div>
                <button @click="closeRolePermissionsModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        
        <div class="p-6">
            <div x-show="loadingRolePermissions" class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-mai-brown text-2xl mb-4"></i>
                <p class="text-gray-600">جاري تحميل صلاحيات الدور...</p>
            </div>
            
            <div x-show="!loadingRolePermissions">
                <div class="space-y-6">
                    <template x-for="(permissions, module) in rolePermissionsByModule" :key="module">
                        <div class="border border-gray-200 rounded-lg">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <h5 class="font-medium text-gray-900" x-text="getModuleNameAr(module)"></h5>
                            </div>
                            <div class="p-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                    <template x-for="permission in permissions" :key="permission.id">
                                        <div class="flex items-center gap-3 p-3 bg-green-50 rounded-lg">
                                            <i class="fas fa-check text-green-600"></i>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900" x-text="permission.permission_name_ar"></div>
                                                <div class="text-xs text-gray-500" x-text="permission.permission_key"></div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
        
        <div class="p-6 border-t border-gray-200 bg-gray-50">
            <div class="flex justify-end">
                <button @click="closeRolePermissionsModal()" 
                        class="px-4 py-2 bg-mai-brown text-white rounded-lg hover:bg-mai-dark-brown">
                    إغلاق
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Additional JavaScript functions for permission management
function extendUserManagement() {
    return {
        // ... existing properties ...
        showPermissionsModal: false,
        showRolesModal: false,
        showRolePermissionsModal: false,
        loadingPermissions: false,
        loadingRoles: false,
        loadingRolePermissions: false,
        permissionsUser: {},
        allPermissions: {},
        groupedPermissions: {},
        rolePermissions: [],
        userPermissions: [],
        allRoles: [],
        selectedRole: null,
        rolePermissionsByModule: {},
        
        // Module names in Arabic
        moduleNames: {
            'dashboard': 'لوحة التحكم',
            'pos': 'نقطة البيع',
            'bookings': 'الحجوزات',
            'clients': 'العملاء',
            'inventory': 'المخزون',
            'hr': 'الموارد البشرية',
            'loyalty': 'نظام الولاء',
            'marketing': 'التسويق',
            'reports': 'التقارير',
            'settings': 'الإعدادات',
            'users': 'إدارة المستخدمين',
            'content': 'إدارة المحتوى'
        },
        
        getModuleNameAr(module) {
            return this.moduleNames[module] || module;
        },
        
        async openPermissionsModal(userId, userName) {
            this.permissionsUser = { id: userId, name: userName };
            this.showPermissionsModal = true;
            this.loadingPermissions = true;
            
            try {
                const formData = new FormData();
                formData.append('action', 'get_user_permissions');
                formData.append('user_id', userId);
                
                const response = await fetch('users_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.permissionsUser = result.user;
                    this.rolePermissions = result.role_permissions;
                    this.userPermissions = result.user_permissions;
                    this.allPermissions = result.all_permissions;
                    this.groupedPermissions = result.all_permissions;
                } else {
                    alert(result.message || 'حدث خطأ أثناء تحميل الصلاحيات');
                }
            } catch (error) {
                alert('حدث خطأ أثناء تحميل الصلاحيات');
            } finally {
                this.loadingPermissions = false;
            }
        },
        
        closePermissionsModal() {
            this.showPermissionsModal = false;
            this.permissionsUser = {};
            this.rolePermissions = [];
            this.userPermissions = [];
            this.allPermissions = {};
            this.groupedPermissions = {};
        },
        
        hasRolePermission(permissionId) {
            return this.rolePermissions.some(p => p.id == permissionId);
        },
        
        getUserPermissionStatus(permissionId) {
            const userPerm = this.userPermissions.find(p => p.id == permissionId);
            if (userPerm) {
                return userPerm.is_granted == 1;
            }
            return this.hasRolePermission(permissionId);
        },
        
        async toggleUserPermission(permissionId, granted) {
            try {
                const formData = new FormData();
                formData.append('action', granted ? 'grant_permission' : 'revoke_permission');
                formData.append('user_id', this.permissionsUser.id);
                formData.append('permission_id', permissionId);
                
                const response = await fetch('users_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update local state
                    const existingIndex = this.userPermissions.findIndex(p => p.id == permissionId);
                    if (existingIndex >= 0) {
                        this.userPermissions[existingIndex].is_granted = granted ? 1 : 0;
                    } else {
                        // Add new permission override
                        const permission = Object.values(this.allPermissions)
                            .flat()
                            .find(p => p.id == permissionId);
                        if (permission) {
                            this.userPermissions.push({
                                ...permission,
                                is_granted: granted ? 1 : 0
                            });
                        }
                    }
                } else {
                    alert(result.message || 'حدث خطأ أثناء تحديث الصلاحية');
                    // Revert checkbox state
                    event.target.checked = !granted;
                }
            } catch (error) {
                alert('حدث خطأ أثناء تحديث الصلاحية');
                // Revert checkbox state
                event.target.checked = !granted;
            }
        },
        
        async openRolesModal() {
            this.showRolesModal = true;
            this.loadingRoles = true;
            
            try {
                const formData = new FormData();
                formData.append('action', 'get_roles');
                
                const response = await fetch('users_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.allRoles = result.roles;
                } else {
                    alert(result.message || 'حدث خطأ أثناء تحميل الأدوار');
                }
            } catch (error) {
                alert('حدث خطأ أثناء تحميل الأدوار');
            } finally {
                this.loadingRoles = false;
            }
        },
        
        closeRolesModal() {
            this.showRolesModal = false;
            this.allRoles = [];
        },
        
        async viewRolePermissions(role) {
            this.selectedRole = role;
            this.showRolePermissionsModal = true;
            this.loadingRolePermissions = true;
            
            try {
                const formData = new FormData();
                formData.append('action', 'get_role_permissions');
                formData.append('role_id', role.id);
                
                const response = await fetch('users_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Group permissions by module
                    const grouped = {};
                    result.permissions.forEach(permission => {
                        if (!grouped[permission.module]) {
                            grouped[permission.module] = [];
                        }
                        grouped[permission.module].push(permission);
                    });
                    this.rolePermissionsByModule = grouped;
                } else {
                    alert(result.message || 'حدث خطأ أثناء تحميل صلاحيات الدور');
                }
            } catch (error) {
                alert('حدث خطأ أثناء تحميل صلاحيات الدور');
            } finally {
                this.loadingRolePermissions = false;
            }
        },
        
        closeRolePermissionsModal() {
            this.showRolePermissionsModal = false;
            this.selectedRole = null;
            this.rolePermissionsByModule = {};
        }
    }
}
</script>
