<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\CacheWarmer\EncoderDecoderCacheWarmer;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

class EncoderDecoderCacheWarmerTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheDir = sprintf('%s/symfony_test', sys_get_temp_dir());

        $encoderCacheDir = $this->cacheDir.'/json_encoder/encoder';
        $decoderCacheDir = $this->cacheDir.'/json_encoder/decoder';

        if (is_dir($encoderCacheDir)) {
            array_map('unlink', glob($encoderCacheDir.'/*'));
            rmdir($encoderCacheDir);
        }

        if (is_dir($decoderCacheDir)) {
            array_map('unlink', glob($decoderCacheDir.'/*'));
            rmdir($decoderCacheDir);
        }
    }

    public function testWarmUp()
    {
        $this->cacheWarmer([ClassicDummy::class])->warmUp('useless');

        $encoderCacheDir = $this->cacheDir.'/json_encoder/encoder';
        $decoderCacheDir = $this->cacheDir.'/json_encoder/decoder';

        $this->assertSame([
            sprintf('%s/d147026bb5d25e5012afcdc1543cf097.json.resource.php', $encoderCacheDir),
            sprintf('%s/d147026bb5d25e5012afcdc1543cf097.json.stream.php', $encoderCacheDir),
            sprintf('%s/d147026bb5d25e5012afcdc1543cf097.json.string.php', $encoderCacheDir),
        ], glob($encoderCacheDir.'/*'));

        $this->assertSame([
            sprintf('%s/d147026bb5d25e5012afcdc1543cf097.json.resource.php', $decoderCacheDir),
            sprintf('%s/d147026bb5d25e5012afcdc1543cf097.json.stream.php', $decoderCacheDir),
            sprintf('%s/d147026bb5d25e5012afcdc1543cf097.json.string.php', $decoderCacheDir),
        ], glob($decoderCacheDir.'/*'));
    }

    /**
     * @param list<class-string> $encodable
     */
    private function cacheWarmer(array $encodable): EncoderDecoderCacheWarmer
    {
        $typeResolver = TypeResolver::create();

        return new EncoderDecoderCacheWarmer($encodable, new PropertyMetadataLoader($typeResolver), new PropertyMetadataLoader($typeResolver), $this->cacheDir);
    }
}
