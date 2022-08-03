<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Validator\Command\DebugCommand;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Expression;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadataInterface;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symfony\Component\Validator\Mapping\PropertyMetadataInterface;
use Symfony\Component\Validator\Tests\Dummy\DummyClassOne;

/**
 * @author Loïc Frémont <lc.fremont@gmail.com>
 */
class DebugCommandTest extends TestCase
{
    public function testOutputWithClassArgument()
    {
        $validator = $this->createMock(MetadataFactoryInterface::class);
        $classMetadata = $this->createMock(ClassMetadataInterface::class);
        $propertyMetadata = $this->createMock(PropertyMetadataInterface::class);

        $validator
            ->expects($this->once())
            ->method('getMetadataFor')
            ->with(DummyClassOne::class)
            ->willReturn($classMetadata);

        $classMetadata
            ->expects($this->once())
            ->method('getConstraints')
            ->willReturn([new Expression('1 + 1 = 2')]);

        $classMetadata
            ->expects($this->once())
            ->method('getConstrainedProperties')
            ->willReturn([
                'firstArgument',
            ]);

        $classMetadata
            ->expects($this->once())
            ->method('getPropertyMetadata')
            ->with('firstArgument')
            ->willReturn([
                $propertyMetadata,
            ]);

        $propertyMetadata
            ->expects($this->once())
            ->method('getConstraints')
            ->willReturn([new NotBlank(), new Email()]);

        $command = new DebugCommand($validator);

        $tester = new CommandTester($command);
        $tester->execute(['class' => DummyClassOne::class], ['decorated' => false]);

        $this->assertSame(<<<TXT

Symfony\Component\Validator\Tests\Dummy\DummyClassOne
-----------------------------------------------------

+---------------+----------------------------------------------------+---------+------------------------------------------------------------+
| Property      | Name                                               | Groups  | Options                                                    |
+---------------+----------------------------------------------------+---------+------------------------------------------------------------+
| -             | Symfony\Component\Validator\Constraints\Expression | Default | [                                                          |
|               |                                                    |         |   "expression" => "1 + 1 = 2",                             |
|               |                                                    |         |   "message" => "This value is not valid.",                 |
|               |                                                    |         |   "negate" => true,                                        |
|               |                                                    |         |   "payload" => null,                                       |
|               |                                                    |         |   "values" => []                                           |
|               |                                                    |         | ]                                                          |
| firstArgument | Symfony\Component\Validator\Constraints\NotBlank   | Default | [                                                          |
|               |                                                    |         |   "allowNull" => false,                                    |
|               |                                                    |         |   "message" => "This value should not be blank.",          |
|               |                                                    |         |   "normalizer" => null,                                    |
|               |                                                    |         |   "payload" => null                                        |
|               |                                                    |         | ]                                                          |
| firstArgument | Symfony\Component\Validator\Constraints\Email      | Default | [                                                          |
|               |                                                    |         |   "message" => "This value is not a valid email address.", |
|               |                                                    |         |   "mode" => null,                                          |
|               |                                                    |         |   "normalizer" => null,                                    |
|               |                                                    |         |   "payload" => null                                        |
|               |                                                    |         | ]                                                          |
+---------------+----------------------------------------------------+---------+------------------------------------------------------------+

TXT
            , $tester->getDisplay(true)
        );
    }

