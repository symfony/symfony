<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures\Annotations;

use Symfony\Component\Serializer\Annotation\Context;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class ContextDummyPromotedProperties extends ContextDummyParent
{
    public function __construct(
        /**
         * @Context({ "foo" = "value", "bar" = "value", "nested" = {
         *   "nested_key" = "nested_value",
         * }, "array": { "first", "second" } })
         * @Context({ "bar" = "value_for_group_a" }, groups = "a")
         */
        public $foo,

        /**
         * @Context(
         *     normalizationContext = { "format" = "d/m/Y" },
         *     denormalizationContext = { "format" = "m-d-Y H:i" },
         *     groups = {"a", "b"}
         * )
         */
        public $bar,

        /**
         * @Context(normalizationContext={ "prop" = "dummy_value" })
         */
        public $overriddenParentProperty,
    ) {
    }

    /**
     * @Context({ "method" = "method_with_context" })
     */
    public function getMethodWithContext()
    {
        return 'method_with_context';
    }
}
