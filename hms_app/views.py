import json
import uuid
from datetime import date, datetime
from django.shortcuts import render, redirect, get_object_or_404
from django.contrib.auth import authenticate, login, logout
from django.contrib.auth.decorators import login_required
from django.contrib.auth.models import User
from django.db.models import Sum, Count, Q, F
from django.contrib import messages
from django.utils import timezone

from .models import (
    Department, Doctor, Patient, Appointment, MedicalRecord,
    Medicine, Ward, Admission, Bill, BillItem
)

# ── AUTHENTICATION VIEWS ──────────────────────────────────────────────────
def login_view(request):
    if request.user.is_authenticated:
        return redirect('dashboard')

    # Ensure a default admin user exists for quick login
    if not User.objects.filter(username='admin').exists():
        User.objects.create_superuser('admin', 'admin@hospital.com', 'admin123', first_name='System', last_name='Administrator')

    err = None
    if request.method == 'POST':
        u = request.POST.get('username', '').strip()
        p = request.POST.get('password', '').strip()
        user = authenticate(request, username=u, password=p)
        if user is not None:
            login(request, user)
            return redirect('dashboard')
        else:
            err = "Invalid username or password."

    return render(request, 'hms_app/login.html', {'err': err})

def logout_view(request):
    logout(request)
    return redirect('login')

# ── DASHBOARD VIEW ────────────────────────────────────────────────────────
@login_required
def dashboard_view(request):
    today = date.today()
    total_patients = Patient.objects.filter(is_active=True).count()
    today_appts = Appointment.objects.filter(appointment_date=today).count()
    waiting_count = Appointment.objects.filter(appointment_date=today, status='Waiting').count()
    total_records = MedicalRecord.objects.count()
    
    paid_sum = Bill.objects.filter(status='Paid').aggregate(s=Sum('paid_amount'))['s'] or 0.0
    pending_bills = Bill.objects.filter(status__in=['Pending', 'Partial']).count()
    admitted_count = Admission.objects.filter(status='Admitted').count()
    low_stock_count = Medicine.objects.filter(is_active=True, stock_qty__lte=F('reorder_level')).count()

    today_queue = Appointment.objects.filter(appointment_date=today).select_related('patient', 'doctor', 'doctor__department').order_by('token_no')[:10]
    recent_patients = Patient.objects.filter(is_active=True).order_by('-created_at')[:5]

    context = {
        'total_patients': total_patients,
        'today_appts': today_appts,
        'waiting_count': waiting_count,
        'total_records': total_records,
        'revenue_paid': paid_sum,
        'pending_bills': pending_bills,
        'admitted_count': admitted_count,
        'low_stock_count': low_stock_count,
        'today_queue': today_queue,
        'recent_patients': recent_patients,
        'today_date': today.strftime('%A, %d %b %Y'),
    }
    return render(request, 'hms_app/dashboard.html', context)

