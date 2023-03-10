<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\EventListener;

trigger_deprecation('symfony/messenger', '6.3', '"%s" is deprecated, use "%s" instead.', StopWorkerOnSigtermSignalListener::class, StopWorkerOnSignalsListener::class);

use Psr\Log\LoggerInterface;

/**
 * @author Tobias Schultze <http://tobion.de>
 *
 * @deprecated since Symfony 6.3, use the StopWorkerOnSignalsListener instead
 */
class StopWorkerOnSigtermSignalListener extends StopWorkerOnSignalsListener
{
    public function __construct(LoggerInterface $logger = null)
    {
        parent::__construct([SIGTERM], $logger);
    }
}
