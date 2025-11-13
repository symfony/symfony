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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * Validates an object that needs to be traversed.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Traverse extends Constraint
{
    public bool $traverse = true;

    /**
     * @param bool|null $traverse Whether to traverse the given object or not (defaults to true). Pass an associative array to configure the constraint's options (e.g. payload).
     */
    #[HasNamedArguments]
    public function __construct(bool|array|null $traverse = null, mixed $payload = null)
    {
        if (\is_array($traverse) && \array_key_exists('groups', $traverse)) {
            throw new ConstraintDefinitionException(\sprintf('The option "groups" is not supported by the constraint "%s".', __CLASS__));
        }

        if (\is_array($traverse)) {
            trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);
            $options = $traverse;
            $traverse = $options['traverse'] ?? null;
        }

        parent::__construct($options ?? null, $payload);

        $this->traverse = $traverse ?? $this->traverse;
    }

    /**
     * @deprecated since Symfony 7.4
     */
    public function getDefaultOption(): ?string
    {
        if (0 === \func_num_args() || func_get_arg(0)) {
            trigger_deprecation('symfony/validator', '7.4', 'The %s() method is deprecated.', __METHOD__);
        }

        return 'traverse';
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