# ── PATIENTS VIEW ─────────────────────────────────────────────────────────
@login_required
def patients_view(request):
    msg = err = None
    if request.method == 'POST' and 'action_patient' in request.POST:
        pid = int(request.POST.get('patient_id', 0))
        name = request.POST.get('full_name', '').strip()
        dob = request.POST.get('dob') or None
        gender = request.POST.get('gender', 'Male')
        phone = request.POST.get('phone', '').strip()
        email = request.POST.get('email', '').strip()
        blood = request.POST.get('blood_group', 'Unknown')
        address = request.POST.get('address', '').strip()
        emerg_name = request.POST.get('emergency_contact_name', '').strip()
        emerg_phone = request.POST.get('emergency_contact_phone', '').strip()
        allergies = request.POST.get('allergies', '').strip()
        chronic = request.POST.get('chronic_conditions', '').strip()
        ins_prov = request.POST.get('insurance_provider', '').strip()
        ins_pol = request.POST.get('insurance_policy_no', '').strip()

        if not name:
            err = "Patient full name is required."
        else:
            if pid > 0:
                p = get_object_or_404(Patient, id=pid)
                p.full_name = name
                p.dob = dob
                p.gender = gender
                p.phone = phone
                p.email = email
                p.blood_group = blood
                p.address = address
                p.emergency_contact_name = emerg_name
                p.emergency_contact_phone = emerg_phone
                p.allergies = allergies
                p.chronic_conditions = chronic
                p.insurance_provider = ins_prov
                p.insurance_policy_no = ins_pol
                p.save()
                msg = f"Patient {p.patient_code} updated successfully."
            else:
                code = "P" + str(timezone.now().strftime('%Y%m%d%H%M%S'))[-6:]
                p = Patient.objects.create(
                    patient_code=code, full_name=name, dob=dob, gender=gender, phone=phone,
                    email=email, blood_group=blood, address=address,
                    emergency_contact_name=emerg_name, emergency_contact_phone=emerg_phone,
                    allergies=allergies, chronic_conditions=chronic,
                    insurance_provider=ins_prov, insurance_policy_no=ins_pol
                )
                msg = f"New Patient {p.patient_code} registered successfully."

    q = request.GET.get('q', '').strip()
    patients = Patient.objects.filter(is_active=True)
    if q:
        patients = patients.filter(Q(full_name__icontains=q) | Q(patient_code__icontains=q) | Q(phone__icontains=q))
    patients = patients.order_by('-created_at')

    return render(request, 'hms_app/patients.html', {'patients': patients, 'q': q, 'msg': msg, 'err': err})

@login_required
def patient_qr_view(request, patient_id):
    patient = get_object_or_404(Patient, id=patient_id)
    host = request.get_host()
    scheme = request.scheme
    qr_url = f"{scheme}://{host}/qr/patient/{patient.qr_token}/"
    qr_api_url = f"https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={qr_url}"

    return render(request, 'hms_app/patient_qr.html', {'patient': patient, 'qr_url': qr_url, 'qr_api_url': qr_api_url})

def qr_patient_public_view(request, qr_token):
    patient = get_object_or_404(Patient, qr_token=qr_token)
    records = MedicalRecord.objects.filter(patient=patient).select_related('doctor').order_by('-visit_date')
    return render(request, 'hms_app/qr_patient_public.html', {'patient': patient, 'records': records})

# ── DOCTORS & DEPARTMENTS VIEW ──────────────────────────────────────────
@login_required
def doctors_view(request):
    msg = err = None
    if request.method == 'POST':
        if 'action_doctor' in request.POST:
            doc_id = int(request.POST.get('doctor_id', 0))
            name = request.POST.get('name', '').strip()
            dept_id = int(request.POST.get('department_id', 0))
            spec = request.POST.get('specialization', '').strip()
            qual = request.POST.get('qualification', '').strip()
            phone = request.POST.get('phone', '').strip()
            email = request.POST.get('email', '').strip()
            days = ", ".join(request.POST.getlist('available_days') or ['Mon-Fri'])
            fee = float(request.POST.get('consult_fee', 0.0))

            if not name:
                err = "Doctor name is required."
            else:
                dept = Department.objects.filter(id=dept_id).first() if dept_id > 0 else None
                if doc_id > 0:
                    doc = get_object_or_404(Doctor, id=doc_id)
                    doc.name = name
                    doc.department = dept
                    doc.specialization = spec
                    doc.qualification = qual
                    doc.phone = phone
                    doc.email = email
                    doc.available_days = days
                    doc.consult_fee = fee
                    doc.save()
                    msg = "Doctor updated successfully."
                else:
                    Doctor.objects.create(
                        name=name, department=dept, specialization=spec, qualification=qual,
                        phone=phone, email=email, available_days=days, consult_fee=fee
                    )
                    msg = "Doctor added successfully."

        elif 'action_department' in request.POST:
            dname = request.POST.get('dept_name', '').strip()
            ddesc = request.POST.get('dept_desc', '').strip()
            if dname:
                Department.objects.update_or_create(name=dname, defaults={'description': ddesc})
                msg = "Department saved successfully."

    q = request.GET.get('q', '').strip()
    dept_filter = int(request.GET.get('dept', 0))

    doctors = Doctor.objects.select_related('department')
    if q:
        doctors = doctors.filter(Q(name__icontains=q) | Q(specialization__icontains=q))
    if dept_filter > 0:
        doctors = doctors.filter(department_id=dept_filter)
    doctors = doctors.order_by('-is_active', 'name')

    departments = Department.objects.annotate(doctor_count=Count('doctors')).order_by('name')

    return render(request, 'hms_app/doctors.html', {
        'doctors': doctors, 'departments': departments, 'q': q, 'dept_filter': dept_filter, 'msg': msg, 'err': err
    })

