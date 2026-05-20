<?php
/**
 * Role Management Page (Full CRUD)
 * Allows administrators to create, edit, and delete roles,
 * and to assign permissions to each role.
 */

require_once 'auth.php';
require_once 'users_functions.php'; // Assuming hasPermission() is here
require_once '../config/database.php';

// --- Authorization Check ---
checkAdminAuth();
requirePermission('roles.manage'); // A new permission for managing roles
 $admin = getCurrentAdmin();
// --- End Authorization ---


 $database = new Database();
 $db = $database->getConnection();
 $current_admin_id = $admin['id'];

// --- START: PHP LOGIC FOR CRUD ACTIONS ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        $db->beginTransaction();

        switch ($action) {
            // ACTION: ADD a new role
            case 'add_role':
                $stmt = $db->prepare(
                    "INSERT INTO roles (role_name, role_name_ar, description) VALUES (?, ?, ?)"
                );
                $stmt->execute([$_POST['role_name'], $_POST['role_name_ar'], $_POST['description']]);
                $role_id = $db->lastInsertId();

                // Assign selected permissions
                if (!empty($_POST['permissions'])) {
                    $stmt_perm = $db->prepare(
                        "INSERT INTO role_permissions (role_id, permission_id, granted_by) VALUES (?, ?, ?)"
                    );
                    foreach ($_POST['permissions'] as $permission_id) {
                        $stmt_perm->execute([$role_id, $permission_id, $current_admin_id]);
                    }
                }
                
                $_SESSION['message'] = 'تم إضافة الدور بنجاح.';
                break;

            // ACTION: UPDATE an existing role
            case 'edit_role':
                $role_id = $_POST['id'];

                // Update role details
                $stmt = $db->prepare(
                    "UPDATE roles SET role_name = ?, role_name_ar = ?, description = ?, updated_at = NOW() WHERE id = ?"
                );
                $stmt->execute([$_POST['role_name'], $_POST['role_name_ar'], $_POST['description'], $role_id]);
                
                // First, delete all existing permissions for this role
                $stmt_delete_perm = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                $stmt_delete_perm->execute([$role_id]);
                
                // Then, insert the new set of permissions
                if (!empty($_POST['permissions'])) {
                    $stmt_insert_perm = $db->prepare(
                        "INSERT INTO role_permissions (role_id, permission_id, granted_by) VALUES (?, ?, ?)"
                    );
                    foreach ($_POST['permissions'] as $permission_id) {
                        $stmt_insert_perm->execute([$role_id, $permission_id, $current_admin_id]);
                    }
                }

                $_SESSION['message'] = 'تم تحديث بيانات الدور بنجاح.';
                break;

            // ACTION: DELETE a role
            case 'delete_role':
                $role_id = $_POST['id'];

                // Prevent deletion of system roles
                $stmt_check = $db->prepare("SELECT is_system_role FROM roles WHERE id = ?");
                $stmt_check->execute([$role_id]);
                if ($stmt_check->fetchColumn() == 1) {
                    throw new Exception('لا يمكن حذف دور أساسي في النظام.');
                }

                // Delete associated permissions first
                $stmt_perm = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                $stmt_perm->execute([$role_id]);
                
                // Then delete the role
                // NOTE: You may want to handle users assigned to this role, e.g., reassign them to a default role.
                $stmt_role = $db->prepare("DELETE FROM roles WHERE id = ?");
                $stmt_role->execute([$role_id]);
                
                $_SESSION['message'] = 'تم حذف الدور بنجاح.';
                break;
        }

        $db->commit();

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error'] = 'حدث خطأ: ' . $e->getMessage();
    }

    header('Location: roles.php');
    exit;
}

// --- END: PHP LOGIC ---


// --- START: DATA FETCHING FOR DISPLAY ---
try {
    // Get all roles
    $roles_stmt = $db->prepare("SELECT * FROM roles ORDER BY created_at DESC");
    $roles_stmt->execute();
    $roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each role, get its assigned permissions
    $role_permissions_stmt = $db->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
    foreach ($roles as $key => $role) {
        $role_permissions_stmt->execute([$role['id']]);
        $roles[$key]['permissions'] = array_column($role_permissions_stmt->fetchAll(PDO::FETCH_ASSOC), 'permission_id');
    }

    // Get all available permissions, grouped by module
    $permissions_stmt = $db->prepare(
        "SELECT id, permission_name_ar, module FROM permissions ORDER BY module, permission_name_ar ASC"
    );
    $permissions_stmt->execute();
    $permissions = $permissions_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $roles = [];
    $permissions = [];
    $_SESSION['error'] = "خطأ في جلب البيانات: " . $e->getMessage();
}
// --- END: DATA FETCHING ---


 $page_title = 'إدارة الأدوار';
 $page_subtitle = 'إنشاء وتعديل الأدوار وتحديد صلاحياتها';
ob_start();
?>

