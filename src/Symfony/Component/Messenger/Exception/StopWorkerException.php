<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Exception;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class StopWorkerException extends RuntimeException implements StopWorkerExceptionInterface
{
    public function __construct(string $message = 'Worker should stop.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
