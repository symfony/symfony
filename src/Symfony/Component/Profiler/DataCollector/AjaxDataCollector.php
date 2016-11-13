<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\DataCollector;

use Symfony\Component\Profiler\Context\ContextInterface;
use Symfony\Component\Profiler\Context\RequestContext;
use Symfony\Component\Profiler\Profile;

/**
 * AjaxDataCollector.
 *
 * @author Bart van den Burg <bart@burgov.nl>
 */
class AjaxDataCollector extends DataCollector
{
    public function collectData(ContextInterface $context, Profile $profile)
    {
        return $context instanceof RequestContext;
    }

    public function getName()
    {
        return 'ajax';
    }
}
