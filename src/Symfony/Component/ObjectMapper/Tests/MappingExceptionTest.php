<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Symfony\Component\ObjectMapper\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\Attribute\MapCollection;

class MapCollectionTest extends TestCase
{
    public function testMapCollectionAttribute()
    {
        $mapper = new ObjectMapper();

        $data = [
            ['id' => 1, 'name' => 'First'],
            ['id' => 2, 'name' => 'Second'],
        ];

        $target = new class {
            #[MapCollection(of: SimpleDto::class)]
            public array $items;
        };

        $mapper->map(['items' => $data], $target);

        $this->assertCount(2, $target->items);
        $this->assertInstanceOf(SimpleDto::class, $target->items[0]);
        $this->assertSame(1, $target->items[0]->id);
        $this->assertSame('Second', $target->items[1]->name);
    }
}

class SimpleDto
{
    public int $id;
    public string $name;
}
