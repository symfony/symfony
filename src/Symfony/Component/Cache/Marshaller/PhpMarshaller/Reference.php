<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Marshaller\PhpMarshaller;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class Reference
{
    public function __construct(int $id)
    {
        $this->{0} = $id;
    }

    public static function __set_state($state)
    {
        return Registry::$objects[$state[0]];
    }
}
