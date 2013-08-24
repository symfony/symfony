<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Propel1\Form;

use Symfony\Component\Form\AbstractExtension;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Represents the Propel form extension, which loads the Propel functionality.
 *
 * @author Joseph Rouff <rouffj@gmail.com>
 *
 * @since v2.1.0
 */
class PropelExtension extends AbstractExtension
{
    /**
     * @since v2.1.0
     */
    protected function loadTypes()
    {
        return array(
            new Type\ModelType(PropertyAccess::getPropertyAccessor()),
            new Type\TranslationCollectionType(),
            new Type\TranslationType()
        );
    }

    /**
     * @since v2.1.0
     */
    protected function loadTypeGuesser()
    {
        return new PropelTypeGuesser();
    }
}
