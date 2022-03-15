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

use Psr\Log\LoggerInterface;

/**
 * @author Tobias Schultze <http://tobion.de>
 * @deprecated Use StopWorkerOnSignalListener instead.
 */
class StopWorkerOnSigtermSignalListener extends StopWorkerOnSignalListener
{
    public function __construct(LoggerInterface $logger = null)
    {
        parent::__construct(\SIGTERM, $logger);
    }
}
