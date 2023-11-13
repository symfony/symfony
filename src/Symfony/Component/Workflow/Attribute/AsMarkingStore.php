<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Attribute;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsMarkingStore
{
    public function __construct(
        /**
         * The name of the property where the marking will be stored in the subject.
         */
        public string $markingName,
        /**
         * The name of the property where the marking name will be stored.
         */
        public string $property = 'property',
    ) {
    }
}
