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
use Symfony\Component\ObjectMapper\Attribute\MapTree;

class MapTreeTest extends TestCase
{
    public function testMapTreeAttribute()
    {
        $mapper = new ObjectMapper();

        $data = [
            [
                'id' => 1,
                'name' => 'Root',
                'children' => [
                    ['id' => 2, 'name' => 'Child 1', 'children' => []],
                    ['id' => 3, 'name' => 'Child 2', 'children' => []],
                ],
            ],
        ];

        // Fonction de transformation récursive pour convertir chaque tableau en TreeDto
        $arrayToTreeDto = function ($item) use (&$arrayToTreeDto) {
            $dto = new TreeDto();
            $dto->id = $item['id'];
            $dto->name = $item['name'];
            $dto->children = array_map($arrayToTreeDto, $item['children'] ?? []);
            return $dto;
        };

        // Création d'une classe source dédiée
        $source = new class($data, $arrayToTreeDto) {
            #[MapTree(of: TreeDto::class, transform: ["arrayToTreeDto"])]
            public array $nodes;
            public function __construct(array $nodes, $arrayToTreeDto) {
                // On transforme chaque élément du tableau en objet
                $this->nodes = array_map($arrayToTreeDto, $nodes);
            }
        };

        $target = new class {
            #[MapTree(of: TreeDto::class)]
            public array $nodes;
        };

        $mapper->map($source, $target);

        $this->assertCount(1, $target->nodes);
        $this->assertInstanceOf(TreeDto::class, $target->nodes[0]);
        $this->assertSame('Root', $target->nodes[0]->name);

        $this->assertCount(2, $target->nodes[0]->children);
        $this->assertSame('Child 1', $target->nodes[0]->children[0]->name);
    }
}

class TreeDto
{
    public int $id;
    public string $name;

    /**
     * @var TreeDto[]
     */
    public array $children = [];
}
