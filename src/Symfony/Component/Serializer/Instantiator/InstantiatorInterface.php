<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Instantiator;

use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;

/**
 * Describes the interface to instantiate an object using constructor parameters when needed.
 *
 * @author Jérôme Desjardins <jewome62@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
interface InstantiatorInterface
{
    /**
     * Instantiates a new object.
     *
     * @throws MissingConstructorArgumentsException When some arguments are missing to use the constructor
     */
    public function instantiate(string $class, array $data, array $context, string $format = null): InstantiatorResult;
}
