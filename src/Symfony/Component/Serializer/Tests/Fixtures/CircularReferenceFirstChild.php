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
class CircularReferenceFirstChild
{
	/**
	 * @var int
	 */
	private $id;

	/**
     * @var CircularReferenceParent
     */
	private $parent;

    /**
     * @var CircularReferenceGrandChild
     */
    private $child;


	public function getId()
	{
		return $this->id;
	}

	public function setId($id)
	{
		$this->id = $id;
	}

    public function getParent()
    {
        return $this->parent;
    }

    public function setParent($parent)
    {
        $this->parent = $parent;
    }

    public function getChild()
    {
        return $this->child;
    }

    public function setChild($child)
    {
        $this->child = $child;
    }

}
