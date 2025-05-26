<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test;

trigger_deprecation('symfony/framework-bundle', '7.4', 'Use Traits\NotifierTrait and Traits\NotifierAssertionsTrait instead.');
trait NotificationAssertionsTrait
{
    use Traits\NotifierTrait;
    use Traits\NotifierAssertionsTrait;
}
