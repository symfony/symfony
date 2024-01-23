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

use Symfony\Component\Validator\Constraint;

/**
 * @author Przemysław Bogusz <przemyslaw.bogusz@tubotax.pl>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class AtLeastOneOf extends Composite
{
    public const AT_LEAST_ONE_OF_ERROR = 'f27e6d6c-261a-4056-b391-6673a623531c';

    protected const ERROR_NAMES = [
        self::AT_LEAST_ONE_OF_ERROR => 'AT_LEAST_ONE_OF_ERROR',
    ];

    public array|Constraint $constraints = [];
    public string $message = 'This value should satisfy at least one of the following constraints:';
    public string $messageCollection = 'Each element of this collection should satisfy its own set of constraints.';
    public bool $includeInternalMessages = true;

    public function __construct(mixed $constraints = null, ?array $groups = null, mixed $payload = null, ?string $message = null, ?string $messageCollection = null, ?bool $includeInternalMessages = null)
    {
        parent::__construct($constraints ?? [], $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->messageCollection = $messageCollection ?? $this->messageCollection;
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
