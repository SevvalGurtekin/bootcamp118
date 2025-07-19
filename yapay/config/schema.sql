-- ai_edu veritabanı şeması ve admin kullanıcısı ekleme

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    surname VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','teacher','student','parent') NOT NULL,
    status ENUM('pending','active','inactive') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS parents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS diagnoses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL
);

CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    age INT NOT NULL,
    diagnosis_id INT NOT NULL,
    teacher_id INT,
    parent_id INT,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (diagnosis_id) REFERENCES diagnoses(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    FOREIGN KEY (parent_id) REFERENCES parents(id)
);

CREATE TABLE IF NOT EXISTS teacher_student_parent (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    student_id INT NOT NULL,
    parent_id INT NOT NULL,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (parent_id) REFERENCES parents(id)
);

CREATE TABLE IF NOT EXISTS approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    approved_by INT,
    approved_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS student_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    type ENUM('gozlem','test') NOT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- Tanıların eklenmesi
INSERT INTO diagnoses (name) VALUES
('Zihinsel Yetersizlik'),
('İşitme Yetersizliği'),
('Bedensel Yetersizlik'),
('Görme Yetersizliği'),
('Otizm ve Spektrum Bozukluğu/Yaygın Gelişimsel Bozukluk'),
('Öğrenme Güçlüğü (Disleksi, Diskalkuli)'),
('Dil Konuşma Güçlüğü'),
('Dikkat Eksikliği ve Hiperaktivite Bozukluğu'),
('Down Sendromu'),
('Serebral Palsi'),
('Duygu ve Davranış Bozuklukları'),
('Özel Yetenek');

-- Admin kullanıcısı ekle
INSERT INTO users (name, surname, email, password, role, status) VALUES ('Admin', 'User', 'admin@gmail.com', 'BURAYA_HASH', 'admin', 'active')
    ON DUPLICATE KEY UPDATE email=email; 