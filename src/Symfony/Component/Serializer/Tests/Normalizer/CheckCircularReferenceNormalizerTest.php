<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\CheckCircularReferenceNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;

/**
 * @author Jérôme Desjardins <jewome62@gmail.com>
 */
class CheckCircularReferenceNormalizerTest extends TestCase
{
    public function testNormalize()
    {
        $subNormalizer = $this
            ->getMockBuilder(NormalizerInterface::class)
            ->getMock()
        ;

        $subNormalizer->method('normalize')->willReturn(['foo' => 'foo']);
        $normalizer = new CheckCircularReferenceNormalizer($subNormalizer);

        $dummy = new Dummy();

        $data = $normalizer->normalize($dummy, 'json');
        $this->assertSame(['foo' => 'foo'], $data);

        $data = $normalizer->normalize($dummy, 'json');
        $this->assertSame(['foo' => 'foo'], $data);
    }

    public function testSupportNormalization()
    {
        $subNormalizer = $this
            ->getMockBuilder([NormalizerInterface::class, DenormalizerInterface::class])
            ->getMock()
        ;

        $subNormalizer->method('supportsNormalization')->willReturn(true);
        $normalizer = new CheckCircularReferenceNormalizer($subNormalizer);

        $this->assertTrue($normalizer->supportsNormalization([], 'json'));

        $subNormalizer = $this
            ->getMockBuilder([NormalizerInterface::class, DenormalizerInterface::class])
            ->getMock()
        ;

        $normalizer = new CheckCircularReferenceNormalizer($subNormalizer);
        $subNormalizer->method('supportsNormalization')->willReturn(false);
        $this->assertFalse($normalizer->supportsNormalization([], 'json'));
    }

    public function testDenormalize()
    {
        $subNormalizer = $this
            ->getMockBuilder([NormalizerInterface::class, DenormalizerInterface::class])
            ->getMock()
        ;

        $dummy = new DummyCircular();
        $subNormalizer->method('denormalize')->willReturn($dummy);
        $normalizer = new CheckCircularReferenceNormalizer($subNormalizer);

        $data = $normalizer->denormalize([], 'type', 'json');

        $this->assertSame($dummy, $data);
    }

    public function testSupportDenormalization()
    {
        $subNormalizer = $this
            ->getMockBuilder([NormalizerInterface::class, DenormalizerInterface::class])
            ->getMock()
        ;

        $subNormalizer->method('supportsDenormalization')->willReturn(true);
        $normalizer = new CheckCircularReferenceNormalizer($subNormalizer);

        $this->assertTrue($normalizer->supportsDenormalization([], 'type', 'json'));

        $subNormalizer = $this
            ->getMockBuilder([NormalizerInterface::class, DenormalizerInterface::class])
            ->getMock()
        ;

        $normalizer = new CheckCircularReferenceNormalizer($subNormalizer);
        $subNormalizer->method('supportsDenormalization')->willReturn(false);
        $this->assertFalse($normalizer->supportsDenormalization([], 'type', 'json'));
    }

    public function testHasCacheableSupportMethod()
    {
        $subNormalizer = $this
            ->getMockBuilder([NormalizerInterface::class, CacheableSupportsMethodInterface::class])
            ->getMock()
        ;

        $subNormalizer->method('hasCacheableSupportsMethod')->willReturn(true);
        $normalizer = new CheckCircularReferenceNormalizer($subNormalizer);

        $this->assertTrue($normalizer->hasCacheableSupportsMethod());

        $subNormalizer = $this
            ->getMockBuilder([NormalizerInterface::class, CacheableSupportsMethodInterface::class])
            ->getMock()
        ;

        $normalizer = new CheckCircularReferenceNormalizer($subNormalizer);
        $subNormalizer->method('hasCacheableSupportsMethod')->willReturn(false);
        $this->assertFalse($normalizer->hasCacheableSupportsMethod());
    }


    /**
     * @expectedException \Symfony\Component\Serializer\Exception\CircularReferenceException
     */
    public function testThrowException()
    {
        $dummyCircular = new DummyCircular();
        $dummyNormalizer = new DummyNormalizer();
        $normalizer = new CheckCircularReferenceNormalizer($dummyNormalizer);
        $serializer = new Serializer([$normalizer]);

        $normalizer->normalize($dummyCircular, 'json');
    }

    public function testLimitCounter()
    {
        $dummyCircular = new DummyCircular();
        $dummyNormalizer = new DummyNormalizer();
        $normalizer = new CheckCircularReferenceNormalizer($dummyNormalizer);
        $serializer = new Serializer([$normalizer]);

        try {
            $normalizer->normalize($dummyCircular, 'json', [
                CheckCircularReferenceNormalizer::CIRCULAR_REFERENCE_LIMIT => 3
            ]);
        } catch (CircularReferenceException $exception) {
            $this->assertSame(3, $dummyCircular->counter);

            return;
        }

        $this->assertFalse(true);
    }

    public function testHandler()
    {
        $dummyCircular = new DummyCircular();
        $dummyNormalizer = new DummyNormalizer();
        $normalizer = new CheckCircularReferenceNormalizer($dummyNormalizer);
        $serializer = new Serializer([$normalizer]);

        $data = $normalizer->normalize($dummyCircular, 'format', [
            'context_key' => 'context_value',
            CheckCircularReferenceNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object, $format, $context) use ($dummyCircular) {
                $this->assertSame($dummyCircular, $object);
                $this->assertSame(1, $object->counter);
                $this->assertSame('format', $format);
                $this->assertInternalType('array', $context);
                $this->assertArrayHasKey('context_key', $context);
                $this->assertSame($context['context_key'], 'context_value');

                return 'dummy';
            }
        ]);

        $this->assertSame('dummy', $data);
    }
}

class DummyCircular
{
    public $counter = 0;
}

class DummyNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    public $object;

    public function normalize($object, $format = null, array $context = [])
    {
        $object->counter++;

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, $format = null)
    {
        return true;
    }
}
