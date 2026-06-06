# ============================================================
#  download_dataset.py
#  Helper script to download plant images from iNaturalist
#  and organise them into the correct folder structure for
#  training.
#
#  USAGE:
#    python download_dataset.py
#
#  This will create:
#    dataset/
#      Ginger/        ← 80+ images
#      Lemongrass/    ← 80+ images
#      ...
# ============================================================

import os
import time
import requests
from plant_classes import PLANT_CLASSES, SCIENTIFIC_NAMES

DATASET_DIR     = 'dataset'
IMAGES_PER_CLASS = 150          # Target images per plant
REQUEST_DELAY    = 0.5          # Seconds between API calls (be polite)

# iNaturalist API — free, no key required
INAT_API = "https://api.inaturalist.org/v1/observations"

def search_inat(scientific_name: str, per_page: int = 30, page: int = 1) -> list:
    """Search iNaturalist for plant observations with photos."""
    params = {
        'taxon_name':  scientific_name,
        'has[]':       'photos',
        'quality_grade': 'research',
        'per_page':    per_page,
        'page':        page,
        'order':       'votes',
        'order_by':    'votes',
    }
    try:
        r = requests.get(INAT_API, params=params, timeout=15)
        r.raise_for_status()
        return r.json().get('results', [])
    except Exception as e:
        print(f"    API error: {e}")
        return []


def download_image(url: str, save_path: str) -> bool:
    """Download a single image to disk."""
    try:
        r = requests.get(url, timeout=15, stream=True)
        r.raise_for_status()
        with open(save_path, 'wb') as f:
            for chunk in r.iter_content(1024):
                f.write(chunk)
        return True
    except Exception:
        return False


def download_plant(common_name: str, scientific_name: str) -> int:
    """Download images for one plant class. Returns count downloaded."""
    plant_dir = os.path.join(DATASET_DIR, common_name)
    os.makedirs(plant_dir, exist_ok=True)

    existing = len([f for f in os.listdir(plant_dir) if f.endswith(('.jpg','.jpeg','.png'))])
    needed   = IMAGES_PER_CLASS - existing
    if needed <= 0:
        print(f"  ✓ {common_name}: already has {existing} images, skipping.")
        return existing

    print(f"  Downloading {needed} images for {common_name} ({scientific_name})...")
    downloaded = 0
    page = 1

    while downloaded < needed:
        results = search_inat(scientific_name, per_page=30, page=page)
        if not results:
            break

        for obs in results:
            if downloaded >= needed:
                break
            photos = obs.get('photos', [])
            if not photos:
                continue
            photo_url = photos[0].get('url', '').replace('square', 'medium')
            if not photo_url:
                continue

            filename = f"{common_name.replace(' ','_')}_{existing+downloaded+1:04d}.jpg"
            save_path = os.path.join(plant_dir, filename)

            if download_image(photo_url, save_path):
                downloaded += 1
                print(f"    [{downloaded}/{needed}] {filename}", end='\r')
            time.sleep(REQUEST_DELAY)

        page += 1
        if page > 5:  # Max 5 pages per plant
            break

    total = existing + downloaded
    print(f"  ✓ {common_name}: {total} images total ({downloaded} new)           ")
    return total


def create_placeholder_structure():
    """Create empty dataset folders so the user can add images manually."""
    print("\nCreating dataset folder structure...")
    for name in PLANT_CLASSES:
        path = os.path.join(DATASET_DIR, name)
        os.makedirs(path, exist_ok=True)
        readme = os.path.join(path, 'README.txt')
        with open(readme, 'w') as f:
            sci = SCIENTIFIC_NAMES.get(name, '')
            f.write(f"Plant: {name}\n")
            f.write(f"Scientific name: {sci}\n\n")
            f.write("Place JPG/PNG images of this plant in this folder.\n")
            f.write("Minimum 50 images recommended. More = better accuracy.\n\n")
            f.write("Good image sources:\n")
            f.write("  - iNaturalist: https://www.inaturalist.org\n")
            f.write("  - PlantNet: https://plantnet.org\n")
            f.write("  - Kaggle: https://www.kaggle.com/search?q=medicinal+plants\n")
        print(f"  Created: dataset/{name}/")
    print("Done. Add your images then run: python train_model.py")


if __name__ == '__main__':
    print("=" * 55)
    print("  Herbal System — Dataset Downloader")
    print("=" * 55)
    print(f"  Target: {IMAGES_PER_CLASS} images per class")
    print(f"  Classes: {len(PLANT_CLASSES)}")
    print(f"  Total target: ~{IMAGES_PER_CLASS * len(PLANT_CLASSES)} images")
    print("=" * 55)

    choice = input("\nOptions:\n  1. Auto-download from iNaturalist\n  2. Create folder structure only\nChoice (1/2): ").strip()

    if choice == '1':
        print(f"\nDownloading to: {DATASET_DIR}/\n")
        total_downloaded = 0
        for common_name in PLANT_CLASSES:
            sci_name = SCIENTIFIC_NAMES.get(common_name, common_name)
            count = download_plant(common_name, sci_name)
            total_downloaded += count

        print(f"\n{'='*55}")
        print(f"Download complete. Total images: {total_downloaded}")
        print(f"Now run: python train_model.py")
    else:
        create_placeholder_structure()
