<?php

namespace App\Services\Codef;

use RuntimeException;

class CodefRsaEncryptor
{
    protected string $publicKey;

    public function __construct()
    {
        $this->publicKey = config('codef.public_key');
    }

    /**
     * RSA 공개키로 평문을 암호화한 뒤 base64 인코딩하여 반환
     */
    public function encrypt(string $plainText): string
    {
        if (empty($this->publicKey)) {
            throw new RuntimeException('CODEF RSA 공개키가 설정되지 않았습니다.');
        }

        $pemKey = $this->formatPublicKey($this->publicKey);

        $publicKeyResource = openssl_pkey_get_public($pemKey);
        if ($publicKeyResource === false) {
            throw new RuntimeException('CODEF RSA 공개키를 파싱할 수 없습니다: ' . openssl_error_string());
        }

        $encrypted = '';
        $success = openssl_public_encrypt($plainText, $encrypted, $publicKeyResource, OPENSSL_PKCS1_PADDING);

        if (!$success) {
            throw new RuntimeException('RSA 암호화 실패: ' . openssl_error_string());
        }

        return base64_encode($encrypted);
    }

    /**
     * PEM 형식이 아닌 경우 PEM 헤더/푸터를 추가
     */
    private function formatPublicKey(string $key): string
    {
        $key = trim($key);

        // 이미 PEM 형식이면 그대로 반환
        if (str_starts_with($key, '-----BEGIN PUBLIC KEY-----')) {
            return $key;
        }

        // base64 문자열만 들어온 경우 PEM 형식으로 감싸기
        $key = str_replace(["\r", "\n", ' '], '', $key);
        $formatted = "-----BEGIN PUBLIC KEY-----\n";
        $formatted .= chunk_split($key, 64, "\n");
        $formatted .= "-----END PUBLIC KEY-----";

        return $formatted;
    }
}
