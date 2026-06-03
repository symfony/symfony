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

class NotInstantiableTypeException extends \Exception implements ExceptionInterface
{
    public function __construct(string|\Throwable $type, ?\Throwable $previous = null)
    {
        if ($type instanceof \Throwable) {
            parent::__construct($type->getMessage(), 0, $type);
        } else {
            parent::__construct(\sprintf('Type "%s" is not instantiable.', $type), 0, $previous);
        }
    }
}
