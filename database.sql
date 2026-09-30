-- =============================================================
--  CareTrack Hospital Management System — Full SQL Backend
--  Database: hospital_db
--  Engine:   MySQL 8.0+
-- =============================================================

CREATE DATABASE IF NOT EXISTS hospital_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE hospital_db;

-- -------------------------------------------------------------
-- 1. USERS  (staff login accounts)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id          INT          AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(80)  UNIQUE NOT NULL,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('admin','doctor','receptionist','nurse','pharmacist') NOT NULL,
    full_name   VARCHAR(120) NOT NULL,
    email       VARCHAR(120) UNIQUE,
    phone       VARCHAR(20),
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    last_login  DATETIME,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- -------------------------------------------------------------
-- 2. DEPARTMENTS
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS departments (
    id          INT          AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    head_doctor_id INT,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------------------------------------------
-- 3. DOCTORS
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS doctors (
    id              INT          AUTO_INCREMENT PRIMARY KEY,
    user_id         INT,
    name            VARCHAR(120) NOT NULL,
    department_id   INT,
    specialization  VARCHAR(100),
    qualification   VARCHAR(150),
    phone           VARCHAR(20),
    email           VARCHAR(120),
    available_days  VARCHAR(100),
    consult_fee     DECIMAL(10,2) DEFAULT 0.00,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

ALTER TABLE departments
    ADD CONSTRAINT fk_dept_head
    FOREIGN KEY (head_doctor_id) REFERENCES doctors(id) ON DELETE SET NULL;

-- -------------------------------------------------------------
-- 4. PATIENTS
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS patients (
    id            INT           AUTO_INCREMENT PRIMARY KEY,
    patient_code  VARCHAR(30)   UNIQUE NOT NULL,
    full_name     VARCHAR(120)  NOT NULL,
    dob           DATE,
    gender        ENUM('Male','Female','Other','Prefer not to say'),
    phone         VARCHAR(20),
    email         VARCHAR(120),
    address       TEXT,
    blood_group   ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') DEFAULT 'Unknown',
    emergency_contact_name  VARCHAR(120),
    emergency_contact_phone VARCHAR(20),
    allergies     TEXT,
    chronic_conditions TEXT,
    insurance_provider  VARCHAR(100),
    insurance_policy_no VARCHAR(60),
    qr_token      VARCHAR(64)   UNIQUE NOT NULL,
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_patient_code (patient_code),
    INDEX idx_qr_token     (qr_token),
    INDEX idx_phone        (phone)
);

-- -------------------------------------------------------------
-- 5. WARDS / ROOMS
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS wards (
    id          INT          AUTO_INCREMENT PRIMARY KEY,
    ward_name   VARCHAR(80)  NOT NULL,
    ward_type   ENUM('General','ICU','Maternity','Pediatric','Private','Semi-Private') NOT NULL DEFAULT 'General',
    total_beds  INT          NOT NULL DEFAULT 0,
    available_beds INT       NOT NULL DEFAULT 0,
    charge_per_day DECIMAL(10,2) DEFAULT 0.00,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------------------------------------------
-- 6. ADMISSIONS  (inpatient)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admissions (
    id              INT       AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT       NOT NULL,
    doctor_id       INT       NOT NULL,
    ward_id         INT,
    bed_no          VARCHAR(20),
    admission_date  DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    discharge_date  DATETIME,
    diagnosis_at_admission TEXT,
    status          ENUM('Admitted','Discharged','Transferred') NOT NULL DEFAULT 'Admitted',
    discharge_notes TEXT,
    created_by      INT,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id)  REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id)   REFERENCES doctors(id)  ON DELETE RESTRICT,
    FOREIGN KEY (ward_id)     REFERENCES wards(id)    ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id)    ON DELETE SET NULL,
    INDEX idx_patient_status (patient_id, status)
);

