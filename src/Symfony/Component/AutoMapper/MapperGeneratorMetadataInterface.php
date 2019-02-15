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
 * Stores metadata needed when generating a mapper.
 *
 * @internal
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
interface MapperGeneratorMetadataInterface extends MapperMetadataInterface
{
    /**
     * Get mapper class name.
     */
    public function getMapperClassName(): string;

    /**
     * Get hash (unique key) for those metadatas.
     */
    public function getHash(): string;

    /**
     * Get a list of callbacks to add for this mapper.
     *
     * @return callable[]
     */
    public function getCallbacks(): array;

    /**
     * Whether the target class has a constructor.
     */
    public function hasConstructor(): bool;

    /**
     * Whether we can use target constructor.
     */
    public function isConstructorAllowed(): bool;

    /**
     * Whether we should generate attributes checking.
     */
    public function shouldCheckAttributes(): bool;

    /**
     * If not using target constructor, allow to know if we can clone a empty target.
     */
    public function isTargetCloneable(): bool;

    /**
     * Whether the mapping can have circular reference.
     *
     * If not the case, allow to not generate code about circular references
     */
    public function canHaveCircularReference(): bool;
}