<div x-data="roleManagement(
    <?php echo htmlspecialchars(json_encode($roles)); ?>, 
    <?php echo htmlspecialchars(json_encode($permissions)); ?>
)">

    <!-- Page Header -->
    <div class="bg-white rounded-xl shadow mb-6 p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div>
                    <h1 class="text-lg font-bold text-mai-dark-brown"><?= $page_title ?></h1>
                    <p class="text-sm text-gray-500"><?= $page_subtitle ?></p>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                 <a href="settings.php" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm flex items-center gap-2 transition-colors">
                    <i class="fas fa-arrow-left"></i>
                    <span>العودة للإعدادات</span>
                </a>
                <button @click="openAddModal()" class="bg-mai-brown text-white px-4 py-2 rounded-lg hover:bg-mai-dark-brown text-sm flex items-center gap-2">
                    <i class="fas fa-plus"></i>
                    <span>إضافة دور جديد</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Display Success/Error Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert"><p><?= $_SESSION['message'] ?></p></div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert"><p><?= $_SESSION['error'] ?></p></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Roles Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">اسم الدور</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الوصف</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <template x-for="role in roles" :key="role.id">
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-medium text-gray-900" x-text="role.role_name_ar"></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="role.description"></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button @click="openEditModal(role)" class="text-blue-600 hover:text-blue-900 ml-4"><i class="fas fa-edit"></i> تعديل</button>
                                <template x-if="role.is_system_role == 0">
                                    <form method="POST" class="inline-block" @submit.prevent="confirmDelete($event.target)">
                                        <input type="hidden" name="action" value="delete_role">
                                        <input type="hidden" name="id" :value="role.id">
                                        <button type="submit" class="text-red-600 hover:text-red-900"><i class="fas fa-trash"></i> حذف</button>
                                    </form>
                                </template>
                            </td>
                        </tr>
                    </template>
                     <tr x-show="roles.length === 0">
                        <td colspan="3" class="px-6 py-4 text-center text-gray-500">لا يوجد أدوار لعرضها.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div x-show="isModalOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" x-cloak>
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl" @click.outside="isModalOpen = false">
            <div class="p-5 border-b flex justify-between items-center">
                <h3 class="text-xl font-bold text-mai-dark-brown" x-text="modalTitle"></h3>
                <button @click="isModalOpen = false" class="text-gray-400 hover:text-red-600">&times;</button>
            </div>
            <form method="POST">
                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                    <input type="hidden" name="action" :value="formMethod">
                    <input type="hidden" name="id" x-model="formData.id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                         <div>
                            <label class="block text-sm font-medium text-gray-700">اسم الدور (العربي)*</label>
                            <input type="text" name="role_name_ar" x-model="formData.role_name_ar" required class="mt-1 w-full border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">اسم الدور (Eng - Key)*</label>
                            <input type="text" name="role_name" x-model="formData.role_name" placeholder="e.g., editor" required class="mt-1 w-full border-gray-300 rounded-md">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">الوصف</label>
                        <textarea name="description" x-model="formData.description" rows="2" class="mt-1 w-full border-gray-300 rounded-md"></textarea>
                    </div>
                    
                    <!-- Permissions Section -->
                    <hr>
                    <fieldset>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                           صلاحيات الدور
                        </label>
                        <template x-for="(group, module) in groupedPermissions" :key="module">
                            <div class="border rounded-md p-3 mb-3">
                                <h4 class="font-semibold text-mai-brown mb-2" x-text="module"></h4>
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-x-4 gap-y-2">
                                    <template x-for="perm in group" :key="perm.id">
                                        <label class="flex items-center space-x-2 space-x-reverse cursor-pointer">
                                            <input type="checkbox" name="permissions[]" :value="perm.id" x-model="formData.permissions" class="rounded border-gray-300 text-mai-brown focus:ring-mai-brown">
                                            <span class="text-sm" x-text="perm.permission_name_ar"></span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </fieldset>

                </div>
                <div class="p-4 bg-gray-50 flex justify-end gap-3 border-t">
                    <button type="button" @click="isModalOpen = false" class="px-4 py-2 border rounded-lg text-gray-700 hover:bg-gray-100">إلغاء</button>
                    <button type="submit" class="px-4 py-2 bg-mai-brown text-white rounded-lg hover:bg-mai-dark-brown">حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function roleManagement(rolesData, permissionsData) {
        return {
            roles: rolesData,
            permissions: permissionsData,
            isModalOpen: false,
            modalTitle: '',
            formMethod: 'add_role',
            formData: {},

            init() { this.resetForm(); },
            
            get groupedPermissions() {
                if (!this.permissions) return {};
                return this.permissions.reduce((acc, perm) => {
                    (acc[perm.module] = acc[perm.module] || []).push(perm);
                    return acc;
                }, {});
            },

            openAddModal() {
                this.resetForm();
                this.modalTitle = 'إضافة دور جديد';
                this.formMethod = 'add_role';
                this.isModalOpen = true;
            },

            openEditModal(role) {
                this.modalTitle = 'تعديل بيانات الدور';
                this.formMethod = 'edit_role';
                this.formData = {
                    id: role.id,
                    role_name: role.role_name,
                    role_name_ar: role.role_name_ar,
                    description: role.description,
                    permissions: role.permissions.map(p => String(p)) // Ensure permissions are strings for checkbox binding
                };
                this.isModalOpen = true;
            },
            
            resetForm() {
                this.formData = {
                    id: null,
                    role_name: '',
                    role_name_ar: '',
                    description: '',
                    permissions: []
                };
            },

            confirmDelete(formElement) {
                if (confirm('هل أنت متأكد من رغبتك في حذف هذا الدور؟ سيؤثر هذا على المستخدمين المرتبطين به.')) {
                    formElement.submit();
                }
            }
        }
    }
</script>

<?php
 $page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>