-- -------------------------------------------------------------
-- 7. APPOINTMENTS
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS appointments (
    id                INT      AUTO_INCREMENT PRIMARY KEY,
    patient_id        INT      NOT NULL,
    doctor_id         INT      NOT NULL,
    appointment_date  DATE     NOT NULL,
    appointment_time  TIME     NOT NULL,
    token_no          INT,
    reason            VARCHAR(255),
    status            ENUM('Waiting','In Consultation','Completed','Cancelled','No Show') NOT NULL DEFAULT 'Waiting',
    priority          ENUM('Normal','Urgent','Emergency') NOT NULL DEFAULT 'Normal',
    booked_by         INT,
    notes             TEXT,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id)  ON DELETE CASCADE,
    FOREIGN KEY (doctor_id)  REFERENCES doctors(id)   ON DELETE CASCADE,
    FOREIGN KEY (booked_by)  REFERENCES users(id)     ON DELETE SET NULL,
    INDEX idx_date_doctor   (appointment_date, doctor_id),
    INDEX idx_patient_appt  (patient_id)
);

-- -------------------------------------------------------------
-- 8. MEDICAL RECORDS
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS medical_records (
    id              INT       AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT       NOT NULL,
    doctor_id       INT       NOT NULL,
    appointment_id  INT,
    visit_date      DATE      NOT NULL,
    chief_complaint VARCHAR(255),
    diagnosis       VARCHAR(255),
    icd_code        VARCHAR(20),
    prescription    TEXT,
    lab_orders      TEXT,
    follow_up_date  DATE,
    notes           TEXT,
    is_confidential TINYINT(1) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id)     REFERENCES patients(id)     ON DELETE CASCADE,
    FOREIGN KEY (doctor_id)      REFERENCES doctors(id)      ON DELETE RESTRICT,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    INDEX idx_patient_visit (patient_id, visit_date)
);

-- -------------------------------------------------------------
-- 9. VITAL SIGNS
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS vital_signs (
    id               INT       AUTO_INCREMENT PRIMARY KEY,
    patient_id       INT       NOT NULL,
    record_id        INT,
    recorded_by      INT,
    recorded_at      DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    temperature      DECIMAL(4,1),
    blood_pressure   VARCHAR(20),
    pulse_rate       INT,
    respiration_rate INT,
    spo2             DECIMAL(5,2),
    weight           DECIMAL(6,2),
    height           DECIMAL(6,2),
    bmi              DECIMAL(5,2),
    notes            VARCHAR(255),
    FOREIGN KEY (patient_id)  REFERENCES patients(id)        ON DELETE CASCADE,
    FOREIGN KEY (record_id)   REFERENCES medical_records(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(id)           ON DELETE SET NULL,
    INDEX idx_patient_vitals (patient_id, recorded_at)
);

-- -------------------------------------------------------------
-- 10. MEDICINE INVENTORY
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS medicines (
    id              INT           AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150)  NOT NULL,
    generic_name    VARCHAR(150),
    category        VARCHAR(80),
    dosage_form     ENUM('Tablet','Capsule','Syrup','Injection','Ointment','Drops','Other') DEFAULT 'Tablet',
    strength        VARCHAR(50),
    manufacturer    VARCHAR(100),
    unit_price      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock_qty       INT           NOT NULL DEFAULT 0,
    reorder_level   INT           NOT NULL DEFAULT 10,
    expiry_date     DATE,
    batch_no        VARCHAR(60),
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_medicine_name (name)
);

-- -------------------------------------------------------------
-- 11. PRESCRIPTIONS
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prescriptions (
    id              INT       AUTO_INCREMENT PRIMARY KEY,
    record_id       INT       NOT NULL,
    medicine_id     INT       NOT NULL,
    dosage          VARCHAR(100),
    frequency       VARCHAR(100),
    duration_days   INT,
    quantity        INT       NOT NULL DEFAULT 1,
    dispensed       TINYINT(1) NOT NULL DEFAULT 0,
    dispensed_by    INT,
    dispensed_at    DATETIME,
    notes           VARCHAR(255),
    FOREIGN KEY (record_id)    REFERENCES medical_records(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id)  REFERENCES medicines(id)       ON DELETE RESTRICT,
    FOREIGN KEY (dispensed_by) REFERENCES users(id)           ON DELETE SET NULL
);

