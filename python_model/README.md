# Plant Detection Microservice — Setup Guide

## What This Is
A Flask REST API that runs a TensorFlow MobileNetV2 model to classify
medicinal plant images. It runs separately from XAMPP on port 5000
and is called by the PHP patient portal when a user uploads a plant photo.

---

## Folder Structure
```
python_model/
├── app.py                ← Main Flask API (run this)
├── train_model.py        ← Train the TF model on your dataset
├── download_dataset.py   ← Auto-download training images
├── test_api.py           ← Test the running API
├── plant_classes.py      ← Plant names and info
├── requirements.txt      ← Python dependencies
├── dataset/              ← Training images (created by download script)
│   ├── Ginger/
│   ├── Lemongrass/
│   └── ...
└── model/
    └── plant_model.h5    ← Trained model (created by train_model.py)
```

---

## Step-by-Step Setup

### Step 1 — Install Python
Download Python 3.10 or 3.11 from https://www.python.org/downloads/
During install, tick **"Add Python to PATH"**

### Step 2 — Open a terminal in this folder
```
Right-click inside python_model/ → Open in Terminal
```

### Step 3 — Create a virtual environment
```bash
python -m venv venv
venv\Scripts\activate        # Windows
source venv/bin/activate     # Mac/Linux
```

### Step 4 — Install dependencies
```bash
pip install -r requirements.txt
```
This installs Flask, TensorFlow, Pillow, NumPy etc.
May take 5-10 minutes depending on internet speed.

### Step 5 — Get your training dataset
**Option A — Auto-download (recommended):**
```bash
python download_dataset.py
# Choose option 1 to download from iNaturalist
```

**Option B — Manual:**
- Create folders: `dataset/Ginger/`, `dataset/Lemongrass/` etc.
- Add at least 50 JPG/PNG images per plant to each folder
- Folder names must match exactly: African Basil, Bitter Leaf,
  Eucalyptus, Garlic, Ginger, Lemongrass, Moringa, Neem, Pawpaw, Turmeric

### Step 6 — Train the model
```bash
python train_model.py
```
- Training takes 20-60 minutes depending on your PC
- The trained model is saved to `model/plant_model.h5`
- A training accuracy plot is saved to `model/training_history.png`
- Target validation accuracy: 80%+

### Step 7 — Start the microservice
```bash
python app.py
```
You should see:
```
  Herbal System — Plant Detection Microservice
  Model ready. Starting Flask server...
 * Running on http://0.0.0.0:5000
```

### Step 8 — Test it
```bash
python test_api.py
```
All 5 tests should pass.

---

## Running Both XAMPP and Python Together
1. Start XAMPP → Apache + MySQL
2. Open a second terminal in `python_model/`
3. Activate venv: `venv\Scripts\activate`
4. Run: `python app.py`
5. Open browser: `http://localhost/herbal_systems/`

Both must be running at the same time for plant detection to work.

---

## Demo Mode (No Model Yet)
If `model/plant_model.h5` does not exist, the service runs in
**demo mode** — it returns random plant predictions for testing.
This lets you test the full PHP UI flow before training is complete.

---

## API Endpoints

| Method | Endpoint    | Description                    |
|--------|-------------|-------------------------------|
| GET    | /health     | Check if service is running    |
| POST   | /predict    | Classify a plant image         |
| GET    | /classes    | List all supported plants      |

### POST /predict — Request
```json
{
  "image_base64": "base64_encoded_image_string",
  "filename": "my_plant.jpg"
}
```

### POST /predict — Response
```json
{
  "plant_name": "Ginger",
  "scientific_name": "Zingiber officinale",
  "confidence": 87.43,
  "all_predictions": [
    {"plant": "Ginger",    "confidence": 87.43},
    {"plant": "Turmeric",  "confidence": 8.12},
    {"plant": "Lemongrass","confidence": 2.91}
  ],
  "medicinal_uses": "Treats cough, cold, nausea...",
  "mode": "inference",
  "message": "OK"
}
```

---

## Troubleshooting

**"ModuleNotFoundError: No module named flask"**
→ Make sure venv is activated: `venv\Scripts\activate`

**"Model not found — running in demo mode"**
→ Train the model first: `python train_model.py`

**"Cannot connect to localhost:5000"**
→ Start the service: `python app.py`

**PHP returns "TF model service offline"**
→ Python app.py is not running, or firewall is blocking port 5000

**Low accuracy after training**
→ Add more images (100+ per plant recommended)
→ Make sure images are clear and show the actual plant clearly
