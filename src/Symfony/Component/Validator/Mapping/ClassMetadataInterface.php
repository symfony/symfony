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
use Symfony\Component\Validator\PropertyMetadataContainerInterface as LegacyPropertyMetadataContainerInterface;;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ClassMetadataInterface extends MetadataInterface, LegacyPropertyMetadataContainerInterface, ClassBasedInterface
{
    public function getConstrainedProperties();

    public function hasGroupSequence();

    public function getGroupSequence();

    public function isGroupSequenceProvider();
}
