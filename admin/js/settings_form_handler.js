// js/settings_form_handler.js

function submitSettingsForm(form, successMessage) {
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin ml-2"></i> جاري الحفظ...';
    submitBtn.disabled = true;

    fetch(form.action, {
        method: 'POST',
        body: new FormData(form)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(successMessage);
            // Optionally redirect or just show a success state
            window.location.reload(); 
        } else {
            alert('خطأ: ' + (data.message || 'حدث خطأ أثناء حفظ الإعدادات'));
        }
    })
    .catch(error => {
        console.error('Fetch Error:', error);
        alert('حدث خطأ في الاتصال: ' + error.message);
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const forms = {
        'business-form': 'تم حفظ معلومات الشركة بنجاح',
        'tax-form': 'تم حفظ إعدادات الضرائب بنجاح',
        'pos-form': 'تم حفظ إعدادات نقطة البيع بنجاح',
        'loyalty-form': 'تم حفظ إعدادات نظام الولاء بنجاح'
    };

    for (const formId in forms) {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                submitSettingsForm(this, forms[formId]);
            });
        }
    }
});