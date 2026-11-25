<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Content\ContentLayout;
use Symfony\Component\HtmlRenderer\Content\IntrinsicSize;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;

final class ReplacedContentTest extends TestCase
{
    public function testFractionalIntrinsicGeometryAndPortableLocalDrawing()
    {
        $size = new IntrinsicSize(10.25, 20.5, 12.75);
        self::assertSame(10.25, $size->minimumWidth);
        self::assertSame(20.5, $size->preferredWidth);
        self::assertSame(12.75, $size->height);
        $display = new DisplayList([['type' => 'push-clip', 'x' => 0, 'y' => 0, 'width' => 20.5, 'height' => 12.75], ['type' => 'text', 'text' => 'Hi', 'x' => 0.25, 'y' => 0.5], ['type' => 'pop-clip']]);
        $layout = new ContentLayout(12.75, $display);
        self::assertSame(12.75, $layout->height);
        self::assertNotSame($display, $layout->displayList);
        self::assertSame($display->getOperations(), $layout->displayList->getOperations());
        self::assertJson(json_encode($layout->displayList->getOperations()));
        self::assertSame(0.0, (new ContentLayout(0, new DisplayList()))->height);
    }

    public function testOnlyExplicitReplacedAspectRatiosAreRetained()
    {
        self::assertNull(new IntrinsicSize(10, 20, 10)->aspectRatio);
        self::assertSame(2.0, new IntrinsicSize(10, 20, 10, 2)->aspectRatio);
        foreach ([0.0, -1.0, \INF, \NAN] as $ratio) {
            try {
                new IntrinsicSize(10, 20, 10, $ratio);
                self::fail('A replaced aspect ratio must be finite and positive.');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testForeignArrayAccessObjectsAreRejectedWithoutInvokingTheirMethods()
    {
        $operation = new class implements \ArrayAccess {
            public int $calls = 0;

            public function offsetExists(mixed $offset): bool
            {
                ++$this->calls;

                return true;
            }

            public function offsetGet(mixed $offset): mixed
            {
                ++$this->calls;

                return 'text';
            }

            public function offsetSet(mixed $offset, mixed $value): void
            {
                ++$this->calls;
            }

            public function offsetUnset(mixed $offset): void
            {
                ++$this->calls;
            }
        };
        try {
            new ContentLayout(0, new DisplayList([$operation]));
            self::fail('Foreign operation objects must not be inspected through callbacks.');
        } catch (\InvalidArgumentException) {
            self::assertSame(0, $operation->calls);
        }
    }

    public function testRecordedContentDetachesExternalReferences()
    {
        $text = 'Hi';
        $color = '#fff';
        $operations = [['type' => 'text', 'text' => &$text, 'color' => &$color]];
        $layout = new ContentLayout(10, new DisplayList($operations));
        $text = 'Changed';
        $color = new \stdClass();
        self::assertSame([['type' => 'text', 'text' => 'Hi', 'color' => '#fff']], $layout->displayList->getOperations());
    }

    #[DataProvider('provideInvalidIntrinsicSizes')]
    public function testInvalidIntrinsicSizesAreRejected(float $minimum, float $preferred, float $height)
    {
        $this->expectException(\InvalidArgumentException::class);
        new IntrinsicSize($minimum, $preferred, $height);
    }

    public static function provideInvalidIntrinsicSizes(): iterable
    {
        yield 'negative minimum' => [-1, 2, 3];
        yield 'reversed widths' => [3, 2, 3];
        yield 'negative height' => [1, 2, -1];
        yield 'infinite width' => [1, \INF, 3];
        yield 'nan height' => [1, 2, \NAN];
    }

    #[DataProvider('provideInvalidLists')]
    public function testInvalidLocalDrawingCannotEscapeItsParent(array $operations)
    {
        $this->expectException(\InvalidArgumentException::class);
        new ContentLayout(1, new DisplayList($operations));
    }

    public static function provideInvalidLists(): iterable
    {
        yield 'unmatched clip pop' => [[['type' => 'pop-clip']]];
        yield 'unclosed transform' => [[['type' => 'push-transform', 'matrix' => [1, 0, 0, 1, 0, 0]]]];
        yield 'unmatched filter pop' => [[['type' => 'pop-filter']]];
        yield 'unclosed filter' => [[['type' => 'push-filter', 'functions' => [['invert', 1.0]]]]];
        yield 'unmatched opacity pop' => [[['type' => 'pop-opacity']]];
        yield 'native object' => [[['type' => 'text', 'text' => 'Hi', 'object' => new \stdClass()]]];
        yield 'nonfinite geometry' => [[['type' => 'rectangle', 'width' => \INF]]];
        yield 'raw terminal escape' => [[['type' => 'text', 'text' => "\x1b[31mHi"]]];
        yield 'missing operation type' => [[['width' => 1]]];
        yield 'non-array operation' => [[new \stdClass()]];
    }

    public function testBinaryImageDataAndIndependentBalancedStateAreAllowed()
    {
        $operations = [['type' => 'push-opacity', 'opacity' => 0.5], ['type' => 'push-transform', 'matrix' => [1, 0, 0, 1, 2, 3]], ['type' => 'pop-opacity'], ['type' => 'image', 'data' => "\x00\x1b\xff"], ['type' => 'pop-transform']];
        self::assertSame($operations, (new ContentLayout(0, new DisplayList($operations)))->displayList->getOperations());
    }

    public function testRecursiveArraysAreRejectedWithoutUnboundedTraversal()
    {
        $operation = ['type' => 'text', 'text' => 'Hi'];
        $operation['cycle'] = &$operation;
        $this->expectException(\InvalidArgumentException::class);
        new ContentLayout(1, new DisplayList([$operation]));
    }
}