-- -------------------------------------------------------------
-- 12. LAB TESTS
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lab_tests (
    id          INT           AUTO_INCREMENT PRIMARY KEY,
    test_name   VARCHAR(150)  NOT NULL,
    category    VARCHAR(80),
    normal_range VARCHAR(100),
    unit        VARCHAR(30),
    price       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    is_active   TINYINT(1)    NOT NULL DEFAULT 1
);

-- -------------------------------------------------------------
-- 13. LAB ORDERS
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lab_orders (
    id              INT       AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT       NOT NULL,
    record_id       INT,
    ordered_by      INT       NOT NULL,
    test_id         INT       NOT NULL,
    ordered_at      DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    result_value    VARCHAR(255),
    result_date     DATETIME,
    result_notes    TEXT,
    status          ENUM('Ordered','Sample Collected','Processing','Resulted','Cancelled') NOT NULL DEFAULT 'Ordered',
    reported_by     INT,
    FOREIGN KEY (patient_id)  REFERENCES patients(id)        ON DELETE CASCADE,
    FOREIGN KEY (record_id)   REFERENCES medical_records(id) ON DELETE SET NULL,
    FOREIGN KEY (ordered_by)  REFERENCES users(id)           ON DELETE RESTRICT,
    FOREIGN KEY (test_id)     REFERENCES lab_tests(id)       ON DELETE RESTRICT,
    FOREIGN KEY (reported_by) REFERENCES users(id)           ON DELETE SET NULL,
    INDEX idx_patient_lab (patient_id, status)
);

-- -------------------------------------------------------------
-- 14. BILLS  (invoice header)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bills (
    id              INT           AUTO_INCREMENT PRIMARY KEY,
    bill_no         VARCHAR(30)   UNIQUE NOT NULL,
    patient_id      INT           NOT NULL,
    admission_id    INT,
    bill_date       DATE          NOT NULL,
    due_date        DATE,
    subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_amount    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    paid_amount     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance         DECIMAL(12,2) GENERATED ALWAYS AS (total_amount - paid_amount) STORED,
    status          ENUM('Draft','Pending','Partial','Paid','Cancelled','Refunded') NOT NULL DEFAULT 'Pending',
    payment_method  ENUM('Cash','Card','UPI','Insurance','Online','Other'),
    insurance_claim TINYINT(1)    NOT NULL DEFAULT 0,
    insurance_ref   VARCHAR(80),
    notes           TEXT,
    created_by      INT,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id)   REFERENCES patients(id)   ON DELETE RESTRICT,
    FOREIGN KEY (admission_id) REFERENCES admissions(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by)   REFERENCES users(id)      ON DELETE SET NULL,
    INDEX idx_bill_patient (patient_id),
    INDEX idx_bill_status  (status)
);

-- -------------------------------------------------------------
-- 15. BILL ITEMS  (line items)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bill_items (
    id          INT           AUTO_INCREMENT PRIMARY KEY,
    bill_id     INT           NOT NULL,
    item_type   ENUM('Consultation','Lab','Medicine','Ward','Surgery','Procedure','Other') NOT NULL,
    description VARCHAR(255)  NOT NULL,
    quantity    INT           NOT NULL DEFAULT 1,
    unit_price  DECIMAL(10,2) NOT NULL,
    amount      DECIMAL(12,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    ref_id      INT,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE,
    INDEX idx_bill_item (bill_id)
);

-- -------------------------------------------------------------
-- 16. PAYMENTS
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
    id              INT           AUTO_INCREMENT PRIMARY KEY,
    bill_id         INT           NOT NULL,
    payment_date    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    amount          DECIMAL(12,2) NOT NULL,
    method          ENUM('Cash','Card','UPI','Insurance','Online','Other') NOT NULL DEFAULT 'Cash',
    transaction_ref VARCHAR(100),
    received_by     INT,
    notes           VARCHAR(255),
    FOREIGN KEY (bill_id)     REFERENCES bills(id) ON DELETE RESTRICT,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
);

