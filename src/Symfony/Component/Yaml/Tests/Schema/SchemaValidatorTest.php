<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Yaml\Tests\Schema;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Exception\RuntimeException;
use Symfony\Component\Yaml\Schema\SchemaValidator;
use Symfony\Component\Yaml\Yaml;

class SchemaValidatorTest extends TestCase
{
    private array $files = [];

    protected function setUp(): void
    {
        if (!(new SchemaValidator())->isSupported()) {
            $this->markTestSkipped('The "opis/json-schema" package is required.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    public function testValidDataReturnsNoError()
    {
        $content = "name: Symfony\nversion: 8";
        $validator = new SchemaValidator();

        $this->assertSame([], $validator->validate(Yaml::parse($content), $this->createSchema(), $content));
    }

    public function testInvalidDataReturnsErrors()
    {
        $content = 'version: not-an-integer';
        $validator = new SchemaValidator();

        $errors = $validator->validate(Yaml::parse($content), $this->createSchema(), $content);

        $this->assertNotEmpty($errors);
        $this->assertIsString($errors[0]['message']);
        $this->assertSame(1, $errors[0]['line']);
    }

    public function testErrorsHaveNoLineWithoutContent()
    {
        $content = 'version: not-an-integer';
        $validator = new SchemaValidator();

        $errors = $validator->validate(Yaml::parse($content), $this->createSchema());

        $this->assertNotEmpty($errors);
        $this->assertSame(0, $errors[0]['line']);
    }

    #[DataProvider('provideDirectoryNames')]
    public function testSchemaPathIsEncodedAsFileUri(string $dirName)
    {
        $dir = sys_get_temp_dir().'/sf-schema-'.bin2hex(random_bytes(4)).$dirName;
        $schema = $dir.'/schema.json';

        mkdir($dir);
        file_put_contents($schema, json_encode([
            'type' => 'object',
            'properties' => ['version' => ['type' => 'integer']],
        ]));

        try {
            $content = 'version: not-an-integer';
            $errors = (new SchemaValidator())->validate(Yaml::parse($content), $schema, $content);

            $this->assertCount(1, $errors);
            $this->assertSame(1, $errors[0]['line']);
        } finally {
            @unlink($schema);
            @rmdir($dir);
        }
    }

    public static function provideDirectoryNames(): iterable
    {
        yield [' with space'];
        yield ['#hash'];
        yield ['%percent'];
        yield ['%20'];
        yield ['-é'];
    }

    #[DataProvider('provideDirectoryNames')]
    public function testRelativeReferenceIsResolvedAgainstTheSchemaFile(string $dirName)
    {
        $dir = sys_get_temp_dir().'/sf-schema-'.bin2hex(random_bytes(4)).$dirName;
        $schema = $dir.'/schema.json';

        mkdir($dir);
        file_put_contents($schema, json_encode([
            'type' => 'object',
            'properties' => ['version' => ['$ref' => 'version.json']],
        ]));
        file_put_contents($dir.'/version.json', json_encode(['type' => 'integer']));

        try {
            $content = 'version: not-an-integer';
            $errors = (new SchemaValidator())->validate(Yaml::parse($content), $schema, $content);

            $this->assertCount(1, $errors);
            $this->assertSame(1, $errors[0]['line']);
        } finally {
            @unlink($schema);
            @unlink($dir.'/version.json');
            @rmdir($dir);
        }
    }

    #[TestWith(['file:///'])]
    #[TestWith(['file://localhost/'])]
    #[TestWith(['file://LOCALHOST/'])]
    public function testRelativeReferenceIsResolvedAgainstAFileUriSchema(string $prefix)
    {
        $version = $this->createFile(json_encode(['type' => 'integer']));
        $schema = $this->createFile(json_encode([
            'type' => 'object',
            'properties' => ['version' => ['$ref' => basename($version)]],
        ]));

        $content = 'version: not-an-integer';
        $errors = (new SchemaValidator())->validate(Yaml::parse($content), $prefix.ltrim(strtr($schema, '\\', '/'), '/'), $content);

        $this->assertCount(1, $errors);
        $this->assertSame(1, $errors[0]['line']);
    }

    #[DataProvider('provideNonLocalReferences')]
    public function testNonLocalReferenceIsNotResolved(string $reference)
    {
        $version = $this->createFile(json_encode(['type' => 'integer']));
        $schema = $this->createFile(json_encode([
            'type' => 'object',
            'properties' => ['version' => ['$ref' => \sprintf($reference, '/'.ltrim(strtr($version, '\\', '/'), '/'))]],
        ]));

        $content = 'version: not-an-integer';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unresolved reference');
        (new SchemaValidator())->validate(Yaml::parse($content), $schema, $content);
    }

    public static function provideNonLocalReferences(): iterable
    {
        yield 'a remote url' => ['https://example.com/version.json'];
        yield 'a file uri with a host' => ['file://example.com%s'];
        yield 'a stream wrapper behind the file scheme' => ['file:file://%s'];
    }

    public function testErrorLineIsResolvedFromNestedKey()
    {
        $schema = $this->createFile(json_encode([
            'type' => 'object',
            'properties' => [
                'parent' => [
                    'type' => 'object',
                    'properties' => ['child' => ['type' => 'integer']],
                ],
            ],
        ]));

        $content = "parent:\n    child: not-an-integer";
        $validator = new SchemaValidator();

        $errors = $validator->validate(Yaml::parse($content), $schema, $content);

        $this->assertNotEmpty($errors);
        $this->assertSame(2, $errors[0]['line']);
    }

    public function testErrorLineSkipsSameKeyNestedDeeper()
    {
        $schema = $this->createFile(json_encode([
            'type' => 'object',
            'properties' => [
                'second' => [
                    'type' => 'object',
                    'properties' => [
                        'other' => ['type' => 'object', 'properties' => ['target' => ['type' => 'integer']]],
                        'target' => ['type' => 'integer'],
                    ],
                ],
            ],
        ]));

        $content = "second:\n    other:\n        target: 1\n    target: not-an-integer";
        $validator = new SchemaValidator();

        $errors = $validator->validate(Yaml::parse($content), $schema, $content);

        $this->assertCount(1, $errors);
        $this->assertSame(4, $errors[0]['line']);
    }

    public function testErrorLineDoesNotLeaveTheParentBlock()
    {
        $schema = $this->createFile(json_encode([
            'type' => 'object',
            'properties' => [
                'first' => ['type' => 'object', 'properties' => ['target' => ['type' => 'string']]],
                'second' => ['type' => 'object', 'properties' => ['target' => ['type' => 'string']]],
            ],
        ]));

        // The invalid value sits in a flow mapping; the same key exists later in another block.
        $content = "first: { target: 1 }\nsecond:\n    target: not-an-integer";
        $validator = new SchemaValidator();

        $errors = $validator->validate(Yaml::parse($content), $schema, $content);

        $this->assertCount(1, $errors);
        $this->assertSame(1, $errors[0]['line']);
    }

    public function testErrorLineFollowsSequenceIndices()
    {
        $schema = $this->createFile(json_encode([
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'items' => ['type' => 'object', 'properties' => ['name' => ['type' => 'integer']]],
                ],
            ],
        ]));

        $content = "items:\n    - name: 1\n    - name: not-an-integer\n    - name: 3";
        $validator = new SchemaValidator();

        $errors = $validator->validate(Yaml::parse($content), $schema, $content);

        $this->assertCount(1, $errors);
        $this->assertSame(3, $errors[0]['line']);
    }

    public function testErrorLineWithSequenceItemsAtTheKeyIndentation()
    {
        $schema = $this->createFile(json_encode([
            'type' => 'object',
            'properties' => [
                'list' => [
                    'type' => 'array',
                    'items' => ['type' => 'object', 'properties' => ['name' => ['type' => 'integer']]],
                ],
            ],
        ]));

        $content = "list:\n- name: 1\n- name: not-an-integer";
        $validator = new SchemaValidator();

        $errors = $validator->validate(Yaml::parse($content), $schema, $content);

        $this->assertCount(1, $errors);
        $this->assertSame(3, $errors[0]['line']);
    }

    public function testErrorLineWithSequenceItemOnItsOwnLine()
    {
        $schema = $this->createFile(json_encode([
            'type' => 'object',
            'properties' => [
                'list' => [
                    'type' => 'array',
                    'items' => ['type' => 'object', 'properties' => ['name' => ['type' => 'integer']]],
                ],
            ],
        ]));

        $content = "list:\n    -\n        name: not-an-integer";
        $validator = new SchemaValidator();

        $errors = $validator->validate(Yaml::parse($content), $schema, $content);

        $this->assertCount(1, $errors);
        $this->assertSame(3, $errors[0]['line']);
    }

    public function testErrorLineFollowsNestedSequences()
    {
        $schema = $this->createFile(json_encode([
            'type' => 'object',
            'properties' => [
                'matrix' => [
                    'type' => 'array',
                    'items' => ['type' => 'array', 'items' => ['type' => 'integer']],
                ],
            ],
        ]));

        $content = "matrix:\n    - - 1\n      - not-an-integer";
        $validator = new SchemaValidator();

        $errors = $validator->validate(Yaml::parse($content), $schema, $content);

        $this->assertNotEmpty($errors);
        $this->assertSame(3, $errors[0]['line']);
    }

    public function testErrorLineForMissingKeyReportsTheOwningBlock()
    {
        $schema = $this->createFile(json_encode([
            'type' => 'object',
            'properties' => [
                'router' => [
                    'type' => 'object',
                    'required' => ['resource'],
                ],
                'session' => ['type' => 'object'],
            ],
        ]));

        // "resource" is missing from "router" but present in "session": the line of the
        // node the violation belongs to must be reported, not the one of the other block.
        $content = "router:\n    utf8: true\nsession:\n    resource: bar";
        $validator = new SchemaValidator();

        $errors = $validator->validate(Yaml::parse($content), $schema, $content);

        $this->assertNotEmpty($errors);
        $this->assertSame(1, $errors[0]['line']);
    }

    public function testErrorLineForDigitMappingKey()
    {
        $schema = $this->createFile(json_encode([
            'type' => 'object',
            'properties' => [
                'codes' => [
                    'type' => 'object',
                    'additionalProperties' => ['type' => 'integer'],
                ],
            ],
        ]));

        $content = "codes:\n    404: not-an-integer";
        $validator = new SchemaValidator();

        $errors = $validator->validate(Yaml::parse($content), $schema, $content);

        $this->assertNotEmpty($errors);
        $this->assertSame(2, $errors[0]['line']);
    }

    public function testErrorLineForKeyContainingDots()
    {
        $schema = $this->createFile(json_encode([
            'type' => 'object',
            'properties' => [
                'hosts' => [
                    'type' => 'object',
                    'additionalProperties' => ['type' => 'integer'],
                ],
            ],
        ]));

        $content = "hosts:\n    example.com: 1\n    other.org: not-an-integer";
        $validator = new SchemaValidator();

        $errors = $validator->validate(Yaml::parse($content), $schema, $content);

        $this->assertNotEmpty($errors);
        $this->assertSame(3, $errors[0]['line']);
    }

    public function testInvalidUtf8ThrowsRuntimeException()
    {
        // Encoding such a value to JSON fails, and the data must not be silently validated as null.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to convert the content to JSON');

        (new SchemaValidator())->validate(['name' => "\xB1\x31"], $this->createSchema());
    }

    #[TestWith(['/nonexistent/schema.json'])]
    #[TestWith(["a\0b.json"])]
    public function testUnresolvableSchemaThrowsRuntimeException(string $schema)
    {
        $content = 'version: 8';

        $this->expectException(RuntimeException::class);
        (new SchemaValidator())->validate(Yaml::parse($content), $schema, $content);
    }

    public function testAllViolationsAreReported()
    {
        $content = "name: 8\nversion: not-an-integer";
        $validator = new SchemaValidator();

        $errors = $validator->validate(Yaml::parse($content), $this->createSchema(), $content);

        $this->assertCount(2, $errors);
        $this->assertSame(1, $errors[0]['line']);
        $this->assertSame(2, $errors[1]['line']);
    }

    public function testLocalPathWithASchemeSeparatorIsNotRemote()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('A colon is not allowed in a file name on Windows.');
        }

