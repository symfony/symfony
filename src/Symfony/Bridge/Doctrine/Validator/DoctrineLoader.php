<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Validator;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata as OrmClassMetadata;
use Doctrine\ORM\Mapping\FieldMapping;
use Doctrine\ORM\Mapping\MappingException as OrmMappingException;
use Doctrine\Persistence\Mapping\MappingException;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Mapping\AutoMappingStrategy;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AutoMappingTrait;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;

/**
 * Guesses and loads the appropriate constraints using Doctrine's metadata.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class DoctrineLoader implements LoaderInterface
{
    use AutoMappingTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ?string $classValidatorRegexp = null,
    ) {
    }

    public function loadClassMetadata(ClassMetadata $metadata): bool
    {
        $className = $metadata->getClassName();
        try {
            $doctrineMetadata = $this->entityManager->getClassMetadata($className);
        } catch (MappingException|OrmMappingException) {
            return false;
        }

        if (!$doctrineMetadata instanceof OrmClassMetadata) {
            return false;
        }

        $loaded = false;
        $enabledForClass = $this->isAutoMappingEnabledForClass($metadata, $this->classValidatorRegexp);

        /* Available keys:
           - type
           - scale
           - length
           - unique
           - nullable
           - precision
         */
        $existingUniqueFields = $this->getExistingUniqueFields($metadata);

        // Type and nullable aren't handled here, use the PropertyInfo Loader instead.
        foreach ($doctrineMetadata->fieldMappings as $mapping) {
            $enabledForProperty = $enabledForClass;
            $lengthConstraint = null;
            foreach ($metadata->getPropertyMetadata(self::getFieldMappingValue($mapping, 'fieldName')) as $propertyMetadata) {
                // Enabling or disabling auto-mapping explicitly always takes precedence
                if (AutoMappingStrategy::DISABLED === $propertyMetadata->getAutoMappingStrategy()) {
                    continue 2;
                }
                if (AutoMappingStrategy::ENABLED === $propertyMetadata->getAutoMappingStrategy()) {
                    $enabledForProperty = true;
                }

                foreach ($propertyMetadata->getConstraints() as $constraint) {
                    if ($constraint instanceof Length) {
                        $lengthConstraint = $constraint;
                    }
                }
            }

            if (!$enabledForProperty) {
                continue;
            }

            if (true === (self::getFieldMappingValue($mapping, 'unique') ?? false) && !isset($existingUniqueFields[self::getFieldMappingValue($mapping, 'fieldName')])) {
                $metadata->addConstraint(new UniqueEntity(['fields' => self::getFieldMappingValue($mapping, 'fieldName')]));
                $loaded = true;
            }

            if (null === (self::getFieldMappingValue($mapping, 'length') ?? null) || null !== (self::getFieldMappingValue($mapping, 'enumType') ?? null) || !\in_array(self::getFieldMappingValue($mapping, 'type'), ['string', 'text'], true)) {
                continue;
            }

            if (null === $lengthConstraint) {
                if (self::getFieldMappingValue($mapping, 'originalClass') && !str_contains(self::getFieldMappingValue($mapping, 'declaredField'), '.')) {
                    $metadata->addPropertyConstraint(self::getFieldMappingValue($mapping, 'declaredField'), new Valid());
                    $loaded = true;
                } elseif (property_exists($className, self::getFieldMappingValue($mapping, 'fieldName')) && (!$doctrineMetadata->isMappedSuperclass || $metadata->getReflectionClass()->getProperty(self::getFieldMappingValue($mapping, 'fieldName'))->isPrivate())) {
                    $metadata->addPropertyConstraint(self::getFieldMappingValue($mapping, 'fieldName'), new Length(['max' => self::getFieldMappingValue($mapping, 'length')]));
                    $loaded = true;
                }
            } elseif (null === $lengthConstraint->max) {
                // If a Length constraint exists and no max length has been explicitly defined, set it
                $lengthConstraint->max = self::getFieldMappingValue($mapping, 'length');
            }
        }

        return $loaded;
    }

    private function getExistingUniqueFields(ClassMetadata $metadata): array
    {
        $fields = [];
        foreach ($metadata->getConstraints() as $constraint) {
            if (!$constraint instanceof UniqueEntity) {
                continue;
            }

            if (\is_string($constraint->fields)) {
                $fields[$constraint->fields] = true;
            } elseif (\is_array($constraint->fields) && 1 === \count($constraint->fields)) {
                $fields[$constraint->fields[0]] = true;
            }
        }

        return $fields;
    }

    private static function getFieldMappingValue(array|FieldMapping $mapping, string $key): mixed
    {
        if ($mapping instanceof FieldMapping) {
            return $mapping->$key ?? null;
        }

        return $mapping[$key] ?? null;
    }
}
