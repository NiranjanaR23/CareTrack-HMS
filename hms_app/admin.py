from django.contrib import admin
from .models import Department, Doctor, Patient, Appointment, MedicalRecord, Medicine, Ward, Admission, Bill, BillItem

@admin.register(Department)
class DepartmentAdmin(admin.ModelAdmin):
    list_display = ('id', 'name', 'created_at')
    search_fields = ('name',)

@admin.register(Doctor)
class DoctorAdmin(admin.ModelAdmin):
    list_display = ('id', 'name', 'department', 'specialization', 'phone', 'consult_fee', 'is_active')
    list_filter = ('department', 'is_active')
    search_fields = ('name', 'specialization', 'phone')

@admin.register(Patient)
class PatientAdmin(admin.ModelAdmin):
    list_display = ('patient_code', 'full_name', 'gender', 'phone', 'blood_group', 'is_active', 'created_at')
    search_fields = ('patient_code', 'full_name', 'phone', 'email')
    list_filter = ('gender', 'blood_group', 'is_active')

@admin.register(Appointment)
class AppointmentAdmin(admin.ModelAdmin):
    list_display = ('token_no', 'patient', 'doctor', 'appointment_date', 'appointment_time', 'status', 'priority')
    list_filter = ('appointment_date', 'status', 'priority', 'doctor')
    search_fields = ('patient__full_name', 'doctor__name')

@admin.register(MedicalRecord)
class MedicalRecordAdmin(admin.ModelAdmin):
    list_display = ('id', 'patient', 'doctor', 'visit_date', 'diagnosis', 'icd_code')
    search_fields = ('patient__full_name', 'doctor__name', 'diagnosis', 'icd_code')

@admin.register(Medicine)
class MedicineAdmin(admin.ModelAdmin):
    list_display = ('name', 'dosage_form', 'unit_price', 'stock_qty', 'reorder_level', 'expiry_date')
    search_fields = ('name', 'generic_name', 'batch_no')
    list_filter = ('dosage_form', 'category')

@admin.register(Ward)
class WardAdmin(admin.ModelAdmin):
    list_display = ('ward_name', 'ward_type', 'total_beds', 'available_beds', 'charge_per_day')
    list_filter = ('ward_type',)

@admin.register(Admission)
class AdmissionAdmin(admin.ModelAdmin):
    list_display = ('id', 'patient', 'doctor', 'ward', 'bed_no', 'admission_date', 'status')
    list_filter = ('status', 'ward')

class BillItemInline(admin.TabularInline):
    model = BillItem
    extra = 1

@admin.register(Bill)
class BillAdmin(admin.ModelAdmin):
    list_display = ('bill_number', 'patient', 'bill_date', 'net_amount', 'paid_amount', 'status')
    list_filter = ('status', 'bill_date')
    inlines = [BillItemInline]
