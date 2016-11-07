<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Validator;

use Symfony\Component\Workflow\Definition;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class WorkflowValidator implements DefinitionValidatorInterface
{
    public function validate(Definition $definition, $name)
    {
    }
}
