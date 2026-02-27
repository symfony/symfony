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
     * Returns a clone of the original instance, configured with the given object mapper.
     */
    public function withObjectMapper(ObjectMapperInterface $objectMapper): static;
}
