<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Fixtures;

class UpdateCompositeObjectNoToStringIdEntity
{
    /**
     * @var SingleIntIdNoToStringEntity
     */
    protected $object1;

    /**
     * @var SingleIntIdNoToStringEntity
     */
    protected $object2;

    public $name;

    public function __construct(SingleIntIdNoToStringEntity $object1, SingleIntIdNoToStringEntity $object2, $name)
    {
        $this->object1 = $object1;
        $this->object2 = $object2;
        $this->name = $name;
    }

    public function getObject1(): SingleIntIdNoToStringEntity
    {
        return $this->object1;
    }

    public function getObject2(): SingleIntIdNoToStringEntity
    {
        return $this->object2;
    }
}
