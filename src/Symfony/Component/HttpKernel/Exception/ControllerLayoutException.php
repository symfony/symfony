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

use Symfony\Component\HttpKernel\Controller\ActionReference;

/**
 * @author Pavel Batanov <pavel@batanov.me>
 */
class ControllerLayoutException extends \InvalidArgumentException
{
    public static function unknownControllerClass(ActionReference $reference, string $try): self
    {
        throw new static(
            sprintf(
                'The _controller value "%s:%s:%s" maps to a "%s" class, but this class was not found. Create this class or check the spelling of the class and its namespace.',
                $reference->bundle->getName(),
                $reference->controller,
                $reference->action,
                $try
            )
        );
    }

    public static function unknownBundleForController(string $controller): self
    {
        return new static(sprintf('Unable to find a bundle that defines controller "%s".', $controller));
    }
}
