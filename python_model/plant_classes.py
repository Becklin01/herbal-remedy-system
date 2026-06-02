# ============================================================
#  plant_classes.py
#  Maps model output class index → plant common name
#  Must match the order used when training the model.
#  If you retrain with more plants, add them here in the
#  same order as your training directory folders.
# ============================================================

PLANT_CLASSES = [
    "African Basil",       # 0  — Ocimum gratissimum
    "Bitter Leaf",         # 1  — Vernonia amygdalina
    "Eucalyptus",          # 2  — Eucalyptus globulus
    "Garlic",              # 3  — Allium sativum
    "Ginger",              # 4  — Zingiber officinale
    "Lemongrass",          # 5  — Cymbopogon citratus
    "Moringa",             # 6  — Moringa oleifera
    "Neem",                # 7  — Azadirachta indica
    "Pawpaw",              # 8  — Carica papaya
    "Turmeric",            # 9  — Curcuma longa
]

# Scientific name lookup
SCIENTIFIC_NAMES = {
    "African Basil": "Ocimum gratissimum",
    "Bitter Leaf":   "Vernonia amygdalina",
    "Eucalyptus":    "Eucalyptus globulus",
    "Garlic":        "Allium sativum",
    "Ginger":        "Zingiber officinale",
    "Lemongrass":    "Cymbopogon citratus",
    "Moringa":       "Moringa oleifera",
    "Neem":          "Azadirachta indica",
    "Pawpaw":        "Carica papaya",
    "Turmeric":      "Curcuma longa",
}

# Brief medicinal use for each plant (shown when DB match not found)
MEDICINAL_USES = {
    "African Basil": "Treats cough, fever, malaria, headache, diarrhea and respiratory infections.",
    "Bitter Leaf":   "Anti-malarial, antipyretic, anti-diabetic and liver tonic.",
    "Eucalyptus":    "Respiratory infections, cough, bronchitis, asthma and nasal congestion.",
    "Garlic":        "Antimicrobial, antiviral, cardiovascular tonic. Used for cough, cold and hypertension.",
    "Ginger":        "Treats cough, cold, nausea, indigestion, stomach cramps and inflammation.",
    "Lemongrass":    "Fever, headache, cough, digestive problems and antibacterial agent.",
    "Moringa":       "Nutritional supplement, anti-inflammatory. Used for malnutrition and anemia.",
    "Neem":          "Anti-malarial, antibacterial, antifungal. Used for malaria, skin infections and fever.",
    "Pawpaw":        "Anti-malarial, digestive aid, platelet booster for dengue and malaria.",
    "Turmeric":      "Anti-inflammatory, antioxidant. Used for joint pain, arthritis and digestive problems.",
}
