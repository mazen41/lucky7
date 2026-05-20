<?php
require_once 'config/database.php';

// Initialize database connection
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Throwable $e) {
    $db = null;
    // It's better to handle the error gracefully
    die("Database connection failed: " . $e->getMessage());
}

// Default values
$specialists = [];

// Get specialists
if ($db) {
    try {
        // Get specialists
        $specialists_query = "SELECT id, name_ar FROM team_members WHERE is_active = 1 AND is_specialist = 1 ORDER BY sort_order ASC";
        $specialists_stmt = $db->prepare($specialists_query);
        $specialists_stmt->execute();
        $specialists = $specialists_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        // In case of an error, you might want to log it and show a user-friendly message
        $specialists = [];
    }
}

// Site title
$site_title = "حجز موعد جديد - صالون MAI";
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $site_title; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'arabic': ['Tajawal', 'sans-serif'],
                    },
                    colors: {
                        'mai-brown': '#8B4513',
                        'mai-gold': '#D4AF37',
                        'mai-cream': '#F5E6D3',
                        'mai-dark-brown': '#654321',
                    }
                }
            }
        }
    </script>
    
    <style>
        body { font-family: 'Tajawal', sans-serif; }
        .cta-button { transition: all 0.3s ease; }
        .cta-button:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
        .cta-button:active { transform: translateY(0); }
        .form-input:focus { border-color: #8B4513; outline: none; box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.2); }
        .form-select:focus { border-color: #8B4513; outline: none; box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.2); }
        
        /* Custom radio button */
        .custom-radio input[type="radio"] {
            display: none;
        }
        .custom-radio input[type="radio"] + label {
            position: relative;
            padding-right: 28px;
            cursor: pointer;
            display: inline-block;
        }
        .custom-radio input[type="radio"] + label:before {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #8B4513;
        }
        .custom-radio input[type="radio"]:checked + label:after {
            content: '';
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #8B4513;
        }
    </style>
</head>
<body class="bg-gray-50 font-arabic" x-data="bookingApp()">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-mai-dark-brown">صالون MAI</h1>
                    <a href="index.php" class="text-mai-brown hover:text-mai-dark-brown">
                        <i class="fas fa-home ml-1"></i>
                        الرئيسية
                    </a>
                </div>
            </div>
        </header>
        
        <!-- Main Content -->
        <main class="flex-grow">
            <div class="max-w-3xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-2xl font-bold text-center text-mai-dark-brown">حجز موعد جديد</h2>
                    </div>
                    
                    <!-- Booking Steps -->
                    <div class="p-6">
                        <!-- Step Indicator -->
                        <div class="mb-8">
                            <div class="flex items-center justify-between">
                                <div class="flex flex-col items-center">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                         :class="step >= 1 ? 'bg-mai-brown text-white' : 'bg-gray-200 text-gray-600'">
                                        1
                                    </div>
                                    <span class="mt-1 text-xs" :class="step >= 1 ? 'text-mai-brown' : 'text-gray-500'">الخدمة</span>
                                </div>
                                <div class="flex-1 h-1 mx-2" :class="step >= 2 ? 'bg-mai-brown' : 'bg-gray-200'"></div>
                                <div class="flex flex-col items-center">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                         :class="step >= 2 ? 'bg-mai-brown text-white' : 'bg-gray-200 text-gray-600'">
                                        2
                                    </div>
                                    <span class="mt-1 text-xs" :class="step >= 2 ? 'text-mai-brown' : 'text-gray-500'">المعلومات</span>
                                </div>
                                <div class="flex-1 h-1 mx-2" :class="step >= 3 ? 'bg-mai-brown' : 'bg-gray-200'"></div>
                                <div class="flex flex-col items-center">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                         :class="step >= 3 ? 'bg-mai-brown text-white' : 'bg-gray-200 text-gray-600'">
                                        3
                                    </div>
                                    <span class="mt-1 text-xs" :class="step >= 3 ? 'text-mai-brown' : 'text-gray-500'">التأكيد</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 1: Service Selection -->
                        <div x-show="step === 1">
                            <h3 class="text-xl font-bold text-mai-dark-brown mb-6">اختر الخدمة <span class="bg-mai-brown text-white px-2 py-1 rounded-full text-xs">1</span></h3>
                            
                            <!-- Loading indicator -->
                            <div x-show="loading" class="text-center py-4">
                                <i class="fas fa-spinner fa-spin text-mai-brown text-2xl"></i>
                                <p class="mt-2 text-gray-600">جاري تحميل البيانات...</p>
                            </div>
                            
                            <!-- Category Selection -->
                            <div class="mb-6">
                                <div class="flex justify-between items-center mb-2">
                                    <label class="block text-sm font-medium text-gray-700">الفئة</label>
                                </div>
                                <select x-model="selectedCategory" @change="validateStep1(); selectedServiceId = ''" class="form-select w-full px-4 py-2 border border-gray-300 rounded-lg">
                                    <option value="">اختر الفئة</option>
                                    <template x-for="category in categories" :key="category.id">
                                        <option :value="category.id" x-text="category.name_ar"></option>
                                    </template>
                                </select>
                                <p class="mt-1 text-sm text-red-600" x-show="errors.category" x-text="errors.category"></p>
                            </div>
                            
                            <!-- Service Selection -->
                            <div class="mb-6" x-show="selectedCategory">
                                <div class="flex justify-between items-center mb-2">
                                    <label class="block text-sm font-medium text-gray-700">اختر الخدمة</label>
                                </div>
                                <select x-model="selectedServiceId" @change="validateStep1()" class="form-select w-full px-4 py-2 border border-gray-300 rounded-lg">
                                    <option value="">اختر الخدمة</option>
                                    <template x-for="service in filteredServices" :key="service.id">
                                        <!-- *** FIXED: Changed fixed_price to price *** -->
                                        <option :value="service.id" x-text="(service.name_ar || service.title_ar) + ' - ' + (service.price || service.min_price || 0) + ' ريال'"></option>
                                    </template>
                                </select>
                                <p class="mt-1 text-sm text-red-600" x-show="errors.service" x-text="errors.service"></p>
                                
                                <button @click="addService(); validateStep1()" 
                                        :disabled="!selectedServiceId"
                                        class="mt-3 bg-mai-gold text-white px-4 py-2 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i class="fas fa-plus ml-1"></i>
                                    إضافة خدمة أخرى
                                </button>
                            </div>
                            
                            <!-- Selected Services List -->
                            <div x-show="selectedServices.length > 0" class="mb-6">
                                <h4 class="text-lg font-bold text-mai-dark-brown mb-3">الخدمات المختارة</h4>
                                <div class="space-y-3">
                                    <template x-for="(service, index) in selectedServices" :key="service.id + '_' + index">
                                        <div class="border border-mai-brown bg-mai-cream rounded-lg p-4">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <h5 class="font-bold text-mai-dark-brown" x-text="service.name_ar || service.title_ar"></h5>
                                                    <p class="text-sm text-gray-600">خدمة متميزة بأجهزة وأيدي محترفة ومنتجات عالمية</p>
                                                </div>
                                                <div class="flex items-center">
                                                    <span class="text-xl font-bold text-mai-brown ml-4" x-text="service.price + ' ريال'"></span>
                                                    <button @click="removeService(index); validateStep1()" class="text-red-600 hover:text-red-800">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                
                                <!-- Total Price -->
                                <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                                    <div class="flex justify-between items-center">
                                        <span class="text-lg font-bold text-gray-700">المجموع الكلي:</span>
                                        <span class="text-2xl font-bold text-mai-brown" x-text="totalPrice + ' ريال'"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-end mt-8">
                                <button @click="nextStep()" 
                                        :disabled="!isStep1Valid"
                                        class="cta-button bg-mai-brown text-white px-8 py-3 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
                                    التالي
                                </button>
                            </div>
                        </div>
                        
                        <!-- Step 2: Date, Time, Specialist & Customer Info -->
                        <div x-show="step === 2">
                            <h3 class="text-xl font-bold text-mai-dark-brown mb-6">المعلومات <span class="bg-mai-brown text-white px-2 py-1 rounded-full text-xs">2</span></h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">اختر الموظفة</label>
                                    <select x-model="selectedSpecialist" @change="validateStep2()" class="form-select w-full px-4 py-2 border border-gray-300 rounded-lg">
                                        <option value="">اختر الموظفة (اختياري)</option>
                                        <?php foreach ($specialists as $specialist): ?>
                                        <option value="<?php echo $specialist['id']; ?>"><?php echo htmlspecialchars($specialist['name_ar']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">التاريخ</label>
                                    <input type="date" x-model="selectedDate" @input="validateStep2()" min="<?php echo date('Y-m-d'); ?>" class="form-input w-full px-4 py-2 border border-gray-300 rounded-lg">
                                    <p class="mt-1 text-sm text-red-600" x-show="errors.date" x-text="errors.date"></p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">اختر الوقت</label>
                                    <select x-model="selectedTime" @change="validateStep2()" class="form-select w-full px-4 py-2 border border-gray-300 rounded-lg">
                                        <option value="">اختر الوقت</option>
                                        <template x-for="time in availableTimes" :key="time.value">
                                            <option :value="time.value" x-text="time.label"></option>
                                        </template>
                                    </select>
                                    <p class="mt-1 text-sm text-red-600" x-show="errors.time" x-text="errors.time"></p>
                                </div>
                            </div>
                            
                            <div class="border-t pt-6">
                                <h4 class="text-lg font-bold text-mai-dark-brown mb-4">معلومات العميل</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">رقم الجوال</label>
                                        <div class="flex">
                                            <div class="flex items-center px-3 bg-gray-100 border border-r-0 border-gray-300 rounded-r-lg">
                                                <img src="https://flagcdn.com/w20/sa.png" alt="SA" class="w-5 h-3">
                                                <span class="mr-2 text-sm">+966</span>
                                            </div>
                                            <input type="tel" x-model="customerPhone" @input="validateStep2()" placeholder="5xxxxxxxx" class="form-input flex-1 px-4 py-2 border border-gray-300 rounded-l-lg">
                                        </div>
                                        <p class="mt-1 text-sm text-red-600" x-show="errors.phone" x-text="errors.phone"></p>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">الاسم</label>
                                        <input type="text" x-model="customerName" @input="validateStep2()" placeholder="أدخل اسمك" class="form-input w-full px-4 py-2 border border-gray-300 rounded-lg">
                                        <p class="mt-1 text-sm text-red-600" x-show="errors.name" x-text="errors.name"></p>
                                    </div>
                                </div>
                                
                                <div class="mb-6">
                                    <h4 class="text-lg font-bold text-mai-dark-brown mb-2">طريقة الدفع</h4>
                                    <div class="flex space-x-6 space-x-reverse">
                                        <div class="custom-radio">
                                            <input type="radio" id="payment_cash" name="payment_method" value="cash" x-model="paymentMethod">
                                            <label for="payment_cash">نقدي</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-between mt-8">
                                <button @click="step = 1" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                    السابق
                                </button>
                                <button @click="nextStep()" 
                                        :disabled="!isStep2Valid"
                                        class="cta-button bg-mai-brown text-white px-6 py-3 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
                                    التالي
                                </button>
                            </div>
                        </div>
                        
                        <!-- Step 3: Confirmation -->
                        <div x-show="step === 3">
                            <h3 class="text-xl font-bold text-mai-dark-brown mb-6">التأكيد <span class="bg-mai-brown text-white px-2 py-1 rounded-full text-xs">3</span></h3>
                            
                            <div class="bg-gray-50 rounded-lg p-6 mb-6">
                                <div class="space-y-4">
                                    <div>
                                        <span class="text-gray-600 block mb-2">الخدمات:</span>
                                        <div class="space-y-2">
                                            <template x-for="service in selectedServices" :key="service.id">
                                                <div class="flex justify-between bg-white p-2 rounded">
                                                    <span class="font-bold text-mai-dark-brown" x-text="service.title_ar"></span>
                                                    <span class="text-mai-brown" x-text="service.price + ' ريال'"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">التاريخ والوقت:</span>
                                        <span class="font-bold text-mai-dark-brown" x-text="formattedDateTime"></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">الموظفة:</span>
                                        <span class="font-bold text-mai-dark-brown" x-text="specialistName || 'سيتم تحديدها لاحقاً'"></span>
                                    </div>
                                    <div class="flex justify-between border-t pt-2">
                                        <span class="text-gray-600 text-lg">المجموع الكلي:</span>
                                        <span class="font-bold text-mai-brown text-xl" x-text="totalPrice + ' ريال'"></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">طريقة الدفع:</span>
                                        <span class="font-bold text-mai-dark-brown">نقدي</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-between mt-8">
                                <button @click="step = 2" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                    السابق
                                </button>
                                <button @click="submitBooking()" 
                                        :disabled="isSubmitting"
                                        class="cta-button bg-mai-brown text-white px-8 py-3 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
                                    <span x-show="!isSubmitting">تأكيد الحجز</span>
                                    <span x-show="isSubmitting">جاري الحجز...</span>
                                </button>
                            </div>

                            <!-- Inline submit error -->
                            <p class="mt-4 text-center text-sm text-red-600" x-show="errors.submit" x-text="errors.submit"></p>
                        </div>
                        
                        <!-- Success Message -->
                        <div x-show="step === 4" class="text-center py-8">
                            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-check text-green-600 text-2xl"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-mai-dark-brown mb-2">تم الحجز بنجاح!</h3>
                            <p class="text-gray-600 mb-6">سيتم التواصل معك قريباً لتأكيد الحجز</p>
                            
                            <div class="bg-gray-50 rounded-lg p-6 mb-6 text-right">
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">رقم الحجز:</span>
                                        <span class="font-bold text-mai-dark-brown" x-text="bookingReference"></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">الخدمة:</span>
                                        <span class="font-bold text-mai-dark-brown" x-text="serviceName"></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">التاريخ والوقت:</span>
                                        <span class="font-bold text-mai-dark-brown" x-text="formattedDateTime"></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">المبلغ:</span>
                                        <span class="font-bold text-mai-brown" x-text="servicePrice + ' ريال'"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <a href="index.php" class="cta-button inline-block bg-mai-brown text-white px-6 py-3 rounded-lg">
                                العودة للرئيسية
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        
        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <p class="text-center text-gray-500 text-sm">
                    &copy; <?php echo date('Y'); ?> صالون MAI - جميع الحقوق محفوظة
                </p>
            </div>
        </footer>
    </div>
    
    <script>
        function bookingApp() {
            return {
                step: 1,
                selectedCategory: '',
                selectedServiceId: '',
                selectedServices: [],
                selectedSpecialist: '',
                selectedDate: '',
                selectedTime: '',
                customerName: '',
                customerPhone: '',
                paymentMethod: 'cash',
                isSubmitting: false,
                bookingReference: '',
                specialistName: '',
                formattedDateTime: '',
                
                // Real-time data
                services: [],
                categories: [],
                loading: true,
                
                // Inline validation state
                errors: { category: '', service: '', date: '', time: '', phone: '', name: '', submit: '' },
                isStep1Valid: false,
                isStep2Valid: false,
                
                // Initialize data loading
                init() {
                    this.loadCategories();
                    this.loadServices();
                },
                
                // Computed properties
                get filteredServices() {
                    if (!this.selectedCategory) return [];
                    return this.services.filter(service => service.category_id == this.selectedCategory);
                },
                
                get totalPrice() {
                    return this.selectedServices.reduce((total, service) => {
                        // *** FIXED: Calculation now uses getServicePrice for accuracy ***
                        return total + parseFloat(service.price || 0);
                    }, 0);
                },
                
                get serviceName() {
                    return this.selectedServices.map(s => s.name_ar || s.title_ar || 'خدمة').join(', ');
                },
                
                get servicePrice() {
                    return this.totalPrice;
                },
                
                // Real-time data loading methods
                loadCategories() {
                    fetch('api/public_booking_api.php?action=get_categories')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.categories) {
                                this.categories = data.categories;
                            }
                        });
                },
                
                loadServices() {
                    fetch('api/public_booking_api.php?action=get_services')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.services) {
                                this.services = data.services;
                                this.loading = false;
                            }
                        });
                },
                
                // Validation methods
                validateStep1() {
                    this.errors.category = '';
                    this.errors.service = '';
                    
                    if (this.selectedServices.length === 0) {
                        if (!this.selectedCategory) {
                            this.errors.category = 'يرجى اختيار الفئة';
                        }
                        if (!this.selectedServiceId) {
                            this.errors.service = 'يرجى اختيار خدمة واحدة على الأقل';
                        }
                    }
                    
                    this.isStep1Valid = this.selectedServices.length > 0;
                },
                
                validateStep2() {
                    this.errors.date = '';
                    this.errors.time = '';
                    this.errors.phone = '';
                    this.errors.name = '';
                    
                    if (!this.selectedDate) this.errors.date = 'يرجى اختيار التاريخ';
                    if (!this.selectedTime) this.errors.time = 'يرجى اختيار الوقت';
                    if (!this.customerName || this.customerName.trim().length < 2) this.errors.name = 'يرجى إدخال الاسم';
                    if (!/^5\d{8}$/.test(this.customerPhone)) this.errors.phone = 'رقم الجوال غير صحيح. يجب أن يبدأ بـ 5 ويتكون من 9 أرقام';
                    
                    this.isStep2Valid = !this.errors.date && !this.errors.time && !this.errors.phone && !this.errors.name;
                },
                
                // Methods
                addService() {
                    if (!this.selectedServiceId) return;
                    
                    const service = this.services.find(s => s.id == this.selectedServiceId);
                    if (service) {
                        const exists = this.selectedServices.find(s => s.id == service.id);
                        if (!exists) {
                            const computedPrice = this.getServicePrice(service);
                            this.selectedServices.push({
                                id: service.id,
                                name_ar: service.name_ar || service.title_ar,
                                title_ar: service.name_ar || service.title_ar,
                                price: computedPrice,
                                duration: service.duration, // Pass duration
                                category_id: service.category_id
                            });
                        }
                    }
                    
                    this.selectedServiceId = '';
                    this.selectedCategory = '';
                    this.validateStep1();
                },
                
                // *** FIXED: Corrected this entire function to use `price` instead of `fixed_price` ***
                getServicePrice(service) {
                    if (service.price_type === 'fixed') {
                        return parseFloat(service.price || 0);
                    }
                    if (service.price_type === 'range') {
                        // Use minimum price as a starting quote
                        return parseFloat(service.min_price || 0);
                    }
                    // Fallback for any other case
                    return parseFloat(service.price || service.min_price || 0);
                },
                
                removeService(index) {
                    this.selectedServices.splice(index, 1);
                    this.validateStep1();
                },
                
                get availableTimes() {
                    const times = [];
                    for (let hour = 9; hour <= 21; hour++) {
                        const value = `${hour.toString().padStart(2, '0')}:00:00`;
                        const label = new Date(`1970-01-01T${value}`).toLocaleTimeString('ar-SA', { hour: 'numeric', minute: '2-digit', hour12: true });
                        times.push({ value, label });
                    }
                    return times;
                },
                
                nextStep() {
                    if (this.step === 1) {
                        this.validateStep1();
                        if (!this.isStep1Valid) return;
                        this.step = 2;
                        return;
                    }
                    
                    if (this.step === 2) {
                        this.validateStep2();
                        if (!this.isStep2Valid) return;
                        const date = new Date(this.selectedDate);
                        const dateStr = date.toLocaleDateString('ar-SA', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                        const timeStr = new Date('1970-01-01T' + this.selectedTime).toLocaleTimeString('ar-SA', {
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        this.formattedDateTime = `${dateStr} - ${timeStr}`;
                        
                        if (this.selectedSpecialist) {
                            const specialists = <?php echo json_encode($specialists); ?>;
                            const specialist = specialists.find(s => s.id == this.selectedSpecialist);
                            this.specialistName = specialist ? specialist.name_ar : '';
                        } else {
                            this.specialistName = '';
                        }
                        this.step = 3;
                    }
                },
                
                submitBooking() {
                    this.errors.submit = '';
                    this.validateStep2();
                    if (this.selectedServices.length === 0) {
                        this.errors.submit = 'يرجى اختيار خدمة واحدة على الأقل.';
                        return;
                    }
                    if (!this.isStep2Valid) {
                        this.errors.submit = 'جميع الحقول المطلوبة يجب ملؤها.';
                        return;
                    }
                    
                    this.isSubmitting = true;
                    
                    const formData = new FormData();
                    formData.append('services', JSON.stringify(this.selectedServices));
                    formData.append('specialist_id', this.selectedSpecialist || '');
                    formData.append('booking_date', this.selectedDate);
                    formData.append('booking_time', this.selectedTime);
                    formData.append('customer_name', this.customerName);
                    formData.append('customer_phone', this.customerPhone);
                    formData.append('payment_method', this.paymentMethod);
                    formData.append('total_amount', this.totalPrice);
                    
                    fetch('booking_api.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        this.isSubmitting = false;
                        if (data.success) {
                            this.bookingReference = data.booking_data.reference;
                            this.step = 4;
                        } else {
                            this.errors.submit = data.message || 'حدث خطأ أثناء الحجز.';
                        }
                    })
                    .catch(error => {
                        this.isSubmitting = false;
                        this.errors.submit = 'حدث خطأ في الاتصال. يرجى المحاولة مرة أخرى.';
                    });
                }
            };
        }
    </script>
</body>
</html>