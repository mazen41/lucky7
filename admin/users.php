<?php
/**
 * User Management Page (Full CRUD)
 * Correctly implemented for a schema where users have a direct `role_id`
 * and extended with user-specific permissions.
 *
 * ENHANCEMENT: Added user working hours management.
 */

require_once 'auth.php';
require_once 'users_functions.php';
require_once '../config/database.php';
checkAdminAuth();
 $admin = getCurrentAdmin();
 $database = new Database();
 $db = $database->getConnection();

// Get the 'custom' role ID
try {
    $stmt = $db->prepare("SELECT id FROM roles WHERE role_name = 'custom' LIMIT 1");
    $stmt->execute();
    $custom_role_row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$custom_role_row) {
        die("Error: The 'custom' role is not defined in the 'roles' table.");
    }
    $customRoleId = $custom_role_row['id'];
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// --- PHP LOGIC FOR CRUD ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $current_admin_id = $admin['id']; 

    try {
        $db->beginTransaction();

        switch ($action) {
            case 'add_user':
                requirePermission('users.create');
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$_POST['email']]);
                if ($stmt->fetch()) {
                    throw new Exception('البريد الإلكتروني مستخدم بالفعل.');
                }

                $role_id = ($_POST['role_id'] === 'custom') ? $customRoleId : $_POST['role_id'];

                $stmt = $db->prepare("INSERT INTO users (name, email, password, role_id, is_active, created_at, created_by) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
                $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt->execute([$_POST['name'], $_POST['email'], $hashed_password, $role_id, $_POST['is_active'], $current_admin_id]);
                $user_id = $db->lastInsertId();

                if ($role_id == $customRoleId && !empty($_POST['permissions'])) {
                    $stmt_perm = $db->prepare("INSERT INTO user_permissions (user_id, permission_id, is_granted, granted_at, granted_by) VALUES (?, ?, 1, NOW(), ?)");
                    foreach ($_POST['permissions'] as $permission_id) {
                        $stmt_perm->execute([$user_id, $permission_id, $current_admin_id]);
                    }
                }
                
                // --- NEW: SAVE WORKING HOURS FOR NEW USER ---
                if (isset($_POST['working_hours']) && is_array($_POST['working_hours'])) {
                    $wh_stmt = $db->prepare("INSERT INTO user_working_hours (user_id, day_of_week, open_time, close_time, is_working) VALUES (?, ?, ?, ?, ?)");
                    foreach ($_POST['working_hours'] as $day => $times) {
                        $is_working = isset($times['is_working']) ? 1 : 0;
                        $open_time = !empty($times['open_time']) ? $times['open_time'] : null;
                        $close_time = !empty($times['close_time']) ? $times['close_time'] : null;
                        if ($is_working) { // Only insert if the day is marked as working
                            $wh_stmt->execute([$user_id, $day, $open_time, $close_time, $is_working]);
                        }
                    }
                }
                // --- END NEW ---
                
                $_SESSION['message'] = 'تم إضافة المستخدم بنجاح.';
                break;

            case 'edit_user':
                requirePermission('users.edit');
                $user_id = $_POST['id'];
                $role_id = ($_POST['role_id'] === 'custom') ? $customRoleId : $_POST['role_id'];
                
                $sql = "UPDATE users SET name = ?, email = ?, role_id = ?, is_active = ?";
                $params = [$_POST['name'], $_POST['email'], $role_id, $_POST['is_active']];

                if (!empty($_POST['password'])) {
                    $sql .= ", password = ?";
                    $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }
                $sql .= " WHERE id = ?";
                $params[] = $user_id;

                $stmt = $db->prepare($sql);
                $stmt->execute($params);

                // --- Handle Permissions Update ---
                $stmt_delete_perm = $db->prepare("DELETE FROM user_permissions WHERE user_id = ?");
                $stmt_delete_perm->execute([$user_id]);

                if ($role_id == $customRoleId && !empty($_POST['permissions'])) {
                    $stmt_insert_perm = $db->prepare("INSERT INTO user_permissions (user_id, permission_id, is_granted, granted_at, granted_by) VALUES (?, ?, 1, NOW(), ?)");
                    foreach ($_POST['permissions'] as $permission_id) {
                        $stmt_insert_perm->execute([$user_id, $permission_id, $current_admin_id]);
                    }
                }
                
                // --- NEW: UPDATE WORKING HOURS FOR USER ---
                // First, delete old schedule
                $db->prepare("DELETE FROM user_working_hours WHERE user_id = ?")->execute([$user_id]);
                // Then, insert new schedule
                if (isset($_POST['working_hours']) && is_array($_POST['working_hours'])) {
                    $wh_stmt = $db->prepare("INSERT INTO user_working_hours (user_id, day_of_week, open_time, close_time, is_working) VALUES (?, ?, ?, ?, ?)");
                    foreach ($_POST['working_hours'] as $day => $times) {
                        $is_working = isset($times['is_working']) ? 1 : 0;
                        $open_time = !empty($times['open_time']) ? $times['open_time'] : null;
                        $close_time = !empty($times['close_time']) ? $times['close_time'] : null;
                        if ($is_working) {
                             $wh_stmt->execute([$user_id, $day, $open_time, $close_time, $is_working]);
                        }
                    }
                }
                // --- END NEW ---

                $_SESSION['message'] = 'تم تحديث بيانات المستخدم بنجاح.';
                break;

            case 'delete_user':
                requirePermission('users.delete');
                $user_id = $_POST['id'];
                $db->prepare("DELETE FROM user_permissions WHERE user_id = ?")->execute([$user_id]);
                $db->prepare("DELETE FROM user_working_hours WHERE user_id = ?")->execute([$user_id]); // Also delete working hours
                $db->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
                $_SESSION['message'] = 'تم حذف المستخدم بنجاح.';
                break;
        }

        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $_SESSION['error'] = 'حدث خطأ: ' . $e->getMessage();
    }
    header('Location: users.php');
    exit;
}

