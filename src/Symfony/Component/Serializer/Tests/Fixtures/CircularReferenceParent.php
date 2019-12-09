<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

/**
 * @author Thomas ANDRE <thomandre@gmail.com>
 */
class CircularReferenceParent
{

    /**
     * @var int
     */
    private $id;

    /**
     * @var CircularReferenceFirstChild
     */
    private $child1;

    /**
     * @var CircularReferenceSecondChild
     */
    private $child2;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getChild1()
    {
        return $this->child1;
    }

    public function setChild1($child1)
    {
        $this->child1 = $child1;
    }

    public function getChild2()
    {
        return $this->child2;
    }

    public function setChild2($child2)
    {
        $this->child2 = $child2;
    }

}
