<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Form\ChoiceList;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use Doctrine\DBAL\Types\DateTimeTzType;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\ChoiceList\Loader\AbstractChoiceLoader;
use Symfony\Component\Uid\AbstractUid;

/**
 * Loads the distinct values of a field of an entity as choices.
 *
 * ChoiceType loads the choices for rendering, during construction of expanded fields, or when mapping submitted values. The loaded choices are cached only within this loader.
 *
 * @internal
 */
final class EntityFieldValueChoiceLoader extends AbstractChoiceLoader
{
    private const DECIMAL_PATTERN = '/^[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)$/D';

    private ?AbstractPlatform $platform = null;

    /**
     * @param string                         $phpType   The PHP type of the values: a builtin type or a class
     * @param class-string<\BackedEnum>|null $enumClass The enum the values are mapped to
     */
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly string $class,
        private readonly string $field,
        private readonly Type $type,
        private readonly string $phpType,
        private readonly ?string $enumClass = null,
    ) {
    }

    /**
     * Returns the database form of a choice, so equal model values match the same choice across distinct instances or PHP types.
     */
    public function getValue(mixed $choice): string
    {
        if (null === $choice) {
            return '';
        }

        try {
            return $this->normalize($choice);
        } catch (ConversionException|\TypeError|\ValueError) {
            // a value of an unexpected type matches no choice
            return '';
        }
    }

    public function getLabel(mixed $choice): string
    {
        return $choice instanceof \UnitEnum ? $choice->name : $this->getValue($choice);
    }

    protected function loadChoices(): iterable
    {
        $values = $this->manager->getRepository($this->class)->createQueryBuilder('e')
            ->select('DISTINCT e.'.$this->field)
            ->andWhere('e.'.$this->field.' IS NOT NULL')
            ->orderBy('e.'.$this->field)
            ->getQuery()->getSingleColumnResult();

        $choices = [];
        foreach ($values as $value) {
            // a stored value that cannot be converted, or that is empty, is no choice
            if (null !== $choice = $this->toPhpValue($value)) {
                $choices[] = $choice;
            }
        }

        return $choices;
    }

    private function normalize(mixed $value): string
    {
        // before \Stringable: a date that can be cast to a string is still compared as a date
        if ($value instanceof \DateTimeInterface) {
            if ($this->type instanceof DateTimeTzType || $this->type instanceof DateTimeTzImmutableType) {
                // formats without an offset are read back in the default timezone
                $value = \DateTimeImmutable::createFromInterface($value)->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            }

            // the date types accept either mutable or immutable dates
            $value = is_a($this->phpType, \DateTimeImmutable::class, true) ? \DateTimeImmutable::createFromInterface($value) : \DateTime::createFromInterface($value);

            return (string) $this->type->convertToDatabaseValue($value, $this->getPlatform());
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if (\is_string($value) && is_a($this->phpType, AbstractUid::class, true)) {
            // a uid given in another format is the same uid
            try {
                return (string) $this->phpType::fromString($value);
            } catch (\InvalidArgumentException) {
                return $value;
            }
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (\is_float($value) && 'float' === $this->phpType) {
            return is_finite($value) ? \sprintf('%.17h', $value) : '';
        }

        if ($this->type instanceof DecimalType && \is_scalar($value)) {
            return self::normalizeDecimal((string) $value);
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        return (string) $this->type->convertToDatabaseValue($value, $this->getPlatform());
    }

    /**
     * "9.90", "9.9", ".90" and "0.9" are the same decimal, whatever scale and format the platform returns.
     */
    private static function normalizeDecimal(string $value): string
    {
        if (1 !== preg_match(self::DECIMAL_PATTERN, $value)) {
            throw new \ValueError('Expected a decimal without scientific notation.');
        }

        $negative = str_starts_with($value, '-');
        [$integer, $fraction] = array_pad(explode('.', ltrim($value, '+-'), 2), 2, '');
        $integer = ltrim($integer, '0');
        $fraction = rtrim($fraction, '0');
        $value = ('' === $integer ? '0' : $integer).('' === $fraction ? '' : '.'.$fraction);

        return $negative && '0' !== $value ? '-'.$value : $value;
    }

    /**
     * Converts a database value to the PHP type of the field.
     *
     * @return mixed null when the value cannot be converted or is empty
     */
    private function toPhpValue(mixed $value): mixed
    {
        if (null === $value || '' === $value) {
            return null;
        }

        try {
            $value = $this->type->convertToPHPValue($value, $this->getPlatform());
        } catch (ConversionException|\TypeError|\ValueError) {
            return null;
        }

        if (\is_float($value) && !is_finite($value)) {
            return null;
        }

        if ($this->type instanceof DecimalType && (!\is_string($value) || 1 !== preg_match(self::DECIMAL_PATTERN, $value))) {
            return null;
        }

        if ($this->enumClass) {
            $value = $this->enumClass::tryFrom($value);
        }

        // e.g. an unsigned bigint above PHP_INT_MAX, which cannot be an int
        if (null === $value || !(get_debug_type($value) === $this->phpType || $value instanceof $this->phpType)) {
            return null;
        }

        return $value;
    }

    private function getPlatform(): AbstractPlatform
    {
        // resolved lazily: getting the platform may open a connection
        return $this->platform ??= $this->manager->getConnection()->getDatabasePlatform();
    }
}
