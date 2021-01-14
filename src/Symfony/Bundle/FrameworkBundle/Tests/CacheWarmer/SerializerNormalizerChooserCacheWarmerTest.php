<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\CacheWarmer;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\SerializerNormalizerChooserCacheWarmer;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Serializer\Cache\CacheNormalizationProviderInterface;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class SerializerNormalizerChooserCacheWarmerTest extends TestCase
{
    public function testWarmUp()
    {
        $file = sys_get_temp_dir().'/serializer.normalization.php';
        @unlink($file);

        $provider1 = $this->createMock(CacheNormalizationProviderInterface::class);
        $provider1->method('provide')->willReturnCallback(function () {
            yield ['json', new \stdClass()];
            yield ['xml', new \stdClass(), ['foo' => 'bar']];
            yield ['xml', new \DateTime()];
        });

        $provider2 = $this->createMock(CacheNormalizationProviderInterface::class);
        $provider2->method('provide')->willReturnCallback(function () {
            yield ['yaml', new \stdClass()];
            yield ['json', new \DateTime()];
        });

        $cacheWarmer = new SerializerNormalizerChooserCacheWarmer(
            [new DateTimeNormalizer(), new ObjectNormalizer()],
            [$provider1, $provider2],
            $file
        );

        $cacheWarmer->warmUp(\dirname($file));
        $this->assertFileExists($file);

        $arrayPool = new PhpArrayAdapter($file, new NullAdapter());
        $compiledContext = hash('md5', json_encode(['foo' => 'bar']));

        foreach (['normalizer', 'denormalizer'] as $action) {
            $this->assertSame(1, $arrayPool->getItem("{$action}_json_stdClass")->get());
            $this->assertSame(1, $arrayPool->getItem("{$action}_xml_stdClass_$compiledContext")->get());
            $this->assertSame(0, $arrayPool->getItem("{$action}_xml_DateTime")->get());
            $this->assertSame(1, $arrayPool->getItem("{$action}_yaml_stdClass")->get());
            $this->assertSame(0, $arrayPool->getItem("{$action}_json_DateTime")->get());
        }
    }
}
