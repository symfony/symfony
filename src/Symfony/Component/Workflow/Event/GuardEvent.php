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
 */
class GuardEvent extends Event
{
    private $allowed = null;

    public function isAllowed()
    {
        return $this->allowed;
    }

    public function setAllowed($allowed)
    {
        $this->allowed = (Boolean) $allowed;
    }
}
