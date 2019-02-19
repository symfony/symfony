<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Tests\Fixtures;

use Symfony\Component\Serializer\Annotation\MaxDepth;

class FooMaxDepth
{
   /**
    * @var int
    */
    private $id;

    /**
     * @var FooMaxDepth|null
     *
     * @MaxDepth(2)
     */
    private $child;

    public function __construct(int $id, ?self $child = null)
    {
        $this->id = $id;
        $this->child = $child;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getChild(): ?self
    {
        return $this->child;
    }
}
