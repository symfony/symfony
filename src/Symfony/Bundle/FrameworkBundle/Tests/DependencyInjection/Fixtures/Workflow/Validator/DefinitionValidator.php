<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Fixtures\Workflow\Validator;

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