-- -------------------------------------------------------------
-- 17. STAFF
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS staff (
    id              INT          AUTO_INCREMENT PRIMARY KEY,
    user_id         INT,
    full_name       VARCHAR(120) NOT NULL,
    role            VARCHAR(80)  NOT NULL,
    department_id   INT,
    phone           VARCHAR(20),
    email           VARCHAR(120),
    joining_date    DATE,
    salary          DECIMAL(12,2),
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- -------------------------------------------------------------
-- 18. AUDIT LOG
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGINT       AUTO_INCREMENT PRIMARY KEY,
    user_id     INT,
    action      VARCHAR(80)  NOT NULL,
    table_name  VARCHAR(80),
    record_id   INT,
    description TEXT,
    ip_address  VARCHAR(45),
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_user  (user_id),
    INDEX idx_audit_table (table_name, record_id),
    INDEX idx_audit_date  (created_at)
);

-- =============================================================
--  VIEWS
-- =============================================================

CREATE OR REPLACE VIEW v_appointments AS
SELECT
    a.id, a.token_no, a.appointment_date, a.appointment_time,
    a.status, a.priority, a.reason,
    p.id AS patient_id, p.patient_code,
    p.full_name AS patient_name, p.phone AS patient_phone,
    d.id AS doctor_id, d.name AS doctor_name,
    dep.name AS department, d.consult_fee
FROM appointments a
JOIN patients  p   ON p.id  = a.patient_id
JOIN doctors   d   ON d.id  = a.doctor_id
LEFT JOIN departments dep ON dep.id = d.department_id;

CREATE OR REPLACE VIEW v_bill_summary AS
SELECT
    b.id, b.bill_no, b.bill_date, b.status,
    b.total_amount, b.paid_amount, b.balance,
    p.patient_code, p.full_name AS patient_name, p.phone AS patient_phone
FROM bills b
JOIN patients p ON p.id = b.patient_id;

CREATE OR REPLACE VIEW v_patient_overview AS
SELECT
    p.*,
    COUNT(DISTINCT a.id)  AS total_appointments,
    COUNT(DISTINCT mr.id) AS total_records,
    COUNT(DISTINCT b.id)  AS total_bills,
    COALESCE(SUM(b.balance),0) AS outstanding_balance,
    adm.id      AS current_admission_id,
    adm.status  AS admission_status,
    w.ward_name AS current_ward
FROM patients p
LEFT JOIN appointments   a   ON a.patient_id   = p.id
LEFT JOIN medical_records mr  ON mr.patient_id  = p.id
LEFT JOIN bills          b   ON b.patient_id   = p.id AND b.status NOT IN ('Cancelled','Refunded')
LEFT JOIN admissions     adm ON adm.patient_id = p.id AND adm.status = 'Admitted'
LEFT JOIN wards          w   ON w.id = adm.ward_id
GROUP BY p.id;

CREATE OR REPLACE VIEW v_low_stock AS
SELECT id, name, generic_name, stock_qty, reorder_level, expiry_date
FROM medicines
WHERE stock_qty <= reorder_level AND is_active = 1;

CREATE OR REPLACE VIEW v_daily_revenue AS
SELECT
    DATE(payment_date) AS revenue_date,
    COUNT(*)           AS transactions,
    SUM(amount)        AS total_collected,
    method
FROM payments
GROUP BY DATE(payment_date), method;

-- =============================================================
--  STORED PROCEDURES
-- =============================================================

DELIMITER $$

CREATE PROCEDURE sp_next_token(
    IN  p_doctor_id INT,
    IN  p_date      DATE,
    OUT p_token     INT
)
BEGIN
    SELECT COALESCE(MAX(token_no), 0) + 1
    INTO   p_token
    FROM   appointments
    WHERE  doctor_id = p_doctor_id AND appointment_date = p_date;
END $$

CREATE PROCEDURE sp_discharge_patient(
    IN p_admission_id INT,
    IN p_notes TEXT
)
BEGIN
    DECLARE v_ward INT;
    SELECT ward_id INTO v_ward FROM admissions WHERE id = p_admission_id;
    UPDATE admissions
    SET status = 'Discharged', discharge_date = NOW(), discharge_notes = p_notes
    WHERE id = p_admission_id;
    IF v_ward IS NOT NULL THEN
        UPDATE wards SET available_beds = available_beds + 1 WHERE id = v_ward;
    END IF;
