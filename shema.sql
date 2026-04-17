-- ============================================================
--  schema.sql — Base de données USTHB Scolarité
-- ============================================================

CREATE DATABASE IF NOT EXISTS usthb_scolarite
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE usthb_scolarite;

-- ── Étudiants ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS etudiants (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(80)  NOT NULL,
    prenom          VARCHAR(80)  NOT NULL,
    email           VARCHAR(120) NOT NULL UNIQUE,
    matricule       VARCHAR(20)  NOT NULL UNIQUE,
    niveau          VARCHAR(30)  NOT NULL DEFAULT 'L1 Info',
    date_naissance  DATE,
    mot_de_passe    VARCHAR(255) NOT NULL,
    actif           TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- ── Enseignants ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS enseignants (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(80)  NOT NULL,
    prenom          VARCHAR(80)  NOT NULL,
    email           VARCHAR(120) NOT NULL UNIQUE,
    grade           VARCHAR(30)  NOT NULL DEFAULT 'Dr.',
    departement     VARCHAR(80)  NOT NULL DEFAULT 'Informatique',
    specialite      VARCHAR(120),
    mot_de_passe    VARCHAR(255) NOT NULL,
    actif           TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- ── Administrateurs ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS admins (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nom             VARCHAR(80)  NOT NULL,
    prenom          VARCHAR(80)  NOT NULL,
    email           VARCHAR(120) NOT NULL UNIQUE,
    service         VARCHAR(120),
    mot_de_passe    VARCHAR(255) NOT NULL,
    actif           TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- ── Modules ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS modules (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(20)  NOT NULL UNIQUE,
    intitule        VARCHAR(120) NOT NULL,
    coefficient     INT          NOT NULL DEFAULT 1,
    niveau          VARCHAR(30)  NOT NULL DEFAULT 'L2 ISIL',
    enseignant_id   INT,
    annee_univ      VARCHAR(10)  NOT NULL DEFAULT '2025/2026',
    FOREIGN KEY (enseignant_id) REFERENCES enseignants(id) ON DELETE SET NULL
);

-- ── Inscriptions (étudiant ↔ module) ─────────────────────
CREATE TABLE IF NOT EXISTS inscriptions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id     INT NOT NULL,
    module_id       INT NOT NULL,
    annee_univ      VARCHAR(10) NOT NULL DEFAULT '2025/2026',
    UNIQUE KEY uq_inscr (etudiant_id, module_id, annee_univ),
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id)  ON DELETE CASCADE,
    FOREIGN KEY (module_id)   REFERENCES modules(id)    ON DELETE CASCADE
);

-- ── Notes ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id     INT NOT NULL,
    module_id       INT NOT NULL,
    enseignant_id   INT,
    note_tp         DECIMAL(4,2) DEFAULT NULL,
    note_td         DECIMAL(4,2) DEFAULT NULL,
    note_exam       DECIMAL(4,2) DEFAULT NULL,
    annee_univ      VARCHAR(10) NOT NULL DEFAULT '2025/2026',
    UNIQUE KEY uq_note (etudiant_id, module_id, annee_univ),
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id)   REFERENCES modules(id)   ON DELETE CASCADE,
    FOREIGN KEY (enseignant_id) REFERENCES enseignants(id) ON DELETE SET NULL
);

-- ── Absences (student absence tracking) ────────────────
CREATE TABLE IF NOT EXISTS absences (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id     INT NOT NULL,
    module_id       INT NOT NULL,
    nombre          INT NOT NULL DEFAULT 0,
    annee_univ      VARCHAR(10) NOT NULL DEFAULT '2025/2026',
    UNIQUE KEY uq_absence (etudiant_id, module_id, annee_univ),
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id)   REFERENCES modules(id)   ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS planning (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id     INT NOT NULL,
    jour            VARCHAR(20) NOT NULL,  -- e.g., 'Lundi', 'Mardi'
    module_name     VARCHAR(255) NOT NULL,
    heure_debut     TIME NOT NULL,
    salle           VARCHAR(50),
    annee_univ      VARCHAR(10) NOT NULL DEFAULT '2025/2026',
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE
);

