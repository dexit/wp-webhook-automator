<?php
/**
 * Signature Generator
 *
 * Generates and verifies HMAC signatures for webhook security.
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Core;

class SignatureGenerator {

    /**
     * The hashing algorithm.
     */
    private const ALGORITHM = 'sha256';

    /**
     * Signature version.
     */
    private const VERSION = 'v1';

    /**
     * Generate a signature for a payload.
     *
     * @param string $payload The payload to sign.
     * @param string $secret  The secret key.
     * @return string
     */
    public function generate(string $payload, string $secret): string {
        return hash_hmac(self::ALGORITHM, $payload, $secret);
    }

    /**
     * Generate a complete signature header value.
     *
     * @param string $payload The payload to sign.
     * @param string $secret  The secret key.
     * @return string
     */
    public function getHeader(string $payload, string $secret): string {
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = $this->generate($signedPayload, $secret);

        return "t={$timestamp}," . self::VERSION . "={$signature}";
    }

    /**
     * Verify a signature.
     *
     * @param string $payload   The payload that was signed.
     * @param string $secret    The secret key.
     * @param string $signature The signature to verify.
     * @param int    $tolerance Maximum age of signature in seconds.
     * @return bool
     */
    public function verify(string $payload, string $secret, string $signature, int $tolerance = 300): bool {
        // Parse the signature header
        $parts = $this->parseSignature($signature);

        if (!isset($parts['t'], $parts[self::VERSION])) {
            return false;
        }

        $timestamp = (int) $parts['t'];

        // Check timestamp tolerance
        if (abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        // Generate expected signature
        $signedPayload = "{$timestamp}.{$payload}";
        $expected = $this->generate($signedPayload, $secret);

        return hash_equals($expected, $parts[self::VERSION]);
    }

    /**
     * Parse a signature header string.
     *
     * @param string $signature The signature header.
     * @return array
     */
    private function parseSignature(string $signature): array {
        $parts = [];

        foreach (explode(',', $signature) as $part) {
            $keyValue = explode('=', $part, 2);
            if (count($keyValue) === 2) {
                $parts[trim($keyValue[0])] = trim($keyValue[1]);
            }
        }

        return $parts;
    }

    /**
     * Get the algorithm used for signatures.
     *
     * @return string
     */
    public function getAlgorithm(): string {
        return self::ALGORITHM;
    }

    /**
     * Get the signature version.
     *
     * @return string
     */
    public function getVersion(): string {
        return self::VERSION;
    }

    /**
     * Generate a new random secret key.
     *
     * @param int $length The length of the key.
     * @return string
     */
    public function generateSecret(int $length = 32): string {
        return wp_generate_password($length, false);
    }
}
