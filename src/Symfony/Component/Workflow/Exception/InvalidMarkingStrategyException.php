<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Exception;

/**
 * An InvalidMarkingStrategyException is thrown when the marking strategy
 * does not match the Marking instance.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
class InvalidMarkingStrategyException extends LogicException
{
    /**
     * @param string $markingStoreClass
     */
    public function __construct($markingStoreClass)
    {
        $this->message = sprintf('The marking store has no strategy set. Did you forgot to call "%::__construct()"?', $markingStoreClass);
    }
}
