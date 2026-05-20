<?php
/**
 * Appointments Management Interface (Corrected Version)
 */

require_once 'auth.php';
checkAdminAuth();

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get specialists for the filter and form
$specialists_stmt = $db->prepare("SELECT id, name_ar FROM team_members WHERE is_specialist = 1 AND is_active = 1 ORDER BY name_ar");
$specialists_stmt->execute();
$specialists = $specialists_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get services for the form
$services_stmt = $db->prepare("SELECT id, name_ar, price, service_duration FROM services WHERE is_active = 1 ORDER BY name_ar");
$services_stmt->execute();
$services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get appointment statistics
$stats_stmt = $db->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done
FROM appointments WHERE DATE(start_at) >= CURDATE() - INTERVAL 30 DAY");
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Layout configuration
$page_title = 'إدارة المواعيد';
ob_start();
?>

<div x-data="appointmentsApp()" x-init="init()">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow mb-6 p-4">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-4">
                 <div class="w-12 h-12 bg-mai-brown rounded-lg flex items-center justify-center text-white text-xl"><i class="fas fa-calendar-alt"></i></div>
                <div>
                    <h1 class="text-2xl font-bold text-mai-dark">إدارة المواعيد</h1>
                    <div class="flex items-center gap-2 mt-1 text-sm">
                        <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full">معلق: <?php echo $stats['pending'] ?? 0; ?></span>
                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full">مؤكد: <?php echo $stats['confirmed'] ?? 0; ?></span>
                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full">منجز: <?php echo $stats['done'] ?? 0; ?></span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button @click="showCreateModal = true" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-semibold">
                    <i class="fas fa-plus ml-2"></i>موعد جديد
                </button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Calendar Section -->
        <div class="lg:col-span-3 bg-white rounded-xl shadow p-4">
            <!-- Calendar Header/Controls -->
             <div class="flex justify-between items-center mb-4 px-2">
                <div class="flex items-center gap-2">
                    <button @click="calendar.prev()" class="w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center"><i class="fas fa-chevron-right"></i></button>
                    <button @click="calendar.next()" class="w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center"><i class="fas fa-chevron-left"></i></button>
                    <h2 class="text-lg font-bold text-gray-800" x-text="calendarTitle"></h2>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <button @click="calendar.changeView('timeGridDay')" :class="{'bg-blue-600 text-white': calendarView === 'timeGridDay'}" class="px-3 py-1.5 rounded-lg hover:bg-blue-500 hover:text-white">يوم</button>
                    <button @click="calendar.changeView('timeGridWeek')" :class="{'bg-blue-600 text-white': calendarView === 'timeGridWeek'}" class="px-3 py-1.5 rounded-lg hover:bg-blue-500 hover:text-white">أسبوع</button>
                    <button @click="calendar.changeView('dayGridMonth')" :class="{'bg-blue-600 text-white': calendarView === 'dayGridMonth'}" class="px-3 py-1.5 rounded-lg hover:bg-blue-500 hover:text-white">شهر</button>
                </div>
            </div>
            <div id="calendar"></div>
        </div>

        <!-- Sidebar Section -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Filters -->
            <div class="bg-white rounded-xl shadow p-4 space-y-4">
                <h3 class="text-lg font-bold text-mai-dark border-b pb-2">فلترة العرض</h3>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الأخصائي</label>
                    <select x-model="selectedSpecialist" @change="loadAppointments()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">جميع الأخصائيين</option>
                        <?php foreach ($specialists as $specialist): ?>
                        <option value="<?php echo $specialist['id']; ?>"><?php echo htmlspecialchars($specialist['name_ar']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الحالة</label>
                    <select x-model="selectedStatus" @change="loadAppointments()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">جميع الحالات</option>
                        <option value="pending">معلق</option>
                        <option value="confirmed">مؤكد</option>
                        <option value="done">منجز</option>
                        <option value="no_show">لم يحضر</option>
                        <option value="cancelled">ملغي</option>
                    </select>
                </div>
            </div>
            
            <!-- Appointment Details -->
            <div x-show="selectedAppointment" x-transition class="bg-white rounded-xl shadow p-4">
                <div class="flex items-center justify-between mb-4 border-b pb-2">
                    <h3 class="text-lg font-semibold">تفاصيل الموعد</h3>
                    <button @click="selectedAppointment = null" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
                </div>
                <template x-if="selectedAppointment">
                    <div class="space-y-4 text-sm">
                        <div><strong class="block text-gray-500">العميل:</strong> <span class="text-gray-900" x-text="selectedAppointment.extendedProps.customer_name"></span> (<span x-text="selectedAppointment.extendedProps.customer_phone"></span>)</div>
                        <div><strong class="block text-gray-500">الخدمة:</strong> <span class="text-gray-900" x-text="selectedAppointment.extendedProps.service_name"></span></div>
                        <div><strong class="block text-gray-500">الأخصائي:</strong> <span class="text-gray-900" x-text="selectedAppointment.extendedProps.specialist_name"></span></div>
                        <div><strong class="block text-gray-500">التوقيت:</strong> <span class="text-gray-900" x-text="formatDateTime(selectedAppointment.start)"></span></div>
                        <div><strong class="block text-gray-500">الحالة:</strong> <span :class="getStatusClass(selectedAppointment.extendedProps.status)" class="px-2 py-1 rounded-full text-xs font-semibold" x-text="getStatusText(selectedAppointment.extendedProps.status)"></span></div>
                        <div x-show="selectedAppointment.extendedProps.notes"><strong class="block text-gray-500">ملاحظات:</strong> <p class="text-gray-800 whitespace-pre-wrap" x-text="selectedAppointment.extendedProps.notes"></p></div>
                        
                        <div class="flex flex-col space-y-2 pt-4 border-t">
                            <button x-show="selectedAppointment.extendedProps.status === 'pending'" @click="updateAppointmentStatus(selectedAppointment.id, 'confirmed')" class="w-full text-center bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">تأكيد الموعد</button>
                            <button x-show="selectedAppointment.extendedProps.status === 'confirmed'" @click="updateAppointmentStatus(selectedAppointment.id, 'done')" class="w-full text-center bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">تم الانتهاء</button>
                            <button x-show="['pending', 'confirmed'].includes(selectedAppointment.extendedProps.status)" @click="updateAppointmentStatus(selectedAppointment.id, 'cancelled')" class="w-full text-center bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">إلغاء الموعد</button>
                            <button @click="editAppointment(selectedAppointment.extendedProps)" class="w-full text-center bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">تعديل الموعد</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Create/Edit Appointment Modal -->
    <div x-cloak x-show="showCreateModal" @keydown.escape.window="closeCreateModal()" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold" x-text="editingAppointment ? 'تعديل الموعد' : 'موعد جديد'"></h3>
                <button @click="closeCreateModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>

            <form @submit.prevent="saveAppointment()">
                <div class="space-y-4">
                    <!-- CORRECTED: Customer Search Functionality Added -->
                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700 mb-1">ابحث برقم الجوال أو الاسم</label>
                        <input type="text" placeholder="e.g., 05xxxxxxxx" x-model="customerSearch" @input.debounce.300ms="searchCustomers()" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <div x-show="customerSearchResults.length > 0" @click.away="customerSearchResults = []" class="absolute z-10 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-40 overflow-y-auto">
                            <ul>
                                <template x-for="customer in customerSearchResults" :key="customer.id">
                                    <li @click="selectCustomer(customer)" class="p-2 cursor-pointer hover:bg-gray-100">
                                        <p class="font-semibold text-sm" x-text="customer.name_ar"></p>
                                        <p class="text-xs text-gray-600" x-text="customer.phone"></p>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">اسم العميل</label>
                            <input type="text" x-model="appointmentForm.customer_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                            <input type="tel" x-model="appointmentForm.customer_phone" required class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-50">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الخدمة</label>
                        <select x-model="appointmentForm.service_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                            <option value="">اختر الخدمة</option>
                            <?php foreach ($services as $service): ?>
                            <option value="<?php echo $service['id']; ?>"><?php echo htmlspecialchars($service['name_ar']); ?> - <?php echo $service['price']; ?> ر.س</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الأخصائي</label>
                        <select x-model="appointmentForm.specialist_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                            <option value="">اختر الأخصائي</option>
                            <?php foreach ($specialists as $specialist): ?>
                            <option value="<?php echo $specialist['id']; ?>"><?php echo htmlspecialchars($specialist['name_ar']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">التاريخ</label>
                            <!-- CORRECTED: min attribute prevents booking before tomorrow -->
                            <input type="date" x-model="appointmentForm.date" required :min="getTomorrowDate()" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">الوقت</label>
                            <input type="time" x-model="appointmentForm.time" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
                        <textarea x-model="appointmentForm.notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" @click="closeCreateModal()" class="px-4 py-2 text-gray-600 border rounded-lg hover:bg-gray-50">إلغاء</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"><span x-text="editingAppointment ? 'تحديث' : 'حفظ'"></span></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function appointmentsApp() {
    return {
        calendar: null,
        calendarView: 'timeGridWeek',
        calendarTitle: '',
        selectedSpecialist: '',
        selectedStatus: '',
        selectedAppointment: null,
        showCreateModal: false,
        editingAppointment: null,
        appointmentForm: { id: null, customer_id: null, customer_name: '', customer_phone: '', service_id: '', specialist_id: '', date: '', time: '', notes: '' },
        customerSearch: '',
        customerSearchResults: [],
        services: <?php echo json_encode($services); ?>,

        init() {
            this.initCalendar();
            this.loadAppointments();
        },

        initCalendar() {
            const calendarEl = document.getElementById('calendar');
            this.calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'timeGridWeek',
                locale: 'ar',
                direction: 'rtl',
                headerToolbar: false,
                slotMinTime: '08:00:00',
                slotMaxTime: '23:00:00',
                allDaySlot: false,
                height: 'auto',
                editable: true,
                eventClick: (info) => { this.selectedAppointment = info.event; },
                eventDrop: (info) => { this.moveAppointment(info.event.id, info.event.start); },
                datesSet: (view) => {
                    this.calendarTitle = view.view.title;
                    this.calendarView = view.view.type;
                }
            });
            this.calendar.render();
        },

        async loadAppointments() {
            try {
                const params = new URLSearchParams({ specialist: this.selectedSpecialist, status: this.selectedStatus });
                const response = await fetch(`appointments_api.php?${params}`);
                const result = await response.json();
                if (result.success) {
                    this.calendar.removeAllEvents();
                    this.calendar.addEventSource(result.data);
                } else { throw new Error(result.message); }
            } catch (error) {
                alert('خطأ في تحميل المواعيد: ' + error.message);
            }
        },

        async searchCustomers() {
            if (this.customerSearch.trim().length < 2) {
                this.customerSearchResults = [];
                return;
            }
            try {
                const response = await fetch(`appointments_api.php?action=search_customers&term=${this.customerSearch}`);
                const result = await response.json();
                if (result.success) this.customerSearchResults = result.data;
            } catch (error) {
                console.error('Customer search failed:', error);
            }
        },

        selectCustomer(customer) {
            this.appointmentForm.customer_id = customer.id;
            this.appointmentForm.customer_name = customer.name_ar;
            this.appointmentForm.customer_phone = customer.phone;
            this.customerSearch = '';
            this.customerSearchResults = [];
        },

        async updateAppointmentStatus(appointmentId, newStatus) {
            if (!confirm('هل أنت متأكد من تغيير حالة الموعد؟')) return;
            try {
                const response = await fetch(`appointments_api.php`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: appointmentId, status: newStatus })
                });
                const result = await response.json();
                if (result.success) {
                    this.loadAppointments();
                    this.selectedAppointment = null;
                } else { throw new Error(result.message); }
            } catch (error) {
                alert('خطأ في تحديث حالة الموعد: ' + error.message);
            }
        },

        editAppointment(appointment) {
            this.editingAppointment = appointment;
            this.appointmentForm = {
                id: appointment.id,
                customer_id: appointment.customer_id,
                customer_name: appointment.customer_name,
                customer_phone: appointment.customer_phone,
                service_id: appointment.service_id,
                specialist_id: appointment.specialist_id,
                date: appointment.start_at.split(' ')[0],
                time: appointment.start_at.split(' ')[1].substring(0, 5),
                notes: appointment.notes || ''
            };
            this.showCreateModal = true;
        },

        async saveAppointment() {
            try {
                const service = this.services.find(s => s.id == this.appointmentForm.service_id);
                const appointmentData = {
                    ...this.appointmentForm,
                    start_at: `${this.appointmentForm.date} ${this.appointmentForm.time}:00`,
                    service_duration: service ? service.service_duration : 60
                };
                
                const response = await fetch(`appointments_api.php`, {
                    method: this.editingAppointment ? 'PATCH' : 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(appointmentData)
                });
                
                const result = await response.json();
                if (result.success) {
                    this.closeCreateModal();
                    this.loadAppointments();
                    alert(result.message);
                } else { throw new Error(result.message); }
            } catch (error) {
                alert('خطأ في حفظ الموعد: ' + error.message);
            }
        },
        
        async moveAppointment(appointmentId, newStart) {
            try {
                const response = await fetch(`appointments_api.php`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: appointmentId, start_at: newStart.toISOString() })
                });
                const result = await response.json();
                if (!result.success) { 
                    alert('فشل نقل الموعد: ' + result.message);
                    this.loadAppointments(); // Revert on failure
                }
            } catch (error) {
                alert('خطأ فني أثناء نقل الموعد.');
                this.loadAppointments(); // Revert on error
            }
        },

        closeCreateModal() {
            this.showCreateModal = false;
            this.editingAppointment = null;
            this.customerSearch = '';
            this.customerSearchResults = [];
            this.appointmentForm = { id: null, customer_id: null, customer_name: '', customer_phone: '', service_id: '', specialist_id: '', date: '', time: '', notes: '' };
        },

        // CORRECTED: Date formatting is now explicitly Gregorian
        formatDateTime(datetime) {
            const date = new Date(datetime);
            const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true, timeZone: 'Asia/Riyadh' };
            // Use 'en-US' locale for guaranteed Gregorian numbers, but the format is universal.
            return new Intl.DateTimeFormat('en-US', options).format(date);
        },

        getStatusText(status) { return { 'pending': 'معلق', 'confirmed': 'مؤكد', 'done': 'منجز', 'no_show': 'لم يحضر', 'cancelled': 'ملغي' }[status] || status; },
        getStatusClass(status) { return { 'pending': 'bg-yellow-100 text-yellow-800', 'confirmed': 'bg-green-100 text-green-800', 'done': 'bg-blue-100 text-blue-800', 'no_show': 'bg-red-100 text-red-800', 'cancelled': 'bg-gray-200 text-gray-600' }[status] || ''; },
        
        getTomorrowDate() {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            return tomorrow.toISOString().split('T')[0];
        }
    }
}
</script>
<style>
    .fc-event { padding: 5px; font-size: 12px; cursor: pointer; }
    .fc-direction-rtl .fc-list-event-time { float: right; }
</style>

<?php
$page_content = ob_get_clean();
include 'layouts/responsive_base.php';
?>