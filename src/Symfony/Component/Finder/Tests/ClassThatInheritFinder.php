<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Tests;

use Symfony\Component\Finder\Finder;

class ClassThatInheritFinder extends Finder
{
    /**
     * @return $this
     */
    public function sortByName()
    {
        parent::sortByName();
    }
}
