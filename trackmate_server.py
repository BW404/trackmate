#!/usr/bin/env python3
"""
TrackMate Activity Detection Server
Standalone server with MediaPipe + YOLOv8 + Ollama fallback
Just install requirements and run this single file!

Installation:
    pip install opencv-python-headless==4.8.1.78 mediapipe==0.10.9 ultralytics flask flask-cors pillow requests numpy

    For desktop/Windows:
    pip install opencv-python==4.8.1.78 mediapipe==0.10.9 ultralytics flask flask-cors pillow requests numpy

Usage:
    python trackmate_server.py
    
    Or with custom settings:
    python trackmate_server.py --host 0.0.0.0 --port 5001
"""

import cv2
import numpy as np
import mediapipe as mp
from ultralytics import YOLO
import base64
from flask import Flask, request, jsonify
from flask_cors import CORS
import requests
import time
import os
import argparse
from io import BytesIO
from PIL import Image

# ============================================================================
# CONFIGURATION (Edit these values as needed)
# ============================================================================

# Server Configuration
DEFAULT_HOST = '0.0.0.0'  # Listen on all interfaces (use '127.0.0.1' for local only)
DEFAULT_PORT = 5001

# Ollama Fallback Configuration
OLLAMA_API_URL = os.getenv('OLLAMA_API_URL', 'https://ollama1.bw404.com/api/generate')
OLLAMA_MODEL = os.getenv('OLLAMA_MODEL', 'qwen3-vl:2b')
OLLAMA_TIMEOUT = 45

# Detection Thresholds
CONFIDENCE_THRESHOLD = 0.7  # Use Ollama if confidence below this
PHONE_NEAR_HAND_DISTANCE = 150  # pixels

# Activity Categories
ACTIVITIES = {
    1: "Using phone",
    2: "Working",
    3: "Using phone while working",
    4: "Sleeping",
    5: "Eating",
    6: "Drinking",
    7: "Other"
}

# ============================================================================
# INITIALIZATION
# ============================================================================

app = Flask(__name__)
CORS(app, resources={r"/*": {"origins": "*"}})

print("🔄 Initializing MediaPipe...")
mp_hands = mp.solutions.hands
hands = mp_hands.Hands(
    static_image_mode=True,
    max_num_hands=2,
    min_detection_confidence=0.5,
    min_tracking_confidence=0.5
)
print("✅ MediaPipe ready")

print("🔄 Loading YOLOv8 model (will download if needed)...")
yolo_model = YOLO('yolov8n.pt')  # Nano model - fastest (~6MB)
print("✅ YOLOv8 ready")

# ============================================================================
# IMAGE PROCESSING
# ============================================================================

def decode_base64_image(base64_string):
    """Decode base64 image to OpenCV format"""
    try:
        if ',' in base64_string:
            base64_string = base64_string.split(',')[1]
        
        img_data = base64.b64decode(base64_string)
        img = Image.open(BytesIO(img_data))
        return cv2.cvtColor(np.array(img), cv2.COLOR_RGB2BGR)
    except Exception as e:
        raise ValueError(f"Failed to decode image: {str(e)}")

# ============================================================================
# MEDIAPIPE HAND DETECTION
# ============================================================================

def detect_hands(image):
    """Detect hands and their positions using MediaPipe"""
    image_rgb = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)
    results = hands.process(image_rgb)
    
    hand_info = {
        'detected': False,
        'count': 0,
        'positions': [],
        'gestures': []
    }
    
    if results.multi_hand_landmarks:
        hand_info['detected'] = True
        hand_info['count'] = len(results.multi_hand_landmarks)
        
        h, w, _ = image.shape
        for hand_landmarks in results.multi_hand_landmarks:
            wrist = hand_landmarks.landmark[mp_hands.HandLandmark.WRIST]
            index_tip = hand_landmarks.landmark[mp_hands.HandLandmark.INDEX_FINGER_TIP]
            
            hand_info['positions'].append({
                'wrist': (int(wrist.x * w), int(wrist.y * h)),
                'index_tip': (int(index_tip.x * w), int(index_tip.y * h))
            })
    
    return hand_info

# ============================================================================
# YOLO OBJECT DETECTION
# ============================================================================

