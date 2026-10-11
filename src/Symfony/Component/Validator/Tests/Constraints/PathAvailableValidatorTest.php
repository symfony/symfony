<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;
use Symfony\Component\Validator\Constraints\PathAvailable;
use Symfony\Component\Validator\Constraints\PathAvailableValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class PathAvailableValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): PathAvailableValidator
    {
        $routes = new RouteCollection();
        $routes->add('logout', new Route('/logout'));
        $routes->add('dummy_name', new Route('/already-existing-path'));

        return new PathAvailableValidator(new Router(new ClosureLoader(), static fn () => $routes));
    }

    public function testNullIsValid()
    {
        $this->validate(null, new PathAvailable());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validate('', new PathAvailable());

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validate(new \stdClass(), new PathAvailable());
    }

    #[DataProvider('getValidValues')]
    public function testValidValues($value)
    {
        $constraint = new PathAvailable();
        $this->validate($value, $constraint);

        $this->assertNoViolation();
    }

    public static function getValidValues()
    {
        return [
            [0],
            ['toto'],
            ['toto/123-000'],
            ['dummy_name'],
            [new class {
                public function __toString(): string
                {
                    return 'toto';
                }
            }],
        ];
    }

    #[DataProvider('getInvalidValues')]
    public function testInvalidValues($value)
    {
        $constraint = new PathAvailable(message: 'myMessage');

        $this->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ path }}', '"'.$value.'"')
            ->assertRaised();
    }

    public static function getInvalidValues()
    {
        return [
            ['logout'],
            ['already-existing-path'],
        ];
    }
}
