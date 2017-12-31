<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Loader;

use Psr\Log\LoggerInterface;
use Symfony\Component\Templating\DebuggerInterface;

/**
 * Loader is the base class for all template loader classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Loader implements LoaderInterface
{
    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * @deprecated since version 2.4, to be removed in 3.0. Use $this->logger instead.
     */
    protected $debugger;

    /**
     * Sets the debug logger to use for this loader.
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Sets the debugger to use for this loader.
     *
     * @param DebuggerInterface $debugger A debugger instance
     *
     * @deprecated since version 2.4, to be removed in 3.0. Use $this->setLogger() instead.
     */
    public function setDebugger(DebuggerInterface $debugger)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since Symfony 2.4 and will be removed in 3.0. Use the setLogger() method instead.', E_USER_DEPRECATED);

        $this->debugger = $debugger;
    }
}
