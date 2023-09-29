<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests\ImportMap;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntries;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;

class ImportMapEntriesTest extends TestCase
{
    public function testGetIterator()
    {
        $entry1 = new ImportMapEntry('entry1', 'path1');
        $entry2 = new ImportMapEntry('entry2', 'path2');

        $entries = new ImportMapEntries([$entry1]);
        $entries->add($entry2);

        $this->assertSame([$entry1, $entry2], iterator_to_array($entries));
    }

    public function testHas()
    {
        $entries = new ImportMapEntries([new ImportMapEntry('entry1', 'path1')]);

        $this->assertTrue($entries->has('entry1'));
        $this->assertFalse($entries->has('entry2'));
    }

    public function testGet()
    {
        $entry = new ImportMapEntry('entry1', 'path1');
        $entries = new ImportMapEntries([$entry]);

        $this->assertSame($entry, $entries->get('entry1'));
    }

    public function testRemove()
    {
        $entries = new ImportMapEntries([new ImportMapEntry('entry1', 'path1')]);
        $entries->remove('entry1');

        $this->assertFalse($entries->has('entry1'));
    }
}
