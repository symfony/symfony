<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Event;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class GuardEvent extends Event
{
    private $blocked = false;

    public function isBlocked()
    {
        return $this->blocked;
    }

    public function setBlocked($blocked)
    {
        $this->blocked = (bool) $blocked;
    }
}
