<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\MimePgp\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\TextPart;
use Symfony\Component\MimePgp\Mime\Part\Multipart\PgpSignedPart;
use Symfony\Component\MimePgp\Mime\Part\PgpKeyPart;
use Symfony\Component\MimePgp\Mime\Part\PgpSignaturePart;
use Symfony\Component\MimePgp\PgpProcess;
use Symfony\Component\MimePgp\PgpSigner;

class PgpSignerTest extends TestCase
{
    private const KEY_EMAIL_ADDRESS = 'pgp@pulli.dev';

    private const KEY_PASSWORD = 'test1234';

    public function testPgpProcessCanSignCorrectly()
    {
        // Given
        $process = new PgpProcess();
        $tester = new PgpTestingProcess();

        // When
        $output = $process->sign('Hello there!', __DIR__.'/_data/pgp_test_secret_key.asc', self::KEY_PASSWORD);

        // Then
        $verified = $tester->verify('Hello there!', $output, __DIR__.'/_data/pgp_test_public_key.asc');
        $this->assertTrue($verified);
        $verified = $tester->verify('Hello there!', $output, __DIR__.'/_data/other_public_key.asc');
        $this->assertFalse($verified);
    }

    public function testSigningWithPublicKey()
    {
        $signer = new PgpSigner(
            __DIR__.'/_data/pgp_test_secret_key.asc',
            __DIR__.'/_data/pgp_test_public_key.asc',
            self::KEY_PASSWORD
        );

        $email = (new Email())
            ->from(new Address(static::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->to(new Address(static::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->text("Hello there!\n\nHow are you?")
            ->subject('PGP Mail');

        // When
        $signedMessage = $signer->sign($email);

        // Then
        $this->assertInstanceOf(Message::class, $signedMessage);

        $body = $signedMessage->getBody();

        $this->assertInstanceOf(PgpSignedPart::class, $body, 'Message is not signed.');

        [$signedPart, $signaturePart] = $body->getParts();

        $this->assertInstanceOf(PgpSignaturePart::class, $signaturePart, 'Not a PgpSignaturePart.');

        $this->assertInstanceOf(MixedPart::class, $signedPart, 'SignedPart is not a MixedPart.');
        // Manually clean the signed part again
        $signedPartString = $this->normalize($signedPart->toString());

        [$signedPart, $publicKeyPart] = $signedPart->getParts();

        $this->assertInstanceOf(TextPart::class, $signedPart, 'Message is not text part.');
        $this->assertInstanceOf(PgpKeyPart::class, $publicKeyPart, 'Message is not public key part.');

        $signature = $signaturePart->bodyToString();

        $this->assertStringContainsString('-----BEGIN PGP SIGNATURE-----', $signature, 'PGP Signature begin is missing.');
        $this->assertStringContainsString('-----END PGP SIGNATURE-----', $signature, 'PGP Signature end end is missing.');

        $originalBody = $this->normalize($email->getBody()->toString());
        $this->assertStringContainsString($originalBody."\r\n", $body->toString(), 'Signed message does not contain the actual message.');

        $tester = new PgpTestingProcess();
        $result = $tester->verify($signedPartString, $signature, __DIR__.'/_data/pgp_test_public_key.asc');
        $this->assertTrue($result, 'Signature is not valid.');
    }

    public function testSigningWithoutPublicKey()
    {
        $signer = new PgpSigner(
            __DIR__.'/_data/pgp_test_secret_key.asc',
            null,
            self::KEY_PASSWORD
        );

        $email = (new Email())
            ->from(new Address(static::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->to(new Address(static::KEY_EMAIL_ADDRESS, 'PuLLi'))
            ->text("Hello there!\n\nHow are you?")
            ->subject('PGP Mail');

        // When
        $signedMessage = $signer->sign($email);

        // Then
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
        $result = $tester->verify($signedPartString, $signature, __DIR__.'/_data/pgp_test_public_key.asc');
        $this->assertTrue($result, 'Signature is not valid.');
    }

    private function normalize(string $part): string
    {
        return str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $part));
    }
}
