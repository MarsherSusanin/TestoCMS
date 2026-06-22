<?php

namespace App\Modules\Updates\Services;

use RuntimeException;

/**
 * Verifies detached Ed25519 (libsodium) signatures over update package bytes.
 *
 * Shared by the cloud-download path (UpdateManifestClient) and the manual
 * upload path (CoreUpdateService) so both enforce the same cryptographic
 * gate before any package is allowed to overwrite application files.
 */
class PackageSignatureVerifier
{
    /**
     * Verify a detached signature over the raw bytes of $filePath against a
     * base64 / base64url-encoded Ed25519 public key.
     */
    public function verifyFile(string $filePath, string $signatureRaw, string $publicKeyRaw): bool
    {
        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            throw new RuntimeException('The sodium extension is required for update signature verification.');
        }

        $signature = $this->decodeBase64($signatureRaw);
        $publicKey = $this->decodeBase64($publicKeyRaw);
        if ($signature === '' || $publicKey === '') {
            return false;
        }

        $payload = @file_get_contents($filePath);
        if ($payload === false) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($signature, $payload, $publicKey);
        } catch (\Throwable) {
            return false;
        }
    }

    public function decodeBase64(string $input): string
    {
        $normalized = trim($input);
        $normalized = str_replace(['-', '_'], ['+', '/'], $normalized);
        $padding = strlen($normalized) % 4;
        if ($padding !== 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);

        return $decoded !== false ? $decoded : '';
    }
}
