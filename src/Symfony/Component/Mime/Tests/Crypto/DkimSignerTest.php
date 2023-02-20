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
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\DkimSigner;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;

/**
 * @group time-sensitive
 *
 * @requires extension openssl
 */
class DkimSignerTest extends TestCase
{
    private static $pk = <<<EOF
-----BEGIN RSA PRIVATE KEY-----
MIICXAIBAAKBgQC6lQYNOMaboSOE/c2KNl8Rwk61zoMXrEmXC926an3/jHrtj9wB
ndP2DY2nUyz0vpmJlcDOjDwTGs8U/C7zn7PDdZ8EuuxlAa7oNo/38YYV+5Oki93m
io6rGV8zLMGLLygAB1sJaJVP5W9wm0RLY776YFL4V/nekA5ZTnA4+KaIYwIDAQAB
AoGAJLhjgoKkA8kI1omkxAjDWRlmqD1Ga4hKy2FYd/GxbnPVVZ+0atUG/Cvarw2d
kWVZjkxcr8nFoPTrwHOJQgUyOXWLuIuirznoTtDKzC+4JlDsZJd8hkVohqwKfdPA
v4iYceN6V0YRQpsLVwKJinr5k6oHpCGs3sNffpHQzrXc24ECQQDb0JLiMm5OZoYZ
G3739DsYVycUmYmYJtXuUBHTIwBAaOyo0yEmeQ8Li4H5dSSWqeOO0XrfP7cQ3TOm
6LuSrIXDAkEA2Uv2PuteQXGSzOEuQbDbYeR0Le0drDUFJkXBM4oS3XB3wx2+umD+
WqpfLEIXWV3/hkuottTmlsQuuAP3Xv+o4QJAf5FyTRfbcGCLnnKYoyn4Sc36fjgE
5GpVaXLKhXAgq0C5Z9jvujYzhw21pqJXU6DQ0Ye8+WcuxPi7Czix8xNwpQJBAMm1
vexCSMivSPpuvaW1KrEAhOhtB/JndVRFxEa3kTOFx2aUIgyZJQO8y4QmBc6rdxuO
+BpgH30st8GRzPuej4ECQAsLon/QgsyhkfquBMLDC1uhO027K59C/aYRlufPyHkq
HIyrMg2pQ46h2ybEuB50Cs+xF19KwBuGafBtRjkvXdU=
-----END RSA PRIVATE KEY-----
EOF;

    /**
     * @dataProvider getSignData
     */
    public function testSign(int $time, string $bodyCanon, string $headerCanon, string $header)
    {
        ClockMock::withClockMock($time);

        $message = (new Email())
            ->from(new Address('fabien@testdkim.symfony.net', 'Fabién'))
            ->to('fabien.potencier@gmail.com')
            ->subject('Tést')
            ->text("Some body \n \n This \r\n\r\n is really interesting and at the same time very long line to see if everything works as expected, does it?\r\n\r\n\r\n\r\n")
            ->date(new \DateTimeImmutable('2005-10-15', new \DateTimeZone('Europe/Paris')));

        $signer = new DkimSigner(self::$pk, 'testdkim.symfony.net', 'sf');
        $signedMessage = $signer->sign($message, [
            'header_canon' => $headerCanon,
            'body_canon' => $bodyCanon,
            'headers_to_ignore' => ['Message-ID'],
        ]);

        $this->assertSame($message->getBody()->toString(), $signedMessage->getBody()->toString());
        $this->assertTrue($signedMessage->getHeaders()->has('DKIM-Signature'));
        $this->assertEquals($header, $signedMessage->getHeaders()->get('DKIM-Signature')->getBody());
    }

    public static function getSignData()
    {
        yield 'simple/simple' => [
            1591597074, DkimSigner::CANON_SIMPLE, DkimSigner::CANON_SIMPLE,
            'v=1; q=dns/txt; a=rsa-sha256; bh=JC6qmm3afMaxL3Rm1YHxrzIpqiUuB7aAarWMcZfuca4=; d=testdkim.symfony.net; h=From: To: Subject: Date: MIME-Version; i=@testdkim.symfony.net; s=sf; t=1591597074; c=simple/simple; b=Z+KvV7QwQ7gdTy49sOzT1c+UDZbT8nFUClbiW8cCKtj4HVuIxGUgWMSN46CX8GoYd0rIsoutF +Cgc4rcp/AU9tgLswliYh66Gk5gR6tA0h13FBVFuWeWz7PiMK5s8nLymMmiKDM0GNjshy4cdD VnQdREINJOD7yycmRDPT0Q828=',
        ];

        yield 'relaxed/simple' => [
            1591597424, DkimSigner::CANON_RELAXED, DkimSigner::CANON_SIMPLE,
            'v=1; q=dns/txt; a=rsa-sha256; bh=JC6qmm3afMaxL3Rm1YHxrzIpqiUuB7aAarWMcZfuca4=; d=testdkim.symfony.net; h=From: To: Subject: Date: MIME-Version; i=@testdkim.symfony.net; s=sf; t=1591597424; c=simple/relaxed; b=F52zm1Pg6VKb0g6ySZ6KcFxC2jlnUVkXb2OjptChUXsJBM83n1Gk48D2ipbP2L+UkKXvKl6YI BdMxkde0Tpw0hTxDJdM5xekacqWZbyC0y8wE5Ks635aDagdV+WfJ3m6l3grb+Ng+qqetEWZpP 3vRRBd8qDn9IUgoPxDJ6MpIMs=',
        ];

        yield 'relaxed/relaxed' => [
            1591597493, DkimSigner::CANON_RELAXED, DkimSigner::CANON_RELAXED,
            'v=1; q=dns/txt; a=rsa-sha256; bh=JC6qmm3afMaxL3Rm1YHxrzIpqiUuB7aAarWMcZfuca4=; d=testdkim.symfony.net; h=From: To: Subject: Date: MIME-Version; i=@testdkim.symfony.net; s=sf; t=1591597493; c=relaxed/relaxed; b=sINllavShGfnMXymubjBflrAlRlv3zGTP/ZbI2XlFqu5G7Bvb0jFReKkgUo/Swezt50w4WqxP 3zNv4W1uilomtgqjihf4WJRi/wMnVjCt8KZ8z3AXrDK+udcXln6OCLw63CrV4FpdOfYyQUQBq NaizUh+k7y1dvqxMJTaAp2POY=',
        ];

        yield 'simple/relaxed' => [
            1591597612, DkimSigner::CANON_SIMPLE, DkimSigner::CANON_RELAXED,
            'v=1; q=dns/txt; a=rsa-sha256; bh=JC6qmm3afMaxL3Rm1YHxrzIpqiUuB7aAarWMcZfuca4=; d=testdkim.symfony.net; h=From: To: Subject: Date: MIME-Version; i=@testdkim.symfony.net; s=sf; t=1591597612; c=relaxed/simple; b=E+BszWWfYJfrWXk5uggwZJmLlh+4IeVScnJhqAj0G4h0dhqRZ0Qs1XNPSS0IZtPSTUgNxAeTi mc8jjVCnrROPnYnaomvgTdkxwRU5ZcA4felmGjcXODrdy9GUAokES6qjy4bVwBvaHxMgr00eP J3sJqBBwcg/HsO52ppJma/1HM=',
        ];
    }