def detect_objects(image):
    """Detect objects using YOLOv8"""
    results = yolo_model(image, verbose=False)
    
    detected_objects = {
        'phone': [],
        'laptop': [],
        'keyboard': [],
        'mouse': [],
        'cup': [],
        'bottle': [],
        'food': [],
        'bed': []
    }
    
    # COCO dataset class IDs
    object_map = {
        67: 'phone',      # cell phone
        63: 'laptop',     # laptop
        66: 'keyboard',   # keyboard
        64: 'mouse',      # mouse
        41: 'cup',        # cup
        39: 'bottle',     # bottle
        47: 'food',       # apple
        48: 'food',       # sandwich
        49: 'food',       # orange
        50: 'food',       # broccoli
        51: 'food',       # carrot
        52: 'food',       # hot dog
        53: 'food',       # pizza
        54: 'food',       # donut
        55: 'food',       # cake
        59: 'bed',        # bed
    }
    
    for result in results:
        boxes = result.boxes
        for box in boxes:
            cls = int(box.cls[0])
            conf = float(box.conf[0])
            
            if cls in object_map and conf > 0.4:
                obj_type = object_map[cls]
                x1, y1, x2, y2 = map(int, box.xyxy[0])
                center = ((x1 + x2) // 2, (y1 + y2) // 2)
                
                detected_objects[obj_type].append({
                    'bbox': [x1, y1, x2, y2],
                    'center': center,
                    'confidence': conf
                })
    
    return detected_objects

# ============================================================================
# HYBRID ANALYSIS
# ============================================================================

def is_phone_near_hand(phone_pos, hand_pos, threshold=PHONE_NEAR_HAND_DISTANCE):
    """Check if phone is near hand position"""
    px, py = phone_pos
    hx, hy = hand_pos
    distance = np.sqrt((px - hx) ** 2 + (py - hy) ** 2)
    return distance < threshold

def analyze_hybrid(hand_info, objects):
    """
    Analyze detections to determine activity
    Returns: (category_id, confidence, reasoning)
    """
    
    # Check for phone in hand
    phone_in_hand = False
    if hand_info['detected'] and objects['phone']:
        for hand_pos_data in hand_info['positions']:
            hand_pos = hand_pos_data['wrist']
            for phone in objects['phone']:
                if is_phone_near_hand(phone['center'], hand_pos):
                    phone_in_hand = True
                    break
    
    # Check for work equipment
    has_laptop = len(objects['laptop']) > 0
    has_keyboard = len(objects['keyboard']) > 0
    has_mouse = len(objects['mouse']) > 0
    working = has_laptop or has_keyboard or has_mouse
    
    # Check for eating/drinking
    has_food = len(objects['food']) > 0
    has_drink = len(objects['cup']) > 0 or len(objects['bottle']) > 0
    
    # Check for sleeping
    has_bed = len(objects['bed']) > 0
    
    # Determine activity with confidence
    if phone_in_hand and working:
        return 3, 0.9, "Phone detected near hand with computer equipment visible"
    
    if phone_in_hand and not working:
        return 1, 0.85, "Phone detected near hand, no work equipment"
    
    if working and not phone_in_hand and hand_info['detected']:
        return 2, 0.8, "Hands detected with computer equipment, no phone in hand"
    
    if has_bed and not hand_info['detected']:
        return 4, 0.75, "Bed detected with no hand movement"
    
    if has_food and hand_info['detected']:
        return 5, 0.75, "Food detected with hand movement"
    
    if has_drink and hand_info['detected']:
        return 6, 0.7, "Drink detected with hand movement"
    
    # Low confidence scenarios
    if hand_info['detected']:
        return 7, 0.4, "Hands detected but unclear activity"
    
    return 7, 0.3, "Insufficient detection data"

# ============================================================================
# OLLAMA FALLBACK
# ============================================================================

def fallback_to_ollama(image_base64):
    """Fallback to Ollama AI for complex scenarios"""
    prompt = """Look at this image carefully. What is the person doing RIGHT NOW?

Choose ONE number (1-7):

1 = Using phone (person is HOLDING phone in their hand)
2 = Working (using computer, typing, at desk with NO phone in hand)
3 = Phone + Work (HOLDING phone AND at computer)
4 = Sleeping
5 = Eating
6 = Drinking  
7 = Other

VERY IMPORTANT:
- Look at the HANDS carefully
- If phone is IN HAND = choose 1 or 3
- If phone is on desk but NOT in hand = choose 2
- If person at computer with NO phone in hand = choose 2

Answer: First write the number (1-7), then explain what you see."""
    
    try:
        response = requests.post(
            OLLAMA_API_URL,
            json={
                'model': OLLAMA_MODEL,
                'prompt': prompt,
                'images': [image_base64],
                'stream': False,
                'options': {
                    'temperature': 0.3,
                    'num_predict': 100
                }
            },
            timeout=OLLAMA_TIMEOUT,
            verify=False  # Disable SSL verification
        )
        
        if response.status_code == 200:
            result = response.json()
            ai_response = result.get('response', '').strip()
            
            # Parse category from response
            for i in range(1, 8):
                if ai_response.startswith(str(i)):
                    return i, 0.6, f"Ollama: {ai_response[:150]}"
            
            return 7, 0.5, f"Ollama uncertain: {ai_response[:100]}"
        
    except Exception as e:
        print(f"⚠️  Ollama fallback error: {e}")
    
    return 7, 0.3, "All detection methods failed"

# ============================================================================
# API ENDPOINTS
# ============================================================================

@app.route('/analyze', methods=['POST'])
def analyze_activity():
    """Main endpoint for hybrid activity detection"""
    try:
        data = request.json
        image_base64 = data.get('image', '')
        
        if not image_base64:
            return jsonify({'error': 'No image provided'}), 400
        
        start_time = time.time()
        
        # Decode image
        image = decode_base64_image(image_base64)
        
        # Run hybrid detection (MediaPipe + YOLO)
        hand_info = detect_hands(image)
        objects = detect_objects(image)
        
        # Analyze detections
        category, confidence, reasoning = analyze_hybrid(hand_info, objects)
        
        # If confidence is low, fallback to Ollama
        use_ollama = confidence < CONFIDENCE_THRESHOLD
        if use_ollama:
            category, confidence, reasoning = fallback_to_ollama(image_base64)
            detection_method = "Ollama (fallback)"
        else:
            detection_method = "Hybrid (MediaPipe + YOLO)"
        
        processing_time = time.time() - start_time
        
        response = {
            'success': True,
            'category': category,
            'activity': ACTIVITIES[category],
            'confidence': confidence,
            'reasoning': reasoning,
            'method': detection_method,
            'processing_time': round(processing_time, 3),
            'details': {
                'hands_detected': hand_info['detected'],
                'hand_count': hand_info['count'],
                'objects_detected': {k: len(v) for k, v in objects.items() if v}
            }
        }
        
        return jsonify(response)
    
    except Exception as e:
        print(f"❌ Error: {e}")
        return jsonify({
            'success': False,
            'error': str(e),
            'category': 7,
            'activity': ACTIVITIES[7]
        }), 500


@app.route('/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({
        'status': 'healthy',
        'mediapipe': 'loaded',
        'yolo': 'loaded',
        'ollama_fallback': 'configured',
        'ollama_url': OLLAMA_API_URL,
        'version': '1.0.0'
    })


