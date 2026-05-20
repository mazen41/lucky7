<?php
require_once 'auth.php'; // Assuming you have an auth file
require_once '../config/database.php';

// Layout configuration
 $page_title = 'إدارة العطلات وأيام الإجازة';
 $page_subtitle = 'تحديد الأيام التي لا يتوفر فيها الحجز';
ob_start();
?>

<div x-data="holidayManager()" x-init="init()" class="container mx-auto p-4">

    <!-- Page Header -->
    <div class="bg-white rounded-xl shadow mb-6 p-4">
        <div class="flex flex-wrap justify-between items-center">
            <div class="flex items-center gap-3">
                <div>
                    <h1 class="text-lg font-bold text-mai-dark-brown"><?php echo $page_title; ?></h1>
                    <p class="text-xs text-gray-500"><?php echo $page_subtitle; ?></p>
                </div>
            </div>

            <!-- Back to Settings Button -->
            <a href="settings.php" 
               class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg shadow-sm transition mt-3 sm:mt-0">
                <i class="fas fa-arrow-right text-mai-brown"></i>
                <span>العودة إلى الإعدادات</span>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Panel: Add Holiday & Weekly Off Days -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Add New Holiday Form -->
            <div class="bg-white p-6 rounded-xl shadow">
                <h3 class="text-lg font-bold text-mai-dark-brown border-b pb-2 mb-4">إضافة عطلة جديدة</h3>
                <form @submit.prevent="addHoliday()">
                    <div class="space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">اسم العطلة</label>
                            <input type="text" id="name" x-model="newHoliday.name" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-mai-brown focus:border-mai-brown">
                        </div>
                        <div>
                            <label for="date" class="block text-sm font-medium text-gray-700">التاريخ</label>
                            <input type="date" id="date" x-model="newHoliday.date" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-mai-brown focus:border-mai-brown">
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="is_recurring" x-model="newHoliday.is_recurring" class="h-4 w-4 text-mai-brown focus:ring-mai-brown border-gray-300 rounded">
                            <label for="is_recurring" class="ml-2 block text-sm text-gray-900">تتكرر سنوياً</label>
                        </div>
                        <div>
                            <button type="submit" class="w-full bg-mai-brown text-white py-2 px-4 rounded-md hover:bg-mai-dark-brown">
                                <i class="fas fa-plus mr-2"></i>إضافة
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Weekly Days Off -->
            <div class="bg-white p-6 rounded-xl shadow">
                <h3 class="text-lg font-bold text-mai-dark-brown border-b pb-2 mb-4">أيام الإجازة الأسبوعية</h3>
                <div class="space-y-3">
                    <template x-for="day in daysOfWeek" :key="day.value">
                        <label class="flex items-center">
                            <input type="checkbox" :value="day.value" x-model="weeklyOffDays" class="h-4 w-4 text-mai-brown focus:ring-mai-brown border-gray-300 rounded">
                            <span class="ml-3 text-sm text-gray-800" x-text="day.name"></span>
                        </label>
                    </template>
                </div>
                <button @click="saveWeeklyOffDays()" class="mt-4 w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700">
                    <i class="fas fa-save mr-2"></i>حفظ أيام الإجازة
                </button>
            </div>
        </div>

        <!-- Right Panel: Holidays List -->
        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow">
            <h3 class="text-lg font-bold text-mai-dark-brown border-b pb-2 mb-4">قائمة العطلات</h3>
            <div x-show="loading" class="text-center py-4">
                <i class="fas fa-spinner fa-spin text-mai-brown text-2xl"></i>
            </div>
            <div x-show="!loading && holidays.length === 0" class="text-center text-gray-500 py-6">
                لا توجد عطلات مضافة حالياً.
            </div>
            <div x-show="!loading && holidays.length > 0" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الاسم</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">التاريخ</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">نوعها</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">إجراء</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="holiday in holidays" :key="holiday.id">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900" x-text="holiday.name"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="holiday.date"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span x-show="holiday.is_recurring == 1" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">سنوية</span>
                                    <span x-show="holiday.is_recurring == 0" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">مرة واحدة</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <button @click="deleteHoliday(holiday.id)" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function holidayManager() {
    return {
        holidays: [],
        weeklyOffDays: [],
        newHoliday: { name: '', date: '', is_recurring: false },
        daysOfWeek: [
            { name: 'الأحد', value: '0' },
            { name: 'الإثنين', value: '1' },
            { name: 'الثلاثاء', value: '2' },
            { name: 'الأربعاء', value: '3' },
            { name: 'الخميس', value: '4' },
            { name: 'الجمعة', value: '5' },
            { name: 'السبت', value: '6' }
        ],
        loading: true,

        init() {
            this.fetchHolidays();
        },

        async fetchHolidays() {
            this.loading = true;
            try {
                const response = await fetch('holidays_api.php?action=get_all');
                const data = await response.json();
                if (data.success) {
                    this.holidays = data.holidays;
                    this.weeklyOffDays = data.weekly_off_days ? data.weekly_off_days.split(',') : [];
                }
            } catch (error) {
                console.error('Error fetching holidays:', error);
                alert('فشل تحميل البيانات.');
            } finally {
                this.loading = false;
            }
        },

        async addHoliday() {
            const formData = new FormData();
            formData.append('name', this.newHoliday.name);
            formData.append('date', this.newHoliday.date);
            formData.append('is_recurring', this.newHoliday.is_recurring ? '1' : '0');

            try {
                const response = await fetch('holidays_api.php?action=add', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    alert('تمت إضافة العطلة بنجاح.');
                    this.newHoliday = { name: '', date: '', is_recurring: false };
                    this.fetchHolidays();
                } else {
                    alert('خطأ: ' + data.message);
                }
            } catch (error) {
                console.error('Error adding holiday:', error);
                alert('فشل في إضافة العطلة.');
            }
        },

        async deleteHoliday(id) {
            if (!confirm('هل أنت متأكد من حذف هذه العطلة؟')) return;
            const formData = new FormData();
            formData.append('id', id);

            try {
                const response = await fetch('holidays_api.php?action=delete', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    alert('تم حذف العطلة.');
                    this.fetchHolidays();
                } else {
                    alert('خطأ: ' + data.message);
                }
            } catch (error) {
                console.error('Error deleting holiday:', error);
                alert('فشل في حذف العطلة.');
            }
        },

        async saveWeeklyOffDays() {
            const formData = new FormData();
            formData.append('days', this.weeklyOffDays.join(','));
            try {
                const response = await fetch('holidays_api.php?action=update_weekly_offs', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    alert('تم تحديث أيام الإجازة الأسبوعية بنجاح.');
                } else {
                    alert('خطأ: ' + data.message);
                }
            } catch (error) {
                console.error('Error saving weekly off days:', error);
                alert('فشل في حفظ أيام الإجازة.');
            }
        }
    };
}
</script>

<?php
 $page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>