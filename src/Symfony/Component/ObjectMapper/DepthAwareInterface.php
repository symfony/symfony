<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper;

/**
 * Allows a callable or mapper to be aware of the current mapping depth.
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
interface DepthAwareInterface
{
    public function setDepth(int $depth): void;
}
