<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Crypto;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\PgpProcess;
use Symfony\Component\Mime\Crypto\PgpSigner;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\Multipart\PgpSignedPart;
use Symfony\Component\Mime\Part\PgpKeyPart;
use Symfony\Component\Mime\Part\PgpSignaturePart;
use Symfony\Component\Mime\Part\TextPart;
use Symfony\Component\Process\ExecutableFinder;

class PgpSignerTest extends TestCase
{
    private const KEY_EMAIL_ADDRESS = 'pgp@pulli.dev';

    private const KEY_PASSWORD = 'test1234';

    protected function setUp(): void
    {
        if (!(new ExecutableFinder())->find('gpg')) {
            $this->markTestSkipped('The "gpg" binary is not available.');
        }
    }

    public function testPgpProcessCanSignCorrectly()
    {
        $process = new PgpProcess();
        $tester = new PgpTestingProcess();

        $output = $process->sign('Hello there!', __DIR__.'/../Fixtures/pgp_test_secret_key.asc', self::KEY_PASSWORD);

        $verified = $tester->verify('Hello there!', $output, __DIR__.'/../Fixtures/pgp_test_public_key.asc');
        $this->assertTrue($verified);
        $verified = $tester->verify('Hello there!', $output, __DIR__.'/../Fixtures/other_public_key.asc');
        $this->assertFalse($verified);
    }

    public function testSigningWithPublicKey()
    {
        $signer = new PgpSigner(
            __DIR__.'/../Fixtures/pgp_test_secret_key.asc',
            __DIR__.'/../Fixtures/pgp_test_public_key.asc',
            self::KEY_PASSWORD
        );

        $email = (new Email())
            ->from(new Address(static::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->to(new Address(static::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->text("Hello there!\n\nHow are you?")
            ->subject('PGP Mail');

        $signedMessage = $signer->sign($email);

        $this->assertInstanceOf(Message::class, $signedMessage);

        $body = $signedMessage->getBody();

        $this->assertInstanceOf(PgpSignedPart::class, $body, 'Message is not signed.');

        [$signedPart, $signaturePart] = $body->getParts();

        $this->assertInstanceOf(PgpSignaturePart::class, $signaturePart, 'Not a PgpSignaturePart.');

        $this->assertInstanceOf(MixedPart::class, $signedPart, 'SignedPart is not a MixedPart.');
        $signedPartString = $signedPart->toString();

        [$signedPart, $publicKeyPart] = $signedPart->getParts();

        $this->assertInstanceOf(TextPart::class, $signedPart, 'Message is not text part.');
        $this->assertInstanceOf(PgpKeyPart::class, $publicKeyPart, 'Message is not public key part.');

        $signature = $signaturePart->bodyToString();

        $this->assertStringContainsString('-----BEGIN PGP SIGNATURE-----', $signature, 'PGP Signature begin is missing.');
        $this->assertStringContainsString('-----END PGP SIGNATURE-----', $signature, 'PGP Signature end end is missing.');

        $originalBody = $this->normalize($email->getBody()->toString());
        $this->assertStringContainsString($originalBody."\r\n", $body->toString(), 'Signed message does not contain the actual message.');

        $tester = new PgpTestingProcess();
        $result = $tester->verify($signedPartString, $signature, __DIR__.'/../Fixtures/pgp_test_public_key.asc');
        $this->assertTrue($result, 'Signature is not valid.');
    }

    public function testSigningWithoutPublicKey()
    {
        $signer = new PgpSigner(
            __DIR__.'/../Fixtures/pgp_test_secret_key.asc',
            null,
            self::KEY_PASSWORD
        );

        $email = (new Email())
            ->from(new Address(static::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->to(new Address(static::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->text("Hello there!\n\nHow are you?")
            ->subject('PGP Mail');

        $signedMessage = $signer->sign($email);

        $this->assertInstanceOf(Message::class, $signedMessage);

        $body = $signedMessage->getBody();

        $this->assertInstanceOf(PgpSignedPart::class, $body, 'Message is not signed.');

        [$signedPart, $signaturePart] = $body->getParts();

        $this->assertInstanceOf(PgpSignaturePart::class, $signaturePart, 'Not a PgpSignaturePart.');

        $this->assertInstanceOf(TextPart::class, $signedPart, 'SignedPart is not a TextPart.');
        $signedPartString = $signedPart->toString();

        $signature = $signaturePart->bodyToString();

        $this->assertStringContainsString('-----BEGIN PGP SIGNATURE-----', $signature, 'PGP Signature begin is missing.');
        $this->assertStringContainsString('-----END PGP SIGNATURE-----', $signature, 'PGP Signature end end is missing.');

        $originalBody = $this->normalize($email->getBody()->toString());
        $this->assertStringContainsString($originalBody."\r\n", $body->toString(), 'Signed message does not contain the actual message.');

        $tester = new PgpTestingProcess();
        $result = $tester->verify($signedPartString, $signature, __DIR__.'/../Fixtures/pgp_test_public_key.asc');
        $this->assertTrue($result, 'Signature is not valid.');
    }

    /**
     * The detached signature must stay valid when verified against the exact bytes
     * that are transmitted, including bodies with trailing whitespace or CRLF endings
     * (RFC 3156 canonicalization).
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideBodiesWithEdgeCaseLineEndings')]
    public function testSignatureVerifiesAgainstTransmittedBytes(string $text)
    {
        $signer = new PgpSigner(
            __DIR__.'/../Fixtures/pgp_test_secret_key.asc',
            __DIR__.'/../Fixtures/pgp_test_public_key.asc',
            self::KEY_PASSWORD
        );

        $email = (new Email())
            ->from(new Address(static::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->to(new Address(static::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->text($text)
            ->subject('PGP Mail');

        $signed = $signer->sign($email);
        [$signedPart, $signaturePart] = $signed->getBody()->getParts();

        $tester = new PgpTestingProcess();
        $result = $tester->verify($signedPart->toString(), $signaturePart->bodyToString(), __DIR__.'/../Fixtures/pgp_test_public_key.asc');
        $this->assertTrue($result, 'Signature is not valid against the transmitted bytes.');
    }

    public static function provideBodiesWithEdgeCaseLineEndings(): iterable
    {
        yield 'simple' => ["Hello there!\n\nHow are you?"];
        yield 'trailing whitespace' => ["Line with trailing space   \nand a tab\t\n"];
        yield 'crlf' => ["Hello\r\nWorld\r\n"];
    }

    private function normalize(string $part): string
    {
        return str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $part));
    }
}
