# ============================================================
#  train_model.py
#  Trains a MobileNetV2 transfer learning model on the
#  medicinal plant image dataset.
#
#  USAGE:
#    1. Organise your dataset like this:
#       dataset/
#         African Basil/   ← folder name MUST match PLANT_CLASSES
#           img1.jpg
#           img2.jpg
#           ...
#         Bitter Leaf/
#           ...
#         (one folder per plant, minimum 50 images each)
#
#    2. Run:  python train_model.py
#    3. The trained model is saved to:  model/plant_model.h5
#
#  RECOMMENDED DATASET SOURCES:
#    - PlantNet (https://plantnet.org)
#    - iNaturalist (https://www.inaturalist.org)
#    - Kaggle Medicinal Plants datasets
#    - Your own collected photos (minimum 50 per plant)
# ============================================================

import os
import numpy as np
import tensorflow as tf
from tensorflow.keras.applications import MobileNetV2
from tensorflow.keras.layers import Dense, GlobalAveragePooling2D, Dropout, BatchNormalization
from tensorflow.keras.models import Model
from tensorflow.keras.preprocessing.image import ImageDataGenerator
from tensorflow.keras.callbacks import ModelCheckpoint, EarlyStopping, ReduceLROnPlateau
from tensorflow.keras.optimizers import Adam
import matplotlib.pyplot as plt
from plant_classes import PLANT_CLASSES

# ── Config ────────────────────────────────────────────────────
DATASET_DIR   = 'dataset'
MODEL_DIR     = 'model'
MODEL_PATH    = os.path.join(MODEL_DIR, 'plant_model.h5')
IMG_SIZE      = (224, 224)
BATCH_SIZE    = 32
EPOCHS_FROZEN = 10    # Phase 1: train only top layers
EPOCHS_FINE   = 20    # Phase 2: fine-tune last 30 base layers
LEARNING_RATE = 0.001
NUM_CLASSES   = len(PLANT_CLASSES)

os.makedirs(MODEL_DIR, exist_ok=True)

print(f"Training model for {NUM_CLASSES} plant classes:")
for i, name in enumerate(PLANT_CLASSES):
    print(f"  [{i}] {name}")

# ── Data Augmentation ─────────────────────────────────────────
train_datagen = ImageDataGenerator(
    rescale=1./255,
    validation_split=0.2,
    rotation_range=30,
    width_shift_range=0.2,
    height_shift_range=0.2,
    shear_range=0.15,
    zoom_range=0.2,
    horizontal_flip=True,
    vertical_flip=False,
    brightness_range=[0.8, 1.2],
    fill_mode='nearest'
)

val_datagen = ImageDataGenerator(
    rescale=1./255,
    validation_split=0.2
)

print("\nLoading training data...")
train_generator = train_datagen.flow_from_directory(
    DATASET_DIR,
    target_size=IMG_SIZE,
    batch_size=BATCH_SIZE,
    class_mode='categorical',
    subset='training',
    shuffle=True,
    classes=PLANT_CLASSES
)

val_generator = val_datagen.flow_from_directory(
    DATASET_DIR,
    target_size=IMG_SIZE,
    batch_size=BATCH_SIZE,
    class_mode='categorical',
    subset='validation',
    shuffle=False,
    classes=PLANT_CLASSES
)

print(f"\nTraining samples  : {train_generator.samples}")
print(f"Validation samples: {val_generator.samples}")
print(f"Classes found     : {list(train_generator.class_indices.keys())}")

# ── Build Model (MobileNetV2 Transfer Learning) ───────────────
print("\nBuilding MobileNetV2 model...")

base_model = MobileNetV2(
    input_shape=(*IMG_SIZE, 3),
    include_top=False,
    weights='imagenet'
)
base_model.trainable = False  # Freeze base for Phase 1

x = base_model.output
x = GlobalAveragePooling2D()(x)
x = BatchNormalization()(x)
x = Dense(512, activation='relu')(x)
x = Dropout(0.4)(x)
x = Dense(256, activation='relu')(x)
x = Dropout(0.3)(x)
output = Dense(NUM_CLASSES, activation='softmax')(x)

model = Model(inputs=base_model.input, outputs=output)

# ── Phase 1: Train top layers only ────────────────────────────
print(f"\nPhase 1: Training top layers for {EPOCHS_FROZEN} epochs...")
model.compile(
    optimizer=Adam(learning_rate=LEARNING_RATE),
    loss='categorical_crossentropy',
    metrics=['accuracy']
)

callbacks_phase1 = [
    ModelCheckpoint(MODEL_PATH, monitor='val_accuracy', save_best_only=True, verbose=1),
    EarlyStopping(monitor='val_accuracy', patience=5, restore_best_weights=True, verbose=1),
    ReduceLROnPlateau(monitor='val_loss', factor=0.5, patience=3, min_lr=1e-6, verbose=1)
]

history1 = model.fit(
    train_generator,
    epochs=EPOCHS_FROZEN,
    validation_data=val_generator,
    callbacks=callbacks_phase1,
    verbose=1
)

# ── Phase 2: Fine-tune last 30 layers of base model ──────────
print(f"\nPhase 2: Fine-tuning last 30 base layers for {EPOCHS_FINE} epochs...")
base_model.trainable = True
for layer in base_model.layers[:-30]:
    layer.trainable = False

model.compile(
    optimizer=Adam(learning_rate=LEARNING_RATE / 10),
    loss='categorical_crossentropy',
    metrics=['accuracy']
)

callbacks_phase2 = [
    ModelCheckpoint(MODEL_PATH, monitor='val_accuracy', save_best_only=True, verbose=1),
    EarlyStopping(monitor='val_accuracy', patience=8, restore_best_weights=True, verbose=1),
    ReduceLROnPlateau(monitor='val_loss', factor=0.3, patience=4, min_lr=1e-7, verbose=1)
]

history2 = model.fit(
    train_generator,
    epochs=EPOCHS_FINE,
    validation_data=val_generator,
    callbacks=callbacks_phase2,
    verbose=1
)

# ── Evaluate ──────────────────────────────────────────────────
print("\nEvaluating on validation set...")
loss, accuracy = model.evaluate(val_generator, verbose=0)
print(f"Final Validation Accuracy : {accuracy*100:.2f}%")
print(f"Final Validation Loss     : {loss:.4f}")
print(f"\nModel saved to: {MODEL_PATH}")

# ── Plot training history ─────────────────────────────────────
all_acc  = history1.history['accuracy']  + history2.history['accuracy']
all_val  = history1.history['val_accuracy'] + history2.history['val_accuracy']
all_loss = history1.history['loss'] + history2.history['loss']

fig, (ax1, ax2) = plt.subplots(1, 2, figsize=(14, 5))
ax1.plot(all_acc,  label='Train Accuracy')
ax1.plot(all_val,  label='Val Accuracy')
ax1.axvline(x=len(history1.history['accuracy'])-1, color='r', linestyle='--', label='Fine-tune start')
ax1.set_title('Model Accuracy'); ax1.legend(); ax1.set_xlabel('Epoch')
ax2.plot(all_loss, label='Train Loss')
ax2.set_title('Model Loss'); ax2.legend(); ax2.set_xlabel('Epoch')
plt.tight_layout()
plt.savefig(os.path.join(MODEL_DIR, 'training_history.png'), dpi=120)
print(f"Training plot saved to: {MODEL_DIR}/training_history.png")