# ── APPOINTMENTS VIEW ────────────────────────────────────────────────────
@login_required
def appointments_view(request):
    msg = err = None
    today = date.today()

    if request.method == 'POST':
        if 'action_book' in request.POST:
            pid = int(request.POST.get('patient_id', 0))
            did = int(request.POST.get('doctor_id', 0))
            appt_date = request.POST.get('appointment_date') or today.isoformat()
            appt_time = request.POST.get('appointment_time') or '09:00'
            reason = request.POST.get('reason', '').strip()
            priority = request.POST.get('priority', 'Normal')

            if pid <= 0 or did <= 0:
                err = "Please select both a patient and a doctor."
            else:
                pat = get_object_or_404(Patient, id=pid)
                doc = get_object_or_404(Doctor, id=did)
                
                # Compute token number
                max_token = Appointment.objects.filter(doctor=doc, appointment_date=appt_date).aggregate(m=models.Max('token_no'))['m'] or 0
                token_no = max_token + 1

                Appointment.objects.create(
                    patient=pat, doctor=doc, appointment_date=appt_date,
                    appointment_time=appt_time, token_no=token_no, reason=reason,
                    priority=priority, booked_by=request.user
                )
                msg = f"Appointment booked! Token #{token_no} for {pat.full_name}."

        elif 'action_status' in request.POST:
            aid = int(request.POST.get('appointment_id', 0))
            status = request.POST.get('status', 'Waiting')
            if aid > 0:
                appt = get_object_or_404(Appointment, id=aid)
                appt.status = status
                appt.save()
                msg = f"Appointment status updated to {status}."

    date_filter = request.GET.get('date', today.isoformat())
    doc_filter = int(request.GET.get('doctor_id', 0))

    appts = Appointment.objects.select_related('patient', 'doctor', 'doctor__department').filter(appointment_date=date_filter)
    if doc_filter > 0:
        appts = appts.filter(doctor_id=doc_filter)
    appts = appts.order_by('token_no')

    patients = Patient.objects.filter(is_active=True).order_by('full_name')
    doctors = Doctor.objects.filter(is_active=True).order_by('name')

    return render(request, 'hms_app/appointments.html', {
        'appointments': appts, 'patients': patients, 'doctors': doctors,
        'date_filter': date_filter, 'doc_filter': doc_filter, 'msg': msg, 'err': err
    })

