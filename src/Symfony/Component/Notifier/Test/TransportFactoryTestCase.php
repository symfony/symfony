<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Test;

/**
 * A test case to ease testing a notifier transport factory.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 *
 * @deprecated since Symfony 7.2, use AbstractTransportFactoryTestCase instead
 */
abstract class TransportFactoryTestCase extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;
    use MissingRequiredOptionTestTrait;

    /**
     * @return iterable<array{0: string, 1?: string|null}>
     */
    public static function unsupportedSchemeProvider(): iterable
    {
        return [];
    }

    /**
     * @return iterable<array{0: string, 1?: string|null}>
     */
    public static function incompleteDsnProvider(): iterable
    {
        return [];
    }

    /**
     * @return iterable<array{0: string, 1?: string|null}>
     */
    public static function missingRequiredOptionProvider(): iterable
    {
        return [];
    }
}
