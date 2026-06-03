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
 * @author Jérôme Desjardin <jewome62@gmail.com>
 */
class DeepObjectPopulateParentDummy
{
    /**
     * @var DeepObjectPopulateChildDummy|null
     */
    private $child;

    public function setChild(?DeepObjectPopulateChildDummy $child)
    {
        $this->child = $child;
    }

    public function getChild(): ?DeepObjectPopulateChildDummy
    {
        return $this->child;
    }
}
