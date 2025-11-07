<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Attribute;

/**
 * Service tag to autoconfigure voters with priority support.
 *
 * @author Ayyoub AFW-ALLAH <ayyoub.afwallah@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AsVoter
{
    public function __construct(public int $priority = 0)
    {
    }
}
