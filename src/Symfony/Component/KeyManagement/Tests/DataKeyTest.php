<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\Exception\LogicException;

class DataKeyTest extends TestCase
{
    public function testUseReturnsTheClosureResultAndConsumesThePlaintext()
    {
        $dataKey = new DataKey('s3cret-bytes', new Ciphertext('wrapped', 'kid'));

        $result = $dataKey->use(static fn (string $p): string => strrev($p));

        $this->assertSame(strrev('s3cret-bytes'), $result);
        $this->assertTrue($dataKey->isConsumed());
    }

    public function testUseRejectsAlreadyConsumedKey()
    {
        $dataKey = new DataKey('s3cret', new Ciphertext('wrapped', 'kid'));
        $dataKey->use(static fn (string $p): string => $p);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('already been consumed');
        $dataKey->use(static fn (string $p): string => $p);
    }

    public function testUseWipesOnException()
    {
        $dataKey = new DataKey('s3cret', new Ciphertext('wrapped', 'kid'));

        try {
            $dataKey->use(static fn () => throw new \DomainException());
        } catch (\DomainException) {
        }

        $this->assertTrue($dataKey->isConsumed());
    }

    public function testFreshDataKeyIsNotConsumed()
    {
        $dataKey = new DataKey('s3cret', new Ciphertext('wrapped', 'kid'));

        $this->assertFalse($dataKey->isConsumed());
    }

    public function testWrappedFormStaysAccessibleAfterConsumption()
    {
        $dataKey = new DataKey('s3cret', new Ciphertext('wrapped', 'kid'));
        $dataKey->use(static fn (string $p): string => $p);

        $this->assertSame('wrapped', $dataKey->wrapped->blob);
        $this->assertSame('kid', $dataKey->wrapped->keyId);
    }
}
