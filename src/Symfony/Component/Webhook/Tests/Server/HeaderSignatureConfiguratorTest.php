<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Tests\Server;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Exception\LogicException;
use Symfony\Component\Webhook\Server\HeaderSignatureConfigurator;
use Symfony\Component\Webhook\Server\SignatureFormat;

class HeaderSignatureConfiguratorTest extends TestCase
{
    private const BODY = '{"foo":"bar"}';
    private const SECRET = 's3cr3t';

    public function testLegacyFormatByDefault()
    {
        $options = (new HttpOptions())->setBody(self::BODY);

        (new HeaderSignatureConfigurator())
            ->configure(new RemoteEvent('event-name', 'event-id', []), self::SECRET, $options);

        $this->assertSame(
            'sha256='.hash_hmac('sha256', 'event-nameevent-id'.self::BODY, self::SECRET),
            $options->toArray()['headers']['Webhook-Signature']
        );
    }

    public function testStandardFormat()
    {
        $options = (new HttpOptions())
            ->setBody(self::BODY)
            ->setHeaders(['Webhook-Timestamp' => '1700000000']);

        (new HeaderSignatureConfigurator(format: SignatureFormat::Standard))
            ->configure(new RemoteEvent('event-name', 'event-id', []), self::SECRET, $options);

        $expected = 'v1,'.base64_encode(hash_hmac('sha256', 'event-id.1700000000.'.self::BODY, self::SECRET, true));
        $this->assertSame($expected, $options->toArray()['headers']['Webhook-Signature']);
    }

    public function testStandardFormatUsesTheDecodedWhsecSecret()
    {
        $options = (new HttpOptions())
            ->setBody(self::BODY)
            ->setHeaders(['Webhook-Timestamp' => '1700000000']);

        (new HeaderSignatureConfigurator(format: SignatureFormat::Standard))
            ->configure(new RemoteEvent('event-name', 'event-id', []), 'whsec_'.base64_encode('raw-key-bytes'), $options);

        $expected = 'v1,'.base64_encode(hash_hmac('sha256', 'event-id.1700000000.'.self::BODY, 'raw-key-bytes', true));
        $this->assertSame($expected, $options->toArray()['headers']['Webhook-Signature']);
    }

    public function testTransitionalFormatEmitsBothSpaceSeparated()
    {
        $options = (new HttpOptions())
            ->setBody(self::BODY)
            ->setHeaders(['Webhook-Timestamp' => '1700000000']);

        (new HeaderSignatureConfigurator(format: SignatureFormat::Transitional))
            ->configure(new RemoteEvent('event-name', 'event-id', []), self::SECRET, $options);

        $this->assertSame([
            'sha256='.hash_hmac('sha256', 'event-nameevent-id'.self::BODY, self::SECRET),
            'v1,'.base64_encode(hash_hmac('sha256', 'event-id.1700000000.'.self::BODY, self::SECRET, true)),
        ], explode(' ', $options->toArray()['headers']['Webhook-Signature']));
    }

    public function testStandardFormatRequiresTimestampHeader()
    {
        $options = (new HttpOptions())->setBody(self::BODY);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"Webhook-Timestamp" header must be set');

        (new HeaderSignatureConfigurator(format: SignatureFormat::Standard))
            ->configure(new RemoteEvent('event-name', 'event-id', []), self::SECRET, $options);
    }

    public function testTheEnumCoversTheConfigurableValues()
    {
        // framework.webhook.signature_format is mapped onto the enum by its value
        $this->assertSame(
            ['legacy', 'standard', 'transitional'],
            array_column(SignatureFormat::cases(), 'value')
        );
    }
}