@app.route('/', methods=['GET'])
def index():
    """Server info endpoint"""
    return jsonify({
        'name': 'TrackMate Activity Detection Server',
        'version': '1.0.0',
        'endpoints': {
            '/analyze': 'POST - Analyze activity from image',
            '/health': 'GET - Health check',
            '/': 'GET - This info page'
        },
        'status': 'running'
    })

# ============================================================================
# MAIN
# ============================================================================

if __name__ == '__main__':
    # Parse command line arguments
    parser = argparse.ArgumentParser(description='TrackMate Activity Detection Server')
    parser.add_argument('--host', default=DEFAULT_HOST, help=f'Host to bind to (default: {DEFAULT_HOST})')
    parser.add_argument('--port', type=int, default=DEFAULT_PORT, help=f'Port to bind to (default: {DEFAULT_PORT})')
    parser.add_argument('--debug', action='store_true', help='Enable debug mode')
    args = parser.parse_args()
    
    print("\n" + "="*70)
    print("🚀 TrackMate Activity Detection Server")
    print("="*70)
    print(f"📡 Host: {args.host}:{args.port}")
    print(f"✅ MediaPipe Hands: Ready")
    print(f"✅ YOLOv8 Model: Ready")
    print(f"🔄 Ollama Fallback: {OLLAMA_API_URL}")
    print(f"⚙️  Confidence Threshold: {CONFIDENCE_THRESHOLD}")
    print("="*70)
    print(f"\n🌐 Server running at http://{args.host}:{args.port}")
    print(f"📊 Health check: http://{args.host}:{args.port}/health")
    print("\nPress Ctrl+C to stop\n")
    
    try:
        app.run(host=args.host, port=args.port, debug=args.debug)
    except KeyboardInterrupt:
        print("\n\n👋 Server stopped")
    except Exception as e:
        print(f"\n❌ Server error: {e}")
