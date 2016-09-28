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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Profiler\DataInterface;
use Symfony\Component\Profiler\Profile;
use Symfony\Component\Profiler\RequestData;

/**
 * AjaxDataCollector.
 *
 * @author Bart van den Burg <bart@burgov.nl>
 */
class AjaxDataCollector extends DataCollector
{
    public function collectData(DataInterface $data, Profile $profile)
    {
        return $data instanceof RequestData;
    }

    public function getName()
    {
        return 'ajax';
    }
}
