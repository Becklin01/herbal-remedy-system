# ============================================================
#  app.py — Plant Detection Flask Microservice
#  Receives a base64 image from PHP, runs it through the
#  TensorFlow MobileNetV2 model, returns JSON prediction.
#
#  ENDPOINTS:
#    GET  /health    → service health check
#    POST /predict   → classify a plant image
#    GET  /classes   → list all supported plant classes
#
#  START:
#    python app.py
#    (runs on http://localhost:5000)
# ============================================================

import os
import io
import base64
import logging
import numpy as np
from flask import Flask, request, jsonify
from flask_cors import CORS
from PIL import Image, ImageOps
import tensorflow as tf
from plant_classes import PLANT_CLASSES, SCIENTIFIC_NAMES, MEDICINAL_USES

# ── Logging ───────────────────────────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S'
)
logger = logging.getLogger(__name__)

# ── App setup ─────────────────────────────────────────────────
app = Flask(__name__)
CORS(app, origins=['http://localhost', 'http://127.0.0.1', 'http://localhost:80'])

# ── Config ────────────────────────────────────────────────────
MODEL_PATH         = os.path.join('model', 'plant_model.h5')
IMG_SIZE           = (224, 224)
CONFIDENCE_THRESHOLD = 40.0   # Minimum % to return a prediction
MAX_IMAGE_SIZE_MB  = 10
NUM_CLASSES        = len(PLANT_CLASSES)

# ── Load model ────────────────────────────────────────────────
model = None

def load_model():
    global model
    if not os.path.exists(MODEL_PATH):
        logger.warning(f"Model not found at {MODEL_PATH}. Running in demo mode.")
        return False
    try:
        logger.info(f"Loading TensorFlow model from {MODEL_PATH}...")
        model = tf.keras.models.load_model(MODEL_PATH)
        model.predict(np.zeros((1, *IMG_SIZE, 3)))  # warm-up
        logger.info(f"Model loaded successfully. Classes: {NUM_CLASSES}")
        return True
    except Exception as e:
        logger.error(f"Failed to load model: {e}")
        return False

# ── Image preprocessing ───────────────────────────────────────
def preprocess_image(image_data: bytes) -> np.ndarray:
    """
    Convert raw image bytes to a normalised numpy array
    ready for the MobileNetV2 model.
    """
    img = Image.open(io.BytesIO(image_data))

    # Convert to RGB (handles RGBA, grayscale, CMYK, etc.)
    if img.mode != 'RGB':
        img = img.convert('RGB')

    # Resize with padding to preserve aspect ratio
    img = ImageOps.fit(img, IMG_SIZE, Image.Resampling.LANCZOS)

    # Normalise to [0, 1]
    arr = np.array(img, dtype=np.float32) / 255.0

    # Add batch dimension → (1, 224, 224, 3)
    return np.expand_dims(arr, axis=0)


def decode_image(b64_string: str) -> bytes:
    """Strip data URI prefix if present and decode base64."""
    if ',' in b64_string:
        b64_string = b64_string.split(',', 1)[1]
    return base64.b64decode(b64_string)


def demo_prediction(filename: str = '') -> dict:
    """
    Returns a mock prediction when the model is not yet trained.
    Useful during development before the dataset is ready.
    """
    import random
    idx = random.randint(0, NUM_CLASSES - 1)
    confidence = round(random.uniform(55.0, 92.0), 2)
    plant_name = PLANT_CLASSES[idx]
    return {
        'plant_name':      plant_name,
        'scientific_name': SCIENTIFIC_NAMES.get(plant_name, ''),
        'confidence':      confidence,
        'all_predictions': [
            {'plant': PLANT_CLASSES[i], 'confidence': round(random.uniform(1.0, 20.0), 2)}
            for i in range(NUM_CLASSES) if i != idx
        ],
        'medicinal_uses':  MEDICINAL_USES.get(plant_name, ''),
        'mode':            'demo',
        'message':         'Demo mode — model not yet trained. Train the model first using train_model.py.'
    }


# ── Routes ────────────────────────────────────────────────────

@app.route('/health', methods=['GET'])
def health():
    """Health check endpoint — called by PHP to verify service is up."""
    return jsonify({
        'status':      'online',
        'model_loaded': model is not None,
        'model_path':  MODEL_PATH,
        'num_classes': NUM_CLASSES,
        'classes':     PLANT_CLASSES,
        'img_size':    IMG_SIZE,
        'mode':        'inference' if model else 'demo'
    }), 200


@app.route('/classes', methods=['GET'])
def get_classes():
    """Returns all supported plant classes with their details."""
    classes = []
    for i, name in enumerate(PLANT_CLASSES):
        classes.append({
            'index':          i,
            'common_name':    name,
            'scientific_name': SCIENTIFIC_NAMES.get(name, ''),
            'medicinal_uses':  MEDICINAL_USES.get(name, '')
        })
    return jsonify({'classes': classes, 'total': len(classes)}), 200


