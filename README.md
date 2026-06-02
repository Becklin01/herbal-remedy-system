# Herbal Remedy Recommendation & Plant Detection System
**Author:** BECKLIN SAMUEL | ICTU20223544 | BSc Software Engineering  
**Stack:** PHP + MySQL + Python (TensorFlow) + Gemini API  
**Server:** XAMPP (Windows)

---

## Folder Structure

```
herbal_system/
│
├── config/
│   ├── config.php          ← App settings, API keys, constants
│   └── database.php        ← PDO singleton database connection
│
├── includes/
│   └── helpers.php         ← Auth, flash messages, CSRF, audit log
│
├── assets/
│   ├── css/
│   │   └── style.css       ← Custom styles (built on Bootstrap 5)
│   ├── js/
│   │   └── main.js         ← Shared JS utilities
│   └── images/
│       ├── plants/         ← Admin-uploaded plant reference images
│       └── uploads/        ← Patient-uploaded scan images
│
├── admin/
│   ├── includes/           ← Admin header/footer/sidebar partials
│   └── pages/              ← Admin panel pages
│       ├── dashboard.php
│       ├── plants.php
│       ├── remedies.php
│       ├── users.php
│       ├── appointments.php
│       └── reports.php
│
├── patient/
│   ├── includes/           ← Patient header/footer partials
│   └── pages/              ← Patient portal pages
│       ├── dashboard.php
│       ├── symptom_checker.php
│       ├── plant_detect.php
│       ├── herbalists.php
│       └── history.php
│
├── herbalist/
│   ├── includes/           ← Herbalist header/footer partials
│   └── pages/              ← Herbalist portal pages
│       ├── dashboard.php
│       ├── appointments.php
│       └── profile.php
│
├── python_model/
│   ├── app.py              ← Flask microservice (REST API for TF model)
│   ├── model/              ← Trained TensorFlow .h5 model goes here
│   └── requirements.txt    ← Python dependencies
│
├── database/
│   └── herbal_system.sql   ← Full database schema + seed data
│
├── index.php               ← Landing page (login/register gateway)
├── login.php               ← Login handler
├── register.php            ← Registration handler
├── logout.php              ← Logout handler
└── README.md               ← This file
```

---

## Setup Instructions

### 1. Copy project to XAMPP
```
Copy the herbal_system/ folder to:
C:\xampp\htdocs\herbal_system\
```

### 2. Import the database
1. Open XAMPP → Start **Apache** and **MySQL**
2. Go to `http://localhost/phpmyadmin`
3. Create database named `herbal_system`
4. Click **Import** → Select `database/herbal_system.sql` → Click **Go**

### 3. Configure settings
Open `config/config.php` and update:
- `GEMINI_API_KEY` — paste your key from https://aistudio.google.com
- `DB_PASS` — if your XAMPP MySQL has a password (default is empty)

### 4. Access the system
Open browser and go to:
```
http://localhost/herbal_system/
```

### Default admin login
- **Email:** admin@herbal-system.com  
- **Password:** Admin@1234

### 5. Python microservice (for plant detection)
```bash
cd python_model/
pip install -r requirements.txt
python app.py
```
The model API will run on `http://localhost:5000`

---

## Build Order (Modules)
1. ✅ Database schema + folder structure
2. ✅ Login, registration, logout (all 3 roles)
3. ✅ Admin panel (plants, remedies, users, appointments)
4. ✅ Patient symptom checker (rules + Gemini hybrid)
5. ✅ Patient plant detection (Python microservice)
6. ✅ Herbalist appointment booking
7. ⬜ Python TensorFlow model + Flask API
