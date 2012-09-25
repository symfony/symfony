<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Propel1\Tests\Fixtures;

class ReadOnlyItem extends \BaseObject
{
    public function getName()
    {
        return 'Marvin';
    }

    public function getPrimaryKey()
    {
        return 42;
    }
}
