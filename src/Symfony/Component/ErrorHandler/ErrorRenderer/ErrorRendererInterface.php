<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\ErrorRenderer;

use Symfony\Component\ErrorHandler\Exception\FlattenException;

/**
 * Formats an exception to be used as response content.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 *
 * @method FlattenException flatten(\Throwable $exception) not implementing it is deprecated since Symfony 5.1
 */
interface ErrorRendererInterface
{
    /**
     * @deprecated since Symfony 5.1, use flatten() instead.
     *
     * Renders a Throwable as a FlattenException.
     */
    public function render(\Throwable $exception): FlattenException;
}
