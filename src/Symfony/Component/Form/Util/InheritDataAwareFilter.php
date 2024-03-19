<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @author Cristoforo Cervino <cristoforo.cervino@me.com>
 */
namespace Symfony\Component\Form\Util;

class InheritDataAwareFilter extends \FilterIterator
{
    public function accept(): bool
    {
        return (bool) $this->current()->getConfig()->getInheritData();
    }
}
