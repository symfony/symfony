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
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
interface ObjectMapperAwareInterface
{
    /**
     * Sets the owning ObjectMapper object.
     */
    public function withObjectMapper(ObjectMapperInterface $objectMapper): static;
}