END $$

CREATE PROCEDURE sp_dispense_medicine(
    IN p_prescription_id INT,
    IN p_dispensed_by    INT
)
BEGIN
    DECLARE v_med_id INT;
    DECLARE v_qty    INT;
    SELECT medicine_id, quantity INTO v_med_id, v_qty
    FROM prescriptions WHERE id = p_prescription_id;
    UPDATE medicines SET stock_qty = stock_qty - v_qty WHERE id = v_med_id;
    UPDATE prescriptions
    SET dispensed = 1, dispensed_by = p_dispensed_by, dispensed_at = NOW()
    WHERE id = p_prescription_id;
END $$

CREATE PROCEDURE sp_recalculate_bill(IN p_bill_id INT)
BEGIN
    UPDATE bills b
    SET subtotal     = (SELECT COALESCE(SUM(amount),0) FROM bill_items WHERE bill_id = p_bill_id),
        total_amount = subtotal - discount + tax,
        paid_amount  = (SELECT COALESCE(SUM(amount),0) FROM payments WHERE bill_id = p_bill_id)
    WHERE b.id = p_bill_id;
    UPDATE bills
    SET status = CASE
        WHEN paid_amount = 0             THEN 'Pending'
        WHEN paid_amount >= total_amount THEN 'Paid'
        ELSE 'Partial'
    END
    WHERE id = p_bill_id;
END $$

DELIMITER ;

-- =============================================================
--  TRIGGERS
-- =============================================================

DELIMITER $$

CREATE TRIGGER trg_after_payment_insert
AFTER INSERT ON payments FOR EACH ROW
BEGIN
    CALL sp_recalculate_bill(NEW.bill_id);
END $$

CREATE TRIGGER trg_after_admission_insert
AFTER INSERT ON admissions FOR EACH ROW
BEGIN
    IF NEW.ward_id IS NOT NULL THEN
        UPDATE wards SET available_beds = available_beds - 1 WHERE id = NEW.ward_id;
    END IF;
END $$

CREATE TRIGGER trg_compute_bmi
BEFORE INSERT ON vital_signs FOR EACH ROW
BEGIN
    IF NEW.weight IS NOT NULL AND NEW.height IS NOT NULL AND NEW.height > 0 THEN
        SET NEW.bmi = NEW.weight / ((NEW.height / 100) * (NEW.height / 100));
    END IF;
END $$

DELIMITER ;

-- =============================================================
--  SEED DATA
-- =============================================================

INSERT INTO departments (name, description) VALUES
('General Medicine',  'Primary outpatient and inpatient care'),
('Cardiology',        'Heart and cardiovascular system'),
('Pediatrics',        'Children and adolescent health'),
('Orthopedics',       'Bones, joints and musculoskeletal'),
('Gynecology',        'Women''s health and maternity'),
('Neurology',         'Brain and nervous system'),
('Ophthalmology',     'Eyes and vision'),
('Dermatology',       'Skin, hair and nails'),
('ENT',               'Ear, Nose and Throat'),
('Radiology',         'Imaging and diagnostics');

INSERT INTO users (username, password, role, full_name, email, phone) VALUES
('admin',       '$2y$10$exampleHashAdmin000000000000000000000000000000000000000', 'admin',         'System Administrator', 'admin@caretrack.local',       '9000000000'),
('dr_anu',      '$2y$10$exampleHashDoctor00000000000000000000000000000000000000', 'doctor',        'Dr. Anu Thomas',       'anu.thomas@caretrack.local',  '9876543210'),
('dr_rahul',    '$2y$10$exampleHashDoctor00000000000000000000000000000000000001', 'doctor',        'Dr. Rahul Menon',      'rahul.menon@caretrack.local', '9876543211'),
('dr_meera',    '$2y$10$exampleHashDoctor00000000000000000000000000000000000002', 'doctor',        'Dr. Meera Nair',       'meera.nair@caretrack.local',  '9876543212'),
('reception',   '$2y$10$exampleHashRecept00000000000000000000000000000000000000', 'receptionist',  'Reception Desk',       'reception@caretrack.local',   '9000000001'),
('nurse1',      '$2y$10$exampleHashNurse000000000000000000000000000000000000000', 'nurse',         'Nurse Priya Das',      'priya.das@caretrack.local',   '9000000002'),
('pharmacist1', '$2y$10$exampleHashPharm00000000000000000000000000000000000000', 'pharmacist',    'Ravi Kumar',           'ravi.kumar@caretrack.local',  '9000000003');

