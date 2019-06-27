<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorCatcher\ErrorRenderer;

use Symfony\Component\ErrorCatcher\Exception\FlattenException;

/**
 * Interface implemented by all error renderers.
 *
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
interface ErrorRendererInterface
{
    /**
     * Gets the format of the content.
     *
     * @return string The content format
     */
    public static function getFormat(): string;

    /**
     * Renders an Exception and returns the Response content.
     *
     * @return string The Response content as a string
     */
    public function render(FlattenException $exception): string;
}