@app.route('/predict', methods=['POST'])
def predict():
    """
    Main prediction endpoint.

    Accepts JSON body with:
      - image_base64 (string): base64-encoded image
      - filename     (string): original filename (optional, for logging)

    Returns JSON:
      - plant_name      : predicted plant common name
      - scientific_name : scientific name
      - confidence      : confidence percentage (0-100)
      - all_predictions : top-5 predictions with confidence scores
      - medicinal_uses  : brief medicinal info
      - mode            : 'inference' | 'demo'
    """
    # ── Validate request ──────────────────────────────────────
    if not request.is_json:
        return jsonify({'error': 'Request must be JSON with Content-Type: application/json'}), 400

    data     = request.get_json(silent=True) or {}
    b64_image = data.get('image_base64', '')
    filename  = data.get('filename', 'unknown')

    if not b64_image:
        return jsonify({'error': 'image_base64 field is required'}), 400

    # ── Decode image ──────────────────────────────────────────
    try:
        image_bytes = decode_image(b64_image)
    except Exception as e:
        logger.warning(f"Failed to decode base64 image [{filename}]: {e}")
        return jsonify({'error': f'Invalid base64 image data: {str(e)}'}), 400

    # Check file size
    size_mb = len(image_bytes) / (1024 * 1024)
    if size_mb > MAX_IMAGE_SIZE_MB:
        return jsonify({'error': f'Image too large ({size_mb:.1f}MB). Maximum is {MAX_IMAGE_SIZE_MB}MB.'}), 400

    logger.info(f"Predicting plant for: {filename} ({size_mb:.2f}MB)")

    # ── Demo mode (no model loaded) ───────────────────────────
    if model is None:
        logger.info("Model not loaded — returning demo prediction")
        return jsonify(demo_prediction(filename)), 200

    # ── Real prediction ───────────────────────────────────────
    try:
        img_array  = preprocess_image(image_bytes)
        predictions = model.predict(img_array, verbose=0)[0]  # shape: (NUM_CLASSES,)

        # Top-5 predictions
        top5_indices = np.argsort(predictions)[::-1][:5]
        top5 = [
            {
                'plant':      PLANT_CLASSES[i],
                'confidence': round(float(predictions[i]) * 100, 2)
            }
            for i in top5_indices
        ]

        top_index      = int(np.argmax(predictions))
        top_confidence = round(float(predictions[top_index]) * 100, 2)
        top_plant      = PLANT_CLASSES[top_index]

        logger.info(f"Prediction: {top_plant} ({top_confidence:.1f}%) for {filename}")

        # Return unknown if below confidence threshold
        if top_confidence < CONFIDENCE_THRESHOLD:
            return jsonify({
                'plant_name':      'Unknown',
                'scientific_name': '',
                'confidence':      top_confidence,
                'all_predictions': top5,
                'medicinal_uses':  '',
                'mode':            'inference',
                'message':         f'Confidence too low ({top_confidence:.1f}%). Please upload a clearer image.'
            }), 200

        return jsonify({
            'plant_name':      top_plant,
            'scientific_name': SCIENTIFIC_NAMES.get(top_plant, ''),
            'confidence':      top_confidence,
            'all_predictions': top5,
            'medicinal_uses':  MEDICINAL_USES.get(top_plant, ''),
            'mode':            'inference',
            'message':         'OK'
        }), 200

    except Exception as e:
        logger.error(f"Prediction error for {filename}: {e}")
        return jsonify({'error': f'Prediction failed: {str(e)}'}), 500


@app.errorhandler(404)
def not_found(e):
    return jsonify({'error': 'Endpoint not found. Available: /health, /predict, /classes'}), 404

@app.errorhandler(405)
def method_not_allowed(e):
    return jsonify({'error': 'Method not allowed.'}), 405

@app.errorhandler(500)
def server_error(e):
    return jsonify({'error': 'Internal server error.'}), 500


# ── Entry point ───────────────────────────────────────────────
if __name__ == '__main__':
    logger.info("=" * 55)
    logger.info("  Herbal System — Plant Detection Microservice")
    logger.info("=" * 55)
    logger.info(f"  Supported plants : {NUM_CLASSES}")
    logger.info(f"  Model path       : {MODEL_PATH}")
    logger.info(f"  Image size       : {IMG_SIZE}")
    logger.info(f"  Confidence min   : {CONFIDENCE_THRESHOLD}%")
    logger.info("=" * 55)

    load_model()

    if model is None:
        logger.warning("Running in DEMO MODE — predictions are random.")
        logger.warning("To use real predictions, train the model first:")
        logger.warning("  python train_model.py")
    else:
        logger.info("Model ready. Starting Flask server...")

    app.run(
        host='0.0.0.0',
        port=5000,
        debug=False,
        threaded=True
    )
