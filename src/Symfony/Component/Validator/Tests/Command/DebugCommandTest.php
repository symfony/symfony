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
use Symfony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;
use Symfony\Component\Validator\Tests\Dummy\DummyClassOne;

/**
 * @author Loïc Frémont <lc.fremont@gmail.com>
 */
class DebugCommandTest extends TestCase
{
    public function testOutputWithClassArgument()
    {
        $command = new DebugCommand(new LazyLoadingMetadataFactory(new AttributeLoader()));

        $tester = new CommandTester($command);
        $tester->execute(['class' => DummyClassOne::class], ['decorated' => false]);

        $this->assertSame(<<<TXT

Symfony\Component\Validator\Tests\Dummy\DummyClassOne
-----------------------------------------------------

+---------------+----------------------------------------------------+------------------------+------------------------------------------------------------+
| Property      | Name                                               | Groups                 | Options                                                    |
+---------------+----------------------------------------------------+------------------------+------------------------------------------------------------+
| -             | Symfony\Component\Validator\Constraints\Expression | Default, DummyClassOne | [                                                          |
|               |                                                    |                        |   "expression" => "1 + 1 = 2",                             |
|               |                                                    |                        |   "message" => "This value is not valid.",                 |
|               |                                                    |                        |   "negate" => true,                                        |
|               |                                                    |                        |   "payload" => null,                                       |
|               |                                                    |                        |   "values" => []                                           |
|               |                                                    |                        | ]                                                          |
| code          | property options                                   |                        | [                                                          |
|               |                                                    |                        |   "cascadeStrategy" => "None",                             |
|               |                                                    |                        |   "autoMappingStrategy" => "None",                         |
|               |                                                    |                        |   "traversalStrategy" => "None"                            |
|               |                                                    |                        | ]                                                          |
| code          | Symfony\Component\Validator\Constraints\NotBlank   | Default, DummyClassOne | [                                                          |
|               |                                                    |                        |   "allowNull" => false,                                    |
|               |                                                    |                        |   "message" => "This value should not be blank.",          |
|               |                                                    |                        |   "normalizer" => null,                                    |
|               |                                                    |                        |   "payload" => null                                        |
|               |                                                    |                        | ]                                                          |
| email         | property options                                   |                        | [                                                          |
|               |                                                    |                        |   "cascadeStrategy" => "None",                             |
|               |                                                    |                        |   "autoMappingStrategy" => "None",                         |
|               |                                                    |                        |   "traversalStrategy" => "None"                            |
|               |                                                    |                        | ]                                                          |
| email         | Symfony\Component\Validator\Constraints\Email      | Default, DummyClassOne | [                                                          |
|               |                                                    |                        |   "message" => "This value is not a valid email address.", |
|               |                                                    |                        |   "mode" => null,                                          |
|               |                                                    |                        |   "normalizer" => null,                                    |
|               |                                                    |                        |   "payload" => null                                        |
|               |                                                    |                        | ]                                                          |
| dummyClassTwo | property options                                   |                        | [                                                          |
|               |                                                    |                        |   "cascadeStrategy" => "Cascade",                          |
|               |                                                    |                        |   "autoMappingStrategy" => "None",                         |
|               |                                                    |                        |   "traversalStrategy" => "Implicit"                        |
|               |                                                    |                        | ]                                                          |
+---------------+----------------------------------------------------+------------------------+------------------------------------------------------------+

TXT
            , $tester->getDisplay(true)
        );
    }