// --- DATA FETCHING FOR DISPLAY ---
try {
    // Get all users with their assigned roles
    $users_stmt = $db->prepare("SELECT u.id, u.name, u.email, u.is_active, u.role_id, r.role_name_ar FROM users u LEFT JOIN roles r ON u.role_id = r.id ORDER BY u.created_at DESC");
    $users_stmt->execute();
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- NEW: FETCH WORKING HOURS FOR EACH USER ---
    $user_permissions_stmt = $db->prepare("SELECT permission_id FROM user_permissions WHERE user_id = ?");
    $user_wh_stmt = $db->prepare("SELECT day_of_week, open_time, close_time, is_working FROM user_working_hours WHERE user_id = ?");

    foreach ($users as $key => $user) {
        // Fetch permissions
        $user_permissions_stmt->execute([$user['id']]);
        $users[$key]['permissions'] = array_column($user_permissions_stmt->fetchAll(PDO::FETCH_ASSOC), 'permission_id');
        
        // Fetch working hours
        $user_wh_stmt->execute([$user['id']]);
        $working_hours_raw = $user_wh_stmt->fetchAll(PDO::FETCH_ASSOC);
        $working_hours = [];
        foreach($working_hours_raw as $wh) {
            $working_hours[$wh['day_of_week']] = $wh;
        }
        $users[$key]['working_hours'] = $working_hours;
    }
    // --- END NEW ---

    // Get all available roles
    $roles_stmt = $db->prepare("SELECT id, role_name_ar FROM roles WHERE role_name != 'custom' ORDER BY role_name_ar ASC");
    $roles_stmt->execute();
    $roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);
    $roles[] = ['id' => 'custom', 'role_name_ar' => 'مخصص (صلاحيات فردية)'];

    // Get all available permissions
    $permissions_stmt = $db->prepare("SELECT id, permission_key, permission_name_ar, module FROM permissions ORDER BY module, permission_name_ar ASC");
    $permissions_stmt->execute();
    $permissions = $permissions_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $users = []; $roles = []; $permissions = [];
    $_SESSION['error'] = "خطأ في جلب البيانات: " . $e->getMessage();
}

 $page_title = 'إدارة المستخدمين';
 $page_subtitle = 'إدارة المستخدمين والصلاحيات والأدوار';
ob_start();
?>

