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

/**
 * @author Jérôme Desjardins <jewome62@gmail.com>
 */
interface InstantiatorInterface
{
    public function instantiate(string $class, $data, $format = null, array $context = []);

    public function createChildContext(string $class, $data, array $context = [], $attribute);

}