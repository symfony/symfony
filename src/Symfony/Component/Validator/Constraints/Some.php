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

use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Tomas Norkūnas <norkunas.tom@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Some extends Composite
{
    public const SOME_TOO_FEW_ERROR = 'a7ea059b-f8e6-4e85-a48a-bc5eddc0103b';
    public const SOME_TOO_MANY_ERROR = '63d385ab-9101-4195-bc32-7283e13a5283';
    public const SOME_EXACTLY_ERROR = '6466f661-8b8e-495d-ac96-408aa2e7ee33';

    protected static $errorNames = [
        self::SOME_TOO_FEW_ERROR => 'SOME_TOO_FEW_ERROR',
        self::SOME_TOO_MANY_ERROR => 'SOME_TOO_MANY_ERROR',
        self::SOME_EXACTLY_ERROR => 'SOME_EXACTLY_ERROR',
    ];

    public array $constraints = [];
    public int $min = 1;
    public ?int $max = null;
    public string $minMessage = 'At least {{ limit }} value should satisfy one of the following constraints:|At least {{ limit }} values should satisfy one of the following constraints:';
    public string $maxMessage = 'At most {{ limit }} value should satisfy one of the following constraints:|At most {{ limit }} values should satisfy one of the following constraints:';
    public string $exactMessage = 'Exactly {{ limit }} value should satisfy one of the following constraints:|Exactly {{ limit }} values should satisfy one of the following constraints:';
    public bool $includeInternalMessages = true;

    public function __construct(array $options = null, mixed $constraints = null, int $exactly = null, int $min = null, int $max = null, array $groups = null, mixed $payload = null, string $minMessage = null, string $maxMessage = null, string $exactMessage = null, bool $includeInternalMessages = null)
    {
        $exactly ??= $options['exactly'] ?? null;

        if (null !== $exactly && null === $min && null === $max) {
            $min = $max = $exactly;
        }

        if (\is_array($constraints)) {
            $options['constraints'] = $constraints;
        }

        unset($options['exactly']);

        parent::__construct($options, $groups, $payload);

        $this->min = $min ?? $this->min;
        $this->max = $max ?? $this->max;
        $this->minMessage = $minMessage ?? $this->minMessage;
        $this->maxMessage = $maxMessage ?? $this->maxMessage;
        $this->exactMessage = $exactMessage ?? $this->exactMessage;
        $this->includeInternalMessages = $includeInternalMessages ?? $this->includeInternalMessages;

        if ($this->min < 0) {
            throw new ConstraintDefinitionException('The "min" option must be greater than 0.');
        }
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
