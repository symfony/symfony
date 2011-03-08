<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Exception;

/**
 * NotFoundHttpException.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class NotFoundHttpException extends \RuntimeException
{
    public function __construct($message = 'Not Found', \Exception $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
