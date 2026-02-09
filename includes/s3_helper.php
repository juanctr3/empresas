<?php
/**
 * Clase S3Helper: Una implementación ligera para interactuar con Amazon S3 vía REST API.
 * Evita la dependencia pesada del SDK de AWS.
 */
class S3Helper {
    private $accessKey;
    private $secretKey;
    private $region;
    private $bucket;
    private $endpoint;
    public $lastError = null; // Para debugging

    public function __construct($accessKey, $secretKey, $region, $bucket) {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->region = $region;
        $this->bucket = $bucket;
        $this->endpoint = "https://{$bucket}.s3.{$region}.amazonaws.com";

        if (empty($this->accessKey) || empty($this->secretKey)) {
            error_log("S3Helper initialized with empty credentials.");
            $this->lastError = ['message' => 'Credenciales de S3 vacías'];
        }
    }

    public function uploadFile($filePath, $s3Key, $contentType = 'application/octet-stream') {
        $content = file_get_contents($filePath);
        if ($content === false) return false;
        return $this->putObject($content, $s3Key, $contentType);
    }

    /**
     * Prueba la conexión subiendo un archivo pequeño
     */
    public function testConnection() {
        return $this->putObject("Test Connection", "test_connection.txt", "text/plain");
    }

    /**
     * Método privado para subir contenido a S3 (usado por uploadFile y testConnection)
     */
    private function putObject($content, $s3Key, $contentType) {
        $date = gmdate('Ymd\THis\Z');
        $method = 'PUT';
        $canonicalUri = '/' . str_replace('%2F', '/', rawurlencode($s3Key));
        
        $headers = [
            'content-type' => $contentType,
            'host' => "{$this->bucket}.s3.{$this->region}.amazonaws.com",
            'x-amz-content-sha256' => hash('sha256', $content),
            'x-amz-date' => $date
        ];

        $authorization = $this->getSignatureV4($method, $canonicalUri, '', $headers, hash('sha256', $content));
        
        $curlHeaders = [
            "Authorization: $authorization",
            "x-amz-date: $date",
            "x-amz-content-sha256: " . hash('sha256', $content),
            "Content-Type: $contentType"
        ];

        $url = $this->endpoint . $canonicalUri;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Cambiar a true para producción
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        // Almacenar último error para debugging
        $this->lastError = [
            'http_code' => $httpCode,
            'curl_error' => $curlError,
            'curl_errno' => $curlErrno,
            'response' => $response,
            'url' => $url
        ];

        // Loguear si hay error
        if ($curlErrno || $httpCode !== 200) {
            error_log("S3 Error: Code:$httpCode, cURL:$curlError ($curlErrno), URL:$url");
        }

        return ($httpCode === 200);
    }

    /**
     * Elimina un archivo de S3
     */
    public function deleteFile($s3Key) {
        $date = gmdate('Ymd\THis\Z');
        $method = 'DELETE';
        $canonicalUri = '/' . str_replace('%2F', '/', rawurlencode($s3Key));
        
        $headers = [
            'host' => "{$this->bucket}.s3.{$this->region}.amazonaws.com",
            'x-amz-content-sha256' => hash('sha256', ''),
            'x-amz-date' => $date
        ];

        $authorization = $this->getSignatureV4($method, $canonicalUri, '', $headers, hash('sha256', ''));
        
        $curlHeaders = [
            "Authorization: $authorization",
            "x-amz-date: $date",
            "x-amz-content-sha256: " . hash('sha256', '')
        ];

        $ch = curl_init($this->endpoint . $canonicalUri);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode === 204 || $httpCode === 200);
    }

    /**
     * Genera una URL firmada (Presigned URL) para descarga temporal
     */
    public function getPresignedUrl($s3Key, $expiresIn = 3600) {
        $date = gmdate('Ymd\THis\Z');
        $shortDate = substr($date, 0, 8);
        $scope = "$shortDate/{$this->region}/s3/aws4_request";
        
        $params = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => "{$this->accessKey}/$scope",
            'X-Amz-Date' => $date,
            'X-Amz-Expires' => $expiresIn,
            'X-Amz-SignedHeaders' => 'host'
        ];
        
        ksort($params);
        $canonicalQuery = http_build_query($params);
        
        $canonicalUri = '/' . str_replace('%2F', '/', rawurlencode($s3Key));
        $canonicalHeaders = "host:{$this->bucket}.s3.{$this->region}.amazonaws.com\n";
        $signedHeaders = "host";
        
        $canonicalRequest = "GET\n$canonicalUri\n$canonicalQuery\n$canonicalHeaders\n$signedHeaders\nUNSIGNED-PAYLOAD";
        $stringToSign = "AWS4-HMAC-SHA256\n$date\n$scope\n" . hash('sha256', $canonicalRequest);
        
        $signingKey = $this->getSigningKey($shortDate);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);
        
        return "{$this->endpoint}{$canonicalUri}?{$canonicalQuery}&X-Amz-Signature={$signature}";
    }

    private function getSignatureV4($method, $uri, $query, $headers, $payloadHash) {
        $date = $headers['x-amz-date'];
        $shortDate = substr($date, 0, 8);
        $scope = "$shortDate/{$this->region}/s3/aws4_request";
        
        $canonicalHeaders = "";
        $signedHeaders = "";
        ksort($headers);
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= strtolower($k) . ":" . trim($v) . "\n";
            $signedHeaders .= strtolower($k) . ";";
        }
        $signedHeaders = rtrim($signedHeaders, ';');
        
        $canonicalRequest = "$method\n$uri\n$query\n$canonicalHeaders\n$signedHeaders\n$payloadHash";
        $stringToSign = "AWS4-HMAC-SHA256\n$date\n$scope\n" . hash('sha256', $canonicalRequest);
        
        $signingKey = $this->getSigningKey($shortDate);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);
        
        return "AWS4-HMAC-SHA256 Credential={$this->accessKey}/$scope, SignedHeaders=$signedHeaders, Signature=$signature";
    }

    private function getSigningKey($shortDate) {
        $kDate = hash_hmac('sha256', $shortDate, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }
}
