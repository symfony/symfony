<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

/**
 * @author Dynèsh Hassanaly <artyum@protonmail.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class ControllerArgument extends Composite
{
    public $constraints = [];

    public function __construct(mixed $constraints = null, array $groups = null, mixed $payload = null)
    {
        parent::__construct($constraints ?? [], $groups, $payload);
    }

    public function getDefaultOption(): ?string
    {
        return 'constraints';
    }

    public function getRequiredOptions(): array
    {
        return ['constraints'];
    }

    /**
     * @inheritDoc
     */
    protected function getCompositeOption(): string
    {
        return 'constraints';
    }
}
