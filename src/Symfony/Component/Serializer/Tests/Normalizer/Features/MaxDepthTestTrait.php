<?php

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\MaxDepthDummy;

/**
 * Covers AbstractObjectNormalizer::ENABLE_MAX_DEPTH and AbstractObjectNormalizer::MAX_DEPTH_HANDLER.
 */
trait MaxDepthTestTrait
{
    abstract protected function getNormalizerForMaxDepth(): NormalizerInterface;

    public function testMaxDepth()
    {
        $normalizer = $this->getNormalizerForMaxDepth();

        $level1 = new MaxDepthDummy();
        $level1->bar = 'level1';

        $level2 = new MaxDepthDummy();
        $level2->bar = 'level2';
        $level1->child = $level2;

        $level3 = new MaxDepthDummy();
        $level3->bar = 'level3';
        $level2->child = $level3;

        $level4 = new MaxDepthDummy();
        $level4->bar = 'level4';
        $level3->child = $level4;

        $result = $normalizer->normalize($level1, null, ['enable_max_depth' => true]);

        $expected = [
            'bar' => 'level1',
            'child' => [
                'bar' => 'level2',
                'child' => [
                    'bar' => 'level3',
                    'child' => [
                        'child' => null,
                    ],
                ],
                'foo' => null,
            ],
            'foo' => null,
        ];

        $this->assertEquals($expected, $result);
    }

    public function testMaxDepthHandler()
    {
        $level1 = new MaxDepthDummy();
        $level1->bar = 'level1';

        $level2 = new MaxDepthDummy();
        $level2->bar = 'level2';
        $level1->child = $level2;

        $level3 = new MaxDepthDummy();
        $level3->bar = 'level3';
        $level2->child = $level3;

        $level4 = new MaxDepthDummy();
        $level4->bar = 'level4';
        $level3->child = $level4;

        $handlerCalled = false;
        $maxDepthHandler = function ($object, $parentObject, $attributeName, $format, $context) use (&$handlerCalled) {
            if ('foo' === $attributeName) {
                return null;
            }

            $this->assertSame('level4', $object);
            $this->assertInstanceOf(MaxDepthDummy::class, $parentObject);
            $this->assertSame('bar', $attributeName);
            $this->assertSame('test', $format);
            $this->assertArrayHasKey('enable_max_depth', $context);

            $handlerCalled = true;

            return 'handler';
        };

        $normalizer = $this->getNormalizerForMaxDepth();
        $normalizer->normalize($level1, 'test', [
            'enable_max_depth' => true,
            'max_depth_handler' => $maxDepthHandler,
        ]);

        $this->assertTrue($handlerCalled);
    }
}
