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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * Constraint for validating that a value references an existing entity.
 *
 * @author Evgen Yurchenko <evgenijvy@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class EntityExists extends Constraint
{
    public const ENTITY_NOT_FOUND_ERROR = 'f7ef7fa8-4ef7-48d2-a264-b57447e1f2ad';
    public const INVALID_VALUE_ERROR = '24f19c7f-eff5-4a5a-9d4c-163c4ce7ba6b';

    protected const ERROR_NAMES = [
        self::ENTITY_NOT_FOUND_ERROR => 'ENTITY_NOT_FOUND_ERROR',
        self::INVALID_VALUE_ERROR => 'INVALID_VALUE_ERROR',
    ];

    public string $message = 'The referenced entity does not exist.';
    public string $invalidMessage = 'This value is not valid.';

    /**
     * @param string      $entityClass      The entity class to look the value up in
     * @param string|null $em               The object manager to use, or null to resolve it from the entity class
     * @param string|null $identifierField  Look the value up by this field instead of the identifier
     * @param string|null $repositoryMethod Call this repository method with the value instead of findOneBy(); a truthy return means the entity exists
     * @param string|null $invalidMessage   The message when the value cannot be converted to the field's type
     */
    #[HasNamedArguments]
    public function __construct(
        public string $entityClass,
        ?string $message = null,
        public ?string $em = null,
        public ?string $identifierField = null,
        public ?string $repositoryMethod = null,
        ?array $groups = null,
        mixed $payload = null,
        ?string $invalidMessage = null,
    ) {
        parent::__construct(null, $groups, $payload);

        if ($this->identifierField && $this->repositoryMethod) {
            throw new ConstraintDefinitionException('The "identifierField" and "repositoryMethod" options cannot be used together.');
        }

        $this->message = $message ?? $this->message;
        $this->invalidMessage = $invalidMessage ?? $this->invalidMessage;
    }
}
