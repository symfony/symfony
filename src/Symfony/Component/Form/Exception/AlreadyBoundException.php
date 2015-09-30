<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Exception;

/**
 * Alias of {@link AlreadySubmittedException}.
 *
 * @deprecated since version 2.3, to be removed in 3.0.
 *             Use {@link AlreadySubmittedException} instead.
 */
class AlreadyBoundException extends LogicException
{
    public function __construct($message = '', $code = 0, \Exception $previous = null)
    {
        if (__CLASS__ === get_class($this)) {
            @trigger_error('The '.__CLASS__.' class is deprecated since version 2.3 and will be removed in 3.0. Use the Symfony\Component\Form\Exception\AlreadySubmittedException class instead.', E_USER_DEPRECATED);
        }

        parent::__construct($message, $code, $previous);
    }
}
