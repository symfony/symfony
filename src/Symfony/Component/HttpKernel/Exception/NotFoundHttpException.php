<?php

namespace Symfony\Component\HttpKernel\Exception;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * NotFoundHttpException.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class NotFoundHttpException extends \RuntimeException
{
    public function __construct($message = 'Not Found', \Exception $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
