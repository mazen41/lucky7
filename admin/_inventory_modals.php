<!-- Add Product Modal -->
<div id="addProductModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col">
        <form id="addProductForm" class="flex flex-col flex-grow overflow-hidden">
            <input type="hidden" name="action" value="add_product">
            
            <div class="p-5 border-b sticky top-0 bg-white rounded-t-xl z-10">
                <h3 class="text-xl font-bold text-gray-800">إضافة منتج جديد</h3>
            </div>
            
            <div class="p-6 flex-grow overflow-y-auto space-y-6">
                
                <fieldset class="border-t pt-4">
                    <legend class="text-base font-semibold text-gray-800 px-2">المعلومات الأساسية</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div><label class="block text-sm mb-2">اسم المنتج (عربي) *</label><input type="text" name="name_ar" required class="w-full border-gray-300 rounded-lg"></div>
                        <div><label class="block text-sm mb-2">اسم المنتج (إنجليزي)</label><input type="text" name="name_en" class="w-full border-gray-300 rounded-lg"></div>
                        <div><label class="block text-sm mb-2">التصنيف</label><select name="category_id" class="w-full border-gray-300 rounded-lg"><option value="">اختر التصنيف</option><?php foreach ($categories as $c):?><option value="<?= $c['id']; ?>"><?= htmlspecialchars($c['name_ar']); ?></option><?php endforeach; ?></select></div>
                        <div><label class="block text-sm mb-2">رمز المنتج (SKU)</label><input type="text" name="sku" class="w-full border-gray-300 rounded-lg"></div>
                    </div>
                </fieldset>

                <fieldset class="border-t pt-4">
                    <legend class="text-base font-semibold text-gray-800 px-2">نوع الاستخدام</legend>
                     <div class="mt-4">
                        <div class="flex items-center gap-x-6 gap-y-2 flex-wrap">
                            <label class="flex items-center cursor-pointer"><input type="radio" name="product_usage" value="direct_sale" class="ml-2" checked onchange="toggleRecipeFields(this.form, this.value)"> بيع مباشر للعملاء</label>
                            <label class="flex items-center cursor-pointer"><input type="radio" name="product_usage" value="internal_use" class="ml-2" onchange="toggleRecipeFields(this.form, this.value)"> استخدام داخلي (مكونات)</label>
                        </div>
                    </div>
                </fieldset>
                
                <fieldset class="border-t pt-4">
                    <legend class="text-base font-semibold text-gray-800 px-2">التسعير والمخزون</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div><label class="block text-sm mb-2">سعر الشراء</label><input type="number" step="0.01" name="purchase_price" class="w-full border-gray-300 rounded-lg"></div>
                        <div><label class="block text-sm mb-2">سعر البيع</label><input type="number" step="0.01" name="selling_price" class="w-full border-gray-300 rounded-lg"></div>
                        <div><label class="block text-sm mb-2">الكمية الابتدائية</label><input type="number" name="stock_quantity" value="0" class="w-full border-gray-300 rounded-lg"></div>
                        <div><label class="block text-sm mb-2">الحد الأدنى للمخزون</label><input type="number" name="min_stock_level" value="5" class="w-full border-gray-300 rounded-lg"></div>
                        <div class="md:col-span-2">
                            <label class="block text-sm mb-2">وحدة القياس</label>
                            <select name="unit" class="w-full border-gray-300 rounded-lg">
                                <option value="Piece">قطعة (Piece)</option>
                                <option value="Gram">جرام (Gram)</option>
                                <option value="Kilogram">كيلوجرام (Kilogram)</option>
                                <option value="Milliliter">ملิลتر (Milliliter)</option>
                                <option value="Liter">لتر (Liter)</option>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <!-- --- NEW --- Recipe Fields (Conditional) -->
                <div class="recipe-fields">
                    <fieldset class="border-t pt-4">
                        <legend class="text-base font-semibold text-gray-800 px-2">مكونات المنتج (الوصفة)</legend>
                        <p class="text-sm text-gray-600 mt-2 px-2">اربط مواد الاستخدام الداخلي لتكوين هذا المنتج. سيتم خصمها من المخزون تلقائياً عند البيع.</p>
                        <div class="ingredients-list space-y-3 mt-4 p-3 bg-gray-50 rounded-lg">
                            <!-- JS will add ingredient selection rows here -->
                        </div>
                        <button type="button" onclick="addIngredientRow(this.closest('.recipe-fields').querySelector('.ingredients-list'))" class="mt-3 px-4 py-2 text-sm bg-blue-500 text-white rounded-lg hover:bg-blue-600"><i class="fas fa-plus mr-2"></i>إضافة مكون</button>
                    </fieldset>
                </div>
            </div>

            <div class="flex justify-end gap-3 p-4 bg-gray-50 border-t sticky bottom-0 rounded-b-xl">
                <button type="button" onclick="closeModal('addProductModal')" class="px-5 py-2.5 text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700">إضافة المنتج</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Product Modal -->
