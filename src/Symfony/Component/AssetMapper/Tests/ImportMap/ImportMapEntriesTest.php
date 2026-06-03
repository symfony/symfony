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
use Symfony\Component\AssetMapper\ImportMap\ImportMapType;

class ImportMapEntriesTest extends TestCase
{
    public function testGetIterator()
    {
        $entry1 = ImportMapEntry::createLocal('entry1', ImportMapType::JS, 'path1', true);
        $entry2 = ImportMapEntry::createLocal('entry2', ImportMapType::CSS, 'path2', false);

        $entries = new ImportMapEntries([$entry1]);
        $entries->add($entry2);

        $this->assertSame([$entry1, $entry2], iterator_to_array($entries));
    }

    public function testHas()
    {
        $entries = new ImportMapEntries([ImportMapEntry::createLocal('entry1', ImportMapType::JS, 'path1', true)]);

        $this->assertTrue($entries->has('entry1'));
        $this->assertFalse($entries->has('entry2'));
    }

    public function testGet()
    {
        $entry = ImportMapEntry::createLocal('entry1', ImportMapType::JS, 'path1', false);
        $entries = new ImportMapEntries([$entry]);

        $this->assertSame($entry, $entries->get('entry1'));
    }

    public function testRemove()
    {
        $entries = new ImportMapEntries([ImportMapEntry::createLocal('entry1', ImportMapType::JS, 'path1', true)]);
        $entries->remove('entry1');

        $this->assertFalse($entries->has('entry1'));
    }
}
