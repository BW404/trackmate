<?php
echo "Testing Ollama Connection...\n";
echo "URL: https://ollama1.bw404.com/\n\n";

$ch = curl_init('https://ollama1.bw404.com/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$errno = curl_errno($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "Error Number: " . $errno . "\n";

if ($error) {
    echo "Error: " . $error . "\n";
} else {
    echo "Success!\n";
    echo "Response: " . substr($response, 0, 200) . "\n";
}

curl_close($ch);
?>
