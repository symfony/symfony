<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Validator\Constraints;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * Constraint for the Unique Entity validator.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class UniqueEntity extends Constraint
{
    public const NOT_UNIQUE_ERROR = '23bd9dbf-6b9b-41cd-a99e-4844bcf3077f';

    protected const ERROR_NAMES = [
        self::NOT_UNIQUE_ERROR => 'NOT_UNIQUE_ERROR',
    ];

    public string $message = 'This value is already used.';
    public string $service = 'doctrine.orm.validator.unique';
    public ?string $em = null;
    public ?string $entityClass = null;
    public string $repositoryMethod = 'findBy';
    public array|string $fields = [];
    public ?string $errorPath = null;
    public bool|array|string $ignoreNull = true;
    public array $identifierFieldNames = [];

    /**
     * @param array|string         $fields           The combination of fields that must contain unique values or a set of options
     * @param bool|string[]|string $ignoreNull       The combination of fields that ignore null values
     * @param string|null          $em               The entity manager used to query for uniqueness instead of the manager of this class
     * @param string|null          $entityClass      The entity class to enforce uniqueness on instead of the current class
     * @param string|null          $repositoryMethod The repository method to check uniqueness instead of findBy. The method will receive as its argument
     *                                               a fieldName => value associative array according to the fields option configuration
     * @param string|null          $errorPath        Bind the constraint violation to this field instead of the first one in the fields option configuration
     */
    #[HasNamedArguments]
    public function __construct(
        array|string $fields,
        ?string $message = null,
        ?string $service = null,
        ?string $em = null,
        ?string $entityClass = null,
        ?string $repositoryMethod = null,
        ?string $errorPath = null,
        bool|string|array|null $ignoreNull = null,
        ?array $identifierFieldNames = null,
        ?array $groups = null,
        $payload = null,
        ?array $options = null,
    ) {
        if (\is_array($fields) && \is_string(key($fields)) && [] === array_diff(array_keys($fields), array_merge(array_keys(get_class_vars(static::class)), ['value']))) {
            trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);

            $options = array_merge($fields, $options ?? []);
        } else {
            if (\is_array($options)) {
                trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);
            } else {
                $options = [];
            }

            $options['fields'] = $fields;
        }

        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->service = $service ?? $this->service;
        $this->em = $em ?? $this->em;
        $this->entityClass = $entityClass ?? $this->entityClass;
        $this->repositoryMethod = $repositoryMethod ?? $this->repositoryMethod;
        $this->errorPath = $errorPath ?? $this->errorPath;
        $this->ignoreNull = $ignoreNull ?? $this->ignoreNull;
        $this->identifierFieldNames = $identifierFieldNames ?? $this->identifierFieldNames;
    }

    public function getRequiredOptions(): array
    {
        return ['fields'];
    }

    /**
     * The validator must be defined as a service with this name.
     */
    public function validatedBy(): string
    {
        return $this->service;
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }

    public function getDefaultOption(): ?string
    {
        return 'fields';
    }
}
