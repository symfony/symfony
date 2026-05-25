<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Util;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Util\XliffUtils;

class XliffUtilsTest extends TestCase
{
    #[DataProvider('providePaths')]
    public function testGetFileUrlEncodesPathSegments(string $path, string $expected)
    {
        $method = new \ReflectionMethod(XliffUtils::class, 'getFileUrl');

        $this->assertSame($expected, $method->invoke(null, $path));
    }

    public static function providePaths(): iterable
    {
        yield 'plain POSIX path' => [
            '/tmp/symfony123',
            'file:////tmp/symfony123',
        ];

        // Windows usernames may contain spaces. Without rawurlencode, the
        // resulting `file:///` URL is syntactically invalid and triggers a
        // libxml "Invalid Schema" warning when fed to schemaValidateSource().
        yield 'POSIX path with spaces' => [
            '/tmp/dir with space/symfony123',
            'file:////tmp/dir%20with%20space/symfony123',
        ];

        yield 'POSIX path with non-ASCII characters' => [
            '/tmp/中文/symfony123',
            'file:////tmp/%E4%B8%AD%E6%96%87/symfony123',
        ];
    }

    public function testEncodedUrlPreventsLibxmlInvalidSchemaErrors()
    {
        $dir = sys_get_temp_dir().\DIRECTORY_SEPARATOR.uniqid('symfony_xliff_', false).' with space';

        if (!@mkdir($dir) && !is_dir($dir)) {
            $this->markTestSkipped(\sprintf('Could not create tmp dir "%s".', $dir));
        }

        $tmpfile = tempnam($dir, 'symfony');

        try {
            if (!\is_string($tmpfile) || \dirname($tmpfile) !== $dir) {
                $this->markTestSkipped(\sprintf('tempnam() did not place the file under "%s".', $dir));
            }

            file_put_contents($tmpfile, '<?xml version="1.0" encoding="utf-8"?>
<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <xsd:element name="test" type="testType" />
  <xsd:complexType name="testType"/>
</xsd:schema>');

            $encodedUrl = (new \ReflectionMethod(XliffUtils::class, 'getFileUrl'))->invoke(null, $tmpfile);

            $result = $this->validateSchemaInclude($encodedUrl);
            $this->assertSame([], $result['php_warnings'], 'Encoded URL must not emit a "DOMDocument::schemaValidateSource(): Invalid Schema" PHP warning.');
            $this->assertSame([], $result['libxml_errors'], 'Encoded URL must not produce libxml URI parsing errors when included.');
        } finally {
            if (\is_string($tmpfile)) {
                @unlink($tmpfile);
            }
            @rmdir($dir);
        }
    }

    /**
     * @return array{php_warnings: list<string>, libxml_errors: list<string>}
     */
    private function validateSchemaInclude(string $schemaLocation): array
    {
        $dom = new \DOMDocument();
        $dom->loadXML('<?xml version="1.0"?><test/>');

        $phpWarnings = [];
        set_error_handler(static function (int $errno, string $msg) use (&$phpWarnings): bool {
            if (str_contains($msg, 'Invalid Schema')) {
                $phpWarnings[] = $msg;
            }

            return true;
        }, \E_WARNING);

        $internalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            @$dom->schemaValidateSource('<?xml version="1.0" encoding="utf-8"?>
<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <xsd:include schemaLocation="'.$schemaLocation.'" />
</xsd:schema>');
        } finally {
            $libxmlErrors = array_values(array_filter(
                array_map(static fn (\LibXMLError $e): string => trim($e->message), libxml_get_errors()),
                static fn (string $m): bool => str_contains($m, 'could not build an URI')
                    || str_contains($m, 'xmlSchemaParseIncludeOrRedefine')
            ));

            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
            restore_error_handler();
        }

        return ['php_warnings' => $phpWarnings, 'libxml_errors' => $libxmlErrors];
    }

    public function testValidateSchemaDoesNotResolveExternalEntities()
    {
        $networkLoads = [];
        libxml_set_external_entity_loader(static function (?string $public, string $system, array $context) use (&$networkLoads) {
            if (preg_match('#^(?:https?|ftp)://#i', $system)) {
                $networkLoads[] = $system;

                return null;
            }

            $path = str_starts_with($system, 'file://') ? substr($system, 7) : $system;
            $resolved = '/' === ($path[0] ?? '') ? $path : ($context['directory'] ?? '').'/'.$path;

            return @fopen(rawurldecode(ltrim($resolved, '/')) ? rawurldecode($resolved) : $resolved, 'r') ?: null;
        });

        $internal = libxml_use_internal_errors(true);

        try {
            $dom = new \DOMDocument();
            $dom->loadXML('<?xml version="1.0"?>'
                .'<!DOCTYPE xliff [<!ENTITY xxe SYSTEM "http://127.0.0.1:1/payload.dtd">]>'
                .'<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">'
                .'<file source-language="en" datatype="plaintext" original="file.ext"><body/></file>'
                .'</xliff>', \LIBXML_NONET);

            XliffUtils::validateSchema($dom);
        } finally {
            libxml_set_external_entity_loader(null);
            libxml_clear_errors();
            libxml_use_internal_errors($internal);
        }

        $this->assertSame([], $networkLoads, 'XliffUtils::validateSchema() must not resolve external entities over the network.');
    }
}
