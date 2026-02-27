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
use Symfony\Component\Validator\Constraints\Yaml;
use Symfony\Component\Validator\Constraints\YamlValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Yaml\Yaml as YamlParser;

/**
 * @author Kev <https://github.com/symfonyaml>
 */
class YamlValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): YamlValidator
    {
        return new YamlValidator();
    }

    #[DataProvider('getValidValues')]
    public function testYamlIsValid($value)
    {
        $this->validator->validate($value, new Yaml());

        $this->assertNoViolation();
    }

    public function testYamlWithFlags()
    {
        $this->validator->validate('date: 2023-01-01', new Yaml(flags: YamlParser::PARSE_DATETIME));
        $this->assertNoViolation();
    }

    #[DataProvider('getInvalidValues')]
    public function testInvalidValues($value, $message, $line)
    {
        $constraint = new Yaml(
            message: 'myMessageTest',
        );

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessageTest')
            ->setParameter('{{ error }}', $message)
            ->setParameter('{{ line }}', $line)
            ->setCode(Yaml::INVALID_YAML_ERROR)
            ->assertRaised();
    }

    public function testInvalidFlags()
    {
        $value = 'tags: [!tagged app.myclass]';
        $this->validator->validate($value, new Yaml());
        $this->buildViolation('This value is not valid YAML.')
            ->setParameter('{{ error }}', 'Tags support is not enabled. Enable the "Yaml::PARSE_CUSTOM_TAGS" flag to use "!tagged" at line 1 (near "tags: [!tagged app.myclass]").')
            ->setParameter('{{ line }}', 1)
            ->setCode(Yaml::INVALID_YAML_ERROR)
            ->assertRaised();
    }

    #[DataProvider('getDeprecationOnLinesData')]
    public function testDeprecationTriggersParseException(int $yamlLine, string $yamlValue)
    {
        $lines = explode("\n", $yamlValue);
        $errorLine = end($lines);
        $expectedError = 'This is a simulated deprecation at line '.$yamlLine.' (near "'.$errorLine.'")';

        $constraint = new Yaml(
            message: 'myMessageTest',
            flags: YamlParser::PARSE_OBJECT,
        );
        $this->validator->validate($yamlValue, $constraint);
        $this->buildViolation('myMessageTest')
            ->setParameter('{{ error }}', $expectedError)
            ->setParameter('{{ line }}', $yamlLine)
            ->setCode(Yaml::INVALID_YAML_ERROR)
            ->assertRaised();
    }

    public static function getValidValues()
    {
        return [
            ['planet_diameters: {earth: 12742, mars: 6779, saturn: 116460, mercury: 4879}'],
            ["key:\n  value"],
            [null],
            [''],
            ['"null"'],
            ['null'],
            ['"string"'],
            ['1'],
            ['true'],
            [1],
        ];
    }

    public static function getInvalidValues(): array
    {
        return [
            ['{:INVALID]', 'Malformed unquoted YAML string at line 1 (near "{:INVALID]").', 1],
            ["key:\nvalue", 'Unable to parse at line 2 (near "value").', 2],
        ];
    }

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function getDeprecationOnLinesData(): array
    {
        $serialized = serialize(new DeprecatedObjectFixture());

        return [
            'deprecation at line 1' => [1, "object: !php/object '".$serialized."'"],
            'deprecation at line 2' => [2, "valid: yaml\nobject: !php/object '".$serialized."'"],
            'deprecation at line 5' => [5, "line1: value\nline2: value\nline3: value\nline4: value\nobject: !php/object '".$serialized."'"],
        ];
    }
}

/**
 * Fixture class for triggering deprecation during unserialize.
 */
class DeprecatedObjectFixture
{
    public function __serialize(): array
    {
        return [];
    }

    public function __unserialize(array $data): void
    {
        @trigger_error('This is a simulated deprecation', \E_USER_DEPRECATED);
    }
}
