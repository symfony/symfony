<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Templating\Loader;

use Psr\Log\LoggerInterface;

/**
 * Loader is the base class for all template loader classes.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
abstract class Loader implements LoaderInterface
{
    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * Sets the debug logger to use for this loader.
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
