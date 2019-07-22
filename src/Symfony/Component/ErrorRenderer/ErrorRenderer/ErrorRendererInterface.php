<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorRenderer\ErrorRenderer;

use Symfony\Component\ErrorRenderer\Exception\FlattenException;

/**
 * Interface for classes that can render errors in a specific format.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
interface ErrorRendererInterface
{
    /**
     * Gets the format this renderer can return errors as.
     */
    public static function getFormat(): string;

    /**
     * Returns the response content of the rendered exception.
     */
    public function render(FlattenException $exception): string;
}