<div x-data="userManagement(
    <?php echo htmlspecialchars(json_encode($users)); ?>, 
    <?php echo htmlspecialchars(json_encode($roles)); ?>,
    <?php echo htmlspecialchars(json_encode($permissions)); ?>,
    <?php echo htmlspecialchars(json_encode($customRoleId)); ?>
)">

    <!-- Page Header -->
    <div class="bg-white rounded-xl shadow mb-6 p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3"><div><h1 class="text-lg font-bold text-mai-dark-brown">إدارة المستخدمين</h1><p class="text-sm text-gray-500"><?= $page_subtitle ?></p></div></div>
            <div class="flex items-center gap-3"><a href="settings.php" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 text-sm flex items-center gap-2 transition-colors"><i class="fas fa-arrow-left"></i><span>العودة للإعدادات</span></a><?php if (hasPermission('users.create')): ?><button @click="openAddModal()" class="bg-mai-brown text-white px-4 py-2 rounded-lg hover:bg-mai-dark-brown text-sm flex items-center gap-2"><i class="fas fa-plus"></i><span>إضافة مستخدم</span></button><?php endif; ?></div>
        </div>
    </div>

    <!-- Display Messages -->
    <?php if (isset($_SESSION['message'])): ?><div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert"><p><?= $_SESSION['message'] ?></p></div><?php unset($_SESSION['message']); endif; ?>
    <?php if (isset($_SESSION['error'])): ?><div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert"><p><?= $_SESSION['error'] ?></p></div><?php unset($_SESSION['error']); endif; ?>

    <!-- Users Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الاسم</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">البريد الإلكتروني</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الدور</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الحالة</th><th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الإجراءات</th></tr></thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <template x-for="user in users" :key="user.id"><tr class="hover:bg-gray-50"><td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900" x-text="user.name"></td><td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="user.email"></td><td class="px-6 py-4 whitespace-nowrap"><span class="px-2 py-1 text-xs rounded-full bg-mai-cream text-mai-brown" x-text="user.role_name_ar || 'بلا دور'"></span></td><td class="px-6 py-4 whitespace-nowrap"><span class="px-2 py-1 text-xs rounded-full" :class="user.is_active == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" x-text="user.is_active == 1 ? 'نشط' : 'غير نشط'"></span></td><td class="px-6 py-4 whitespace-nowrap text-sm font-medium"><?php if (hasPermission('users.edit')): ?><button @click="openEditModal(user)" class="text-blue-600 hover:text-blue-900 ml-4"><i class="fas fa-edit"></i> تعديل</button><?php endif; ?><?php if (hasPermission('users.delete')): ?><form method="POST" class="inline-block" @submit.prevent="confirmDelete($event.target)"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" :value="user.id"><button type="submit" class="text-red-600 hover:text-red-900"><i class="fas fa-trash"></i> حذف</button></form><?php endif; ?></td></tr></template>
                    <tr x-show="users.length === 0"><td colspan="5" class="px-6 py-4 text-center text-gray-500">لا يوجد مستخدمين لعرضهم.</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div x-show="isModalOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" x-cloak>
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl" @click.outside="isModalOpen = false">
            <div class="p-5 border-b flex justify-between items-center"><h3 class="text-xl font-bold text-mai-dark-brown" x-text="modalTitle"></h3><button @click="isModalOpen = false" class="text-gray-400 hover:text-red-600">&times;</button></div>
            <form method="POST">
                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                    <input type="hidden" name="action" :value="formMethod"><input type="hidden" name="id" x-model="formData.id">
                    <div><label class="block text-sm font-medium text-gray-700">الاسم*</label><input type="text" name="name" x-model="formData.name" required class="mt-1 w-full border-gray-300 rounded-md"></div>
                    <div><label class="block text-sm font-medium text-gray-700">البريد الإلكتروني*</label><input type="email" name="email" x-model="formData.email" required class="mt-1 w-full border-gray-300 rounded-md"></div>
                    <div><label class="block text-sm font-medium text-gray-700">كلمة المرور <span x-show="formMethod === 'add_user'">(مطلوب)</span><span x-show="formMethod === 'edit_user'">(اتركه فارغاً لعدم التغيير)</span></label><input type="password" name="password" x-model="formData.password" :required="formMethod === 'add_user'" class="mt-1 w-full border-gray-300 rounded-md"></div>
                    <div class="grid grid-cols-2 gap-4"><div><label class="block text-sm font-medium text-gray-700">الدور*</label><select name="role_id" x-model="formData.role_id" @change="handleRoleChange()" required class="mt-1 w-full border-gray-300 rounded-md"><option value="">اختر دور</option><template x-for="role in roles" :key="role.id"><option :value="role.id" x-text="role.role_name_ar"></option></template></select></div><div><label class="block text-sm font-medium text-gray-700">الحالة*</label><select name="is_active" x-model="formData.is_active" required class="mt-1 w-full border-gray-300 rounded-md"><option value="1">نشط</option><option value="0">غير نشط</option></select></div></div>
                    
                    <!-- --- NEW: WORKING HOURS SECTION --- -->
                    <hr>
                    <fieldset class="border rounded-md p-4">
                        <legend class="text-sm font-medium text-gray-700 px-2">أوقات الدوام (للحضور والانصراف)</legend>
                        <div class="space-y-3 mt-2">
                            <template x-for="day in weekDays" :key="day.key">
                                <div class="grid grid-cols-3 gap-x-4 items-center">
                                    <label class="flex items-center space-x-2 space-x-reverse col-span-1">
                                        <input type="checkbox" :name="'working_hours[' + day.key + '][is_working]'" value="1" x-model="formData.working_hours[day.key].is_working" class="rounded border-gray-300 text-mai-brown focus:ring-mai-brown">
                                        <span class="text-sm font-semibold" x-text="day.name"></span>
                                    </label>
                                    <div class="col-span-2 grid grid-cols-2 gap-x-2">
                                        <input type="time" :name="'working_hours[' + day.key + '][open_time]'" x-model="formData.working_hours[day.key].open_time" :disabled="!formData.working_hours[day.key].is_working" class="w-full border-gray-300 rounded-md text-sm disabled:bg-gray-100">
                                        <input type="time" :name="'working_hours[' + day.key + '][close_time]'" x-model="formData.working_hours[day.key].close_time" :disabled="!formData.working_hours[day.key].is_working" class="w-full border-gray-300 rounded-md text-sm disabled:bg-gray-100">
                                    </div>
                                </div>
                            </template>
                        </div>
                    </fieldset>
                    <!-- --- END NEW --- -->

                    <hr>
                    <fieldset :disabled="formData.role_id !== 'custom'" class="transition-opacity" :class="{ 'opacity-50 cursor-not-allowed': formData.role_id !== 'custom' }">
                        <label class="block text-sm font-medium text-gray-700 mb-2">الصلاحيات المخصصة <span class="text-xs text-gray-500">(فعّالة فقط عند اختيار دور "مخصص")</span></label>
                        <template x-for="(group, module) in groupedPermissions" :key="module"><div class="border rounded-md p-3 mb-3"><h4 class="font-semibold text-mai-brown mb-2" x-text="module"></h4><div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-2"><template x-for="perm in group" :key="perm.id"><label class="flex items-center space-x-2 space-x-reverse cursor-pointer"><input type="checkbox" name="permissions[]" :value="perm.id" x-model="formData.permissions" class="rounded border-gray-300 text-mai-brown focus:ring-mai-brown"><span class="text-sm" x-text="perm.permission_name_ar"></span></label></template></div></div></template>
                    </fieldset>
                </div>
                <div class="p-4 bg-gray-50 flex justify-end gap-3 border-t"><button type="button" @click="isModalOpen = false" class="px-4 py-2 border rounded-lg text-gray-700 hover:bg-gray-100">إلغاء</button><button type="submit" class="px-4 py-2 bg-mai-brown text-white rounded-lg hover:bg-mai-dark-brown">حفظ</button></div>
            </form>
        </div>
    </div>
