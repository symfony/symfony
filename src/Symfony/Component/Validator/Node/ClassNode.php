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

use Symfony\Component\Validator\Mapping\ClassMetadataInterface;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ClassNode extends Node
{
    /**
     * @var ClassMetadataInterface
     */
    public $metadata;

    public function __construct($value, ClassMetadataInterface $metadata, $propertyPath, array $groups)
    {
        if (!is_object($value)) {
            // error
        }

        parent::__construct(
            $value,
            $metadata,
            $propertyPath,
            $groups
        );
    }

}