-- ── Tasks (tâches/assignments) ─────────────────────────────
CREATE TABLE IF NOT EXISTS tasks (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id     INT NOT NULL,
    titre           VARCHAR(255) NOT NULL,
    echeance        DATE,
    priorite        VARCHAR(20) DEFAULT 'NORMAL',  -- HIGH, NORMAL, LOW
    statut          VARCHAR(20) DEFAULT 'PENDING', -- PENDING, COMPLETED
    annee_univ      VARCHAR(10) NOT NULL DEFAULT '2025/2026',
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE
);

-- ============================================================
--  Données de démonstration
-- ============================================================

-- Mot de passe commun : "password123"  (bcrypt hash)
SET @hash = '$2y$12$IQGznSs9ofVhk4R7VzHdge1Q3kN302qUvdUcbgSHiDd3dh7awq5Na';

INSERT IGNORE INTO etudiants (nom, prenom, email, matricule, niveau, date_naissance, mot_de_passe)
VALUES
    ('Karim',  'Ali',   'ali.karim@usthb.dz',   '12345', 'L2 ISIL',  '2002-03-14', @hash),
    ('Houda',  'Leila', 'leila.houda@usthb.dz',  '67899', 'L2 ISIL',  '2002-07-22', @hash),
    ('Bouzid', 'Omar',  'omar.bouzid@usthb.dz',  '34521', 'L3 Info',  '2001-11-05', @hash);

INSERT IGNORE INTO enseignants (nom, prenom, email, grade, departement, specialite, mot_de_passe)
VALUES
    ('Laachemi',  'Salim',  'laachemi@usthb.dz',  'Dr.', 'Informatique',  'Bases de données, Web', @hash),
    ('Hamidi',    'Nadia',  'hamidi@usthb.dz',    'Pr.', 'Math & Info',   'Algorithmique, Maths',  @hash),
    ('Benali',    'Youssef','benali@usthb.dz',     'Dr.', 'Informatique',  'Réseaux',               @hash);

INSERT IGNORE INTO admins (nom, prenom, email, service, mot_de_passe)
VALUES ('Admin', 'Scolarité', 'admin@usthb.dz', 'Scolarité centrale', @hash);

INSERT IGNORE INTO modules (code, intitule, coefficient, niveau, enseignant_id, annee_univ)
VALUES
    ('PWEB', 'Programmation Web',      3, 'L2 ISIL', 1, '2025/2026'),
    ('BD01', 'Bases de données',        3, 'L2 ISIL', 1, '2025/2026'),
    ('ALGO', 'Algorithmique avancée',   4, 'L2 ISIL', 2, '2025/2026'),
    ('RES1', 'Réseaux informatiques',   3, 'L2 ISIL', 3, '2025/2026');

-- Inscriptions
INSERT IGNORE INTO inscriptions (etudiant_id, module_id, annee_univ)
VALUES (1,1,'2025/2026'),(1,2,'2025/2026'),(1,3,'2025/2026'),(1,4,'2025/2026'),
       (2,1,'2025/2026'),(2,2,'2025/2026'),(2,3,'2025/2026'),
       (3,1,'2025/2026'),(3,3,'2025/2026'),(3,4,'2025/2026');

-- Notes
INSERT IGNORE INTO notes (etudiant_id, module_id, note_exam, annee_univ)
VALUES
    (1,1,18.00,'2025/2026'),(1,2,12.50,'2025/2026'),(1,3,15.00,'2025/2026'),(1,4,9.50,'2025/2026'),
    (2,1,14.00,'2025/2026'),(2,2, 8.00,'2025/2026'),(2,3,11.00,'2025/2026'),
    (3,1,16.00,'2025/2026'),(3,3,13.00,'2025/2026'),(3,4,10.00,'2025/2026');

-- Planning (sample for student 1)
INSERT IGNORE INTO planning (etudiant_id, jour, module_name, heure_debut, salle, annee_univ)
VALUES
    (1, 'Lundi', 'Programmation Web', '08:30:00', 'Hall B-12', '2025/2026'),
    (1, 'Mardi', 'Bases de données', '10:00:00', 'Salle 101', '2025/2026'),
    (1, 'Mercredi', 'Algorithmique avancée', '14:00:00', 'Amphi A', '2025/2026');

-- Tasks (sample for student 1)
INSERT IGNORE INTO tasks (etudiant_id, titre, echeance, priorite, statut, annee_univ)
VALUES
    (1, 'Algorithms Assignment 4', '2026-04-11', 'HIGH', 'PENDING', '2025/2026'),
    (1, 'Web Project Submission', '2026-04-15', 'NORMAL', 'PENDING', '2025/2026');
