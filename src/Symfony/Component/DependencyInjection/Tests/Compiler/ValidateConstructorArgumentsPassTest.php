<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\ValidateConstructorArgumentsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Validator\Constraints\Ip;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class ValidateConstructorArgumentsPassTest extends TestCase
{
    public function testValidationSuccess()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('service', \stdClass::class);
        $definition
            ->setArguments([
                '$int' => 1,
                '$array' => [1, 2, 3],
                '$email' => 'test@email.com',
                '$datetime' => '2020-12-31 23:59:59',
                '$ipAddresses' => ['8.8.4.4', '8.8.8.8'],
                '$noConstraints' => 'no constraints for this argument',
            ])
            ->setConstraints([
                '$int' => ['EqualTo' => 1],
                '$array' => [
                    'Count' => [
                        'min' => 1,
                        'max' => 5,
                    ],
                ],
                '$email' => ['Email' => null],
                '$datetime' => [
                    'NotBlank' => null,
                    'DateTime' => null,
                ],
                '$ipAddresses' => [
                    'All' => [
                        'NotBlank' => null,
                        'Ip' => ['version' => Ip::V4_ONLY_PUBLIC],
                    ],
                ],
            ]);

        $pass = new ValidateConstructorArgumentsPass(false);
        $pass->process($container);

        $this->assertCount(0, $definition->getErrors());
    }

    public function testValidationFailedWithThrowExceptionOnFailure()
    {
        $this->expectException(ValidationFailedException::class);
        $this->expectExceptionMessage('Provided string does not look like JSON. (code 0789c8ad-2d2b-49a4-8356-e2ce63998504)');

        $container = new ContainerBuilder();
        $definition = $container->register('service', \stdClass::class);
        $definition
            ->setArguments([
                '$json' => 'wrong json',
            ])
            ->setConstraints([
                '$json' => [
                    'Json' => ['message' => 'Provided string does not look like JSON.'],
                ],
            ]);

        $pass = new ValidateConstructorArgumentsPass();
        $pass->process($container);
    }

    public function testValidationFailedWithDoNotThrowExceptionOnFailure()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('service', \stdClass::class);
        $definition
            ->setArguments([
                '$choice' => 'foo',
            ])
            ->setConstraints([
                '$choice' => [
                    'Choice' => [
                        'choices' => ['bar', 'baz'],
                        'message' => 'Choice should be one of: bar, baz.',
                    ],
                ],
            ]);

        $pass = new ValidateConstructorArgumentsPass(false);
        $pass->process($container);

        $this->assertCount(1, $definition->getErrors());
        $this->assertMatchesRegularExpression(
            '/Choice should be one of: bar, baz. \(code 8e179f1b-97aa-4560-a02f-2a8b42e49df7\)/',
            $definition->getErrors()[0]
        );
    }
}
