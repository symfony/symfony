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
 * Declares a form type derived from the class this attribute is applied to.
 *
 * The class name can then be used wherever a form type name is expected;
 * its properties marked with {@see FormField} become the fields of the form.
 *
 * @author Benjamin Georgeault <git@wedgesama.fr>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AsFormType
{
    /**
     * @param array<string, mixed> $options The default options of the derived form type
     */
    public function __construct(
        public array $options = [],
    ) {
    }
}
