# TrackMate AI Activity Detection - PHP Only Setup

## ✨ Overview
AI-powered activity detection using **PHP + Ollama** (No Python required!)

## 📋 Prerequisites

### 1. Ollama Installation
- Download from: https://ollama.ai
- Install and start the Ollama service

### 2. Download LLaVA Model
```bash
ollama pull llava:7b
```

### 3. PHP Requirements
- PHP 7.4 or higher
- GD extension enabled
- cURL extension enabled

## ⚙️ Configuration

### 1. Update Ollama Server URL
Edit `config/ollama-config.php`:

```php
define('OLLAMA_API_URL', 'http://YOUR_OLLAMA_IP:11433/api/generate');
define('OLLAMA_MODEL', 'llava:7b');
```

**Current Settings:**
- Server: `http://100.105.118.56:11433/api/generate`
- Model: `llava:7b`

### 2. Enable PHP Extensions
Check `php.ini` and ensure these are enabled:

```ini
extension=gd
extension=curl
```

Restart Apache after changes.

### 3. Database Setup
Import the activity logs table:

```bash
mysql -u root -p trackmate < database/activity_logs.sql
```

Or via phpMyAdmin: Import `database/activity_logs.sql`

## 🚀 Quick Start

### Step 1: Start Ollama
```bash
ollama serve
```

Or if it's on another machine, make sure it's accessible via network.

### Step 2: Start XAMPP
- Start Apache
- Start MySQL

### Step 3: Test Ollama Connection
Open browser: `http://YOUR_OLLAMA_IP:11434/`

You should see: `Ollama is running`

### Step 4: Use TrackMate
1. Go to `http://localhost/trackmate`
2. Login to your account
3. Navigate to **Camera Monitoring**
4. Click toggle to **Start Camera**
5. Enable **AI Activity Detection** toggle
6. Watch real-time activity detection!

## 🎯 How It Works

### Architecture (PHP Only):
```
Camera (Browser)
    ↓ (captures frame every 10s)
JavaScript (camera.js)
    ↓ (sends base64 image)
PHP (analyze-activity.php)
    ↓ (resizes & encodes)
Ollama API (LLaVA model)
    ↓ (analyzes & classifies)
PHP (parses response)
    ↓ (logs to database)
MySQL (activity_logs table)
    ↓ (returns result)
Browser (displays activity)
```

### No Python Server Needed! 🎉

## 📊 Activity Categories

The AI detects **7 types** of activities:

| # | Activity | Icon | Description |
|---|----------|------|-------------|
| 1 | Using phone | 📱 | Actively holding smartphone |
| 2 | Working | 💻 | Using computer/laptop |
| 3 | Phone + Work | 📱💻 | Using phone while at computer |
| 4 | Sleeping | 😴 | Eyes closed, resting |
| 5 | Eating | 🍽️ | Consuming food |
| 6 | Drinking | 🥤 | Drinking beverages |
| 7 | Other | 🤷 | Anything else |

## 🔧 Customization

### Change Analysis Interval
Edit `assets/js/camera.js` line ~293:
```javascript
aiDetectionInterval = setInterval(() => {
    analyzeCurrentFrame();
}, 10000); // Change to 5000 for 5 seconds, 30000 for 30 seconds, etc.
```

Or update `config/ollama-config.php`:
```php
define('ANALYSIS_INTERVAL', 5000); // milliseconds
```

### Change Image Quality
Edit `config/ollama-config.php`:
```php
define('IMAGE_MAX_SIZE', 320); // pixels (smaller = faster)
define('IMAGE_QUALITY', 85);    // 1-100 (higher = better quality)
```

### Use Different Model
```php
// In config/ollama-config.php
define('OLLAMA_MODEL', 'llava:13b'); // Larger, more accurate
```

First download the model:
```bash
ollama pull llava:13b
```

## 🔍 Troubleshooting

### ❌ Cannot connect to Ollama server
**Solutions:**
1. Check if Ollama is running: `ollama list`
2. Verify IP address in `config/ollama-config.php`
3. Test connection: `curl http://YOUR_IP:11434/`
4. Check firewall settings

### ❌ GD extension not found
**Solutions:**
1. Edit `php.ini`
2. Uncomment: `extension=gd`
3. Restart Apache
4. Verify: `php -m | grep gd`

### ❌ cURL extension not enabled
**Solutions:**
1. Edit `php.ini`
2. Uncomment: `extension=curl`
3. Restart Apache
4. Verify: `php -m | grep curl`

### ❌ Database error
**Solutions:**
1. Import `database/activity_logs.sql`
2. Check MySQL is running
3. Verify credentials in `config/config.php`
4. Check table exists: `SHOW TABLES LIKE 'activity_logs';`

### ⏱️ Request timeout
**Solutions:**
1. Increase timeout in `config/ollama-config.php`:
   ```php
   define('OLLAMA_TIMEOUT', 300); // 5 minutes
   ```
2. Use smaller image size
3. Use faster model

### 🐌 Slow performance
**Solutions:**
1. Reduce image size (current: 320px)
2. Increase analysis interval (current: 10s)
3. Lower JPEG quality
4. Use GPU-accelerated Ollama if available

## 📁 File Structure

```
trackmate/
├── config/
│   ├── config.php              # Database config
│   └── ollama-config.php       # Ollama settings (NEW)
├── api/
│   └── analyze-activity.php    # Main AI analysis endpoint (UPDATED - Pure PHP)
├── assets/
│   └── js/
│       └── camera.js           # Camera & detection logic
├── camera.html                 # Camera monitoring UI
├── database/
│   └── activity_logs.sql       # Activity logs table
└── OLLAMA_PHP_SETUP.md         # This file
```

## 🔐 Security Notes

1. **Authentication**: API checks user session before processing
2. **Image Privacy**: Images processed but not stored
3. **Database**: Only activity classification is logged
4. **Network**: Consider restricting Ollama access in production

## 🧪 Testing

### Test Ollama API directly:
```bash
curl http://YOUR_IP:11434/api/generate -d '{
  "model": "llava:7b",
  "prompt": "Describe this image",
  "images": ["BASE64_IMAGE_HERE"]
}'
```

### Test PHP endpoint:
```bash
curl -X POST http://localhost/trackmate/api/analyze-activity.php \
  -H "Content-Type: application/json" \
  -d '{"image":"data:image/jpeg;base64,YOUR_BASE64_IMAGE"}'
```

## 📈 Performance Tips

1. **Local Ollama**: Run Ollama on same machine for best performance
2. **SSD Storage**: Improves model loading time
3. **GPU Support**: Use NVIDIA GPU for 10x faster inference
4. **Image Size**: Keep under 320px for real-time performance
5. **Interval**: 10-15 seconds is optimal balance

## ✅ Advantages of PHP-Only Solution

- ✅ No Python installation needed
- ✅ No separate server to manage
- ✅ Simpler deployment
- ✅ Better integration with existing PHP codebase
- ✅ Fewer dependencies
- ✅ Easier to debug
- ✅ Works with standard XAMPP setup

## 📞 Support

Check logs:
- Apache error log: `xampp/apache/logs/error.log`
- PHP errors: Enable `display_errors` in `php.ini`
- Browser console: F12 Developer Tools

## 🎉 You're All Set!

No Python required - Just PHP + Ollama!

Enjoy AI-powered activity monitoring! 🚀
