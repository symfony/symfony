<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\RoutingConditionServiceBundle\Service;

class ManuallyTaggedService
{
    public function giveMeTrue(): bool
    {
        return true;
    }

    public function giveMeFalse(): bool
    {
        return false;
    }
}
