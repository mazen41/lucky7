// js/discount_policies_handler.js

document.addEventListener('DOMContentLoaded', function() {
    loadDiscountPolicies();

    document.getElementById('discount-policy-form').addEventListener('submit', handleFormSubmit);
});


function handleFormSubmit(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const roleSelect = document.getElementById('policy-role');
    const selectedRoleText = roleSelect.options[roleSelect.selectedIndex].text;
    formData.append('role_name_ar', selectedRoleText);
    
    fetch('discount_policies_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('تم حفظ سياسة الخصم بنجاح');
            resetPolicyForm();
            loadDiscountPolicies();
        } else {
            alert('خطأ: ' + (data.message || 'فشل حفظ السياسة'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('حدث خطأ في الاتصال');
    });
}

function loadDiscountPolicies() {
    const container = document.getElementById('discount-policies-container');
    container.innerHTML = '<p class="text-center py-8">جاري تحميل السياسات...</p>';

    fetch('discount_policies_api.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderDiscountPolicies(data.policies);
        } else {
            container.innerHTML = `<p class="text-red-500 text-center py-8">${data.message || 'فشل في تحميل سياسات الخصم'}</p>`;
        }
    })
    .catch(error => {
        container.innerHTML = `<p class="text-red-500 text-center py-8">حدث خطأ في تحميل سياسات الخصم</p>`;
    });
}

function renderDiscountPolicies(policies) {
    const container = document.getElementById('discount-policies-container');
    
    if (!policies || policies.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-8">لا توجد سياسات خصم محددة حالياً</p>';
        return;
    }
    
    let html = '<div class="space-y-4">';
    policies.forEach(policy => {
        html += `
            <div class="bg-gray-50 rounded-lg p-4 border">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h4 class="font-bold text-mai-dark-brown">${policy.role_name_ar}</h4>
                        <p class="text-sm text-gray-500">${policy.role_name}</p>
                    </div>
                    <div class="flex items-center space-x-2 space-x-reverse">
                        <span class="px-2 py-1 rounded text-xs font-medium ${policy.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                            ${policy.is_active ? 'نشط' : 'غير نشط'}
                        </span>
                        <button onclick="editDiscountPolicy('${policy.id}')" class="text-mai-brown hover:text-mai-dark-brown" title="تعديل">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600">أقصى نسبة:</span>
                        <span class="font-bold text-mai-brown">${policy.max_discount_percent}%</span>
                    </div>
                    <div>
                        <span class="text-gray-600">أقصى مبلغ:</span>
                        <span class="font-bold text-mai-brown">${policy.max_discount_amount || 'غير محدد'} ر.س</span>
                    </div>
                    <div>
                        <span class="text-gray-600">يتطلب سبب:</span>
                        <span class="font-bold ${policy.require_reason ? 'text-green-600' : 'text-gray-500'}">
                            ${policy.require_reason ? 'نعم' : 'لا'}
                        </span>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    container.innerHTML = html;
}

function editDiscountPolicy(policyId) {
    fetch(`discount_policies_api.php?id=${policyId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.policy) {
            const policy = data.policy;
            document.getElementById('policy-id').value = policy.id;
            document.getElementById('policy-role').value = policy.role_name;
            document.getElementById('policy-max-discount').value = policy.max_discount_percent;
            document.getElementById('policy-max-amount').value = policy.max_discount_amount || '';
            document.getElementById('policy-require-reason').checked = !!parseInt(policy.require_reason);
            document.getElementById('policy-active').checked = !!parseInt(policy.is_active);
            
            document.getElementById('policy-form-title').textContent = 'تعديل سياسة الخصم';
            document.getElementById('policy-submit-text').textContent = 'تحديث السياسة';
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            alert('خطأ في تحميل بيانات السياسة');
        }
    });
}

function resetPolicyForm() {
    document.getElementById('discount-policy-form').reset();
    document.getElementById('policy-id').value = '';
    document.getElementById('policy-active').checked = true;
    document.getElementById('policy-form-title').textContent = 'إضافة سياسة خصم جديدة';
    document.getElementById('policy-submit-text').textContent = 'إضافة السياسة';
}