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
    private $extraAttributes;

    public function __construct(string $class, array $extraAttributes, \Throwable $previous = null)
    {
        $msg = sprintf('Cannot create an instance of "%s" from serialized data because extra attributes are not allowed ("%s" are unknown).', $class, implode('", "', $extraAttributes));

        $this->extraAttributes = $extraAttributes;

        parent::__construct($msg, 0, $previous);
    }

    /**
     * Get the extra attributes that are not allowed.
     *
     * @return array
     */
    public function getExtraAttributes()
    {
        return $this->extraAttributes;
    }
}
