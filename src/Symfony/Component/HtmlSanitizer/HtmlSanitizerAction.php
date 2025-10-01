<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlSanitizer;

enum HtmlSanitizerAction: string
{
    /**
     * Dropped elements are elements the sanitizer should remove from the input, including their children.
     */
    case Drop = 'drop';

    /**
     * Blocked elements are elements the sanitizer should remove from the input, but retain their children.
     */
    case Block = 'block';

    /**
     * Allowed elements are elements the sanitizer should retain from the input.
     */
    case Allow = 'allow';
}
