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

/**
 * Define the template to render in the controller.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
class Template
{
    /**
     * @param string        $template The name of the template to render
     * @param string[]|null $vars     The controller method arguments to pass to the template
     * @param bool          $stream   Enables streaming the template
     */
    public function __construct(
        public string $template,
        public ?array $vars = null,
        public bool $stream = false,
    ) {
    }
}
