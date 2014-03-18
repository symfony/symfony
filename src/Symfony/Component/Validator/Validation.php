<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

/**
 * Entry point for the Validator component.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class Validation
{
    /**
     * The Validator API provided by Symfony 2.4 and older.
     */
    const API_VERSION_2_4 = 1;

    /**
     * The Validator API provided by Symfony 2.5 and newer.
     */
    const API_VERSION_2_5 = 2;

    /**
     * The Validator API provided by Symfony 2.5 and newer with a backwards
     * compatibility layer for 2.4 and older.
     */
    const API_VERSION_2_5_BC = 3;

    /**
     * Creates a new validator.
     *
     * If you want to configure the validator, use
     * {@link createValidatorBuilder()} instead.
     *
     * @return ValidatorInterface The new validator.
     */
    public static function createValidator()
    {
        return self::createValidatorBuilder()->getValidator();
    }

    /**
     * Creates a configurable builder for validator objects.
     *
     * @return ValidatorBuilderInterface The new builder.
     */
    public static function createValidatorBuilder()
    {
        return new ValidatorBuilder();
    }

    /**
     * This class cannot be instantiated.
     */
    private function __construct()
    {
    }
}
