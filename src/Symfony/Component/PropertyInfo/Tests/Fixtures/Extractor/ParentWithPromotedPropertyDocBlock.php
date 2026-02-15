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

class ParentWithPromotedPropertyDocBlock
{
    /**
     * @param array<string, int> $items
     */
    public function __construct(
        public $items = [],
    ) {
    }
}

class ChildWithoutConstructorOverride extends ParentWithPromotedPropertyDocBlock
{
}

class ChildWithConstructorOverride extends ParentWithPromotedPropertyDocBlock
{
    public function __construct(
        public $extraProp = null,
    ) {
        parent::__construct();
    }
}