    public function testOutputWithPathArgument()
    {
        $command = new DebugCommand(new LazyLoadingMetadataFactory(new AttributeLoader()));

        $tester = new CommandTester($command);
        $tester->execute(['class' => __DIR__.'/../Dummy'], ['decorated' => false]);

        $this->assertSame(<<<TXT

Symfony\Component\Validator\Tests\Dummy\DummyClassOne
-----------------------------------------------------

+---------------+----------------------------------------------------+------------------------+------------------------------------------------------------+
| Property      | Name                                               | Groups                 | Options                                                    |
+---------------+----------------------------------------------------+------------------------+------------------------------------------------------------+
| -             | Symfony\Component\Validator\Constraints\Expression | Default, DummyClassOne | [                                                          |
|               |                                                    |                        |   "expression" => "1 + 1 = 2",                             |
|               |                                                    |                        |   "message" => "This value is not valid.",                 |
|               |                                                    |                        |   "negate" => true,                                        |
|               |                                                    |                        |   "payload" => null,                                       |
|               |                                                    |                        |   "values" => []                                           |
|               |                                                    |                        | ]                                                          |
| code          | property options                                   |                        | [                                                          |
|               |                                                    |                        |   "cascadeStrategy" => "None",                             |
|               |                                                    |                        |   "autoMappingStrategy" => "None",                         |
|               |                                                    |                        |   "traversalStrategy" => "None"                            |
|               |                                                    |                        | ]                                                          |
| code          | Symfony\Component\Validator\Constraints\NotBlank   | Default, DummyClassOne | [                                                          |
|               |                                                    |                        |   "allowNull" => false,                                    |
|               |                                                    |                        |   "message" => "This value should not be blank.",          |
|               |                                                    |                        |   "normalizer" => null,                                    |
|               |                                                    |                        |   "payload" => null                                        |
|               |                                                    |                        | ]                                                          |
| email         | property options                                   |                        | [                                                          |
|               |                                                    |                        |   "cascadeStrategy" => "None",                             |
|               |                                                    |                        |   "autoMappingStrategy" => "None",                         |
|               |                                                    |                        |   "traversalStrategy" => "None"                            |
|               |                                                    |                        | ]                                                          |
| email         | Symfony\Component\Validator\Constraints\Email      | Default, DummyClassOne | [                                                          |
|               |                                                    |                        |   "message" => "This value is not a valid email address.", |
|               |                                                    |                        |   "mode" => null,                                          |
|               |                                                    |                        |   "normalizer" => null,                                    |
|               |                                                    |                        |   "payload" => null                                        |
|               |                                                    |                        | ]                                                          |
| dummyClassTwo | property options                                   |                        | [                                                          |
|               |                                                    |                        |   "cascadeStrategy" => "Cascade",                          |
|               |                                                    |                        |   "autoMappingStrategy" => "None",                         |
|               |                                                    |                        |   "traversalStrategy" => "Implicit"                        |
|               |                                                    |                        | ]                                                          |
+---------------+----------------------------------------------------+------------------------+------------------------------------------------------------+

Symfony\Component\Validator\Tests\Dummy\DummyClassTwo
-----------------------------------------------------

+---------------+----------------------------------------------------+------------------------+------------------------------------------------------------+
| Property      | Name                                               | Groups                 | Options                                                    |
+---------------+----------------------------------------------------+------------------------+------------------------------------------------------------+
| -             | Symfony\Component\Validator\Constraints\Expression | Default, DummyClassTwo | [                                                          |
|               |                                                    |                        |   "expression" => "1 + 1 = 2",                             |
|               |                                                    |                        |   "message" => "This value is not valid.",                 |
|               |                                                    |                        |   "negate" => true,                                        |
|               |                                                    |                        |   "payload" => null,                                       |
|               |                                                    |                        |   "values" => []                                           |
|               |                                                    |                        | ]                                                          |
| code          | property options                                   |                        | [                                                          |
|               |                                                    |                        |   "cascadeStrategy" => "None",                             |
|               |                                                    |                        |   "autoMappingStrategy" => "None",                         |
|               |                                                    |                        |   "traversalStrategy" => "None"                            |
|               |                                                    |                        | ]                                                          |
| code          | Symfony\Component\Validator\Constraints\NotBlank   | Default, DummyClassTwo | [                                                          |
|               |                                                    |                        |   "allowNull" => false,                                    |
|               |                                                    |                        |   "message" => "This value should not be blank.",          |
|               |                                                    |                        |   "normalizer" => null,                                    |
|               |                                                    |                        |   "payload" => null                                        |
|               |                                                    |                        | ]                                                          |
| email         | property options                                   |                        | [                                                          |
|               |                                                    |                        |   "cascadeStrategy" => "None",                             |
|               |                                                    |                        |   "autoMappingStrategy" => "None",                         |
|               |                                                    |                        |   "traversalStrategy" => "None"                            |
|               |                                                    |                        | ]                                                          |
| email         | Symfony\Component\Validator\Constraints\Email      | Default, DummyClassTwo | [                                                          |
|               |                                                    |                        |   "message" => "This value is not a valid email address.", |
|               |                                                    |                        |   "mode" => null,                                          |
|               |                                                    |                        |   "normalizer" => null,                                    |
|               |                                                    |                        |   "payload" => null                                        |
|               |                                                    |                        | ]                                                          |
| dummyClassOne | property options                                   |                        | [                                                          |
|               |                                                    |                        |   "cascadeStrategy" => "None",                             |
|               |                                                    |                        |   "autoMappingStrategy" => "Disabled",                     |
|               |                                                    |                        |   "traversalStrategy" => "None"                            |
|               |                                                    |                        | ]                                                          |
+---------------+----------------------------------------------------+------------------------+------------------------------------------------------------+

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
