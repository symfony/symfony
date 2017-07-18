<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Exception;

/**
 * ExtraAttributesException.
 *
 * @author Julien DIDIER <julien@didier.io>
 */
class ExtraAttributesException extends RuntimeException
{
    public function __construct(array $extraAttributes, \Exception $previous = null)
    {
        $msg = sprintf('Extra attributes are not allowed ("%s" are unknown).', implode('", "', $extraAttributes));

        parent::__construct($msg, 0, $previous);
    }
}
