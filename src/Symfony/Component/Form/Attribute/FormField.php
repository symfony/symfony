<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Attribute;

/**
 * Declares a field of the form type derived from a class marked with {@see AsFormType}.
 *
 * @author Benjamin Georgeault <git@wedgesama.fr>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class FormField
{
    /**
     * @param class-string|null    $type    The form type of the field, guessed when null
     * @param array<string, mixed> $options The options of the field
     * @param string|null          $name    The name of the field, defaults to the property name
     */
    public function __construct(
        public ?string $type = null,
        public array $options = [],
        public ?string $name = null,
    ) {
    }
}
