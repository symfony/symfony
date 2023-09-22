<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Exception;

/**
 * @author Yuriy Vilks <igrizzli@gmail.com>
 */
class MultipleExclusiveOptionsUsedException extends InvalidArgumentException
{
    /**
     * @param string[]      $usedExclusiveOptions
     * @param string[]|null $exclusiveOptions
     */
    public function __construct(array $usedExclusiveOptions, array $exclusiveOptions = null, \Throwable $previous = null)
    {
        $message = sprintf('Multiple exclusive options have been used "%s".', implode('", "', $usedExclusiveOptions));
        if (null !== $exclusiveOptions) {
            $message .= sprintf(' Only one of %s can be used.', implode('", "', $exclusiveOptions));
        }

        parent::__construct($message, 0, $previous);
    }
}
