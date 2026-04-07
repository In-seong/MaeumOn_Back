<?php

namespace App\Services\Codef;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CodefAuthService
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $baseUrl;

    private const CACHE_KEY = 'codef_access_token';
    private const TOKEN_BUFFER_SECONDS = 60; // 만료 전 버퍼

    public function __construct()
    {
        $this->clientId = config('codef.client_id');
        $this->clientSecret = config('codef.client_secret');
        $this->baseUrl = config('codef.base_url');
    }

    /**
     * OAuth Access Token 반환 (캐시 우선)
     */
    public function getAccessToken(): string
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached) {
            return $cached;
        }

        return $this->requestNewToken();
    }

    /**
     * 토큰 강제 갱신
     */
    public function refreshToken(): string
    {
        Cache::forget(self::CACHE_KEY);

        return $this->requestNewToken();
    }

    /**
     * CODEF OAuth 토큰 발급 (client_credentials)
     */
    private function requestNewToken(): string
    {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new RuntimeException('CODEF client_id 또는 client_secret이 설정되지 않았습니다.');
        }

        $tokenUrl = config('codef.oauth_url', 'https://oauth.codef.io') . '/oauth/token';

        try {
            $credentials = base64_encode(urlencode($this->clientId) . ':' . urlencode($this->clientSecret));

            $response = Http::asForm()
                ->withHeaders(['Authorization' => 'Basic ' . $credentials])
                ->timeout(30)
                ->post($tokenUrl, [
                    'grant_type' => 'client_credentials',
                ]);

            if (!$response->successful()) {
                Log::error('CODEF OAuth 토큰 발급 실패', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new RuntimeException('CODEF OAuth 토큰 발급 실패: HTTP ' . $response->status());
            }

            $data = $response->json();

            if (empty($data['access_token'])) {
                throw new RuntimeException('CODEF OAuth 응답에 access_token이 없습니다.');
            }

            $accessToken = $data['access_token'];
            $expiresIn = $data['expires_in'] ?? 3600;

            // 만료 전 버퍼를 빼고 캐싱
            $cacheTtl = max($expiresIn - self::TOKEN_BUFFER_SECONDS, 60);
            Cache::put(self::CACHE_KEY, $accessToken, $cacheTtl);

            Log::info('CODEF OAuth 토큰 발급 성공', [
                'expires_in' => $expiresIn,
                'cache_ttl' => $cacheTtl,
            ]);

            return $accessToken;

        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('CODEF OAuth 토큰 발급 중 오류', [
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('CODEF OAuth 토큰 발급 중 오류: ' . $e->getMessage());
        }
    }
}
