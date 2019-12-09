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
class CircularReferenceGrandChild
{
	/**
	 * @var int
	 */
	private $id;

	/**
     * @var CircularReferenceParent
     */
	private $grandParent;

	/**
     * @var CircularReferenceFirstChild
     */
	private $parent;


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

    public function getGrandParent()
    {
        return $this->grandParent;
    }

    public function setGrandParent($grandParent)
    {
        $this->grandParent = $grandParent;
    }
}
