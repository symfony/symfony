<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Serializer\Command\DebugCommand;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Tests\Dummy\DummyClassWithDiscriminatorMap;
use Symfony\Component\Serializer\Tests\Dummy\DummyClassOne;

/**
 * @author Loïc Frémont <lc.fremont@gmail.com>
 */
class DebugCommandTest extends TestCase
{
    public function testOutputWithClassArgument()
    {
        $command = new DebugCommand(new ClassMetadataFactory(new AttributeLoader()));

        $tester = new CommandTester($command);
        $tester->execute(['class' => DummyClassOne::class], ['decorated' => false]);

        $this->assertSame(<<<TXT

            Symfony\Component\Serializer\Tests\Dummy\DummyClassOne
            ------------------------------------------------------

            +----------+---------------------------------------+
            | Property | Options                               |
            +----------+---------------------------------------+
            | code     | [                                     |
            |          |   "groups" => [                       |
            |          |     "book:read",                      |
            |          |     "book:write"                      |
            |          |   ],                                  |
            |          |   "maxDepth" => 1,                    |
            |          |   "serializedName" => "identifier",   |
            |          |   "serializedPath" => null,           |
            |          |   "ignore" => true,                   |
            |          |   "normalizationContexts" => [        |
            |          |     "*" => [                          |
            |          |       "groups" => [                   |
            |          |         "book:read"                   |
            |          |       ]                               |
            |          |     ]                                 |
            |          |   ],                                  |
            |          |   "denormalizationContexts" => [      |
            |          |     "*" => [                          |
            |          |       "groups" => [                   |
            |          |         "book:write"                  |
            |          |       ]                               |
            |          |     ]                                 |
            |          |   ]                                   |
            |          | ]                                     |
            | name     | [                                     |
            |          |   "groups" => [],                     |
            |          |   "maxDepth" => null,                 |
            |          |   "serializedName" => null,           |
            |          |   "serializedPath" => "[data][name]", |
            |          |   "ignore" => false,                  |
            |          |   "normalizationContexts" => [],      |
            |          |   "denormalizationContexts" => []     |
            |          | ]                                     |
            +----------+---------------------------------------+

            TXT,
            $tester->getDisplay(true),
        );
    }

    public function testOutputWithDiscriminatorMapClass()
    {
        $command = new DebugCommand(new ClassMetadataFactory(new AttributeLoader()));

        $tester = new CommandTester($command);
        $tester->execute(['class' => DummyClassWithDiscriminatorMap::class], ['decorated' => false]);

        $this->assertSame(<<<TXT

            Symfony\Component\Serializer\Tests\Dummy\DummyClassWithDiscriminatorMap
            -----------------------------------------------------------------------

            +----------+------------------------------------------------------------------------+
            | Property | Options                                                                |
            +----------+------------------------------------------------------------------------+
            | type     | [                                                                      |
            |          |   "groups" => [],                                                      |
            |          |   "maxDepth" => null,                                                  |
            |          |   "serializedName" => null,                                            |
            |          |   "serializedPath" => null,                                            |
            |          |   "ignore" => false,                                                   |
            |          |   "normalizationContexts" => [],                                       |
            |          |   "denormalizationContexts" => [],                                     |
            |          |   "discriminatorMap" => [                                              |
            |          |     "one" => "Symfony\Component\Serializer\Tests\Dummy\DummyClassOne", |
            |          |     "two" => "Symfony\Component\Serializer\Tests\Dummy\DummyClassTwo"  |
            |          |   ]                                                                    |
            |          | ]                                                                      |
            +----------+------------------------------------------------------------------------+

            TXT,
            $tester->getDisplay(true),
        );
    }

    public function testOutputWithInvalidClassArgument()
    {
        $serializer = $this->createMock(ClassMetadataFactoryInterface::class);

        $command = new DebugCommand($serializer);

        $tester = new CommandTester($command);
        $tester->execute(['class' => 'App\\NotFoundResource'], ['decorated' => false]);

        $this->assertStringContainsString('[ERROR] Class "App\NotFoundResource" was not found.', $tester->getDisplay(true)
        );
    }
}
