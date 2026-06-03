<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Part;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Part\SMimePart;

class SMimePartTestToStringGadget
{
    public static bool $fired = false;

    public function __toString(): string
    {
        self::$fired = true;

        return '';
    }
}

class SMimePartTest extends TestCase
{
    #[DataProvider('provideTrampolineKeys')]
    public function testUnserializeRejectsObjectInTypedStringProperty(string $key)
    {
        $template = (new SMimePart('body content', 'application', 'pkcs7-mime', []))->__serialize();
        $template[$key] = new SMimePartTestToStringGadget();
        $payload = \sprintf('O:%d:"%s":%d:{', \strlen(SMimePart::class), SMimePart::class, \count($template));
        foreach ($template as $k => $v) {
            $payload .= serialize($k).serialize($v);
        }
        $payload .= '}';
        SMimePartTestToStringGadget::$fired = false;

        try {
            unserialize($payload);
            $this->fail('Expected BadMethodCallException.');
        } catch (\BadMethodCallException $e) {
        }

        $this->assertFalse(SMimePartTestToStringGadget::$fired, '__toString gadget must not fire during unserialize');
    }

    public static function provideTrampolineKeys(): iterable
    {
        yield ['body'];
        yield ['type'];
        yield ['subtype'];
    }
}