INSERT INTO doctors (user_id, name, department_id, specialization, qualification, phone, email, available_days, consult_fee) VALUES
(2, 'Dr. Anu Thomas',      1, 'General Physician',     'MBBS, MD',             '9876543210', 'anu.thomas@caretrack.local',  'Mon,Tue,Wed,Thu,Fri', 500.00),
(3, 'Dr. Rahul Menon',     2, 'Interventional Cardio', 'MBBS, MD, DM',         '9876543211', 'rahul.menon@caretrack.local', 'Mon,Wed,Fri',         800.00),
(4, 'Dr. Meera Nair',      3, 'Pediatric Medicine',    'MBBS, MD Pediatrics',  '9876543212', 'meera.nair@caretrack.local',  'Tue,Thu,Sat',         600.00),
(NULL,'Dr. Sanjay Iyer',   4, 'Joint Replacement',     'MBBS, MS Ortho',       '9876543213', 'sanjay.iyer@caretrack.local', 'Mon,Thu',             700.00),
(NULL,'Dr. Lakshmi Pillai',5, 'Obstetrics & Gynae',    'MBBS, MS OBG',         '9876543214', 'lakshmi.p@caretrack.local',   'Mon,Tue,Wed,Thu,Fri', 650.00);

INSERT INTO wards (ward_name, ward_type, total_beds, available_beds, charge_per_day) VALUES
('Ward A — General',  'General',     20, 15, 800.00),
('Ward B — General',  'General',     20, 12, 800.00),
('ICU',               'ICU',          10,  6, 5000.00),
('Maternity Ward',    'Maternity',   10,  8, 1500.00),
('Pediatric Ward',    'Pediatric',   10,  7, 1200.00),
('Private Room 101',  'Private',      1,  1, 3000.00),
('Private Room 102',  'Private',      1,  0, 3000.00),
('Semi-Private 201',  'Semi-Private', 2,  2, 2000.00);

INSERT INTO patients (patient_code, full_name, dob, gender, phone, email, address, blood_group, qr_token, emergency_contact_name, emergency_contact_phone) VALUES
('P2600001','Arjun Sharma',   '1985-04-12','Male',  '9811111111','arjun@mail.com', '12, MG Road, Kochi',    'B+','qrtoken0001aabbccdd0001aabbccdd001','Sunita Sharma', '9811111112'),
('P2600002','Priya Krishnan', '1992-08-25','Female','9822222222','priya@mail.com', '45, Park Ave, Thrissur','A+','qrtoken0002aabbccdd0002aabbccdd002','Rajan Krishnan','9822222223'),
('P2600003','Mohammed Ali',   '1978-01-30','Male',  '9833333333',NULL,             '7, Lake View, Calicut', 'O+','qrtoken0003aabbccdd0003aabbccdd003','Fatima Ali',    '9833333334'),
('P2600004','Aisha Banu',     '2010-11-15','Female','9844444444',NULL,             '23, Hill Road, Kollam', 'AB+','qrtoken0004aabbccdd0004aabbccdd004','Raza Banu',     '9844444445'),
('P2600005','Deepa Menon',    '1995-06-07','Female','9855555555','deepa@mail.com', '9, Nehru Nagar, TVM',   'A-','qrtoken0005aabbccdd0005aabbccdd005','Suresh Menon',  '9855555556');

