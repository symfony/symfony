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

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * Checks that at least one of the given constraint is satisfied.
 *
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

    /**
     * @param array<Constraint>|null $constraints             An array of validation constraints
     * @param string[]|null          $groups
     * @param string|null            $message                 Intro of the failure message that will be followed by the failed constraint(s) message(s)
     * @param string|null            $messageCollection       Failure message for All and Collection inner constraints
     * @param bool|null              $includeInternalMessages Whether to include inner constraint messages (defaults to true)
     */
    #[HasNamedArguments]
    public function __construct(mixed $constraints = null, ?array $groups = null, mixed $payload = null, ?string $message = null, ?string $messageCollection = null, ?bool $includeInternalMessages = null)
    {
        if (null === $constraints || [] === $constraints) {
            throw new MissingOptionsException(\sprintf('The options "constraints" must be set for constraint "%s".', self::class), ['constraints']);
        }

        if (!$constraints instanceof Constraint && !\is_array($constraints) || \is_array($constraints) && !array_is_list($constraints)) {
            trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);
            $options = $constraints;
        } else {
            $this->constraints = $constraints;
        }

        parent::__construct($options ?? null, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->messageCollection = $messageCollection ?? $this->messageCollection;
        $this->includeInternalMessages = $includeInternalMessages ?? $this->includeInternalMessages;
    }

    /**
     * @deprecated since Symfony 7.4
     */
    public function getDefaultOption(): ?string
    {
        if (0 === \func_num_args() || func_get_arg(0)) {
            trigger_deprecation('symfony/validator', '7.4', 'The %s() method is deprecated.', __METHOD__);
        }

        return 'constraints';
    }

    /**
     * @deprecated since Symfony 7.4
     */
    public function getRequiredOptions(): array
    {
        if (0 === \func_num_args() || func_get_arg(0)) {
            trigger_deprecation('symfony/validator', '7.4', 'The %s() method is deprecated.', __METHOD__);
        }

        return ['constraints'];
    }

    protected function getCompositeOption(): string
    {
        return 'constraints';
    }
}
