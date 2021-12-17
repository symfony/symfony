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
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Tomas Norkūnas <norkunas.tom@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class None extends Composite
{
    public array $constraints = [];
    public string $message = 'None of values should satisfy one of the following constraints:';
    public bool $includeInternalMessages = true;

    public function __construct(mixed $constraints = null, array $groups = null, mixed $payload = null, string $message = null, bool $includeInternalMessages = null)
    {
        parent::__construct($constraints ?? [], $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->includeInternalMessages = $includeInternalMessages ?? $this->includeInternalMessages;
    }

    public function getDefaultOption(): ?string
    {
        return 'constraints';
    }

    public function getRequiredOptions(): array
    {
        return ['constraints'];
    }

    protected function getCompositeOption(): string
    {
        return 'constraints';
    }
}
