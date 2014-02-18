<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Node;

use Symfony\Component\Validator\Mapping\PropertyMetadataInterface;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PropertyNode extends Node
{
    /**
     * @var PropertyMetadataInterface
     */
    public $metadata;

    public function __construct($value, PropertyMetadataInterface $metadata, $propertyPath, array $groups, array $cascadedGroups)
    {
        parent::__construct(
            $value,
            $metadata,
            $propertyPath,
            $groups,
            $cascadedGroups
        );
    }

}
