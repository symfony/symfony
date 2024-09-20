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

    /**
     * @dataProvider getValidValues
     */
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

    /**
     * @dataProvider getInvalidValues
     */
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
}
