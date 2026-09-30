from django.urls import path
from . import views

urlpatterns = [
    path('', views.dashboard_view, name='dashboard'),
    path('login/', views.login_view, name='login'),
    path('logout/', views.logout_view, name='logout'),
    
    path('patients/', views.patients_view, name='patients'),
    path('patient/qr/<int:patient_id>/', views.patient_qr_view, name='patient_qr'),
    path('qr/patient/<str:qr_token>/', views.qr_patient_public_view, name='qr_patient_public'),
    
    path('doctors/', views.doctors_view, name='doctors'),
    path('appointments/', views.appointments_view, name='appointments'),
    path('records/', views.records_view, name='records'),
    path('pharmacy/', views.pharmacy_view, name='pharmacy'),
    path('admissions/', views.admissions_view, name='admissions'),
    path('billing/', views.billing_view, name='billing'),
    path('reports/', views.reports_view, name='reports'),
]
