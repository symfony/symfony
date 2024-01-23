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

    /**
     * @param array|string      $fields     The combination of fields that must contain unique values or a set of options
     * @param bool|array|string $ignoreNull The combination of fields that ignore null values
     */
    public function __construct(
        array|string $fields,
        ?string $message = null,
        ?string $service = null,
        ?string $em = null,
        ?string $entityClass = null,
        ?string $repositoryMethod = null,
        ?string $errorPath = null,
        bool|string|array|null $ignoreNull = null,
        ?array $groups = null,
        $payload = null,
        array $options = []
    ) {
        if (\is_array($fields) && \is_string(key($fields))) {
            $options = array_merge($fields, $options);
        } elseif (null !== $fields) {
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
