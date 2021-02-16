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
class BadMethodContextDummy extends ContextDummyParent
{
    /**
     * @Context({ "foo" = "bar" })
     */
    public function badMethod()
    {
        return 'bad_method';
    }
}
