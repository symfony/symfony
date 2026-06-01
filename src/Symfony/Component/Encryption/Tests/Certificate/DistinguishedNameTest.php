<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Tests\Certificate;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Certificate\DistinguishedName;

final class DistinguishedNameTest extends TestCase
{
    private function dn(): DistinguishedName
    {
        return new DistinguishedName([
            'CN' => 'example.com',
            'O' => 'Acme',
            'OU' => 'Engineering',
            'C' => 'US',
            'ST' => 'California',
            'L' => 'San Francisco',
            'emailAddress' => 'admin@example.com',
        ]);
    }

    public function testNamedAccessors()
    {
        $dn = $this->dn();

        self::assertSame('example.com', $dn->commonName());
        self::assertSame('Acme', $dn->organization());
        self::assertSame('Engineering', $dn->organizationalUnit());
        self::assertSame('US', $dn->country());
        self::assertSame('California', $dn->state());
        self::assertSame('San Francisco', $dn->locality());
        self::assertSame('admin@example.com', $dn->emailAddress());
    }

    public function testMissingFieldsAreNull()
    {
        $dn = new DistinguishedName(['CN' => 'minimal.example']);

        self::assertSame('minimal.example', $dn->commonName());
        self::assertNull($dn->organization());
        self::assertNull($dn->country());
    }

    public function testGetAndToArray()
    {
        $dn = $this->dn();

        self::assertSame('Acme', $dn->get('O'));
        self::assertNull($dn->get('UNKNOWN'));
        self::assertSame('example.com', $dn->toArray()['CN']);
    }

    public function testEquals()
    {
        $a = new DistinguishedName(['CN' => 'x', 'O' => 'y']);
        $b = new DistinguishedName(['CN' => 'x', 'O' => 'y']);
        $c = new DistinguishedName(['CN' => 'x', 'O' => 'z']);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
