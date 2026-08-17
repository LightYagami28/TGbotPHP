<?php

declare(strict_types=1);

namespace TGbotPHP\Traits;

use TGbotPHP\Exceptions\ApiException;

/**
 * HTTP client functionality for API calls
 */
trait HttpClientTrait
{
    /**
     * Make HTTP request to Telegram API
     */
    protected function httpRequest(
        string $method,
        array $data = [],
        bool $returnResponse = false
    ): array|null {
        $url = "https://api.telegram.org/bot" . $this->getToken() . "/" . urlencode($method);

        $curl = curl_init($url);
        if (!$curl) {
            throw new ApiException("Failed to initialize cURL", 0);
        }

        try {
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $data,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'User-Agent: TGbotPHP/2.0.0',
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($httpCode !== 200) {
                throw new ApiException("HTTP $httpCode from Telegram API", $httpCode);
            }

            if (!$response) {
                throw new ApiException("Empty response from Telegram API", 0);
            }

            $decoded = json_decode($response, true);

            if (!($decoded['ok'] ?? false)) {
                throw new ApiException(
                    $decoded['description'] ?? 'Unknown error',
                    $decoded['error_code'] ?? 0,
                    $decoded
                );
            }

            return $returnResponse ? ($decoded['result'] ?? $decoded) : null;
        } finally {
            curl_close($curl);
        }
    }
}
