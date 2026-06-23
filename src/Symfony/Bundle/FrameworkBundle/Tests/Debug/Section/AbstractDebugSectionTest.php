<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Debug\Section;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Debug\DebugItem;
use Symfony\Bundle\FrameworkBundle\Debug\Section\AbstractDebugSection;

class AbstractDebugSectionTest extends TestCase
{
    public function testEmptyQueryReturnsTheFullSet()
    {
        $section = $this->createSection(
            new DebugItem('t', 'one', 'one'),
            new DebugItem('t', 'two', 'two'),
        );

        $this->assertCount(2, $section->search(''));
    }

    public function testItemListIsBuiltOnlyOnce()
    {
        $section = $this->createSection(new DebugItem('t', 'one', 'one'));

        $section->search('');
        $section->search('one');

        $this->assertSame(1, $section->buildCount);
    }

    public function testLabelMatchesRankBeforeSearchTextMatches()
    {
        $section = $this->createSection(
            new DebugItem('t', 'aaa', 'aaa', searchText: 'kernel'),
            new DebugItem('t', 'kernel.listener', 'kernel.listener'),
        );

        $this->assertSame(['kernel.listener', 'aaa'], array_column($section->search('kernel'), 'label'));
    }

    public function testRankingPreservesGroupContiguity()
    {
        $section = $this->createSection(
            new DebugItem('t', 'a1', 'a1', 'Group A', searchText: 'kernel'),
            new DebugItem('t', 'a2', 'kernel.a2', 'Group A'),
            new DebugItem('t', 'b1', 'b1', 'Group B', searchText: 'kernel'),
            new DebugItem('t', 'b2', 'kernel.b2', 'Group B'),
        );

        $this->assertSame(
            ['kernel.a2', 'a1', 'kernel.b2', 'b1'],
            array_column($section->search('kernel'), 'label'),
        );
    }

    public function testFilterIsConsistentWithSearch()
    {
        $items = [
            new DebugItem('t', 'one', 'one'),
            new DebugItem('t', 'two', 'two'),
        ];
        $section = $this->createSection(...$items);

        $this->assertEquals($section->search('two'), $section->filter($items, 'two'));
    }

    private function createSection(DebugItem ...$items): AbstractDebugSection
    {
        return new class(array_values($items)) extends AbstractDebugSection {
            public int $buildCount = 0;

            public function __construct(
                private readonly array $fixtures,
            ) {
            }

            public function getLabel(): string
            {
                return 'Test';
            }

            public function getShortLabel(): string
            {
                return 'Test';
            }

            public function describe(DebugItem $item, int $width): string
            {
                return $item->value;
            }

            protected function buildItems(): array
            {
                ++$this->buildCount;

                return $this->fixtures;
            }
        };
    }
}