        $dir = sys_get_temp_dir().'/sf-schema-'.bin2hex(random_bytes(4));
        mkdir($dir.'/foo:', 0o777, true);
        file_put_contents($dir.'/foo:/schema.json', json_encode([
            'type' => 'object',
            'properties' => ['version' => ['type' => 'integer']],
        ]));

        try {
            $content = 'version: not-an-integer';
            // As for editors, only "file:", "http://" and "https://" locations are URLs.
            $errors = (new SchemaValidator())->validate(Yaml::parse($content), $dir.'/foo://schema.json', $content);

            $this->assertCount(1, $errors);
        } finally {
            @unlink($dir.'/foo:/schema.json');
            @rmdir($dir.'/foo:');
            @rmdir($dir);
        }
    }

    public function testRemoteSchemaIsRejected()
    {
        $content = 'version: 8';

        // A remote schema is never fetched over the network while linting.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('only local schema files are supported');
        (new SchemaValidator())->validate(Yaml::parse($content), 'https://example.com/schema.json', $content);
    }

    private function createSchema(): string
    {
        return $this->createFile(json_encode([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'version' => ['type' => 'integer'],
            ],
        ]));
    }

    private function createFile(string $content): string
    {
        $filename = tempnam(sys_get_temp_dir(), 'sf-schema-');
        file_put_contents($filename, $content);

        $this->files[] = $filename;

        return $filename;
    }
}