    public function testOutputWithPathArgument()
    {
        $validator = $this->createMock(MetadataFactoryInterface::class);
        $classMetadata = $this->createMock(ClassMetadataInterface::class);
        $propertyMetadata = $this->createMock(PropertyMetadataInterface::class);

        $validator
            ->expects($this->exactly(2))
            ->method('getMetadataFor')
            ->withAnyParameters()
            ->willReturn($classMetadata);

        $classMetadata
            ->method('getConstrainedProperties')
            ->willReturn([
                'firstArgument',
            ]);

        $classMetadata
            ->expects($this->exactly(2))
            ->method('getConstraints')
            ->willReturn([new Expression('1 + 1 = 2')]);

        $classMetadata
            ->method('getPropertyMetadata')
            ->with('firstArgument')
            ->willReturn([
                $propertyMetadata,
            ]);

        $propertyMetadata
            ->method('getConstraints')
            ->willReturn([new NotBlank(), new Email()]);

        $command = new DebugCommand($validator);

        $tester = new CommandTester($command);
        $tester->execute(['class' => __DIR__.'/../Dummy'], ['decorated' => false]);

        $this->assertSame(<<<TXT

Symfony\Component\Validator\Tests\Dummy\DummyClassOne
-----------------------------------------------------

+---------------+----------------------------------------------------+---------+------------------------------------------------------------+
| Property      | Name                                               | Groups  | Options                                                    |
+---------------+----------------------------------------------------+---------+------------------------------------------------------------+
| -             | Symfony\Component\Validator\Constraints\Expression | Default | [                                                          |
|               |                                                    |         |   "expression" => "1 + 1 = 2",                             |
|               |                                                    |         |   "message" => "This value is not valid.",                 |
|               |                                                    |         |   "negate" => true,                                        |
|               |                                                    |         |   "payload" => null,                                       |
|               |                                                    |         |   "values" => []                                           |
|               |                                                    |         | ]                                                          |
| firstArgument | Symfony\Component\Validator\Constraints\NotBlank   | Default | [                                                          |
|               |                                                    |         |   "allowNull" => false,                                    |
|               |                                                    |         |   "message" => "This value should not be blank.",          |
|               |                                                    |         |   "normalizer" => null,                                    |
|               |                                                    |         |   "payload" => null                                        |
|               |                                                    |         | ]                                                          |
| firstArgument | Symfony\Component\Validator\Constraints\Email      | Default | [                                                          |
|               |                                                    |         |   "message" => "This value is not a valid email address.", |
|               |                                                    |         |   "mode" => null,                                          |
|               |                                                    |         |   "normalizer" => null,                                    |
|               |                                                    |         |   "payload" => null                                        |
|               |                                                    |         | ]                                                          |
+---------------+----------------------------------------------------+---------+------------------------------------------------------------+

Symfony\Component\Validator\Tests\Dummy\DummyClassTwo
-----------------------------------------------------

+---------------+----------------------------------------------------+---------+------------------------------------------------------------+
| Property      | Name                                               | Groups  | Options                                                    |
+---------------+----------------------------------------------------+---------+------------------------------------------------------------+
| -             | Symfony\Component\Validator\Constraints\Expression | Default | [                                                          |
|               |                                                    |         |   "expression" => "1 + 1 = 2",                             |
|               |                                                    |         |   "message" => "This value is not valid.",                 |
|               |                                                    |         |   "negate" => true,                                        |
|               |                                                    |         |   "payload" => null,                                       |
|               |                                                    |         |   "values" => []                                           |
|               |                                                    |         | ]                                                          |
| firstArgument | Symfony\Component\Validator\Constraints\NotBlank   | Default | [                                                          |
|               |                                                    |         |   "allowNull" => false,                                    |
|               |                                                    |         |   "message" => "This value should not be blank.",          |
|               |                                                    |         |   "normalizer" => null,                                    |
|               |                                                    |         |   "payload" => null                                        |
|               |                                                    |         | ]                                                          |
| firstArgument | Symfony\Component\Validator\Constraints\Email      | Default | [                                                          |
|               |                                                    |         |   "message" => "This value is not a valid email address.", |
|               |                                                    |         |   "mode" => null,                                          |
|               |                                                    |         |   "normalizer" => null,                                    |
|               |                                                    |         |   "payload" => null                                        |
|               |                                                    |         | ]                                                          |
+---------------+----------------------------------------------------+---------+------------------------------------------------------------+

TXT
            , $tester->getDisplay(true)
        );
    }

    public function testOutputWithInvalidClassArgument()
    {
        $validator = $this->createMock(MetadataFactoryInterface::class);

        $command = new DebugCommand($validator);

        $tester = new CommandTester($command);
        $tester->execute(['class' => 'App\\NotFoundResource'], ['decorated' => false]);

        $this->assertStringContainsString(<<<TXT
Neither class nor path were found with "App\NotFoundResource" argument.
TXT
            , $tester->getDisplay(true)
        );
    }
}