</div>

<script>
    function userManagement(usersData, rolesData, permissionsData, customRoleId) {
        return {
            users: usersData,
            roles: rolesData,
            permissions: permissionsData,
            customRoleId: customRoleId,
            isModalOpen: false,
            modalTitle: '',
            formMethod: 'add_user',
            formData: {},
            
            // --- NEW: WEEKDAYS FOR THE FORM ---
            weekDays: [
                { key: 'saturday', name: 'السبت' }, { key: 'sunday', name: 'الأحد' },
                { key: 'monday', name: 'الإثنين' }, { key: 'tuesday', name: 'الثلاثاء' },
                { key: 'wednesday', name: 'الأربعاء' }, { key: 'thursday', name: 'الخميس' },
                { key: 'friday', name: 'الجمعة' }
            ],

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
                this.modalTitle = 'إضافة مستخدم جديد';
                this.formMethod = 'add_user';
                this.isModalOpen = true;
            },

            openEditModal(user) {
                this.modalTitle = 'تعديل بيانات المستخدم';
                this.formMethod = 'edit_user';
                
                // --- NEW: HANDLE WORKING HOURS FOR EDIT ---
                const defaultSchedule = this.getDefaultSchedule();
                // Merge saved schedule into the default one
                if(user.working_hours) {
                    for (const dayKey in user.working_hours) {
                        if(defaultSchedule[dayKey]) {
                            defaultSchedule[dayKey] = {
                                is_working: user.working_hours[dayKey].is_working == 1,
                                open_time: user.working_hours[dayKey].open_time,
                                close_time: user.working_hours[dayKey].close_time,
                            };
                        }
                    }
                }

                this.formData = {
                    id: user.id, name: user.name, email: user.email, password: '', 
                    role_id: user.role_id == this.customRoleId ? 'custom' : user.role_id,
                    is_active: user.is_active, permissions: user.permissions || [],
                    working_hours: defaultSchedule // Assign the merged schedule
                };
                this.isModalOpen = true;
            },
            
            // --- NEW: HELPER TO GET A DEFAULT SCHEDULE ---
            getDefaultSchedule() {
                let schedule = {};
                this.weekDays.forEach(day => {
                    schedule[day.key] = { is_working: false, open_time: '09:00', close_time: '17:00' };
                });
                return schedule;
            },

            resetForm() {
                this.formData = {
                    id: null, name: '', email: '', password: '', role_id: '', is_active: 1, permissions: [],
                    working_hours: this.getDefaultSchedule()
                };
            },

            handleRoleChange() {
                if (this.formData.role_id !== 'custom') {
                    this.formData.permissions = [];
                }
            },

            confirmDelete(formElement) {
                if (confirm('هل أنت متأكد من رغبتك في حذف هذا المستخدم؟ لا يمكن التراجع عن هذا الإجراء.')) {
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