<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\Exception;

if (!class_exists(FlattenException::class, false)) {
    class_alias(\Symfony\Component\ErrorRenderer\Exception\FlattenException::class, FlattenException::class);
}

if (false) {
    /**
     * @deprecated since Symfony 4.4, use Symfony\Component\ErrorRenderer\Exception\FlattenException instead.
     */
    class FlattenException extends \Symfony\Component\ErrorRenderer\Exception\FlattenException
    {
    }
}
