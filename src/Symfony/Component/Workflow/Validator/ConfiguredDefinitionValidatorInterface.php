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

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
interface ConfiguredDefinitionValidatorInterface extends DefinitionValidatorInterface
{
    /**
     * @return list<string> A list of workflow name, or "*" for all workflows
     */
    public static function getSupportedWorkflows(): iterable;
}
