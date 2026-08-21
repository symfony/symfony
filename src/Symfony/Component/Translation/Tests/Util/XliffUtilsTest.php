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

    public function testSchemaValidationInsideAPharDoesNotLeakTemporaryFiles()
    {
        if (!\extension_loaded('phar')) {
            $this->markTestSkipped('The "phar" extension is not loaded.');
        }

        $workspace = sys_get_temp_dir().\DIRECTORY_SEPARATOR.uniqid('symfony_xliff_phar_', false);
        $tmpDir = $workspace.\DIRECTORY_SEPARATOR.'tmp';

        if (!@mkdir($tmpDir, 0o777, true) && !is_dir($tmpDir)) {
            $this->markTestSkipped(\sprintf('Could not create tmp dir "%s".', $tmpDir));
        }

        $code = <<<'EOPHP'
            <?php

            [, $componentDir, $workspace] = $argv;

            if (!Phar::canWrite()) {
                echo 'SKIP';
                exit(0);
            }

            $phar = new Phar($workspace.'/translation.phar');
            $phar->addFile($componentDir.'/Util/XliffUtils.php', 'Util/XliffUtils.php');
            $phar->addFile($componentDir.'/Resources/schemas/xliff-core-1.2-transitional.xsd', 'Resources/schemas/xliff-core-1.2-transitional.xsd');
            $phar->addFile($componentDir.'/Resources/schemas/xml.xsd', 'Resources/schemas/xml.xsd');
            $phar->setStub('<?php __HALT_COMPILER();');
            unset($phar);

            require 'phar://'.$workspace.'/translation.phar/Util/XliffUtils.php';

            $xliff = '<?xml version="1.0"?><xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2"><file source-language="en" datatype="plaintext" original="file.ext"><body><trans-unit id="1"><source>foo</source><target>bar</target></trans-unit></body></file></xliff>';

            $validateAndCountTempFiles = function () use ($xliff) {
                $dom = new DOMDocument();
                $dom->loadXML($xliff);

                if ([] !== $errors = Symfony\Component\Translation\Util\XliffUtils::validateSchema($dom)) {
                    echo 'KO ', json_encode($errors);
                    exit(1);
                }

                return count(glob(sys_get_temp_dir().'/symfony*'));
            };

            $afterOne = $validateAndCountTempFiles();

            for ($i = 0; $i < 4; ++$i) {
                $afterFive = $validateAndCountTempFiles();
            }

            echo 'OK ', $afterOne, ' ', $afterFive;
            EOPHP;

        $command = [\PHP_BINARY, '-d', 'phar.readonly=0', '-d', 'sys_temp_dir="'.$tmpDir.'"', '--', \dirname(__DIR__, 2), $workspace];

        if (!\is_resource($process = @proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes))) {
            $this->markTestSkipped('Could not start a PHP subprocess.');
        }

        try {
            fwrite($pipes[0], $code);
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $this->assertSame(0, proc_close($process), $output.$error);

            if ('SKIP' === $output) {
                $this->markTestSkipped('Phar archives cannot be created.');
            }

            [$status, $afterOne, $afterFive] = explode(' ', $output) + [null, null, null];
            $this->assertSame('OK', $status, $output.$error);
            $this->assertSame($afterOne, $afterFive, 'Validating more files must not copy the schema again.');
            $this->assertSame([], glob($tmpDir.'/symfony*'), 'Temporary files must be removed when the process ends.');
        } finally {
            foreach (glob($tmpDir.'/*') as $file) {
                @unlink($file);
            }
            @unlink($workspace.'/translation.phar');
            @rmdir($tmpDir);
            @rmdir($workspace);
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
