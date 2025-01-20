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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Yaml;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;
use Symfony\Component\Yaml\Yaml as YamlParser;

/**
 * @author Kev <https://github.com/symfonyaml>
 */
class YamlTest extends TestCase
{
    public function testAttributes()
    {
        $metadata = new ClassMetadata(YamlDummy::class);
        $loader = new AttributeLoader();
        self::assertTrue($loader->loadClassMetadata($metadata));

        [$bConstraint] = $metadata->properties['b']->getConstraints();
        self::assertSame('myMessage', $bConstraint->message);
        self::assertSame(['Default', 'YamlDummy'], $bConstraint->groups);

        [$cConstraint] = $metadata->properties['c']->getConstraints();
        self::assertSame(['my_group'], $cConstraint->groups);
        self::assertSame('some attached data', $cConstraint->payload);

        [$cConstraint] = $metadata->properties['d']->getConstraints();
        self::assertSame(YamlParser::PARSE_CONSTANT | YamlParser::PARSE_CUSTOM_TAGS, $cConstraint->flags);
    }
}

class YamlDummy
{
    #[Yaml]
    private $a;

    #[Yaml(message: 'myMessage')]
    private $b;

    #[Yaml(groups: ['my_group'], payload: 'some attached data')]
    private $c;

    #[Yaml(flags: YamlParser::PARSE_CONSTANT | YamlParser::PARSE_CUSTOM_TAGS)]
    private $d;
}
