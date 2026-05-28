-- ============================================================
--  HERBAL REMEDY RECOMMENDATION & PLANT DETECTION SYSTEM
--  Database Schema
--  Author  : BECKLIN SAMUEL (ICTU20223544)
--  Engine  : MySQL 5.7+
--  Charset : utf8mb4
-- ============================================================

CREATE DATABASE IF NOT EXISTS herbal_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE herbal_system;

-- ============================================================
-- TABLE 1: users
-- Stores all system users: patients, herbalists, admins
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  full_name     VARCHAR(120)     NOT NULL,
  email         VARCHAR(180)     NOT NULL UNIQUE,
  password_hash VARCHAR(255)     NOT NULL,
  role          ENUM('patient','herbalist','admin') NOT NULL DEFAULT 'patient',
  phone         VARCHAR(20)      DEFAULT NULL,
  profile_photo VARCHAR(255)     DEFAULT NULL,
  is_active     TINYINT(1)       NOT NULL DEFAULT 1,
  is_approved   TINYINT(1)       NOT NULL DEFAULT 0
                COMMENT 'Herbalists require admin approval before login',
  created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_email  (email),
  INDEX idx_role   (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLE 2: herbalist_profiles
-- Extra details for herbalist accounts
-- ============================================================
CREATE TABLE IF NOT EXISTS herbalist_profiles (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED  NOT NULL,
  specialisation  VARCHAR(255)  DEFAULT NULL
                  COMMENT 'e.g. Respiratory, Digestive, Skin conditions',
  bio             TEXT          DEFAULT NULL,
  location        VARCHAR(255)  DEFAULT NULL,
  years_experience INT UNSIGNED DEFAULT 0,
  consultation_fee DECIMAL(10,2) DEFAULT 0.00,
  available_days  VARCHAR(100)  DEFAULT NULL
                  COMMENT 'Comma-separated: Mon,Tue,Wed',
  start_time      TIME          DEFAULT '08:00:00',
  end_time        TIME          DEFAULT '17:00:00',
  rating_avg      DECIMAL(3,2)  DEFAULT 0.00,
  total_reviews   INT UNSIGNED  DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user (user_id),
  CONSTRAINT fk_hp_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLE 3: plant_families
-- e.g. Zingiberaceae, Lamiaceae, Asteraceae
-- ============================================================
CREATE TABLE IF NOT EXISTS plant_families (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  name        VARCHAR(120)  NOT NULL UNIQUE,
  description TEXT          DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLE 4: plants
-- Core medicinal plant database — managed by admin
-- ============================================================
CREATE TABLE IF NOT EXISTS plants (
  id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  common_name      VARCHAR(150)  NOT NULL,
  scientific_name  VARCHAR(150)  NOT NULL,
  local_name       VARCHAR(150)  DEFAULT NULL
                   COMMENT 'Local Cameroonian name if applicable',
  family_id        INT UNSIGNED  DEFAULT NULL,
  description      TEXT          DEFAULT NULL,
  origin           VARCHAR(255)  DEFAULT NULL,
  parts_used       VARCHAR(255)  DEFAULT NULL
                   COMMENT 'e.g. Leaves, Root, Bark, Seeds',
  medicinal_uses   TEXT          NOT NULL
                   COMMENT 'Detailed medicinal benefits',
  preparation      TEXT          DEFAULT NULL
                   COMMENT 'How to prepare the remedy',
  dosage_notes     TEXT          DEFAULT NULL,
  contraindications TEXT         DEFAULT NULL
                   COMMENT 'Risks, warnings, who should avoid',
  toxicity_level   ENUM('none','low','moderate','high') DEFAULT 'none',
  image_filename   VARCHAR(255)  DEFAULT NULL
                   COMMENT 'Stored in assets/images/plants/',
  is_active        TINYINT(1)   NOT NULL DEFAULT 1,
  created_by       INT UNSIGNED  DEFAULT NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_common_name (common_name),
  CONSTRAINT fk_plant_family FOREIGN KEY (family_id)
    REFERENCES plant_families(id) ON DELETE SET NULL,
  CONSTRAINT fk_plant_creator FOREIGN KEY (created_by)
    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLE 5: symptoms
-- Symptom keyword dictionary for the rule engine
-- ============================================================
CREATE TABLE IF NOT EXISTS symptoms (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  name        VARCHAR(120)  NOT NULL UNIQUE
              COMMENT 'e.g. cough, fever, stomach ache',
  category    VARCHAR(80)   DEFAULT NULL
              COMMENT 'e.g. respiratory, digestive, skin',
  keywords    TEXT          DEFAULT NULL
              COMMENT 'Comma-separated synonyms for keyword matching',
  PRIMARY KEY (id),
  INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLE 6: remedies
-- Curated herbal remedy records — managed by admin
-- ============================================================
CREATE TABLE IF NOT EXISTS remedies (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  name         VARCHAR(200)  NOT NULL,
  target_illness VARCHAR(200) NOT NULL
               COMMENT 'Illness or condition this remedy treats',
  ingredients  TEXT          NOT NULL
               COMMENT 'List of plant ingredients',
  preparation  TEXT          NOT NULL
               COMMENT 'Step-by-step preparation instructions',
  dosage       TEXT          DEFAULT NULL,
  effectiveness_notes TEXT   DEFAULT NULL,
  warnings     TEXT          DEFAULT NULL,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  created_by   INT UNSIGNED  DEFAULT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_illness (target_illness),
  CONSTRAINT fk_remedy_creator FOREIGN KEY (created_by)
    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLE 7: symptom_remedy_map
-- Links symptoms to remedies (rule engine pivot table)
-- ============================================================
CREATE TABLE IF NOT EXISTS symptom_remedy_map (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  symptom_id  INT UNSIGNED  NOT NULL,
  remedy_id   INT UNSIGNED  NOT NULL,
  relevance   TINYINT UNSIGNED DEFAULT 5
              COMMENT 'Relevance score 1-10 used by rule engine',
  PRIMARY KEY (id),
  UNIQUE KEY uq_sym_rem (symptom_id, remedy_id),
  CONSTRAINT fk_srm_symptom FOREIGN KEY (symptom_id)
    REFERENCES symptoms(id) ON DELETE CASCADE,
  CONSTRAINT fk_srm_remedy FOREIGN KEY (remedy_id)
    REFERENCES remedies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLE 8: symptom_plant_map
-- Links symptoms directly to plants
-- ============================================================
CREATE TABLE IF NOT EXISTS symptom_plant_map (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  symptom_id  INT UNSIGNED  NOT NULL,
  plant_id    INT UNSIGNED  NOT NULL,
  relevance   TINYINT UNSIGNED DEFAULT 5,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sym_plant (symptom_id, plant_id),
  CONSTRAINT fk_spm_symptom FOREIGN KEY (symptom_id)
    REFERENCES symptoms(id) ON DELETE CASCADE,
  CONSTRAINT fk_spm_plant FOREIGN KEY (plant_id)
    REFERENCES plants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLE 9: search_history
-- Logs every symptom search made by a patient
-- ============================================================
CREATE TABLE IF NOT EXISTS search_history (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED  NOT NULL,
  symptom_input   TEXT          NOT NULL
                  COMMENT 'Raw text typed by the patient',
  matched_symptoms VARCHAR(500) DEFAULT NULL
                  COMMENT 'Comma-separated matched symptom IDs',
  remedy_ids      VARCHAR(500)  DEFAULT NULL
                  COMMENT 'Comma-separated remedy IDs returned',
  gemini_response TEXT          DEFAULT NULL
                  COMMENT 'Full LLM response stored for audit',
  source          ENUM('rules','gemini','hybrid') DEFAULT 'hybrid',
  searched_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_user (user_id),
  INDEX idx_date (searched_at),
  CONSTRAINT fk_sh_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLE 10: plant_scans
-- Logs every plant image uploaded by a patient
-- ============================================================
CREATE TABLE IF NOT EXISTS plant_scans (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED  NOT NULL,
  image_filename  VARCHAR(255)  NOT NULL,
  predicted_plant VARCHAR(150)  DEFAULT NULL,
  confidence      DECIMAL(5,2)  DEFAULT NULL
                  COMMENT 'Model confidence 0.00 to 100.00',
  plant_id        INT UNSIGNED  DEFAULT NULL
                  COMMENT 'Matched plant record if confidence >= threshold',
  model_response  TEXT          DEFAULT NULL
                  COMMENT 'Full JSON from Python microservice',
  is_flagged      TINYINT(1)   DEFAULT 0
                  COMMENT 'Admin can flag low-quality or wrong scans',
  scanned_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_user  (user_id),
  CONSTRAINT fk_ps_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ps_plant FOREIGN KEY (plant_id)
    REFERENCES plants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLE 11: appointments
-- Herbalist booking records
-- ============================================================
CREATE TABLE IF NOT EXISTS appointments (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  patient_id      INT UNSIGNED  NOT NULL,
  herbalist_id    INT UNSIGNED  NOT NULL,
  appointment_date DATE          NOT NULL,
  appointment_time TIME          NOT NULL,
  reason          TEXT          DEFAULT NULL,
  status          ENUM('pending','confirmed','completed','cancelled')
                  NOT NULL DEFAULT 'pending',
  notes           TEXT          DEFAULT NULL
                  COMMENT 'Herbalist notes after consultation',
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_patient    (patient_id),
  INDEX idx_herbalist  (herbalist_id),
  INDEX idx_date       (appointment_date),
  CONSTRAINT fk_apt_patient FOREIGN KEY (patient_id)
    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_apt_herbalist FOREIGN KEY (herbalist_id)
    REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLE 12: reviews
-- Patient reviews of herbalists after appointments
-- ============================================================
CREATE TABLE IF NOT EXISTS reviews (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  appointment_id  INT UNSIGNED  NOT NULL UNIQUE,
  patient_id      INT UNSIGNED  NOT NULL,
  herbalist_id    INT UNSIGNED  NOT NULL,
  rating          TINYINT UNSIGNED NOT NULL
                  COMMENT '1 to 5 stars',
  comment         TEXT          DEFAULT NULL,
  is_approved     TINYINT(1)   DEFAULT 0
                  COMMENT 'Admin must approve before it is public',
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_herbalist (herbalist_id),
  CONSTRAINT fk_rev_appointment FOREIGN KEY (appointment_id)
    REFERENCES appointments(id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_patient FOREIGN KEY (patient_id)
    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_herbalist FOREIGN KEY (herbalist_id)
    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLE 13: audit_log
-- Records every significant admin action for accountability
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_log (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED  DEFAULT NULL,
  action      VARCHAR(100)  NOT NULL
              COMMENT 'e.g. CREATE_PLANT, DELETE_USER, APPROVE_HERBALIST',
  target_table VARCHAR(60)  DEFAULT NULL,
  target_id   INT UNSIGNED  DEFAULT NULL,
  description TEXT          DEFAULT NULL,
  ip_address  VARCHAR(45)   DEFAULT NULL,
  logged_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_user   (user_id),
  INDEX idx_action (action),
  INDEX idx_date   (logged_at),
  CONSTRAINT fk_al_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- TABLE 14: password_resets
-- Stores tokens for password reset emails
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  email       VARCHAR(180)  NOT NULL,
  token       VARCHAR(100)  NOT NULL UNIQUE,
  expires_at  DATETIME     NOT NULL,
  used        TINYINT(1)   DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_token (token),
  INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- SEED DATA — Default admin account
-- Password: Admin@1234 (bcrypt hash — change after first login)
-- ============================================================
INSERT INTO users (full_name, email, password_hash, role, is_active, is_approved)
VALUES (
  'System Administrator',
  'admin@herbal-system.com',
  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Admin@1234
  'admin',
  1,
  1
);


-- ============================================================
-- SEED DATA — Plant families
-- ============================================================
INSERT INTO plant_families (name, description) VALUES
('Zingiberaceae',   'Ginger family — aromatic herbs common in tropical Africa'),
('Lamiaceae',       'Mint family — widely used in herbal medicine worldwide'),
('Asteraceae',      'Daisy family — large family with many medicinal species'),
('Euphorbiaceae',   'Spurge family — several species used in African traditional medicine'),
('Fabaceae',        'Legume family — various species with medicinal applications'),
('Apocynaceae',     'Dogbane family — includes several anti-malarial plants'),
('Rutaceae',        'Citrus family — rich in antioxidants and antimicrobial compounds'),
('Annonaceae',      'Custard apple family — used for fever and parasitic infections'),
('Meliaceae',       'Mahogany family — includes Neem, widely used medicinally'),
('Moraceae',        'Mulberry family — figs and relatives used in traditional medicine');


-- ============================================================
-- SEED DATA — Medicinal plants (10 common Cameroonian plants)
-- ============================================================
INSERT INTO plants
  (common_name, scientific_name, local_name, family_id, description,
   parts_used, medicinal_uses, preparation, dosage_notes,
   contraindications, toxicity_level, is_active, created_by)
VALUES
(
  'Ginger', 'Zingiber officinale', 'Tangawisi',
  1,
  'A widely cultivated rhizomatous plant native to Southeast Asia but extensively grown and used in Cameroon for culinary and medicinal purposes.',
  'Rhizome (root)',
  'Treats cough, cold, nausea, indigestion, stomach cramps, and inflammation. Also used for fever and as an immune booster.',
  'Boil 2-3 slices of fresh ginger root in 2 cups of water for 10 minutes. Strain and drink warm. Honey and lemon may be added.',
  '1 cup 2-3 times daily for adults. Reduce quantity for children.',
  'Avoid in large doses during pregnancy. May interact with blood-thinning medications.',
  'low', 1, 1
),
(
  'Lemongrass', 'Cymbopogon citratus', 'Citronelle',
  2,
  'A tall perennial grass with a strong lemon scent, widely cultivated across Cameroon and used extensively in both cooking and traditional medicine.',
  'Leaves and stems',
  'Used for fever, headache, cough, digestive problems, anxiety, and as an antibacterial and antifungal agent.',
  'Boil a handful of fresh lemongrass leaves in 1 litre of water for 15 minutes. Strain and drink as a tea. Can also be used as steam inhalation for respiratory conditions.',
  '2 cups daily. As steam inhalation, 10-15 minutes once daily.',
  'Generally safe. Avoid excessive quantities during pregnancy.',
  'none', 1, 1
),
(
  'Neem', 'Azadirachta indica', 'Nim',
  9,
  'A fast-growing tree native to South Asia but widely naturalised across Africa. One of the most versatile medicinal plants used in Cameroonian traditional medicine.',
  'Leaves, bark, seeds, oil',
  'Anti-malarial, antibacterial, antifungal, anti-inflammatory. Used for malaria, skin infections, diabetes management, wound healing, and fever.',
  'For malaria/fever: Boil 20 fresh leaves in 1 litre of water for 20 minutes. Strain and drink. For skin: crush leaves into a paste and apply topically.',
  '1 cup of leaf decoction twice daily for up to 7 days. Do not exceed recommended dose.',
  'Not recommended during pregnancy — may cause miscarriage. Avoid in children under 2 years. Do not take large doses internally for extended periods.',
  'moderate', 1, 1
),
(
  'Moringa', 'Moringa oleifera', 'Moringa / Nebeday',
  NULL,
  'Known as the miracle tree, Moringa is one of the most nutrient-dense plants known. It grows widely across Cameroon and is used both as food and medicine.',
  'Leaves, seeds, pods, roots',
  'Nutritional supplement, anti-inflammatory, antioxidant. Used for malnutrition, anemia, high blood pressure, diabetes, wound healing, and general immunity.',
  'Dry and powder the leaves. Mix 1 teaspoon in warm water or food daily. Fresh leaves can be boiled as a vegetable or brewed as tea.',
  '1-2 teaspoons of powder daily. Consult a herbalist for therapeutic doses.',
  'Root and root bark may cause uterine contractions — avoid in pregnancy. Very high doses of root extract may be toxic.',
  'low', 1, 1
),
(
  'African Basil', 'Ocimum gratissimum', 'Efirin / Nchuanwu',
  2,
  'A shrubby aromatic herb native to Africa and Asia. Widely used in Cameroon for respiratory and digestive conditions.',
  'Leaves',
  'Treats cough, fever, malaria, headache, diarrhea, skin infections, and respiratory infections. Strong antimicrobial properties.',
  'Boil a handful of fresh leaves in 2 cups of water for 10 minutes. Strain and drink. Leaves can also be crushed and inhaled for headache and nasal congestion.',
  '1 cup twice daily. Steam inhalation as needed.',
  'Avoid large doses during pregnancy. May lower blood sugar — monitor if diabetic.',
  'low', 1, 1
),
(
  'Bitter Leaf', 'Vernonia amygdalina', 'Ndole',
  3,
  'A widely used medicinal plant in Cameroon, also used as a vegetable in the national dish Ndole. The bitterness comes from active compounds with strong therapeutic properties.',
  'Leaves, stems, roots',
  'Anti-malarial, antipyretic (fever reducer), anti-diabetic, liver tonic. Used for malaria, typhoid, stomach pain, high blood pressure, and loss of appetite.',
  'Squeeze fresh leaves to extract juice. Drink 2 tablespoons of the juice twice daily. Alternatively, boil leaves and drink as a tea.',
  '2 tablespoons of fresh juice twice daily, or 1 cup of decoction twice daily.',
  'Bitter compounds may irritate the stomach in sensitive individuals. Use with caution in pregnancy.',
  'low', 1, 1
),
(
  'Pawpaw / Papaya', 'Carica papaya', 'Pawpaw',
  NULL,
  'A tropical fruit tree widely grown across Cameroon. Both the fruit and leaves have well-documented medicinal uses.',
  'Leaves, seeds, unripe fruit',
  'Anti-malarial (leaves), digestive aid (papain enzyme), anti-parasitic (seeds), wound healing, platelet booster for dengue and malaria.',
  'For malaria: boil 5 fresh papaya leaves in 2 litres of water for 20 minutes. Strain and drink. For digestion: eat ripe papaya fruit or brew dried seeds in hot water.',
  '1 cup of leaf decoction twice daily for malaria treatment. Ripe fruit consumed freely as food.',
  'Green papaya and papaya leaf extract should be avoided during pregnancy due to risk of miscarriage. Seeds in large quantities may be toxic.',
  'low', 1, 1
),
(
  'Garlic', 'Allium sativum', 'Ail',
  NULL,
  'Garlic is one of the most researched and widely used medicinal plants worldwide. It is commonly grown and used in Cameroon for both culinary and therapeutic purposes.',
  'Bulb (cloves)',
  'Antimicrobial, antiviral, antifungal, anti-inflammatory, cardiovascular tonic. Used for cough, cold, hypertension, infections, and immune support.',
  'Crush 2-3 fresh garlic cloves and mix with honey. Take raw on an empty stomach. Can also be boiled in water or milk for a gentler preparation.',
  '2-3 raw cloves daily, or 1 cup of garlic tea once daily.',
  'May cause stomach irritation if taken in large amounts on an empty stomach. Can interact with anticoagulant medications. Avoid excessive consumption before surgery.',
  'none', 1, 1
),
(
  'Eucalyptus', 'Eucalyptus globulus', 'Eucalyptus',
  NULL,
  'A tall tree originally from Australia now widely planted across Cameroon. The leaves contain volatile oils with strong medicinal properties.',
  'Leaves (fresh or dried)',
  'Treats respiratory infections, cough, bronchitis, asthma, nasal congestion. Strong antibacterial and decongestant properties.',
  'Steam inhalation: boil a handful of leaves in water, place head over steam with a towel and inhale for 10-15 minutes. Tea: boil 4-5 leaves in 2 cups of water for 10 minutes, strain and drink.',
  '1 cup of tea twice daily. Steam inhalation once or twice daily as needed.',
  'Do not apply essential oil directly to skin undiluted. Avoid internal use of the essential oil — it is toxic when ingested directly. Use only leaf tea or steam.',
  'low', 1, 1
),
(
  'Turmeric', 'Curcuma longa', 'Safran des Indes',
  1,
  'A rhizomatous plant closely related to ginger, widely used in Cameroon both as a spice and medicinal herb. Known for its bright yellow colour from curcumin.',
  'Rhizome (root)',
  'Anti-inflammatory, antioxidant, antimicrobial. Used for joint pain, arthritis, digestive problems, liver health, wound healing, and skin conditions.',
  'Boil 1 teaspoon of powdered turmeric in 2 cups of water or milk for 10 minutes. Add honey and black pepper (black pepper increases absorption of curcumin). Drink warm.',
  '1 cup twice daily. May also be applied as a paste topically on wounds or skin inflammations.',
  'High doses may cause stomach irritation or worsen acid reflux. Avoid large therapeutic doses during pregnancy. May interact with blood thinners.',
  'none', 1, 1
);


-- ============================================================
-- SEED DATA — Common symptoms
-- ============================================================
INSERT INTO symptoms (name, category, keywords) VALUES
('cough',           'respiratory',  'cough,coughing,dry cough,wet cough,persistent cough,chest cough'),
('fever',           'general',      'fever,high temperature,hot body,chills,temperature'),
('headache',        'neurological', 'headache,head pain,migraine,head ache'),
('stomach ache',    'digestive',    'stomach ache,stomach pain,belly pain,abdominal pain,tummy ache'),
('nausea',          'digestive',    'nausea,nauseous,feeling sick,want to vomit,queasiness'),
('diarrhea',        'digestive',    'diarrhea,diarrhoea,loose stool,running stomach,watery stool'),
('malaria',         'parasitic',    'malaria,suspected malaria,paludisme,chills and fever'),
('sore throat',     'respiratory',  'sore throat,throat pain,painful throat,difficulty swallowing'),
('skin rash',       'skin',         'rash,skin rash,itching,hives,skin irritation,redness'),
('high blood pressure', 'cardiovascular', 'high blood pressure,hypertension,elevated bp,high bp'),
('diabetes',        'metabolic',    'diabetes,high blood sugar,sugar disease'),
('indigestion',     'digestive',    'indigestion,bloating,gas,flatulence,burping,heartburn'),
('joint pain',      'musculoskeletal', 'joint pain,arthritis,swollen joints,knee pain,back pain'),
('wound',           'skin',         'wound,cut,bruise,injury,laceration,sore'),
('cold',            'respiratory',  'cold,common cold,runny nose,stuffy nose,sneezing,flu');


-- ============================================================
-- SEED DATA — Remedies
-- ============================================================
INSERT INTO remedies
  (name, target_illness, ingredients, preparation, dosage, warnings, is_active, created_by)
VALUES
(
  'Ginger and Honey Cough Remedy',
  'Cough, sore throat, cold',
  'Fresh ginger root (3 slices), Honey (2 tablespoons), Lemon juice (1 teaspoon), Water (2 cups)',
  '1. Boil 3 slices of fresh ginger in 2 cups of water for 10 minutes.\n2. Remove from heat and let cool slightly.\n3. Strain into a cup.\n4. Add 2 tablespoons of honey and 1 teaspoon of lemon juice.\n5. Stir well and drink warm.',
  '1 cup 2-3 times daily until symptoms subside.',
  'Not recommended for infants under 1 year (honey risk). Reduce dosage for children under 5.',
  1, 1
),
(
  'Lemongrass Fever Tea',
  'Fever, headache, flu',
  'Fresh lemongrass stalks (4-5 stalks), Water (1 litre), Ginger (optional, 2 slices)',
  '1. Wash and roughly chop the lemongrass stalks.\n2. Boil in 1 litre of water for 15 minutes.\n3. Add ginger slices if available.\n4. Strain and allow to cool slightly.\n5. Drink warm.',
  '2 cups per day. Continue until fever breaks.',
  'Generally safe. If fever persists beyond 3 days, seek medical attention.',
  1, 1
),
(
  'Bitter Leaf Malaria Decoction',
  'Malaria, fever, typhoid',
  'Fresh bitter leaf (Vernonia amygdalina) leaves (15-20 leaves), Water (1 litre)',
  '1. Wash the bitter leaf thoroughly.\n2. Squeeze the leaves with your hands to release the juice.\n3. Mix the squeezed juice with 1 cup of clean water.\n4. Alternatively, boil the leaves in 1 litre of water for 20 minutes and strain.\n5. Drink the juice or decoction.',
  '2 tablespoons of fresh juice twice daily, or 1 cup of decoction twice daily for 5-7 days.',
  'Do not use as a replacement for prescribed anti-malarial medication for confirmed malaria. Seek medical advice for severe symptoms.',
  1, 1
),
(
  'Garlic and Ginger Immune Booster',
  'Cold, cough, general immunity',
  'Garlic cloves (3), Fresh ginger (2 slices), Honey (1 tablespoon), Warm water (1 cup)',
  '1. Crush 3 garlic cloves thoroughly.\n2. Grate or finely chop the ginger.\n3. Mix both with 1 tablespoon of honey in warm water.\n4. Stir well and drink immediately on an empty stomach.',
  'Once daily in the morning on an empty stomach.',
  'May cause stomach discomfort if taken without food by sensitive individuals. Avoid large quantities if on blood-thinning medication.',
  1, 1
),
(
  'Eucalyptus Steam Inhalation',
  'Cough, nasal congestion, bronchitis',
  'Fresh eucalyptus leaves (a large handful), Water (1 litre), Large bowl, Towel',
  '1. Boil 1 litre of water.\n2. Pour into a large heat-safe bowl.\n3. Add a large handful of fresh eucalyptus leaves.\n4. Lean over the bowl at a safe distance.\n5. Cover your head with a towel to trap the steam.\n6. Inhale deeply for 10-15 minutes.',
  'Once or twice daily as needed. Best done before bedtime.',
  'Keep children supervised — hot water is a burn risk. Do not ingest eucalyptus oil directly.',
  1, 1
),
(
  'Moringa Nutritional Tonic',
  'Malnutrition, low immunity, anemia, fatigue',
  'Moringa leaf powder (1 teaspoon) or fresh Moringa leaves (handful), Water or milk (1 cup), Honey to taste',
  '1. If using powder: mix 1 teaspoon of moringa powder into warm water or milk.\n2. If using fresh leaves: boil a handful of leaves in 2 cups of water for 10 minutes and strain.\n3. Add honey to taste.\n4. Drink warm.',
  '1 cup once daily, preferably in the morning.',
  'Avoid root extract during pregnancy. Consult a healthcare provider before using as a supplement for serious medical conditions.',
  1, 1
),
(
  'Turmeric Anti-Inflammatory Tea',
  'Joint pain, arthritis, inflammation, digestive issues',
  'Turmeric powder (1 teaspoon), Milk or water (2 cups), Black pepper (a pinch), Honey (1 tablespoon)',
  '1. Heat 2 cups of milk or water in a pot.\n2. Add 1 teaspoon of turmeric powder.\n3. Add a pinch of black pepper (increases curcumin absorption significantly).\n4. Simmer for 10 minutes stirring continuously.\n5. Strain, add honey, and drink warm.',
  '1 cup twice daily. Best taken morning and evening.',
  'Avoid high doses during pregnancy. May interact with blood-thinning medications. Those with gallstones should consult a doctor before use.',
  1, 1
);


-- ============================================================
-- SEED DATA — Symptom-Remedy mappings
-- ============================================================
INSERT INTO symptom_remedy_map (symptom_id, remedy_id, relevance) VALUES
(1,  1, 10), -- cough       → Ginger Honey Cough Remedy
(8,  1, 9),  -- sore throat → Ginger Honey Cough Remedy
(15, 1, 9),  -- cold        → Ginger Honey Cough Remedy
(2,  2, 10), -- fever       → Lemongrass Fever Tea
(3,  2, 8),  -- headache    → Lemongrass Fever Tea
(15, 2, 7),  -- cold        → Lemongrass Fever Tea
(7,  3, 10), -- malaria     → Bitter Leaf Decoction
(2,  3, 8),  -- fever       → Bitter Leaf Decoction
(1,  4, 8),  -- cough       → Garlic Ginger Immune Booster
(15, 4, 9),  -- cold        → Garlic Ginger Immune Booster
(1,  5, 10), -- cough       → Eucalyptus Steam
(8,  5, 7),  -- sore throat → Eucalyptus Steam
(13, 7, 10), -- joint pain  → Turmeric Tea
(12, 7, 8);  -- indigestion → Turmeric Tea


-- ============================================================
-- SEED DATA — Symptom-Plant mappings
-- ============================================================
INSERT INTO symptom_plant_map (symptom_id, plant_id, relevance) VALUES
(1,  1, 10), -- cough       → Ginger
(1,  3, 7),  -- cough       → Neem
(1,  5, 8),  -- cough       → African Basil
(1,  9, 9),  -- cough       → Eucalyptus
(2,  2, 9),  -- fever       → Lemongrass
(2,  3, 8),  -- fever       → Neem
(2,  7, 8),  -- fever       → Pawpaw
(7,  3, 9),  -- malaria     → Neem
(7,  6, 10), -- malaria     → Bitter Leaf
(7,  7, 8),  -- malaria     → Pawpaw
(3,  2, 8),  -- headache    → Lemongrass
(3,  5, 7),  -- headache    → African Basil
(4,  1, 8),  -- stomach ache→ Ginger
(12, 1, 9),  -- indigestion → Ginger
(12, 10,8),  -- indigestion → Turmeric
(13, 10,10), -- joint pain  → Turmeric
(8,  1, 8),  -- sore throat → Ginger
(8,  8, 9),  -- sore throat → Garlic
(15, 8, 9),  -- cold        → Garlic
(15, 1, 9),  -- cold        → Ginger
(10, 6, 7),  -- high BP     → Bitter Leaf
(10, 4, 8);  -- high BP     → Moringa
