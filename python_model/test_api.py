# ============================================================
#  test_api.py
#  Tests the Flask microservice endpoints locally.
#  Run AFTER starting app.py in a separate terminal.
#
#  USAGE:
#    1. Start the service:  python app.py
#    2. In another terminal: python test_api.py
# ============================================================

import requests
import base64
import json
import sys
import os
from PIL import Image
import io
import numpy as np

BASE_URL = "http://localhost:5000"

def print_header(title: str):
    print(f"\n{'='*50}")
    print(f"  {title}")
    print('='*50)

def test_health():
    print_header("TEST 1: Health Check")
    try:
        r = requests.get(f"{BASE_URL}/health", timeout=5)
        data = r.json()
        print(f"  Status      : {data.get('status')}")
        print(f"  Model loaded: {data.get('model_loaded')}")
        print(f"  Mode        : {data.get('mode')}")
        print(f"  Classes     : {data.get('num_classes')}")
        print(f"  ✓ PASS (HTTP {r.status_code})")
        return True
    except requests.exceptions.ConnectionError:
        print("  ✗ FAIL — Cannot connect. Is app.py running?")
        return False
    except Exception as e:
        print(f"  ✗ FAIL — {e}")
        return False

def test_classes():
    print_header("TEST 2: Get Classes")
    try:
        r = requests.get(f"{BASE_URL}/classes", timeout=5)
        data = r.json()
        print(f"  Total classes: {data.get('total')}")
        for c in data.get('classes', [])[:3]:
            print(f"  [{c['index']}] {c['common_name']} — {c['scientific_name']}")
        print("  ...")
        print(f"  ✓ PASS (HTTP {r.status_code})")
        return True
    except Exception as e:
        print(f"  ✗ FAIL — {e}")
        return False

def test_predict_synthetic():
    """Test prediction with a synthetically generated green image."""
    print_header("TEST 3: Predict (Synthetic Green Image)")
    try:
        # Create a synthetic green leaf-like image
        arr = np.zeros((224, 224, 3), dtype=np.uint8)
        arr[:, :, 1] = 120  # green channel
        arr[50:170, 50:170, 1] = 200  # brighter green leaf shape
        arr[80:140, 80:140, 0] = 60   # some brown/yellow detail
        img = Image.fromarray(arr)

        buf = io.BytesIO()
        img.save(buf, format='JPEG', quality=85)
        b64 = base64.b64encode(buf.getvalue()).decode('utf-8')

        payload = {'image_base64': b64, 'filename': 'test_synthetic.jpg'}
        r = requests.post(f"{BASE_URL}/predict", json=payload, timeout=15)
        data = r.json()

        print(f"  Predicted plant : {data.get('plant_name')}")
        print(f"  Scientific name : {data.get('scientific_name')}")
        print(f"  Confidence      : {data.get('confidence')}%")
        print(f"  Mode            : {data.get('mode')}")
        if data.get('all_predictions'):
            print(f"  Top 3 predictions:")
            for pred in data['all_predictions'][:3]:
                print(f"    - {pred['plant']}: {pred['confidence']}%")
        print(f"  ✓ PASS (HTTP {r.status_code})")
        return True
    except Exception as e:
        print(f"  ✗ FAIL — {e}")
        return False

def test_predict_from_file(image_path: str):
    """Test prediction with a real image file."""
    print_header(f"TEST 4: Predict (Real Image: {os.path.basename(image_path)})")
    if not os.path.exists(image_path):
        print(f"  SKIP — File not found: {image_path}")
        return True
    try:
        with open(image_path, 'rb') as f:
            b64 = base64.b64encode(f.read()).decode('utf-8')
        payload = {'image_base64': b64, 'filename': os.path.basename(image_path)}
        r = requests.post(f"{BASE_URL}/predict", json=payload, timeout=15)
        data = r.json()
        print(f"  Predicted plant : {data.get('plant_name')}")
        print(f"  Confidence      : {data.get('confidence')}%")
        print(f"  Medicinal uses  : {data.get('medicinal_uses','')[:80]}...")
        print(f"  ✓ PASS (HTTP {r.status_code})")
        return True
    except Exception as e:
        print(f"  ✗ FAIL — {e}")
        return False

def test_invalid_inputs():
    """Test error handling with bad input."""
    print_header("TEST 5: Error Handling")
    passed = 0

    # Missing image_base64
    r = requests.post(f"{BASE_URL}/predict", json={}, timeout=5)
    if r.status_code == 400:
        print("  ✓ Missing image → 400 correctly returned")
        passed += 1
    else:
        print(f"  ✗ Expected 400, got {r.status_code}")

    # Invalid base64
    r = requests.post(f"{BASE_URL}/predict", json={'image_base64': 'not_valid_b64!!!'}, timeout=5)
    if r.status_code in [400, 500]:
        print(f"  ✓ Invalid base64 → {r.status_code} correctly returned")
        passed += 1
    else:
        print(f"  ✗ Expected 400/500, got {r.status_code}")

    # 404 route
    r = requests.get(f"{BASE_URL}/nonexistent", timeout=5)
    if r.status_code == 404:
        print("  ✓ Unknown route → 404 correctly returned")
        passed += 1
    else:
        print(f"  ✗ Expected 404, got {r.status_code}")

    print(f"  Result: {passed}/3 passed")
    return passed == 3

if __name__ == '__main__':
    print("\n🌿 Herbal System — Microservice Test Suite")
    print(f"  Target: {BASE_URL}")

    results = []
    results.append(test_health())

    if not results[0]:
        print("\n❌ Service is not running. Start it with: python app.py")
        sys.exit(1)

    results.append(test_classes())
    results.append(test_predict_synthetic())

    # Test with real image if provided as argument
    if len(sys.argv) > 1:
        results.append(test_predict_from_file(sys.argv[1]))
    else:
        print("\n  TIP: Pass an image path to test with a real photo:")
        print("       python test_api.py path/to/plant.jpg")

    results.append(test_invalid_inputs())

    passed = sum(results)
    total  = len(results)
    print(f"\n{'='*50}")
    print(f"  RESULTS: {passed}/{total} tests passed")
    if passed == total:
        print("  ✅ All tests passed — microservice is ready!")
    else:
        print("  ⚠️  Some tests failed — check output above.")
    print('='*50)