# ── MEDICAL RECORDS VIEW ──────────────────────────────────────────────────
@login_required
def records_view(request):
    msg = err = None
    if request.method == 'POST' and 'action_record' in request.POST:
        pid = int(request.POST.get('patient_id', 0))
        did = int(request.POST.get('doctor_id', 0))
        appt_id = int(request.POST.get('appointment_id', 0)) or None
        visit_date = request.POST.get('visit_date') or date.today().isoformat()
        chief = request.POST.get('chief_complaint', '').strip()
        diag = request.POST.get('diagnosis', '').strip()
        icd = request.POST.get('icd_code', '').strip()
        presc = request.POST.get('prescription', '').strip()
        lab = request.POST.get('lab_orders', '').strip()
        follow_up = request.POST.get('follow_up_date') or None

        if pid <= 0 or did <= 0 or not diag:
            err = "Patient, Doctor, and Diagnosis are required."
        else:
            pat = get_object_or_404(Patient, id=pid)
            doc = get_object_or_404(Doctor, id=did)
            appt = Appointment.objects.filter(id=appt_id).first() if appt_id else None

            MedicalRecord.objects.create(
                patient=pat, doctor=doc, appointment=appt, visit_date=visit_date,
                chief_complaint=chief, diagnosis=diag, icd_code=icd,
                prescription=presc, lab_orders=lab, follow_up_date=follow_up
            )
            if appt:
                appt.status = 'Completed'
                appt.save()

            msg = f"Medical Record created for {pat.full_name}."

    q = request.GET.get('q', '').strip()
    records = MedicalRecord.objects.select_related('patient', 'doctor').order_by('-visit_date')
    if q:
        records = records.filter(Q(patient__full_name__icontains=q) | Q(diagnosis__icontains=q) | Q(icd_code__icontains=q))

    patients = Patient.objects.filter(is_active=True).order_by('full_name')
    doctors = Doctor.objects.filter(is_active=True).order_by('name')

    return render(request, 'hms_app/records.html', {
        'records': records, 'patients': patients, 'doctors': doctors, 'q': q, 'msg': msg, 'err': err
    })

# ── PHARMACY INVENTORY VIEW ──────────────────────────────────────────────
@login_required
def pharmacy_view(request):
    msg = err = None
    if request.method == 'POST':
        if 'action_medicine' in request.POST:
            med_id = int(request.POST.get('med_id', 0))
            name = request.POST.get('name', '').strip()
            generic = request.POST.get('generic_name', '').strip()
            cat = request.POST.get('category', '').strip()
            dosage = request.POST.get('dosage_form', 'Tablet')
            strength = request.POST.get('strength', '').strip()
            manuf = request.POST.get('manufacturer', '').strip()
            price = float(request.POST.get('unit_price', 0.0))
            stock = int(request.POST.get('stock_qty', 0))
            reorder = int(request.POST.get('reorder_level', 10))
            exp = request.POST.get('expiry_date') or None
            batch = request.POST.get('batch_no', '').strip()

            if not name:
                err = "Medicine name is required."
            else:
                if med_id > 0:
                    med = get_object_or_404(Medicine, id=med_id)
                    med.name = name
                    med.generic_name = generic
                    med.category = cat
                    med.dosage_form = dosage
                    med.strength = strength
                    med.manufacturer = manuf
                    med.unit_price = price
                    med.stock_qty = stock
                    med.reorder_level = reorder
                    med.expiry_date = exp
                    med.batch_no = batch
                    med.save()
                    msg = "Medicine details updated successfully."
                else:
                    Medicine.objects.create(
                        name=name, generic_name=generic, category=cat, dosage_form=dosage,
                        strength=strength, manufacturer=manuf, unit_price=price, stock_qty=stock,
                        reorder_level=reorder, expiry_date=exp, batch_no=batch
                    )
                    msg = "New medicine added to inventory."

        elif 'action_stock' in request.POST:
            med_id = int(request.POST.get('med_id', 0))
            change = int(request.POST.get('adjust_qty', 0))
            if med_id > 0 and change != 0:
                med = get_object_or_404(Medicine, id=med_id)
                med.stock_qty = max(0, med.stock_qty + change)
                med.save()
                msg = f"Stock level for {med.name} updated."

    q = request.GET.get('q', '').strip()
    low_only = request.GET.get('low_stock') == '1'

    medicines = Medicine.objects.filter(is_active=True)
    if q:
        medicines = medicines.filter(Q(name__icontains=q) | Q(generic_name__icontains=q) | Q(batch_no__icontains=q))
    if low_only:
        medicines = medicines.filter(stock_qty__lte=F('reorder_level'))

    total_count = Medicine.objects.filter(is_active=True).count()
    low_count = Medicine.objects.filter(is_active=True, stock_qty__lte=F('reorder_level')).count()
    out_count = Medicine.objects.filter(is_active=True, stock_qty=0).count()

    return render(request, 'hms_app/pharmacy.html', {
        'medicines': medicines, 'total_count': total_count, 'low_count': low_count,
        'out_count': out_count, 'q': q, 'low_only': low_only, 'msg': msg, 'err': err
    })

