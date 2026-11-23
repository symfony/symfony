<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Resources;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\Util\XliffUtils;

class TranslationFilesTest extends TestCase
{
    #[DataProvider('provideTranslationFiles')]
    public function testTranslationFileIsValid($filePath)
    {
        $document = new \DOMDocument();
        $document->loadXML(file_get_contents($filePath));

        $errors = XliffUtils::validateSchema($document);

        $this->assertCount(0, $errors, \sprintf('"%s" is invalid:%s', $filePath, \PHP_EOL.implode(\PHP_EOL, array_column($errors, 'message'))));
    }

    #[DataProvider('provideTranslationFiles')]
    public function testTranslationFileIsValidWithoutEntityLoader($filePath)
    {
        $document = new \DOMDocument();
        $document->loadXML(file_get_contents($filePath));

        $errors = XliffUtils::validateSchema($document);

        $this->assertCount(0, $errors, \sprintf('"%s" is invalid:%s', $filePath, \PHP_EOL.implode(\PHP_EOL, array_column($errors, 'message'))));
    }

    public static function provideTranslationFiles()
    {
        return array_map(
            static fn ($filePath) => (array) $filePath,
            glob(\dirname(__DIR__, 2).'/Resources/translations/*.xlf')
        );
    }

    public function testNorwegianAlias()
    {
        $this->assertFileEquals(
            \dirname(__DIR__, 2).'/Resources/translations/validators.nb.xlf',
            \dirname(__DIR__, 2).'/Resources/translations/validators.no.xlf',
            'The NO locale should be an alias for the NB variant of the Norwegian language.'
        );
    }

    public function testTargetsOnlyUseTheirSourcePlaceholders()
    {
        $invalidUnits = [];

        foreach (glob(\dirname(__DIR__, 2).'/Resources/translations/*.xlf') as $filePath) {
            $document = new \DOMDocument();
            $document->load($filePath);

            foreach ($document->getElementsByTagName('trans-unit') as $unit) {
                preg_match_all('/\{\{ \w++ \}\}/', $unit->getElementsByTagName('source')->item(0)->textContent, $placeholders);
                $target = str_replace($placeholders[0], '', $unit->getElementsByTagName('target')->item(0)->textContent);

                if (str_contains($target, '{{') || str_contains($target, '}}')) {
                    $invalidUnits[] = basename($filePath).' #'.$unit->getAttribute('id');
                }
            }
        }

        $this->assertSame([], $invalidUnits);
    }

    public function testSingleFormLocalesDisplayTheCountOfPluralMessages()
    {
        $invalidUnits = [];

        foreach (glob(\dirname(__DIR__, 2).'/Resources/translations/*.xlf') as $filePath) {
            $translator = new IdentityTranslator();
            $translator->setLocale(substr(basename($filePath), \strlen('validators.'), -\strlen('.xlf')));

            // locales with a single plural form only display the first form of a message
            if ('a' !== $translator->trans('a|b|c|d|e|f', ['%count%' => 2])) {
                continue;
            }

            $document = new \DOMDocument();
            $document->load($filePath);

            foreach ($document->getElementsByTagName('trans-unit') as $unit) {
                $sourceForms = explode('|', $unit->getElementsByTagName('source')->item(0)->textContent);

                if (1 === \count($sourceForms)) {
                    continue;
                }

                $firstTargetForm = explode('|', $unit->getElementsByTagName('target')->item(0)->textContent)[0];
                preg_match_all('/\{\{ \w++ \}\}/', end($sourceForms), $placeholders);

                foreach ($placeholders[0] as $placeholder) {
                    if (!str_contains($firstTargetForm, $placeholder)) {
                        $invalidUnits[] = basename($filePath).' #'.$unit->getAttribute('id');
                        break;
                    }
                }
            }
        }

        $this->assertSame([], $invalidUnits);
    }
}
