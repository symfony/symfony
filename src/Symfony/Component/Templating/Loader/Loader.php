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

@trigger_error('The '.Loader::class.' class is deprecated since version 3.3 and will be removed in 4.0. Use Twig instead.', E_USER_DEPRECATED);

use Psr\Log\LoggerInterface;

/**
 * Loader is the base class for all template loader classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated The Loader class will be removed in Symfony 4.0. You should use Twig instead.
 */
abstract class Loader implements LoaderInterface
{
    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * Sets the debug logger to use for this loader.
     *
     * @param LoggerInterface $logger A logger instance
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