# ── ADMISSIONS & WARDS VIEW ──────────────────────────────────────────────
@login_required
def admissions_view(request):
    msg = err = None
    if request.method == 'POST':
        if 'action_admit' in request.POST:
            pid = int(request.POST.get('patient_id', 0))
            did = int(request.POST.get('doctor_id', 0))
            wid = int(request.POST.get('ward_id', 0))
            bed = request.POST.get('bed_no', '').strip()
            diag = request.POST.get('diagnosis_at_admission', '').strip()

            if pid <= 0 or did <= 0 or wid <= 0:
                err = "Patient, Doctor, and Ward selection are required."
            elif Admission.objects.filter(patient_id=pid, status='Admitted').exists():
                err = "Patient is currently already admitted."
            else:
                pat = get_object_or_404(Patient, id=pid)
                doc = get_object_or_404(Doctor, id=did)
                ward = get_object_or_404(Ward, id=wid)

                Admission.objects.create(
                    patient=pat, doctor=doc, ward=ward, bed_no=bed,
                    diagnosis_at_admission=diag, status='Admitted', created_by=request.user
                )
                ward.available_beds = max(0, ward.available_beds - 1)
                ward.save()
                msg = f"Patient {pat.full_name} admitted to {ward.ward_name}."

        elif 'action_discharge' in request.POST:
            aid = int(request.POST.get('admission_id', 0))
            notes = request.POST.get('discharge_notes', '').strip()
            if aid > 0:
                adm = get_object_or_404(Admission, id=aid)
                adm.status = 'Discharged'
                adm.discharge_date = timezone.now()
                adm.discharge_notes = notes
                adm.save()
                if adm.ward:
                    adm.ward.available_beds = min(adm.ward.total_beds, adm.ward.available_beds + 1)
                    adm.ward.save()
                msg = f"Patient {adm.patient.full_name} discharged."

        elif 'action_add_ward' in request.POST:
            wname = request.POST.get('ward_name', '').strip()
            wtype = request.POST.get('ward_type', 'General')
            tbeds = int(request.POST.get('total_beds', 10))
            charge = float(request.POST.get('charge_per_day', 0.0))

            if wname and tbeds > 0:
                Ward.objects.create(ward_name=wname, ward_type=wtype, total_beds=tbeds, available_beds=tbeds, charge_per_day=charge)
                msg = f"Ward {wname} created successfully."

    status_filter = request.GET.get('status', 'Admitted')
    q = request.GET.get('q', '').strip()

    admissions = Admission.objects.select_related('patient', 'doctor', 'ward')
    if status_filter != 'All':
        admissions = admissions.filter(status=status_filter)
    if q:
        admissions = admissions.filter(Q(patient__full_name__icontains=q) | Q(patient__patient_code__icontains=q) | Q(ward__ward_name__icontains=q))

    wards = Ward.objects.all().order_by('ward_name')
    patients = Patient.objects.filter(is_active=True).order_by('full_name')
    doctors = Doctor.objects.filter(is_active=True).order_by('name')

    total_admitted = Admission.objects.filter(status='Admitted').count()
    avail_beds = Ward.objects.aggregate(s=Sum('available_beds'))['s'] or 0
    total_beds = Ward.objects.aggregate(s=Sum('total_beds'))['s'] or 0

    return render(request, 'hms_app/admissions.html', {
        'admissions': admissions, 'wards': wards, 'patients': patients, 'doctors': doctors,
        'status_filter': status_filter, 'q': q, 'total_admitted': total_admitted,
        'avail_beds': avail_beds, 'total_beds': total_beds, 'msg': msg, 'err': err
    })

