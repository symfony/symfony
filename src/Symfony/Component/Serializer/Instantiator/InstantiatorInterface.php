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
 * @author Jérôme Desjardins <jewome62@gmail.com>
 */
interface InstantiatorInterface
{
    /**
     * Instantiate a new object.
     *
     * @throws MissingConstructorArgumentsException When some arguments are missing to use the constructor
     *
     * @return mixed
     */
    public function instantiate(string $class, $data, $format = null, array $context = []);
}
