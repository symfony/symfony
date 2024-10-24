<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

use Symfony\Component\Serializer\Attribute\Groups;

class GroupPropertyDummy
{
    public bool $noGroup = true;

    #[Groups('custom')]
    public bool $customGroup = true;

    #[Groups('Default')]
    public bool $defaultGroup = true;

    #[Groups('GroupPropertyDummy')]
    public bool $classGroup = true;
}
