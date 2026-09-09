<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\Fixtures;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Validator\DefinitionValidatorInterface;

class DefinitionValidator implements DefinitionValidatorInterface
{
    public static bool $called = false;

    public function validate(Definition $definition, string $name): void
    {
        self::$called = true;
    }
}
