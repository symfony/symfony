<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
class Template
{
    public function __construct(
        /**
         * The name of the template to render.
         */
        public string $template,

        /**
         * The controller method arguments to pass to the template.
         */
        public ?array $vars = null,

        /**
         * Enables streaming the template.
         */
        public bool $stream = false,
    ) {
    }
}