INSERT INTO lab_tests (test_name, category, normal_range, unit, price) VALUES
('Complete Blood Count (CBC)',  'Haematology',   'Varies',              '',       350.00),
('Blood Glucose (Fasting)',     'Biochemistry',  '70-100',              'mg/dL',  120.00),
('HbA1c',                      'Biochemistry',  '< 5.7%',              '%',      450.00),
('Lipid Profile',              'Biochemistry',  'Varies',              'mg/dL',  550.00),
('Liver Function Test (LFT)',   'Biochemistry',  'Varies',              '',       600.00),
('Kidney Function Test (KFT)',  'Biochemistry',  'Varies',              '',       600.00),
('Thyroid Profile (TSH)',       'Endocrinology', '0.4-4.0',            'mIU/L',  350.00),
('Urine Routine & Microscopy', 'Microbiology',  'Varies',              '',       150.00),
('X-Ray Chest PA',             'Radiology',     'Normal',              '',       400.00),
('ECG',                        'Cardiology',    'Normal Sinus Rhythm', '',       250.00),
('Echocardiogram',             'Cardiology',    'EF > 55%',            '',      1500.00),
('MRI Brain',                  'Radiology',     'Normal',              '',      5000.00),
('COVID-19 RT-PCR',            'Microbiology',  'Negative',            '',       800.00),
('Blood Culture & Sensitivity','Microbiology',  'No Growth',           '',       700.00),
('Dengue NS1 Antigen',         'Serology',      'Negative',            '',       600.00);

INSERT INTO medicines (name, generic_name, category, dosage_form, strength, manufacturer, unit_price, stock_qty, reorder_level, expiry_date) VALUES
('Paracetamol 500mg',   'Paracetamol',  'Analgesic',         'Tablet',    '500mg',   'Sun Pharma',    2.50,  500, 50, '2027-12-31'),
('Amoxicillin 500mg',   'Amoxicillin',  'Antibiotic',        'Capsule',   '500mg',   'Cipla',         8.00,  200, 30, '2027-06-30'),
('Metformin 500mg',     'Metformin',    'Anti-diabetic',     'Tablet',    '500mg',   'Dr Reddys',     3.50,  300, 40, '2027-08-31'),
('Atorvastatin 10mg',   'Atorvastatin', 'Anti-lipid',        'Tablet',    '10mg',    'Lupin',         6.00,  150, 25, '2027-10-31'),
('Omeprazole 20mg',     'Omeprazole',   'PPI',               'Capsule',   '20mg',    'Alkem',         5.50,  250, 30, '2027-09-30'),
('Azithromycin 500mg',  'Azithromycin', 'Antibiotic',        'Tablet',    '500mg',   'Abbott',       18.00,  100, 20, '2026-12-31'),
('Amlodipine 5mg',      'Amlodipine',   'Anti-hypertensive', 'Tablet',    '5mg',     'Cipla',         4.00,  200, 25, '2027-11-30'),
('Levothyroxine 50mcg', 'Levothyroxine','Thyroid',           'Tablet',    '50mcg',   'GSK',           9.00,   80, 15, '2027-07-31'),
('Ceftriaxone 1g',      'Ceftriaxone',  'Antibiotic',        'Injection', '1g',      'Pfizer',       90.00,   60, 10, '2026-10-31'),
('Insulin Regular',     'Human Insulin','Anti-diabetic',     'Injection', '40IU/mL', 'Novo Nordisk', 180.00,   40,  8, '2026-11-30'),
('Salbutamol Inhaler',  'Salbutamol',   'Bronchodilator',    'Other',     '100mcg',  'GSK',          210.00,   30,  5, '2027-03-31'),
('Pantoprazole 40mg',   'Pantoprazole', 'PPI',               'Tablet',    '40mg',    'Sun Pharma',    7.00,  180, 25, '2027-09-30'),
('Ibuprofen 400mg',     'Ibuprofen',    'NSAID',             'Tablet',    '400mg',   'Cipla',         4.50,  220, 30, '2027-08-31'),
('Cetirizine 10mg',     'Cetirizine',   'Antihistamine',     'Tablet',    '10mg',    'Mankind',       3.00,  160, 20, '2028-01-31'),
('Ondansetron 4mg',     'Ondansetron',  'Antiemetic',        'Tablet',    '4mg',     'Cipla',        12.00,  120, 20, '2027-06-30');
