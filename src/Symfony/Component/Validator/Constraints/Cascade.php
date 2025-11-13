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
 * Validates a whole class, including nested objects in properties.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Cascade extends Constraint
{
    public array $exclude = [];

    /**
     * @param non-empty-string[]|non-empty-string|null $exclude Properties excluded from validation
     */
    #[HasNamedArguments]
    public function __construct(array|string|null $exclude = null, ?array $options = null)
    {
        if (\is_array($exclude) && !array_is_list($exclude)) {
            trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);

            $options = array_merge($exclude, $options ?? []);
            $options['exclude'] = array_flip((array) ($options['exclude'] ?? []));
            $exclude = $options['exclude'] ?? null;
        } else {
            if (\is_array($options)) {
                trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);
            }

            $exclude = array_flip((array) $exclude);
            $this->exclude = $exclude;
        }

        if (\is_array($options) && \array_key_exists('groups', $options)) {
            throw new ConstraintDefinitionException(\sprintf('The option "groups" is not supported by the constraint "%s".', __CLASS__));
        }

        parent::__construct($options, null, $options['payload'] ?? null);

        $this->exclude = $exclude ?? $this->exclude;
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
