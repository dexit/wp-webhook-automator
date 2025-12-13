<?php
/**
 * SignatureGenerator Tests
 *
 * @package WP_Webhook_Automator
 */

namespace WWA\Tests\Unit\Core;

use WWA\Tests\TestCase;
use WWA\Core\SignatureGenerator;
use Brain\Monkey\Functions;

class SignatureGeneratorTest extends TestCase
{
    private SignatureGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new SignatureGenerator();
    }

    /**
     * Test basic signature generation.
     */
    public function testGenerate(): void
    {
        $payload = '{"test": "data"}';
        $secret = 'mysecretkey';

        $signature = $this->generator->generate($payload, $secret);

        $this->assertIsString($signature);
        $this->assertSame(64, strlen($signature)); // SHA256 produces 64 hex chars
        $this->assertTrue(ctype_xdigit($signature)); // Should be hexadecimal
    }

    /**
     * Test signature is consistent for same input.
     */
    public function testGenerateConsistent(): void
    {
        $payload = 'consistent payload';
        $secret = 'secret';

        $sig1 = $this->generator->generate($payload, $secret);
        $sig2 = $this->generator->generate($payload, $secret);

        $this->assertSame($sig1, $sig2);
    }

    /**
     * Test different payloads produce different signatures.
     */
    public function testGenerateDifferentPayloads(): void
    {
        $secret = 'secret';

        $sig1 = $this->generator->generate('payload1', $secret);
        $sig2 = $this->generator->generate('payload2', $secret);

        $this->assertNotSame($sig1, $sig2);
    }

    /**
     * Test different secrets produce different signatures.
     */
    public function testGenerateDifferentSecrets(): void
    {
        $payload = 'same payload';

        $sig1 = $this->generator->generate($payload, 'secret1');
        $sig2 = $this->generator->generate($payload, 'secret2');

        $this->assertNotSame($sig1, $sig2);
    }

    /**
     * Test getHeader format.
     */
    public function testGetHeaderFormat(): void
    {
        $payload = 'test payload';
        $secret = 'secret';

        $header = $this->generator->getHeader($payload, $secret);

        // Should match format: t=timestamp,v1=signature
        $this->assertMatchesRegularExpression('/^t=\d+,v1=[a-f0-9]{64}$/', $header);
    }

    /**
     * Test getHeader contains valid timestamp.
     */
    public function testGetHeaderTimestamp(): void
    {
        $beforeTime = time();
        $header = $this->generator->getHeader('payload', 'secret');
        $afterTime = time();

        // Extract timestamp
        preg_match('/t=(\d+)/', $header, $matches);
        $timestamp = (int) $matches[1];

        $this->assertGreaterThanOrEqual($beforeTime, $timestamp);
        $this->assertLessThanOrEqual($afterTime, $timestamp);
    }

    /**
     * Test verify with valid signature.
     */
    public function testVerifyValid(): void
    {
        $payload = 'verify this';
        $secret = 'verifySecret';
        $timestamp = time();

        // Manually create a valid signature
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);
        $header = "t={$timestamp},v1={$signature}";

        $result = $this->generator->verify($payload, $secret, $header);

        $this->assertTrue($result);
    }

    /**
     * Test verify fails with wrong secret.
     */
    public function testVerifyFailsWithWrongSecret(): void
    {
        $payload = 'payload';
        $timestamp = time();

        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, 'correct-secret');
        $header = "t={$timestamp},v1={$signature}";

        $result = $this->generator->verify($payload, 'wrong-secret', $header);

        $this->assertFalse($result);
    }

    /**
     * Test verify fails with modified payload.
     */
    public function testVerifyFailsWithModifiedPayload(): void
    {
        $payload = 'original payload';
        $secret = 'secret';
        $timestamp = time();

        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);
        $header = "t={$timestamp},v1={$signature}";

        $result = $this->generator->verify('modified payload', $secret, $header);

        $this->assertFalse($result);
    }

    /**
     * Test verify fails with expired timestamp.
     */
    public function testVerifyFailsWithExpiredTimestamp(): void
    {
        $payload = 'payload';
        $secret = 'secret';
        $timestamp = time() - 600; // 10 minutes ago

        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);
        $header = "t={$timestamp},v1={$signature}";

        // Default tolerance is 300 seconds
        $result = $this->generator->verify($payload, $secret, $header);

        $this->assertFalse($result);
    }

    /**
     * Test verify with custom tolerance.
     */
    public function testVerifyWithCustomTolerance(): void
    {
        $payload = 'payload';
        $secret = 'secret';
        $timestamp = time() - 600; // 10 minutes ago

        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);
        $header = "t={$timestamp},v1={$signature}";

        // With 1000 second tolerance, should pass
        $result = $this->generator->verify($payload, $secret, $header, 1000);

        $this->assertTrue($result);
    }

    /**
     * Test verify fails with missing timestamp.
     */
    public function testVerifyFailsWithMissingTimestamp(): void
    {
        $header = 'v1=somesignature';

        $result = $this->generator->verify('payload', 'secret', $header);

        $this->assertFalse($result);
    }

    /**
     * Test verify fails with missing signature version.
     */
    public function testVerifyFailsWithMissingSignature(): void
    {
        $header = 't=123456789';

        $result = $this->generator->verify('payload', 'secret', $header);

        $this->assertFalse($result);
    }

    /**
     * Test verify fails with malformed header.
     */
    public function testVerifyFailsWithMalformedHeader(): void
    {
        $malformedHeaders = [
            '',
            'garbage',
            't=abc,v1=def', // Non-numeric timestamp
            '=,=',
            'key=value',
        ];

        foreach ($malformedHeaders as $header) {
            $result = $this->generator->verify('payload', 'secret', $header);
            $this->assertFalse($result, "Should fail for: {$header}");
        }
    }

    /**
     * Test getAlgorithm returns sha256.
     */
    public function testGetAlgorithm(): void
    {
        $this->assertSame('sha256', $this->generator->getAlgorithm());
    }

    /**
     * Test getVersion returns v1.
     */
    public function testGetVersion(): void
    {
        $this->assertSame('v1', $this->generator->getVersion());
    }

    /**
     * Test generateSecret with mock.
     */
    public function testGenerateSecret(): void
    {
        Functions\when('wp_generate_password')->alias(function ($length, $special) {
            return bin2hex(random_bytes($length / 2));
        });

        $secret = $this->generator->generateSecret();

        $this->assertSame(32, strlen($secret));
    }

    /**
     * Test generateSecret with custom length.
     */
    public function testGenerateSecretWithCustomLength(): void
    {
        Functions\when('wp_generate_password')->alias(function ($length, $special) {
            return bin2hex(random_bytes($length / 2));
        });

        $secret = $this->generator->generateSecret(64);

        $this->assertSame(64, strlen($secret));
    }

    /**
     * Test round-trip: getHeader then verify.
     */
    public function testRoundTrip(): void
    {
        $payload = 'round trip test';
        $secret = 'roundtripsecret123';

        $header = $this->generator->getHeader($payload, $secret);
        $result = $this->generator->verify($payload, $secret, $header);

        $this->assertTrue($result);
    }

    /**
     * Test signature with empty payload.
     */
    public function testSignatureWithEmptyPayload(): void
    {
        $signature = $this->generator->generate('', 'secret');

        $this->assertIsString($signature);
        $this->assertSame(64, strlen($signature));
    }

    /**
     * Test signature with unicode payload.
     */
    public function testSignatureWithUnicodePayload(): void
    {
        $payload = '{"message": "Hello 世界 🌍"}';
        $secret = 'unicodesecret';

        $sig1 = $this->generator->generate($payload, $secret);
        $sig2 = $this->generator->generate($payload, $secret);

        $this->assertSame($sig1, $sig2);
        $this->assertSame(64, strlen($sig1));
    }

    /**
     * Test signature with large payload.
     */
    public function testSignatureWithLargePayload(): void
    {
        $payload = str_repeat('x', 100000); // 100KB payload
        $secret = 'secret';

        $signature = $this->generator->generate($payload, $secret);

        $this->assertIsString($signature);
        $this->assertSame(64, strlen($signature));
    }

    /**
     * Test verify with future timestamp is rejected.
     */
    public function testVerifyRejectsFutureTimestamp(): void
    {
        $payload = 'payload';
        $secret = 'secret';
        $timestamp = time() + 600; // 10 minutes in the future

        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);
        $header = "t={$timestamp},v1={$signature}";

        // Default tolerance is 300 seconds
        $result = $this->generator->verify($payload, $secret, $header);

        $this->assertFalse($result);
    }

    /**
     * Test header with whitespace is handled.
     */
    public function testVerifyHandlesWhitespace(): void
    {
        $payload = 'payload';
        $secret = 'secret';
        $timestamp = time();

        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);
        $header = "t = {$timestamp} , v1 = {$signature}"; // Extra whitespace

        $result = $this->generator->verify($payload, $secret, $header);

        $this->assertTrue($result);
    }
}
