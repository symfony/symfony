<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
namespace Symfony\Component\Validator;

use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Makes it possible to define dynamic constraints for an object.
 */
interface ConstraintProviderInterface
{
    /**
     * Lets the user create dynamic constraints
     *
     * @param Mapping\ClassMetadata
     */
    function loadDynamicValidatorMetadata(ClassMetadata $metadata);
}