<div id="editProductModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col">
        <form id="editProductForm" class="flex flex-col flex-grow overflow-hidden">
            <input type="hidden" name="action" value="update_product">
            <input type="hidden" name="product_id">
            
            <div class="p-5 border-b sticky top-0 bg-white rounded-t-xl z-10">
                <h3 class="text-xl font-bold text-gray-800">تعديل المنتج</h3>
            </div>
            
            <div class="p-6 flex-grow overflow-y-auto space-y-6">
                <fieldset class="border-t pt-4">
                    <legend class="text-base font-semibold text-gray-800 px-2">المعلومات الأساسية</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div><label class="block text-sm mb-2">اسم المنتج (عربي) *</label><input type="text" name="name_ar" required class="w-full border-gray-300 rounded-lg"></div>
                        <div><label class="block text-sm mb-2">اسم المنتج (إنجليزي)</label><input type="text" name="name_en" class="w-full border-gray-300 rounded-lg"></div>
                        <div><label class="block text-sm mb-2">التصنيف</label><select name="category_id" class="w-full border-gray-300 rounded-lg"><option value="">اختر التصنيف</option><?php foreach ($categories as $c):?><option value="<?= $c['id']; ?>"><?= htmlspecialchars($c['name_ar']); ?></option><?php endforeach; ?></select></div>
                        <div><label class="block text-sm mb-2">رمز المنتج (SKU) *</label><input type="text" name="sku" required class="w-full border-gray-300 rounded-lg"></div>
                    </div>
                </fieldset>

                <fieldset class="border-t pt-4">
                    <legend class="text-base font-semibold text-gray-800 px-2">نوع الاستخدام</legend>
                     <div class="mt-4">
                        <div class="flex items-center gap-x-6 gap-y-2 flex-wrap">
                            <label class="flex items-center cursor-pointer"><input type="radio" name="product_usage" value="direct_sale" class="ml-2" onchange="toggleRecipeFields(this.form, this.value)"> بيع مباشر للعملاء</label>
                            <label class="flex items-center cursor-pointer"><input type="radio" name="product_usage" value="internal_use" class="ml-2" onchange="toggleRecipeFields(this.form, this.value)"> استخدام داخلي (مكونات)</label>
                        </div>
                    </div>
                </fieldset>
                
                <fieldset class="border-t pt-4">
                    <legend class="text-base font-semibold text-gray-800 px-2">التسعير والمخزون</legend>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div><label class="block text-sm mb-2">سعر الشراء</label><input type="number" step="0.01" name="purchase_price" class="w-full border-gray-300 rounded-lg"></div>
                        <div><label class="block text-sm mb-2">سعر البيع</label><input type="number" step="0.01" name="selling_price" class="w-full border-gray-300 rounded-lg"></div>
                        <div><label class="block text-sm mb-2">سعر التكلفة</label><input type="number" step="0.01" name="cost_price" class="w-full border-gray-300 rounded-lg"></div>
                        <div><label class="block text-sm mb-2">الحد الأدنى للمخزون</label><input type="number" name="min_stock_level" class="w-full border-gray-300 rounded-lg"></div>
                         <div class="md:col-span-2">
                            <label class="block text-sm mb-2">وحدة القياس</label>
                            <select name="unit" class="w-full border-gray-300 rounded-lg">
                                <option value="Piece">قطعة (Piece)</option>
                                <option value="Gram">جرام (Gram)</option>
                                <option value="Kilogram">كيلوجرام (Kilogram)</option>
                                <option value="Milliliter">ملิลتر (Milliliter)</option>
                                <option value="Liter">لتر (Liter)</option>
                            </select>
                        </div>
                    </div>
                </fieldset>
            
                <!-- --- NEW --- Recipe Fields (Conditional) -->
                <div class="recipe-fields">
                    <fieldset class="border-t pt-4">
                        <legend class="text-base font-semibold text-gray-800 px-2">مكونات المنتج (الوصفة)</legend>
                        <p class="text-sm text-gray-600 mt-2 px-2">اربط مواد الاستخدام الداخلي لتكوين هذا المنتج. سيتم خصمها من المخزون تلقائياً عند البيع.</p>
                        <div class="ingredients-list space-y-3 mt-4 p-3 bg-gray-50 rounded-lg">
                           <!-- JS will populate this list -->
                        </div>
                        <button type="button" onclick="addIngredientRow(this.closest('.recipe-fields').querySelector('.ingredients-list'))" class="mt-3 px-4 py-2 text-sm bg-blue-500 text-white rounded-lg hover:bg-blue-600"><i class="fas fa-plus mr-2"></i>إضافة مكون</button>
                    </fieldset>
                </div>
            </div>

            <div class="flex justify-end gap-3 p-4 bg-gray-50 border-t sticky bottom-0 rounded-b-xl">
                <button type="button" onclick="closeModal('editProductModal')" class="px-5 py-2.5 text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700">حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>