    public function testSignWithUnsupportedAlgorithm()
    {
        $message = $this->createMock(Message::class);

        $signer = new DkimSigner(self::$pk, 'testdkim.symfony.net', 'sf', [
            'algorithm' => 'unsupported-value',
        ]);

        $this->expectExceptionObject(
            new \LogicException('Invalid DKIM signing algorithm "unsupported-value".')
        );

        $signer->sign($message, []);
    }

    /**
     * @dataProvider getCanonicalizeHeaderData
     */
    public function testCanonicalizeHeader(string $bodyCanon, string $canonBody, string $body, int $maxLength)
    {
        $message = (new Email())
            ->from(new Address('fabien@testdkim.symfony.net', 'Fabién'))
            ->to('fabien.potencier@gmail.com')
            ->subject('Tést')
            ->text($body)
        ;

        $signer = new DkimSigner(self::$pk, 'testdkim.symfony.net', 'sf');
        $signedMessage = $signer->sign($message, [
            'body_canon' => $bodyCanon,
            'body_max_length' => $maxLength,
            'body_show_length' => true,
        ]);

        preg_match('{bh=([^;]+).+l=([^;]+)}', $signedMessage->getHeaders()->get('DKIM-Signature')->getBody(), $matches);
        $bh = $matches[1];
        $l = $matches[2];
        $this->assertEquals(base64_encode(hash('sha256', $canonBody, true)), $bh);
        $this->assertEquals(\strlen($canonBody), $l);
    }

    public static function getCanonicalizeHeaderData()
    {
        yield 'simple_empty' => [
            DkimSigner::CANON_SIMPLE, "\r\n", '', \PHP_INT_MAX,
        ];
        yield 'relaxed_empty' => [
            DkimSigner::CANON_RELAXED, '', '', \PHP_INT_MAX,
        ];

        yield 'simple_empty_single_ending_CLRF' => [
            DkimSigner::CANON_SIMPLE, "\r\n", "\r\n", \PHP_INT_MAX,
        ];
        yield 'relaxed_empty_single_ending_CLRF' => [
            DkimSigner::CANON_RELAXED, '', "\r\n", \PHP_INT_MAX,
        ];

        yield 'simple_multiple_ending_CLRF' => [
            DkimSigner::CANON_SIMPLE, "Some body\r\n", "Some body\r\n\r\n\r\n\r\n\r\n\r\n", \PHP_INT_MAX,
        ];
        yield 'relaxed_multiple_ending_CLRF' => [
            DkimSigner::CANON_RELAXED, "Some body\r\n", "Some body\r\n\r\n\r\n\r\n\r\n\r\n", \PHP_INT_MAX,
        ];

        yield 'simple_basic' => [
            DkimSigner::CANON_SIMPLE, "Some body\r\n", "Some body\r\n", \PHP_INT_MAX,
        ];
        yield 'relaxed_basic' => [
            DkimSigner::CANON_RELAXED, "Some body\r\n", "Some body\r\n", \PHP_INT_MAX,
        ];

        $body = "Some    body      with whitespaces\r\n";
        yield 'simple_with_many_inline_whitespaces' => [
            DkimSigner::CANON_SIMPLE, $body, $body, \PHP_INT_MAX,
        ];
        yield 'relaxed_with_many_inline_whitespaces' => [
            DkimSigner::CANON_RELAXED, "Some body with whitespaces\r\n", $body, \PHP_INT_MAX,
        ];

        yield 'simple_basic_with_length' => [
            DkimSigner::CANON_SIMPLE, 'Some b', "Some body\r\n", 6,
        ];
        yield 'relaxed_basic_with_length' => [
            DkimSigner::CANON_RELAXED, 'Some b', "Some body\r\n", 6,
        ];
    }
}
