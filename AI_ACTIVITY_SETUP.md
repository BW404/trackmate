# TrackMate AI Activity Detection Setup Guide

## Overview
This guide will help you set up AI-powered activity detection using Ollama and the LLaVA vision model to monitor user activities through the camera.

## Prerequisites

### 1. Install Python Dependencies
```bash
pip install flask flask-cors pillow requests
```

### 2. Install Ollama
- Download and install Ollama from: https://ollama.ai
- Or use your existing Ollama installation

### 3. Download LLaVA Model
```bash
ollama pull llava:7b
```

## Configuration

### 1. Update Ollama Server URL
Edit `ai_server.py` and update the Ollama API endpoint:

```python
# Line 12-13
OLLAMA_API = "http://YOUR_OLLAMA_SERVER_IP:11433/api/generate"
MODEL_NAME = "llava:7b"
```

**Current Configuration:**
- Server: `http://100.105.118.56:11433/api/generate`
- Model: `llava:7b`

### 2. Database Setup
Run the SQL script to create the activity logs table:

```bash
# Using MySQL command line
mysql -u root -p trackmate < database/activity_logs.sql

# Or import via phpMyAdmin
# Navigate to phpMyAdmin -> trackmate database -> Import -> Select activity_logs.sql
```

### 3. Check PHP cURL Extension
Make sure PHP cURL is enabled in `php.ini`:

```ini
extension=curl
```

## Running the System

### Step 1: Start Ollama Server
If Ollama is not running as a service:

```bash
ollama serve
```

### Step 2: Start Python AI Server
```bash
# Navigate to TrackMate directory
cd e:\XAMPP\htdocs\trackmate

# Run the AI server
python ai_server.py
```

You should see:
```
============================================================
TrackMate AI Activity Detection Server
============================================================
Model: llava:7b
Ollama API: http://100.105.118.56:11433/api/generate
Server: http://localhost:5000
============================================================

Server starting...
```

### Step 3: Start XAMPP
- Start Apache
- Start MySQL

### Step 4: Access TrackMate
- Open browser: `http://localhost/trackmate`
- Login to your account
- Navigate to Camera Monitoring page

### Step 5: Enable AI Detection
1. Click the camera toggle to start camera feed
2. Enable "AI Activity Detection" toggle
3. The system will analyze frames every 10 seconds

## Activity Categories

The AI can detect 7 types of activities:

1. 📱 **Using phone** - Actively holding/using smartphone
2. 💻 **Working** - Using computer/laptop/keyboard
3. 📱💻 **Using phone while working** - Both phone in hand AND at computer
4. 😴 **Sleeping** - Eyes closed, lying down, resting
5. 🍽️ **Eating** - Consuming food, holding utensils
6. 🥤 **Drinking** - Holding cup/glass/bottle
7. 🤷 **Other** - Any other activity

## How It Works

### Process Flow:
1. **Camera Capture**: JavaScript captures frame from webcam every 10 seconds
2. **Send to PHP**: Frame sent to `api/analyze-activity.php`
3. **Forward to AI**: PHP forwards image to Python Flask server (port 5000)
4. **AI Analysis**: Flask sends image to Ollama with LLaVA model
5. **Classification**: AI analyzes and classifies the activity
6. **Database Log**: Activity is logged in `activity_logs` table
7. **Display Result**: Activity displayed in real-time on camera page

### Performance Optimization:
- Images resized to 320x240 pixels for faster processing
- JPEG compression at 85% quality
- Streaming response from Ollama for better feedback
- 180-second timeout for analysis

## Troubleshooting

### Python Server Not Starting
**Error**: `ModuleNotFoundError`
**Solution**: Install missing dependencies
```bash
pip install flask flask-cors pillow requests
```

### Cannot Connect to Ollama
**Error**: "Cannot connect to Ollama server"
**Solution**: 
- Check if Ollama is running: `ollama list`
- Verify the IP address in `ai_server.py`
- Check firewall settings

### AI Detection Shows Error
**Error**: "Connection error - Make sure Python server is running"
**Solution**:
- Verify Python server is running on port 5000
- Check console for errors: `http://localhost:5000/health`

### Database Error
**Error**: "Activity detected but not logged to database"
**Solution**:
- Run `database/activity_logs.sql` to create the table
- Check MySQL is running
- Verify database connection in `config/config.php`

### Slow Analysis
**Issue**: Takes too long to analyze
**Solution**:
- Reduce image size in `camera.js` (currently 320x240)
- Use faster Ollama model if available
- Check network latency to Ollama server

## API Endpoints

### Python Flask Server (Port 5000)

#### Health Check
```
GET http://localhost:5000/health
```
Response:
```json
{
  "status": "running",
  "model": "llava:7b",
  "timestamp": "2024-12-24T12:00:00"
}
```

#### Analyze Activity
```
POST http://localhost:5000/analyze
Content-Type: application/json

{
  "image": "data:image/jpeg;base64,..."
}
```
Response:
```json
{
  "success": true,
  "activity": "2. Working 💻",
  "category": 2,
  "description": "Person is sitting at desk using computer...",
  "timestamp": "2024-12-24T12:00:00"
}
```

### PHP API (TrackMate)

#### Analyze & Log Activity
```
POST http://localhost/trackmate/api/analyze-activity.php
Content-Type: application/json

{
  "image": "data:image/jpeg;base64,..."
}
```
Response:
```json
{
  "success": true,
  "activity": "2. Working 💻",
  "category": 2,
  "log_id": 123,
  "timestamp": "2024-12-24T12:00:00+00:00"
}
```

## Customization

### Change Analysis Interval
Edit `camera.js` line 293:
```javascript
// Analyze frame every 10 seconds (10000 ms)
aiDetectionInterval = setInterval(() => {
    analyzeCurrentFrame();
}, 10000); // Change this value
```

### Change Image Quality
Edit `camera.js` line 314:
```javascript
const imageData = canvas.toDataURL('image/jpeg', 0.8); // 0.8 = 80% quality
```

### Modify Activity Categories
Edit `ai_server.py` to add/modify categories in the prompt and parsing logic.

## Security Considerations

1. **Network Access**: The AI server runs on `0.0.0.0` (all interfaces)
   - For production, bind to localhost only: `app.run(host='127.0.0.1')`

2. **Authentication**: The PHP API checks user session
   - Camera page requires login

3. **Data Privacy**: Images are processed but not stored
   - Only activity classification is logged

4. **CORS**: Currently allows all origins
   - For production, restrict CORS in `ai_server.py`

## Advanced Configuration

### Use Different Ollama Model
```python
# In ai_server.py
MODEL_NAME = "llava:13b"  # Larger, more accurate but slower
# or
MODEL_NAME = "llava:34b"  # Even larger
```

### Remote Ollama Server
If Ollama is on a different machine:
```python
OLLAMA_API = "http://192.168.1.100:11434/api/generate"
```

### Adjust Timeout
```python
REQUEST_TIMEOUT = 300  # 5 minutes for slower systems
```

## Support

For issues or questions:
1. Check console logs in browser (F12)
2. Check Python server terminal output
3. Check Apache error logs
4. Verify all services are running

## File Structure
```
trackmate/
├── ai_server.py              # Python Flask AI server
├── api/
│   └── analyze-activity.php  # PHP API endpoint
├── assets/
│   └── js/
│       └── camera.js         # Camera & AI detection logic
├── camera.html               # Camera monitoring page
├── database/
│   └── activity_logs.sql     # Database schema
└── AI_ACTIVITY_SETUP.md      # This file
```
