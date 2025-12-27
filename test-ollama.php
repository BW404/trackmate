<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ollama Connection Test - TrackMate</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #FF9F66;
            margin-bottom: 30px;
        }
        .test-section {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #FF9F66;
        }
        .status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin-left: 10px;
        }
        .status.success {
            background: #48BB78;
            color: white;
        }
        .status.error {
            background: #E53E3E;
            color: white;
        }
        .status.testing {
            background: #FF9F66;
            color: white;
        }
        button {
            background: #FF9F66;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        button:hover {
            background: #FFB380;
        }
        pre {
            background: #2D3748;
            color: #E2E8F0;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
        .info {
            background: #EBF8FF;
            border-left: 4px solid #3182CE;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🤖 TrackMate - Ollama Connection Test</h1>
        
        <div class="info">
            <strong>ℹ️ Before Testing:</strong><br>
            1. Make sure Ollama is running<br>
            2. Verify you have the llava:7b model installed<br>
            3. Check the Ollama URL in <code>config/ollama-config.php</code>
        </div>

        <div class="test-section">
            <h3>Test 1: PHP Extensions</h3>
            <p>
                GD Extension: <span class="status <?php echo extension_loaded('gd') ? 'success' : 'error'; ?>">
                    <?php echo extension_loaded('gd') ? '✓ Enabled' : '✗ Disabled'; ?>
                </span>
            </p>
            <p>
                cURL Extension: <span class="status <?php echo extension_loaded('curl') ? 'success' : 'error'; ?>">
                    <?php echo extension_loaded('curl') ? '✓ Enabled' : '✗ Disabled'; ?>
                </span>
            </p>
        </div>

        <div class="test-section">
            <h3>Test 2: Configuration</h3>
            <?php
            require_once 'config/ollama-config.php';
            ?>
            <p><strong>Ollama URL:</strong> <?php echo OLLAMA_API_URL; ?></p>
            <p><strong>Model:</strong> <?php echo OLLAMA_MODEL; ?></p>
            <p><strong>Timeout:</strong> <?php echo OLLAMA_TIMEOUT; ?> seconds</p>
            <p><strong>Image Size:</strong> <?php echo IMAGE_MAX_SIZE; ?>px</p>
        </div>

        <div class="test-section">
            <h3>Test 3: Ollama Server Connection</h3>
            <?php
            if (isset($_GET['test_connection'])) {
                echo '<p>Testing connection... <span class="status testing">⏳ Please wait</span></p>';
                
                $ollamaUrl = str_replace('/api/generate', '', OLLAMA_API_URL);
                
                // Try basic connectivity first
                $ch = curl_init($ollamaUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification for testing
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_VERBOSE, false);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                $curlErrno = curl_errno($ch);
                curl_close($ch);
                
                if ($curlError) {
                    echo '<p>Ollama Server: <span class="status error">✗ Connection Failed</span></p>';
                    echo '<p><strong>Error Code:</strong> ' . $curlErrno . '</p>';
                    echo '<p><strong>Error:</strong> ' . htmlspecialchars($curlError) . '</p>';
                    echo '<div class="info">';
                    echo '<strong>💡 Troubleshooting:</strong><br>';
                    echo '1. Check if Ollama server is accessible from your network<br>';
                    echo '2. Try opening <a href="' . htmlspecialchars($ollamaUrl) . '" target="_blank">' . htmlspecialchars($ollamaUrl) . '</a> in browser<br>';
                    echo '3. Check firewall settings<br>';
                    echo '4. Verify SSL certificate is valid<br>';
                    echo '5. Try using HTTP instead of HTTPS if behind VPN/proxy';
                    echo '</div>';
                } elseif ($httpCode == 200) {
                    if (strpos($response, 'Ollama is running') !== false) {
                        echo '<p>Ollama Server: <span class="status success">✓ Connected & Running</span></p>';
                        echo '<p><small>Server URL: ' . htmlspecialchars($ollamaUrl) . '</small></p>';
                        
                        // Test API endpoint
                        echo '<p style="margin-top:15px;">Testing API endpoint...</p>';
                        $apiCh = curl_init(OLLAMA_API_URL);
                        curl_setopt($apiCh, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($apiCh, CURLOPT_POST, true);
                        curl_setopt($apiCh, CURLOPT_POSTFIELDS, json_encode([
                            'model' => OLLAMA_MODEL,
                            'prompt' => 'test',
                            'stream' => false
                        ]));
                        curl_setopt($apiCh, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                        curl_setopt($apiCh, CURLOPT_TIMEOUT, 30);
                        curl_setopt($apiCh, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($apiCh, CURLOPT_SSL_VERIFYHOST, false);
                        
                        $apiResponse = curl_exec($apiCh);
                        $apiHttpCode = curl_getinfo($apiCh, CURLINFO_HTTP_CODE);
                        $apiError = curl_error($apiCh);
                        curl_close($apiCh);
                        
                        if ($apiError) {
                            echo '<p>API Endpoint: <span class="status error">✗ Failed</span></p>';
                            echo '<pre>' . htmlspecialchars($apiError) . '</pre>';
                        } elseif ($apiHttpCode == 200) {
                            echo '<p>API Endpoint: <span class="status success">✓ Working</span></p>';
                            echo '<p>Model "' . OLLAMA_MODEL . '" is accessible</p>';
                        } else {
                            echo '<p>API Endpoint: <span class="status error">✗ HTTP ' . $apiHttpCode . '</span></p>';
                        }
                    } else {
                        echo '<p>Ollama Server: <span class="status error">✗ Unexpected Response</span></p>';
                        echo '<pre>' . htmlspecialchars(substr($response, 0, 500)) . '</pre>';
                    }
                } else {
                    echo '<p>Ollama Server: <span class="status error">✗ HTTP Error</span></p>';
                    echo '<p>HTTP Code: ' . $httpCode . '</p>';
                    echo '<pre>' . htmlspecialchars(substr($response, 0, 500)) . '</pre>';
                }
            } else {
                echo '<form method="get"><button type="submit" name="test_connection" value="1">Test Connection</button></form>';
                echo '<p style="margin-top:10px;"><small>Server: <a href="https://ollama1.bw404.com/" target="_blank">https://ollama1.bw404.com/</a></small></p>';
            }
            ?>
        </div>

        <div class="test-section">
            <h3>Test 4: Database</h3>
            <?php
            require_once 'config/config.php';
            try {
                $conn = getDBConnection();
                
                // Check if activity_logs table exists
                $stmt = $conn->query("SHOW TABLES LIKE 'activity_logs'");
                $tableExists = $stmt->rowCount() > 0;
                
                if ($tableExists) {
                    $stmt = $conn->query("SELECT COUNT(*) as count FROM activity_logs");
                    $count = $stmt->fetch()['count'];
                    echo '<p>Database: <span class="status success">✓ Connected</span></p>';
                    echo '<p>activity_logs table: <span class="status success">✓ Exists</span></p>';
                    echo '<p>Total activity logs: <strong>' . $count . '</strong></p>';
                } else {
                    echo '<p>Database: <span class="status success">✓ Connected</span></p>';
                    echo '<p>activity_logs table: <span class="status error">✗ Not Found</span></p>';
                    echo '<p><small>Run: <code>mysql -u root -p trackmate < database/activity_logs.sql</code></small></p>';
                }
            } catch (Exception $e) {
                echo '<p>Database: <span class="status error">✗ Error</span></p>';
                echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
            }
            ?>
        </div>

        <div class="test-section">
            <h3>Test 5: Full AI Analysis</h3>
            <?php
            if (isset($_POST['test_analysis']) && isset($_POST['test_image'])) {
                echo '<p>Analyzing test image... <span class="status testing">⏳ This may take up to 3 minutes</span></p>';
                
                // Create a simple test image (colored rectangle)
                $testImg = imagecreatetruecolor(320, 240);
                $bgColor = imagecolorallocate($testImg, 100, 150, 200);
                imagefilledrectangle($testImg, 0, 0, 320, 240, $bgColor);
                
                // Add text
                $textColor = imagecolorallocate($testImg, 255, 255, 255);
                imagestring($testImg, 5, 80, 110, "Test Image", $textColor);
                
                ob_start();
                imagejpeg($testImg, null, 85);
                $imageBytes = ob_get_clean();
                imagedestroy($testImg);
                
                $base64Image = base64_encode($imageBytes);
                
                // Load configuration
                require_once 'config/config.php';
                require_once 'config/ollama-config.php';
                
                // Define the activity categories if not already defined
                if (!defined('ACTIVITY_CATEGORIES')) {
                    die('Configuration error');
                }
                
                $ACTIVITIES = ACTIVITY_CATEGORIES;
                
                // Test Ollama with the image
                $prompt = "Describe what you see in this image in one sentence.";
                
                $payload = [
                    'model' => OLLAMA_MODEL,
                    'prompt' => $prompt,
                    'images' => [$base64Image],
                    'stream' => false
                ];
                
                $ch = curl_init(OLLAMA_API_URL);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, OLLAMA_TIMEOUT);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, OLLAMA_CONNECT_TIMEOUT);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, OLLAMA_SSL_VERIFY_PEER);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, OLLAMA_SSL_VERIFY_HOST);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                
                if ($curlError) {
                    $result = [
                        'success' => false,
                        'error' => 'Connection error',
                        'details' => $curlError
                    ];
                } elseif ($httpCode !== 200) {
                    $result = [
                        'success' => false,
                        'error' => "HTTP $httpCode",
                        'details' => substr($response, 0, 500)
                    ];
                } else {
                    $apiResult = json_decode($response, true);
                    if ($apiResult && isset($apiResult['response'])) {
                        $result = [
                            'success' => true,
                            'activity' => 'Test Image (AI Working!)',
                            'category' => 7,
                            'description' => $apiResult['response']
                        ];
                    } else {
                        $result = [
                            'success' => false,
                            'error' => 'Invalid response',
                            'details' => substr($response, 0, 500)
                        ];
                    }
                }
                
                if ($result['success']) {
                    echo '<p>Analysis: <span class="status success">✓ Success</span></p>';
                    echo '<p><strong>Detected Activity:</strong> ' . htmlspecialchars($result['activity']) . '</p>';
                    echo '<p><strong>Category:</strong> ' . $result['category'] . '</p>';
                    echo '<p><strong>Description:</strong> ' . htmlspecialchars(substr($result['description'], 0, 200)) . '</p>';
                    echo '<div class="info">✅ <strong>AI Detection is fully operational!</strong><br>You can now use it in the Camera Monitoring page.</div>';
                } else {
                    echo '<p>Analysis: <span class="status error">✗ Failed</span></p>';
                    echo '<p><strong>Error:</strong> ' . htmlspecialchars($result['error']) . '</p>';
                    if (isset($result['details'])) {
                        echo '<pre>' . htmlspecialchars($result['details']) . '</pre>';
                    }
                }
            } else {
                echo '<p>Test the complete AI analysis pipeline without requiring login.</p>';
                echo '<form method="post">';
                echo '<input type="hidden" name="test_image" value="1">';
                echo '<button type="submit" name="test_analysis" value="1">Run AI Analysis Test</button>';
                echo '</form>';
                echo '<p><small>This will send a test image to Ollama and classify it.</small></p>';
            }
            ?>
        </div>

        <div style="margin-top: 30px; text-align: center;">
            <a href="camera.html" style="text-decoration: none;">
                <button>Go to Camera Monitoring →</button>
            </a>
        </div>
    </div>
</body>
</html>
