<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor;

class PromotedPropertiesWithDocBlock
{
    /**
     * @param string $foo Just a foo property
     * @param string $qux A qux property
     */
    public function __construct(
        public string $foo,
        public int $bar,
        /** @var string $baz A baz property */
        public string $baz,
        /** @var int $qux An overridden qux property */
        public string $qux = '',
        /**
         * A corge property.
         *
         * A detailed explanation of corge.
         */
        public string $corge = '',
    ) {
    }
}
