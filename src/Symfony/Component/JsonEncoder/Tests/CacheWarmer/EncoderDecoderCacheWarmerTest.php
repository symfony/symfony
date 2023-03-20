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
    private string $encodersDir;
    private string $decodersDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->encodersDir = \sprintf('%s/symfony_json_encoder_test/json_encoder/encoder', sys_get_temp_dir());
        $this->decodersDir = \sprintf('%s/symfony_json_encoder_test/json_encoder/decoder', sys_get_temp_dir());

        if (is_dir($this->encodersDir)) {
            array_map('unlink', glob($this->encodersDir.'/*'));
            rmdir($this->encodersDir);
        }

        if (is_dir($this->decodersDir)) {
            array_map('unlink', glob($this->decodersDir.'/*'));
            rmdir($this->decodersDir);
        }
    }

    public function testWarmUp()
    {
        $this->cacheWarmer([ClassicDummy::class])->warmUp('useless');

        $this->assertSame([
            \sprintf('%s/d147026bb5d25e5012afcdc1543cf097.json.php', $this->encodersDir),
        ], glob($this->encodersDir.'/*'));

        $this->assertSame([
            \sprintf('%s/d147026bb5d25e5012afcdc1543cf097.json.php', $this->decodersDir),
            \sprintf('%s/d147026bb5d25e5012afcdc1543cf097.json.stream.php', $this->decodersDir),
        ], glob($this->decodersDir.'/*'));
    }

    /**
     * @param list<class-string> $encodable
     */
    private function cacheWarmer(array $encodable): EncoderDecoderCacheWarmer
    {
        $typeResolver = TypeResolver::create();

        return new EncoderDecoderCacheWarmer(
            $encodable,
            new PropertyMetadataLoader($typeResolver),
            new PropertyMetadataLoader($typeResolver),
            $this->encodersDir,
            $this->decodersDir,
        );
    }
}
