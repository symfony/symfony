<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Workflow\Validator;

use Symphony\Component\Workflow\Definition;
use Symphony\Component\Workflow\Exception\InvalidDefinitionException;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
interface DefinitionValidatorInterface
{
    /**
     * @param Definition $definition
     * @param string     $name
     *
     * @throws InvalidDefinitionException on invalid definition
     */
    public function validate(Definition $definition, $name);
}
