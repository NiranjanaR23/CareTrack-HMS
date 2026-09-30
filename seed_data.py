import os
import django
from datetime import date, timedelta

os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'caretrack_hms.settings')
django.setup()

from django.contrib.auth.models import User
from hms_app.models import Department, Doctor, Patient, Appointment, Medicine, Ward, Admission, Bill, BillItem

def seed():
    # 1. Admin User
    if not User.objects.filter(username='admin').exists():
        User.objects.create_superuser('admin', 'admin@hospital.com', 'admin123', first_name='System', last_name='Administrator')
        print("Created superuser admin/admin123")

    # 2. Departments
    dept_cardio, _ = Department.objects.get_or_create(name='Cardiology', defaults={'description': 'Heart and vascular care'})
    dept_ortho, _ = Department.objects.get_or_create(name='Orthopedics', defaults={'description': 'Bone and joint specialists'})
    dept_peds, _ = Department.objects.get_or_create(name='Pediatrics', defaults={'description': 'Child healthcare services'})
    dept_gen, _ = Department.objects.get_or_create(name='General Medicine', defaults={'description': 'Primary care and consultation'})
    print("Departments seeded.")

    # 3. Doctors
    doc1, _ = Doctor.objects.get_or_create(name='Dr. Rajesh Sharma', defaults={'department': dept_cardio, 'specialization': 'Cardiologist', 'qualification': 'MD, DM', 'phone': '+91 9876543210', 'email': 'rsharma@caretrack.com', 'consult_fee': 750.00})
    doc2, _ = Doctor.objects.get_or_create(name='Dr. Priya Nair', defaults={'department': dept_peds, 'specialization': 'Pediatrician', 'qualification': 'MBBS, DCH', 'phone': '+91 9876543211', 'email': 'pnair@caretrack.com', 'consult_fee': 500.00})
    doc3, _ = Doctor.objects.get_or_create(name='Dr. Anish Kumar', defaults={'department': dept_ortho, 'specialization': 'Orthopedic Surgeon', 'qualification': 'MS (Ortho)', 'phone': '+91 9876543212', 'email': 'akumar@caretrack.com', 'consult_fee': 600.00})
    print("Doctors seeded.")

    # 4. Patients
    p1, _ = Patient.objects.get_or_create(patient_code='P10001', defaults={'full_name': 'Ramesh Verma', 'gender': 'Male', 'phone': '9876500001', 'blood_group': 'O+', 'allergies': 'Penicillin', 'emergency_contact_name': 'Suresh Verma', 'emergency_contact_phone': '9876500002', 'insurance_provider': 'Star Health'})
    p2, _ = Patient.objects.get_or_create(patient_code='P10002', defaults={'full_name': 'Sunita Patel', 'gender': 'Female', 'phone': '9876500003', 'blood_group': 'B+', 'chronic_conditions': 'Hypertension', 'insurance_provider': 'HDFC Ergo'})
    print("Patients seeded.")

    # 5. Wards
    w1, _ = Ward.objects.get_or_create(ward_name='General Ward A', defaults={'ward_type': 'General', 'total_beds': 15, 'available_beds': 15, 'charge_per_day': 1200.00})
    w2, _ = Ward.objects.get_or_create(ward_name='ICU Complex', defaults={'ward_type': 'ICU', 'total_beds': 5, 'available_beds': 5, 'charge_per_day': 4500.00})
    print("Wards seeded.")

    # 6. Medicines
    Medicine.objects.get_or_create(name='Paracetamol 500mg', defaults={'generic_name': 'Acetaminophen', 'category': 'Analgesic', 'dosage_form': 'Tablet', 'unit_price': 5.00, 'stock_qty': 200, 'reorder_level': 30, 'batch_no': 'PARA-2026'})
    Medicine.objects.get_or_create(name='Amoxicillin 250mg', defaults={'generic_name': 'Amoxicillin', 'category': 'Antibiotic', 'dosage_form': 'Capsule', 'unit_price': 15.00, 'stock_qty': 12, 'reorder_level': 20, 'batch_no': 'AMOX-2026'})
    print("Medicines seeded.")

    print("Database seeding completed successfully!")

if __name__ == '__main__':
    seed()
