<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Event;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Allows to execute logic after a response was sent
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class PostResponseEvent extends Event
{
    /**
     * The kernel in which this event was thrown
     * @var HttpKernelInterface
     */
    private $kernel;

    public function __construct(HttpKernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * Returns the kernel in which this event was thrown
     *
     * @return HttpKernelInterface
     */
    public function getKernel()
    {
        return $this->kernel;
    }
}
