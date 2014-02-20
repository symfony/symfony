<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping;

use Symfony\Component\Validator\ClassBasedInterface;
use Symfony\Component\Validator\PropertyMetadataInterface as LegacyPropertyMetadataInterface;

/**
 * A container for validation metadata of a property.
 *
 * What exactly you define as "property" is up to you. The validator expects
 * implementations of {@link MetadataInterface} that contain constraints and
 * optionally a list of named properties that also have constraints (and may
 * have further sub properties). Such properties are mapped by implementations
 * of this interface.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see MetadataInterface
 */
interface PropertyMetadataInterface extends MetadataInterface, LegacyPropertyMetadataInterface, ClassBasedInterface
{
}
