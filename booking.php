<?php
// --- START OF PHP LOGIC ---
require_once 'config/database.php';

// Initialize database connection
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Throwable $e) {
    $db = null;
    die("Database connection failed: " . $e->getMessage());
}

// Default values
$categories = [];

// Calculate tomorrow's date to disable same-day booking
$tomorrow = new DateTime('tomorrow');
$min_booking_date = $tomorrow->format('Y-m-d');


// Get Categories
if ($db) {
    try {
        $categories_query = "SELECT id, name_ar FROM service_categories WHERE is_active = 1 ORDER BY sort_order ASC";
        $categories_stmt = $db->prepare($categories_query);
        $categories_stmt->execute();
        $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {
        // In case of an error, keep default values
        $categories = [];
    }
}

// Site title
$site_title = "حجز موعد جديد - صالون MAI";
// --- END OF PHP LOGIC ---
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_title; ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'sans': ['Tajawal', 'sans-serif'],
                    },
                    colors: {
                        'primary': '#6B4F4B',
                        'accent': '#f5deb3',
                        'background': '#FFFFFF',
                        'text-main': '#6B4F4B',
                        'text-secondary': '#8D7B78'
                    }
                }
            }
        }
    </script>

    <style>
        body { font-family: 'Tajawal', sans-serif; }
        .cta-button { transition: all 0.3s ease; }
        .cta-button:hover { transform: translateY(-3px); box-shadow: 0 10px 20px -5px rgba(107, 79, 75, 0.25); }
        .form-input:focus, .form-select:focus { border-color: #6B4F4B; outline: none; box-shadow: 0 0 0 3px rgba(107, 79, 75, 0.2); }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-accent" x-data="bookingApp()" x-init="AOS.init({ duration: 800, once: true })">
    <div class="min-h-screen flex flex-col items-center justify-center p-4">

        <div class="w-full max-w-3xl bg-white rounded-2xl shadow-2xl overflow-hidden" data-aos="fade-up">
            <div class="p-8">
                <!-- Header -->
                <div class="text-center mb-8">
                    <a href="index.php" class="inline-block mb-4 text-primary hover:underline"><i class="fas fa-arrow-right ml-1"></i> العودة للرئيسية</a>
                    <h1 class="text-3xl font-bold text-primary">حجز موعد</h1>
                    <p class="text-text-secondary mt-2">املئي الحقول التالية لإتمام حجزك معنا</p>
                </div>
                
                <!-- Step Indicator -->
                <div class="w-full px-4 sm:px-8 mb-10">
                    <div class="relative flex items-center justify-between">
                        <div class="absolute left-0 top-1/2 -translate-y-1/2 w-full h-1 bg-gray-200">
                             <div class="h-full bg-primary transition-all duration-500" :style="`width: ${step === 1 ? '0%' : (step === 2 ? '50%' : '100%')}`"></div>
                        </div>
                        <div class="relative flex flex-col items-center">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300" :class="step >= 1 ? 'bg-primary text-white' : 'bg-white border-2 border-gray-300 text-gray-400'">1</div>
                            <span class="mt-2 text-xs font-bold" :class="step >= 1 ? 'text-primary' : 'text-gray-400'">الاختيار</span>
                        </div>
                        <div class="relative flex flex-col items-center">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300" :class="step >= 2 ? 'bg-primary text-white' : 'bg-white border-2 border-gray-300 text-gray-400'">2</div>
                            <span class="mt-2 text-xs font-bold" :class="step >= 2 ? 'text-primary' : 'text-gray-400'">الوقت والمعلومات</span>
                        </div>
                        <div class="relative flex flex-col items-center">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300" :class="step >= 3 ? 'bg-primary text-white' : 'bg-white border-2 border-gray-300 text-gray-400'">3</div>
                            <span class="mt-2 text-xs font-bold" :class="step >= 3 ? 'text-primary' : 'text-gray-400'">التأكيد</span>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <!-- Step 1: Selection (Category, Service, Specialist, Date) -->
                    <div x-show="step === 1" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                        <div x-show="loading" class="text-center py-10"><i class="fas fa-spinner fa-spin text-primary text-3xl"></i></div>
                        
                        <div x-show="!loading">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Service Category -->
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">فئة الخدمة</label>
                                    <select x-model="selectedCategory" @change="handleCategoryChange()" class="form-select w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50">
                                        <option value="">-- اختاري الفئة أولاً --</option>
                                        <?php foreach ($categories as $category): ?><option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name_ar']); ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Service -->
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">الخدمة</label>
                                    <select x-model="selectedServiceId" @change="handleServiceChange()" class="form-select w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50" :disabled="!selectedCategory || services.length === 0">
                                        <option value="">-- اختر خدمة --</option>
                                        <template x-for="service in services" :key="service.id">
                                            <option :value="service.id" x-text="service.name_ar + ' - ' + (service.min_price > 0 ? `يبدأ من ${service.min_price}` : service.price) + ' ريال'"></option>
                                        </template>
                                    </select>
                                </div>

                                <!-- Specialist -->
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">الأخصائية</label>
                                    <select x-model="selectedSpecialist" @change="validateStep1()" class="form-select w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50" :disabled="!selectedServiceId || specialists.length === 0">
                                        <option value="">-- اختر أخصائية --</option>
                                        <template x-for="specialist in specialists" :key="specialist.id"><option :value="specialist.id" x-text="specialist.name_ar"></option></template>
                                    </select>
                                     <p x-show="!loading && selectedServiceId && specialists.length === 0" class="text-xs text-gray-500 mt-1">لا توجد أخصائيات متاحات لهذه الخدمة.</p>
                                </div>

                                <!-- Date -->
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">التاريخ</label>
                                    <input type="date" x-model="selectedDate" @change="validateStep1()" min="<?php echo $min_booking_date; ?>" class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50" :disabled="!selectedSpecialist">
                                    <p class="mt-1 text-sm text-red-600 h-4" x-show="errors.date" x-text="errors.date"></p>
                                </div>
                            </div>
                            
                            <!-- Variable Price Note -->
                            <div x-show="serviceHasPriceRange" x-cloak x-transition class="mt-6 p-4 bg-yellow-100 border-r-4 border-yellow-500 text-yellow-800">
                                <p class="font-bold">ملاحظة هامة بخصوص السعر</p>
                                <p class="text-sm">يرجى ملاحظة أن السعر يتراوح من <strong x-text="selectedService?.min_price"></strong> إلى <strong x-text="selectedService?.max_price"></strong> ريال، ويختلف بحسب الحالة، ويتم تحديد السعر النهائي بعد تقييم الأخصائية قبل بدء الخدمة.</p>
                            </div>

                            <p class="text-sm text-red-600 h-4 mt-2" x-show="errors.step1" x-text="errors.step1"></p>
                            
                             <div x-show="selectedServiceId" class="mt-6 p-4 bg-gray-100 rounded-lg">
                                <div class="flex justify-between items-center">
                                    <span class="text-lg font-bold text-gray-700">المجموع المبدئي:</span>
                                    <span class="text-2xl font-bold text-primary" x-text="totalPrice + ' ريال'"></span>
                                </div>
                            </div>

                            <div class="flex justify-end mt-8">
                                <button @click="nextStep()" :disabled="!isStep1Valid" class="cta-button bg-primary text-white px-10 py-3 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed font-bold">التالي</button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 2: Time and Info -->
                    <div x-show="step === 2" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-gray-700 mb-2">الوقت المتاح</label>
                                <div class="relative">
                                    <select x-model="selectedTime" @change="validateStep2()" :disabled="isLoadingTimes || availableTimes.length === 0" class="form-select w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 disabled:bg-gray-200">
                                        <option value="">-- اختر الوقت --</option>
                                        <template x-for="time in availableTimes" :key="time.value">
                                            <option :value="time.value" x-text="time.label"></option>
                                        </template>
                                    </select>
                                    <i x-show="isLoadingTimes" class="fas fa-spinner fa-spin absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                </div>
                                <p class="mt-1 text-sm text-red-600 h-4" x-show="errors.time" x-text="errors.time"></p>
                                <p x-show="!isLoadingTimes && selectedDate && availableTimes.length === 0" class="text-xs text-gray-500 mt-1">لا توجد أوقات متاحة في هذا اليوم. يرجى تغيير التاريخ في الخطوة السابقة.</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">رقم الجوال</label>
                                <input type="tel" x-model="customerPhone" @input.debounce.500ms="handleCustomerSearch()" placeholder="05xxxxxxxx" class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-left" dir="ltr">
                                <p class="mt-1 text-sm text-red-600 h-4" x-show="errors.phone" x-text="errors.phone"></p>
                                <!-- START: EDITED CODE -->
                                <p x-show="showNewCustomerNote" x-cloak x-transition class="mt-1 text-sm text-blue-600">
                                    <i class="fas fa-info-circle mr-1"></i> عميل جديد، سيتم إنشاء حساب بهذا الرقم.
                                </p>
                                <!-- END: EDITED CODE -->
                            </div>

                            <div class="relative">
                                <label class="block text-sm font-bold text-gray-700 mb-2">الاسم الكامل</label>
                                <div class="relative">
                                    <!-- START: EDITED CODE (added @input) -->
                                    <input type="text" x-model="customerName" @input="validateStep2()" placeholder="سيتم ملؤه تلقائياً أو أدخل اسم جديد" class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50" :readonly="customerFound" :class="{ 'bg-gray-200 text-gray-500 cursor-not-allowed': customerFound }">
                                    <!-- END: EDITED CODE -->
                                    <i x-show="isSearchingCustomer" class="fas fa-spinner fa-spin absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                </div>
                                <p class="mt-1 text-sm text-red-600 h-4" x-show="errors.name" x-text="errors.name"></p>
                            </div>
                        </div>
                        <div class="flex justify-between mt-8">
                            <button @click="step = 1" class="cta-button bg-gray-200 text-gray-700 px-10 py-3 rounded-lg font-bold">السابق</button>
                            <button @click="nextStep()" :disabled="!isStep2Valid" class="cta-button bg-primary text-white px-10 py-3 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed font-bold">التالي</button>
                        </div>
                    </div>
                    
                    <!-- Step 3: Confirmation -->
                    <div x-show="step === 3" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
                        <h3 class="text-xl font-bold text-primary mb-6 text-center">مراجعة تفاصيل الحجز</h3>
                        <div class="bg-accent/30 rounded-lg p-6 mb-6 space-y-4">
                            <div class="flex justify-between"><span class="text-gray-600">الخدمة:</span><span class="font-bold text-primary" x-text="serviceName"></span></div>
                            <div class="flex justify-between border-t border-primary/20 pt-3"><span class="text-gray-600">الأخصائية:</span><span class="font-bold text-primary" x-text="specialistName"></span></div>
                            <div class="flex justify-between"><span class="text-gray-600">التاريخ والوقت:</span><span class="font-bold text-primary" x-text="formattedDateTime"></span></div>
                            <div class="flex justify-between border-t border-primary/20 pt-3 mt-3"><span class="text-lg font-bold">المجموع المبدئي:</span><span class="font-bold text-primary text-xl" x-text="totalPrice + ' ريال'"></span></div>
                             <div x-show="serviceHasPriceRange" class="text-center text-xs text-gray-600 pt-2">السعر النهائي يحدد من قبل الأخصائية.</div>
                        </div>
                        <p class="mt-4 text-center text-sm text-red-600" x-show="errors.submit" x-text="errors.submit"></p>
                        <div class="flex justify-between mt-8">
                            <button @click="step = 2" class="cta-button bg-gray-200 text-gray-700 px-10 py-3 rounded-lg font-bold">السابق</button>
                            <button @click="submitBooking()" :disabled="isSubmitting" class="cta-button bg-primary text-white px-10 py-3 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed font-bold">
                                <span x-show="!isSubmitting">تأكيد الحجز</span>
                                <span x-show="isSubmitting"><i class="fas fa-spinner fa-spin mr-2"></i>جاري التأكيد...</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Success Message -->
                    <div x-show="step === 4" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="text-center py-8">
                         <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-5"><i class="fas fa-check text-green-600 text-4xl"></i></div>
                        <h3 class="text-2xl font-bold text-primary mb-2">تم الحجز بنجاح!</h3>
                        <p class="text-text-secondary mb-6">شكراً لاختيارك صالون MAI، سيتم التواصل معكِ قريباً للتأكيد.</p>
                        <div class="bg-accent/30 rounded-lg p-6 mb-6 text-right space-y-3">
                            <div class="flex justify-between"><span class="text-gray-600">رقم الحجز:</span><span class="font-bold text-primary" x-text="bookingReference"></span></div>
                            <div class="flex justify-between"><span class="text-gray-600">الخدمة:</span><span class="font-bold text-primary max-w-[60%] truncate" x-text="serviceName"></span></div>
                            <div class="flex justify-between"><span class="text-gray-600">المبلغ المبدئي:</span><span class="font-bold text-primary" x-text="totalPrice + ' ريال'"></span></div>
                        </div>
                        <a href="index.php" class="cta-button inline-block bg-primary text-white px-8 py-3 rounded-lg font-bold">العودة للرئيسية</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
function bookingApp() {
    return {
        step: 1,
        loading: false,
        isSubmitting: false,
        
        // Step 1 models
        selectedCategory: '',
        selectedServiceId: '',
        selectedSpecialist: '',
        selectedDate: '',
        
        // Step 2 models
        selectedTime: '',
        customerName: '',
        customerPhone: '',
        customerFound: false,
        showNewCustomerNote: false, // <-- START: EDITED CODE
        
        // Data stores
        services: [],
        specialists: [],
        availableTimes: [],
        holidays: [],
        weeklyOffDays: [],
        
        // UI state
        isLoadingTimes: false,
        isSearchingCustomer: false,
        bookingReference: '',

        errors: { step1: '', date: '', time: '', phone: '', name: '', submit: '' },
        isStep1Valid: false,
        isStep2Valid: false,

        init() {
            this.loadDisabledDates();
        },

        // Computed properties
        get selectedService() {
            if (!this.selectedServiceId) return null;
            return this.services.find(s => s.id == this.selectedServiceId) || null;
        },
        get serviceHasPriceRange() {
            return this.selectedService && parseFloat(this.selectedService.min_price) > 0;
        },
        get totalPrice() {
            if (!this.selectedService) return 0;
            return parseFloat(this.selectedService.min_price || this.selectedService.price || 0);
        },
        get serviceName() {
            return this.selectedService ? this.selectedService.name_ar : '';
        },
        get specialistName() {
            if (!this.selectedSpecialist) return '';
            const specialist = this.specialists.find(s => s.id == this.selectedSpecialist);
            return specialist ? specialist.name_ar : '';
        },
        get formattedDateTime() {
            if (!this.selectedDate || !this.selectedTime) return '';
            const date = new Date(this.selectedDate + 'T00:00:00');
            const dateStr = date.toLocaleDateString('ar-EG', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            const timeObj = this.availableTimes.find(t => t.value === this.selectedTime);
            return `${dateStr} - ${timeObj ? timeObj.label : ''}`;
        },

        // Methods
        async loadDisabledDates() {
            try {
                const response = await fetch('api/public_booking_api.php?action=get_disabled_dates');
                const data = await response.json();
                if (data.success) {
                    this.holidays = data.holidays;
                    this.weeklyOffDays = data.weeklyOffDays.map(d => parseInt(d, 10));
                }
            } catch (error) { console.error('Error loading disabled dates:', error); }
        },

        isDateDisabled(dateString) {
            if (!dateString) return false;
            if (this.holidays.includes(dateString)) return true;
            const date = new Date(dateString);
            const dayOfWeek = date.getUTCDay(); // Sunday = 0, Monday = 1, etc.
            return this.weeklyOffDays.includes(dayOfWeek);
        },

        async handleCategoryChange() {
            this.services = []; this.specialists = [];
            this.selectedServiceId = ''; this.selectedSpecialist = ''; this.selectedDate = '';
            this.validateStep1();

            if (!this.selectedCategory) return;
            
            this.loading = true;
            try {
                const response = await fetch(`api/public_booking_api.php?action=get_services_by_category&category_id=${this.selectedCategory}`);
                const data = await response.json();
                this.services = data.success ? data.services : [];
            } catch (e) { console.error(e); } finally { this.loading = false; }
        },

        async handleServiceChange() {
            this.specialists = [];
            this.selectedSpecialist = ''; this.selectedDate = '';
            this.validateStep1();

            if (!this.selectedServiceId) return;

            this.loading = true;
            try {
                const response = await fetch(`api/public_booking_api.php?action=get_specialists_for_service&service_id=${this.selectedServiceId}`);
                const data = await response.json();
                this.specialists = data.success ? data.specialists : [];
            } catch (e) { console.error(e); } finally { this.loading = false; }
        },
        
        validateStep1() {
            this.errors.date = '';
            if (this.selectedDate && this.isDateDisabled(this.selectedDate)) {
                this.errors.date = 'هذا اليوم غير متاح للحجز.';
            }
            const isSelectionComplete = !!this.selectedCategory && !!this.selectedServiceId && !!this.selectedSpecialist && !!this.selectedDate;
            this.isStep1Valid = isSelectionComplete && !this.errors.date;
            this.errors.step1 = !this.isStep1Valid ? 'يرجى إكمال جميع الخيارات.' : '';
        },

        // --- START: FULLY REWRITTEN FUNCTION ---
        async handleCustomerSearch() {
            const phoneQuery = this.customerPhone.trim();
            this.showNewCustomerNote = false; // Reset the note on each input

            // If the phone number is not complete, reset everything
            if (!/^05\d{8}$/.test(phoneQuery)) {
                if (this.customerFound) {
                    this.customerName = ''; // Clear name only if it was auto-filled
                }
                this.customerFound = false;
                this.validateStep2();
                return;
            }

            // Phone number is complete, start search
            this.isSearchingCustomer = true;
            try {
                const response = await fetch(`api/public_booking_api.php?action=find_customer&phone=${phoneQuery}`);
                const data = await response.json();
                
                if (data.success && data.customer) {
                    // Customer found: fill name, lock field, hide note
                    this.customerName = data.customer.name_ar;
                    this.customerFound = true; 
                    this.showNewCustomerNote = false;
                } else {
                    // New customer: clear name, unlock field, show note
                    this.customerName = ''; 
                    this.customerFound = false; 
                    this.showNewCustomerNote = true;
                }
            } catch (error) {
                console.error('Find customer by phone error:', error);
                this.customerFound = false;
                this.showNewCustomerNote = false; // Hide note on API error
            } finally {
                this.isSearchingCustomer = false;
                this.validateStep2(); // Re-validate step 2 with the new state
            }
        },
        // --- END: REWRITTEN FUNCTION ---
        
        validateStep2() {
            this.errors.time = !this.selectedTime ? 'يرجى اختيار الوقت' : '';
            this.errors.name = (!this.customerName || this.customerName.trim().length < 2) ? 'يرجى إدخال الاسم' : '';
            this.errors.phone = !/^05\d{8}$/.test(this.customerPhone) ? 'رقم الجوال يجب أن يبدأ بـ 05 ويتكون من 10 أرقام' : '';
            this.isStep2Valid = !this.errors.time && !this.errors.phone && !this.errors.name;
        },

        async loadAvailableTimes() {
            this.selectedTime = ''; this.availableTimes = [];
            if (!this.selectedDate || !this.selectedSpecialist || !this.selectedServiceId || this.isDateDisabled(this.selectedDate)) {
                return;
            }
            this.isLoadingTimes = true;
            try {
                const url = `api/public_booking_api.php?action=get_available_slots&date=${this.selectedDate}&specialist_id=${this.selectedSpecialist}&service_id=${this.selectedServiceId}`;
                const response = await fetch(url);
                const data = await response.json();
                this.availableTimes = data.success ? data.slots : [];
            } catch (error) { console.error("API error for times:", error); }
            finally { this.isLoadingTimes = false; this.validateStep2(); }
        },

        nextStep() {
            if (this.step === 1) {
                this.validateStep1();
                if (this.isStep1Valid) { 
                    this.step = 2; 
                    this.loadAvailableTimes();
                }
            } else if (this.step === 2) {
                this.validateStep2();
                if (this.isStep2Valid) {
                    this.step = 3;
                }
            }
        },

        async submitBooking() {
            this.validateStep1(); this.validateStep2();
            if (!this.isStep1Valid || !this.isStep2Valid) {
                this.errors.submit = 'الرجاء التأكد من تعبئة جميع الحقول بشكل صحيح.';
                return;
            }
            this.isSubmitting = true;
            const formData = new FormData();
            formData.append('service_id', this.selectedServiceId);
            formData.append('specialist_id', this.selectedSpecialist);
            formData.append('booking_date', this.selectedDate);
            formData.append('booking_time', this.selectedTime);
            formData.append('customer_name', this.customerName);
            formData.append('customer_phone', this.customerPhone);
            formData.append('total_amount', this.totalPrice);

            try {
                const response = await fetch('booking_api.php', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    this.bookingReference = data.booking_data.reference;
                    this.step = 4;
                } else {
                    this.errors.submit = data.message || 'حدث خطأ أثناء الحجز.';
                }
            } catch (e) {
                this.errors.submit = 'حدث خطأ في الاتصال.';
            } finally { this.isSubmitting = false; }
        }
    };
}
</script>
</body>
</html>