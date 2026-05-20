<?php

/**
 * Standalone Calendar Implementation for Appointments
 * Simplified version with working FullCalendar integration
 */

require_once 'auth.php';
require_once '../config/database.php';

checkAdminAuth();
$admin = getCurrentAdmin();

$database = new Database();
$db = $database->getConnection();

// Get specialists for filter
$specialists_query = "SELECT id, name_ar FROM team_members WHERE is_active = 1 ORDER BY name_ar";
$specialists_stmt = $db->prepare($specialists_query);
$specialists_stmt->execute();
$specialists = $specialists_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get services for appointment creation
$services_query = "SELECT id, title_ar, price, duration_min FROM services WHERE is_active = 1 ORDER BY title_ar";
$services_stmt = $db->prepare($services_query);
$services_stmt->execute();
$services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);


// Layout configuration
$page_title = 'تقويم المواعيد';
$page_subtitle = 'إدارة وعرض البيانات';
$page_actions = '';

// Get current admin info
$admin = [
    'id' => $_SESSION['admin_id'] ?? 1,
    'username' => $_SESSION['admin_username'] ?? 'admin'
];

ob_start();
?>


    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4 space-x-reverse">
                    <h1 class="text-2xl font-bold text-gray-900">تقويم المواعيد</h1>
                    <div class="flex items-center space-x-2 space-x-reverse text-sm">
                        <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs">معلق</span>
                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">مؤكد</span>
                        <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs">منجز</span>
                        <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs">لم يحضر</span>
                        <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs">ملغي</span>
                    </div>
                </div>
                <div class="flex items-center space-x-3 space-x-reverse">
                    <button @click="showCreateModal = true" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-plus ml-2"></i>موعد جديد
                    </button>
                    <button @click="refreshCalendar()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                        <i class="fas fa-sync-alt ml-2"></i>تحديث
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4 space-x-reverse">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الأخصائي</label>
                        <select x-model="selectedSpecialist" @change="filterAppointments()" class="border border-gray-300 rounded-lg px-3 py-2">
                            <option value="">جميع الأخصائيين</option>
                            <?php foreach ($specialists as $specialist): ?>
                            <option value="<?php echo $specialist['id']; ?>"><?php echo htmlspecialchars($specialist['name_ar']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الحالة</label>
                        <select x-model="selectedStatus" @change="filterAppointments()" class="border border-gray-300 rounded-lg px-3 py-2">
                            <option value="">جميع الحالات</option>
                            <option value="pending">معلق</option>
                            <option value="confirmed">مؤكد</option>
                            <option value="done">منجز</option>
                            <option value="no_show">لم يحضر</option>
                            <option value="cancelled">ملغي</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center space-x-2 space-x-reverse">
                    <button @click="changeView('dayGridMonth')" 
                            :class="currentView === 'dayGridMonth' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                            class="px-3 py-2 rounded text-sm">شهر</button>
                    <button @click="changeView('timeGridWeek')" 
                            :class="currentView === 'timeGridWeek' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                            class="px-3 py-2 rounded text-sm">أسبوع</button>
                    <button @click="changeView('timeGridDay')" 
                            :class="currentView === 'timeGridDay' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                            class="px-3 py-2 rounded text-sm">يوم</button>
                </div>
            </div>
        </div>

        <!-- Calendar -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div id="calendar" style="min-height: 600px;"></div>
        </div>
    </div>

    <!-- Appointment Details Modal -->
    <div x-show="selectedAppointment" x-cloak class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">تفاصيل الموعد</h3>
                <button @click="selectedAppointment = null" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <template x-if="selectedAppointment">
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-600">العميل</label>
                        <p class="text-gray-900" x-text="selectedAppointment.customer_name"></p>
                        <p class="text-sm text-gray-600" x-text="selectedAppointment.customer_phone"></p>
                    </div>
                    
                    <div>
                        <label class="text-sm font-medium text-gray-600">الخدمة</label>
                        <p class="text-gray-900" x-text="selectedAppointment.service_name"></p>
                    </div>
                    
                    <div>
                        <label class="text-sm font-medium text-gray-600">الأخصائي</label>
                        <p class="text-gray-900" x-text="selectedAppointment.specialist_name"></p>
                    </div>
                    
                    <div>
                        <label class="text-sm font-medium text-gray-600">التوقيت</label>
                        <p class="text-gray-900" x-text="formatDateTime(selectedAppointment.start_at)"></p>
                    </div>
                    
                    <div>
                        <label class="text-sm font-medium text-gray-600">الحالة</label>
                        <span :class="getStatusClass(selectedAppointment.status)" 
                              class="inline-block px-2 py-1 rounded text-sm" 
                              x-text="getStatusText(selectedAppointment.status)"></span>
                    </div>
                    
                    <div x-show="selectedAppointment.notes">
                        <label class="text-sm font-medium text-gray-600">ملاحظات</label>
                        <p class="text-gray-900" x-text="selectedAppointment.notes"></p>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <script>
        function calendarApp() {
            return {
                calendar: null,
                currentView: 'timeGridWeek',
                selectedSpecialist: '',
                selectedStatus: '',
                selectedAppointment: null,
                showCreateModal: false,

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
                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: ''
                        },
                        slotMinTime: '08:00:00',
                        slotMaxTime: '22:00:00',
                        allDaySlot: false,
                        height: 600,
                        eventClick: (info) => {
                            this.selectAppointment(info.event.extendedProps);
                        },
                        eventDidMount: (info) => {
                            // Add status class to event element
                            info.el.classList.add(info.event.extendedProps.status);
                        },
                        editable: false, // Disable drag for now
                        eventStartEditable: false,
                        eventDurationEditable: false
                    });
                    
                    this.calendar.render();
                },

                async loadAppointments() {
                    try {
                        const params = new URLSearchParams();
                        params.append('from', this.getDateRange().from);
                        params.append('to', this.getDateRange().to);
                        
                        if (this.selectedSpecialist) {
                            params.append('specialist', this.selectedSpecialist);
                        }
                        if (this.selectedStatus) {
                            params.append('status', this.selectedStatus);
                        }
                        
                        const response = await fetch(`appointments_api.php?${params}`);
                        const result = await response.json();
                        
                        if (result.success) {
                            this.calendar.removeAllEvents();
                            
                            result.data.forEach(appointment => {
                                this.calendar.addEvent({
                                    id: appointment.id,
                                    title: `${appointment.customer_name} - ${appointment.service_name}`,
                                    start: appointment.start_at,
                                    end: appointment.end_at,
                                    extendedProps: appointment
                                });
                            });
                        } else {
                            console.error('Failed to load appointments:', result.message);
                        }
                    } catch (error) {
                        console.error('Error loading appointments:', error);
                    }
                },

                getDateRange() {
                    const view = this.calendar.view;
                    return {
                        from: view.activeStart.toISOString().split('T')[0],
                        to: view.activeEnd.toISOString().split('T')[0]
                    };
                },

                selectAppointment(appointment) {
                    this.selectedAppointment = appointment;
                },

                changeView(viewName) {
                    this.currentView = viewName;
                    this.calendar.changeView(viewName);
                },

                filterAppointments() {
                    this.loadAppointments();
                },

                refreshCalendar() {
                    this.loadAppointments();
                },

                formatDateTime(datetime) {
                    const date = new Date(datetime);
                    return date.toLocaleString('ar-SA', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                },

                getStatusText(status) {
                    const statusMap = {
                        'pending': 'معلق',
                        'confirmed': 'مؤكد',
                        'done': 'منجز',
                        'no_show': 'لم يحضر',
                        'cancelled': 'ملغي'
                    };
                    return statusMap[status] || status;
                },

                getStatusClass(status) {
                    const classMap = {
                        'pending': 'bg-yellow-100 text-yellow-800',
                        'confirmed': 'bg-green-100 text-green-800',
                        'done': 'bg-gray-100 text-gray-800',
                        'no_show': 'bg-red-100 text-red-800',
                        'cancelled': 'bg-gray-100 text-gray-600'
                    };
                    return classMap[status] || 'bg-gray-100 text-gray-800';
                }
            }
        }
    </script>


<?php
$page_content = ob_get_clean();
include 'includes/admin_layout.php';
?>