# ── BILLING & PAYMENTS VIEW ──────────────────────────────────────────────
@login_required
def billing_view(request):
    msg = err = None
    if request.method == 'POST':
        if 'action_create_bill' in request.POST:
            pid = int(request.POST.get('patient_id', 0))
            disc = float(request.POST.get('discount_amount', 0.0))
            tax = float(request.POST.get('tax_amount', 0.0))
            paid = float(request.POST.get('paid_amount', 0.0))

            items_json = request.POST.get('items_json', '[]')
            try:
                items_data = json.loads(items_json)
            except Exception:
                items_data = []

            if pid <= 0 or not items_data:
                err = "Please select a patient and add at least one line item."
            else:
                pat = get_object_or_404(Patient, id=pid)
                bill_no = "INV-" + timezone.now().strftime('%Y%m%d%H%M%S')

                subtotal = sum(float(it.get('price', 0)) * int(it.get('qty', 1)) for it in items_data)
                net_total = max(0.0, subtotal - disc + tax)
                status = 'Paid' if paid >= net_total and net_total > 0 else ('Partial' if paid > 0 else 'Pending')

                bill = Bill.objects.create(
                    patient=pat, bill_number=bill_no, total_amount=subtotal,
                    discount_amount=disc, tax_amount=tax, net_amount=net_total,
                    paid_amount=paid, status=status
                )

                for it in items_data:
                    p = float(it.get('price', 0))
                    q = int(it.get('qty', 1))
                    BillItem.objects.create(bill=bill, item_name=it.get('desc', 'Medical Service'), quantity=q, unit_price=p, total_price=p * q)

                msg = f"Invoice {bill_no} generated successfully."

        elif 'action_pay' in request.POST:
            bid = int(request.POST.get('bill_id', 0))
            add_pay = float(request.POST.get('pay_amount', 0.0))
            if bid > 0 and add_pay > 0:
                bill = get_object_or_404(Bill, id=bid)
                bill.paid_amount = float(bill.paid_amount) + add_pay
                if bill.paid_amount >= float(bill.net_amount):
                    bill.status = 'Paid'
                else:
                    bill.status = 'Partial'
                bill.save()
                msg = f"Payment recorded for Invoice {bill.bill_number}."

    q = request.GET.get('q', '').strip()
    status_filter = request.GET.get('status', 'All')

    bills = Bill.objects.select_related('patient').prefetch_related('items').order_by('-created_at')
    if status_filter != 'All':
        bills = bills.filter(status=status_filter)
    if q:
        bills = bills.filter(Q(bill_number__icontains=q) | Q(patient__full_name__icontains=q))

    patients = Patient.objects.filter(is_active=True).order_by('full_name')

    return render(request, 'hms_app/billing.html', {
        'bills': bills, 'patients': patients, 'q': q, 'status_filter': status_filter, 'msg': msg, 'err': err
    })

# ── REPORTS & ANALYTICS VIEW ──────────────────────────────────────────────
@login_required
def reports_view(request):
    total_rev = Bill.objects.filter(status='Paid').aggregate(s=Sum('paid_amount'))['s'] or 0.0
    total_appts = Appointment.objects.count()
    completed_appts = Appointment.objects.filter(status='Completed').count()
    active_patients = Patient.objects.filter(is_active=True).count()

    dept_stats = Department.objects.annotate(
        doc_count=Count('doctors'),
        record_count=Count('doctors__medical_records')
    )

    top_doctors = Doctor.objects.annotate(appt_count=Count('appointments')).order_by('-appt_count')[:5]
    blood_groups = Patient.objects.values('blood_group').annotate(count=Count('id')).order_by('-count')

    # Monthly revenue simulation for chart
    monthly_labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    monthly_data = [12000, 15000, 18000, 22000, 25000, 28000, 31000, 35000, float(total_rev), 0, 0, 0]

    context = {
        'total_rev': total_rev,
        'total_appts': total_appts,
        'completed_appts': completed_appts,
        'active_patients': active_patients,
        'dept_stats': dept_stats,
        'top_doctors': top_doctors,
        'blood_groups': blood_groups,
        'monthly_labels_json': json.dumps(monthly_labels),
        'monthly_data_json': json.dumps(monthly_data),
    }
    return render(request, 'hms_app/reports.html', context)