<!-- Stock Movement Modal (no changes) -->
<div id="stockMovementModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-md w-full">
        <form id="stockMovementForm">
            <input type="hidden" name="action" value="adjust_stock">
            <input type="hidden" name="product_id">
            <div class="p-6 border-b"><h3 class="text-lg font-bold">حركة مخزون</h3></div>
            <div class="p-6 space-y-4">
                <div>
                    <h4 id="stockMovementProductName" class="font-bold text-lg text-gray-800"></h4>
                    <p class="text-sm text-gray-500">المخزون الحالي: <span id="currentStock" class="font-medium"></span></p>
                </div>
                <div>
                    <label class="block text-sm mb-2">نوع الحركة *</label>
                    <div class="flex gap-4">
                       <label class="flex items-center"><input type="radio" name="type" value="add" required class="ml-2"> إضافة للمخزون (شراء)</label>
                       <label class="flex items-center"><input type="radio" name="type" value="remove" required class="ml-2"> سحب من المخزون (تلف/استخدام)</label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm mb-2">الكمية *</label>
                    <input type="number" name="quantity" required min="1" class="w-full border-gray-300 rounded-lg">
                </div>
                 <div>
                    <label class="block text-sm mb-2">ملاحظات (اختياري)</label>
                    <input type="text" name="notes" class="w-full border-gray-300 rounded-lg">
                </div>
            </div>
            <div class="flex justify-end gap-3 p-4 bg-gray-50">
                <button type="button" onclick="closeModal('stockMovementModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">تنفيذ الحركة</button>
            </div>
        </form>
    </div>
</div>


<!-- View Product Modal (no changes) -->
<div id="viewProductModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-lg w-full">
        <div class="p-6 border-b flex justify-between items-center">
            <h3 id="viewProductName" class="text-lg font-bold">تفاصيل المنتج</h3>
            <button onclick="closeModal('viewProductModal')" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
        </div>
        <div class="p-6">
            <ul id="viewProductData" class="space-y-2 text-gray-700">
                <!-- Data will be injected by JavaScript -->
            </ul>
             <!-- --- NEW: Display Recipe --- -->
            <div id="viewProductRecipe" class="mt-4"></div>
        </div>
        <div class="p-4 bg-gray-50 text-left">
            <button type="button" onclick="closeModal('viewProductModal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-100">إغلاق</button>
        </div>
    </div>
</div>