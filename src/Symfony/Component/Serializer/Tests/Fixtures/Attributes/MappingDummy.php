<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures\Attributes;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Serializer\Annotation\Mapping;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[Mapping(attributes: ['foo', 'bar', ['name' => 'baz', 'groups' => 'groupAttribute'], ['name'=> 'qux', 'groups' => 'groupAttribute'], 'quux', ['name' => 'quuz', 'groups' => 'groupAttribute']], groups: 'groupAttributes')]
#[Mapping(attributes: ['foo', 'bar', ['name' => 'baz', 'maxDepth' => 3], ['name'=> 'qux', 'maxDepth' => 99], 'quux', ['name' => 'quuz', 'maxDepth' => 99]], maxDepth: 1)]
#[Mapping(attributes: [['name' => 'bar', 'serializedName' => 'barError'], ['name' => 'baz', 'serializedName' => 'bazOk'], ['name' => 'quux', 'serializedName' => 'quuxOk'], ['name' => 'quuz', 'serializedName' => 'quuzError']])]
class MappingDummy
{
    public $foo;

    #[SerializedName(['barOk'])]
    #[Groups('groupProperty')]
    #[MaxDepth(2)]
    public $bar;

    public $baz;

    #[Groups('groupProperty')]
    #[MaxDepth(4)]
    public $qux;

    public $quux;
    public $quuz;

    #[Groups('groupMethod')]
    #[MaxDepth(5)]
    public function getQuux()
    {
        return $this->quux;
    }

    #[SerializedName(['quuzOk'])]
    #[Groups('groupMethod')]
    #[MaxDepth(6)]
    public function getQuuz()
    {
        return $this->quuz;
    }
}
