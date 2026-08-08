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
use Symfony\Component\KeyManagement\DataKeyHandle;
use Symfony\Component\KeyManagement\Exception\LogicException;

class DataKeyHandleTest extends TestCase
{
    public function testTheReferenceIsExposed()
    {
        $handle = self::handle();

        $this->assertSame('ref', $handle->reference);
    }

    public function testUseReturnsTheConsumerResult()
    {
        $handle = self::handle();

        $this->assertSame('SECRET', $handle->use(strtoupper(...)));
    }

    public function testTheKeyStaysUsableAcrossCalls()
    {
        $handle = self::handle();

        $this->assertSame('secret', $handle->use(static fn (string $k): string => $k));
        $this->assertSame('secret', $handle->use(static fn (string $k): string => $k), 'a handle is meant to be reused, unlike a DataKey.');
        $this->assertFalse($handle->isReleased());
    }

    public function testTheKeyMovesOutOfTheDataKeyItComesFrom()
    {
        $dataKey = new DataKey('secret', new Ciphertext('wrapped', 'app'));

        $handle = new DataKeyHandle('ref', $dataKey);

        $this->assertTrue($dataKey->isConsumed(), 'the handle takes the key over, so the DataKey has nothing left to hand out.');
        $this->assertSame('secret', $handle->use(static fn (string $k): string => $k));
    }

    public function testAnEmptyKeyIsCarriedAsIs()
    {
        $handle = self::handle('');

        $this->assertSame('', $handle->use(static fn (string $k): string => $k));
    }

    public function testDataKeyIsSingleUseWhereTheHandleIsNot()
    {
        $dataKey = new DataKey('secret', new Ciphertext('wrapped', 'app'));
        $dataKey->use(static fn (string $k): string => $k);

        $this->expectException(LogicException::class);
        $dataKey->use(static fn (string $k): string => $k);
    }

    public function testAConsumedDataKeyCannotBackAHandle()
    {
        $dataKey = new DataKey('secret', new Ciphertext('wrapped', 'app'));
        $dataKey->use(static fn (string $k): string => $k);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('already been consumed');
        new DataKeyHandle('ref', $dataKey);
    }

    public function testReleaseWipesThePlaintext()
    {
        $handle = self::handle();
        $handle->release();

        $this->assertTrue($handle->isReleased());
    }

    public function testReleaseIsIdempotent()
    {
        $handle = self::handle();
        $handle->release();
        $handle->release();

        $this->assertTrue($handle->isReleased());
    }

    public function testUseAfterReleaseThrows()
    {
        $handle = self::handle();
        $handle->release();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('already been released');
        $handle->use(static fn (string $k): string => $k);
    }

    private static function handle(string $plaintext = 'secret'): DataKeyHandle
    {
        return new DataKeyHandle('ref', new DataKey($plaintext, new Ciphertext('wrapped', 'app')));
    }
}
