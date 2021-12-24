<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures\Attributes;

use Symfony\Component\Serializer\Annotation\Groups;

class BadAttributeDummy extends ContextDummyParent
{
    #[Groups(['bar'])]
    #[Groups(['foo'])]
    public function myMethod()
    {
    }
}
