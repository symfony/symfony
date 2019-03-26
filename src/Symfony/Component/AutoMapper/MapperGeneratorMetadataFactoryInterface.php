<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper;

/**
 * Metadata factory, used to autoregistering new mapping without creating them.
 *
 * @expiremental in 4.3
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
interface MapperGeneratorMetadataFactoryInterface
{
    public function create(MapperGeneratorMetadataRegistryInterface $autoMapperRegister, string $source, string $target): MapperGeneratorMetadataInterface;
}
