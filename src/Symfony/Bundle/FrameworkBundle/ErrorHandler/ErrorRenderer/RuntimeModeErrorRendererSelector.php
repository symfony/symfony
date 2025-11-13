<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\ErrorHandler\ErrorRenderer;

use Symfony\Component\ErrorHandler\ErrorRenderer\ErrorRendererInterface;

/**
 * @internal
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
final class RuntimeModeErrorRendererSelector
{
    /**
     * @param \Closure(): ErrorRendererInterface $htmlErrorRenderer
     * @param \Closure(): ErrorRendererInterface $cliErrorRenderer
     */
    public static function select(bool $isWebMode, \Closure $htmlErrorRenderer, \Closure $cliErrorRenderer): ErrorRendererInterface
    {
        return ($isWebMode ? $htmlErrorRenderer : $cliErrorRenderer)();
    }
}
