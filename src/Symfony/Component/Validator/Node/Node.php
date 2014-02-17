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

use Symfony\Component\Validator\Mapping\MetadataInterface;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class Node
{
    public $value;

    public $metadata;

    public $propertyPath;

    public $groups;

    public function __construct($value, MetadataInterface $metadata, $propertyPath, array $groups)
    {
        $this->value = $value;
        $this->metadata = $metadata;
        $this->propertyPath = $propertyPath;
        $this->groups = $groups;
    }
}
