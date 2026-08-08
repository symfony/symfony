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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

/**
 * What every object holding key material owes, whichever tool walks it.
 *
 * A single `dump()` of a service, or one exception page rendered in debug, is enough to put a key
 * in a log or on a screen, so the guarantee is checked against every tool that prints an object
 * rather than against the one that is easiest to intercept.
 */
class KeyMaterialTest extends TestCase
{
    private const string SECRET = 'S3CR3T-KEY-MATERIAL-0123456789ab';

    /**
     * @return iterable<string, array{\Closure(): object}>
     */
    public static function objectsHoldingKeyMaterial(): iterable
    {
        yield 'data key' => [static fn (): object => new DataKey(self::SECRET, new Ciphertext('wrapped', 'kid'))];
        yield 'in-memory key loader' => [static fn (): object => new InMemoryKeyLoader(['app' => self::SECRET])];
    }

    /**
     * @param \Closure(): object $factory
     */
    #[DataProvider('objectsHoldingKeyMaterial')]
    public function testNoPrintingToolShowsTheKey(\Closure $factory)
    {
        $object = $factory();

        ob_start();
        var_dump($object);
        print_r($object);
        var_export($object);
        $printed = ob_get_clean();

        $this->assertStringNotContainsString(self::SECRET, $printed);
    }

    /**
     * Which is what covers `dump()`, the profiler and the exception page in one go.
     *
     * @param \Closure(): object $factory
     */
    #[DataProvider('objectsHoldingKeyMaterial')]
    public function testTheVarClonerShowsNoKeyEither(\Closure $factory)
    {
        $dumped = (new CliDumper())->dump((new VarCloner())->cloneVar($factory()), true);

        $this->assertStringNotContainsString(self::SECRET, $dumped);
    }

    /**
     * @param \Closure(): object $factory
     */
    #[DataProvider('objectsHoldingKeyMaterial')]
    public function testSerializingIsRefused(\Closure $factory)
    {
        $object = $factory();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf('An instance of "%s" cannot be serialized', $object::class));

        serialize($object);
    }
}
