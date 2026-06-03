<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Exception;

class ClassNotFoundException extends \Exception implements ExceptionInterface
{
    public function __construct(string|\Throwable $class, ?\Throwable $previous = null)
    {
        if ($class instanceof \Throwable) {
            parent::__construct($class->getMessage(), 0, $class);
        } else {
            parent::__construct(\sprintf('Class "%s" not found.', $class), 0, $previous);
        }
    }
}
