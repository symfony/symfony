<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\EventListener\DkimSignedMessageListener;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\DkimSigner;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\TextPart;

class DkimSignedMessageListenerTest extends TestCase
{
    /**
     * @requires extension openssl
     */
    public function testDkimMessageSigningProcess()
    {
        $signer = new DkimSigner(<<<KEY
-----BEGIN RSA PRIVATE KEY-----
MIICXAIBAAKBgQDbESTusnjpHAlqnU/zIcNcI1dskQBEG8N4mQo408n33M5FGLxo
WoCQyqnnyujc2gkpG8aiPJFFnToWkbE8H+ursRRLBgdrCQOJh+akPEt4aNqiD/JK
HxLr+1ar9zVUvvPuT4nbrJK44ixEyFbpV+xgiSWWIb8xtsRtoXEoH9yYrQIDAQAB
AoGAGHi00HldamT4ZFGIcdeOtEc6Z+VWy0TytBEchDacdDTVUgCuY1Xg0Mvi6QLQ
uoMczNvOd6ceb1FeANFrpBTIxaM68K/lIUn2fYrtcBpGtbXbSf5hTZhApFiEnQ1u
H2TV+ItW6OYFLtZNY+Vym5/7HrXx/ADHSlMqA3KS8UEN/v0CQQD5RGhrwy92M0wh
2UdSuL5IepjMvpeHcpbJlxtY8jhygnSDmvJtSdgFbP/VSzkYqnlFwXztRRt5F4XT
aUQepFk/AkEA4PvpowzEZk6YnYMQV7qFYARJIB5nLpwCxPbvIooi8adiCuUBwxxe
hRwRM9vHp641safMUaE/T/OovLVEezbHEwJBAOVn0t5SjWywO0HvsRdtlRopUlUk
l1p92E6Bdha/HbotW8P/J1vzmQ8tSKpph4uu4NuU/j9z2ZvxTSXLfHji8osCQCUD
jNaUXSNvvs/7Jg8o0pSPX/B20AbtB8+byI/oJgOXxBuCvZ1551sC2RmtCNXfZVoK
/yRW4PGoZpRVRiT3SB0CQCkSOXB6YoLDagS3X10RInlGkB5pfBd1cG1pQS7YEFjX
Y4x0EYVpNU9oHyeMlLgyevy07udFZXvHItT6WgbspQQ=
-----END RSA PRIVATE KEY-----
KEY, 'symfony.com', 's1');
        $listener = new DkimSignedMessageListener($signer);
        $message = new Message(
            new Headers(
                new MailboxListHeader('From', [new Address('sender@example.com')])
            ),
            new TextPart('hello')
        );
        $envelope = new Envelope(new Address('sender@example.com'), [new Address('r1@example.com')]);
        $event = new MessageEvent($message, $envelope, 'default');

        $listener->onMessage($event);
        $this->assertNotSame($message, $event->getMessage());
        $this->assertTrue($event->getMessage()->getHeaders()->has('DKIM-Signature'));
        $this->assertFalse($message->getHeaders()->has('DKIM-Signature'));
    }
}
