<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Translation\Command\TranslationLintCommand;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

final class TranslationLintCommandTest extends TestCase
{
    /**
     * @requires extension intl
     */
    public function testLintCorrectTranslations()
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['hello' => 'Hello!'], 'en', 'messages');
        $translator->addResource('array', [
            'hello_name' => 'Hello {name}!',
            'num_of_apples' => <<<ICU
                {apples, plural,
                    =0    {There are no apples}
                    =1    {There is one apple...}
                    other {There are # apples!}
                }
            ICU,
        ], 'en', 'messages+intl-icu');
        $translator->addResource('array', ['hello' => 'Bonjour !'], 'fr', 'messages');
        $translator->addResource('array', [
            'hello_name' => 'Bonjour {name} !',
            'num_of_apples' => <<<ICU
                {apples, plural,
                    =0    {Il n'y a pas de pommes}
                    =1    {Il y a une pomme}
                    other {Il y a # pommes !}
                }
            ICU,
        ], 'fr', 'messages+intl-icu');

        $command = $this->createCommand($translator, ['en', 'fr']);
        $commandTester = new CommandTester($command);

        $commandTester->execute([], ['decorated' => false]);

        $commandTester->assertCommandIsSuccessful();

        $display = $this->getNormalizedDisplay($commandTester);
        $this->assertStringContainsString('[OK] All translations are valid.', $display);
    }

    /**
     * @requires extension intl
     */
    public function testLintMalformedIcuTranslations()
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['hello' => 'Hello!'], 'en', 'messages');
        $translator->addResource('array', [
            'hello_name' => 'Hello {name}!',
            // Missing "other" case
            'num_of_apples' => <<<ICU
                {apples, plural,
                    =0    {There are no apples}
                    =1    {There is one apple...}
                }
            ICU,
        ], 'en', 'messages+intl-icu');
        $translator->addResource('array', ['hello' => 'Bonjour !'], 'fr', 'messages');
        $translator->addResource('array', [
            // Missing "}"
            'hello_name' => 'Bonjour {name !',
            // "other" is translated
            'num_of_apples' => <<<ICU
                {apples, plural,
                    =0    {Il n'y a pas de pommes}
                    =1    {Il y a une pomme}
                    autre {Il y a # pommes !}
                }
            ICU,
        ], 'fr', 'messages+intl-icu');

        $command = $this->createCommand($translator, ['en', 'fr']);
        $commandTester = new CommandTester($command);

        $this->assertSame(1, $commandTester->execute([], ['decorated' => false]));

        $display = $this->getNormalizedDisplay($commandTester);
        $this->assertStringContainsString(<<<EOF
 -------- ---------- --------
  Locale   Domains    Valid?
 -------- ---------- --------
  en       messages   No
  fr       messages   No
 -------- ---------- --------
EOF, $display);
        $this->assertStringContainsString(<<<EOF
Errors for locale "en" and domain "messages"
--------------------------------------------

 Translation key "num_of_apples" is invalid:

 [ERROR] Invalid message format (error #65807): msgfmt_create: message formatter creation failed:
         U_DEFAULT_KEYWORD_MISSING
EOF, $display);
        $this->assertStringContainsString(<<<EOF
Errors for locale "fr" and domain "messages"
--------------------------------------------

 Translation key "hello_name" is invalid:

 [ERROR] Invalid message format (error #65799): pattern syntax error (parse error at offset 9, after "Bonjour {", before
         or at "name !"): U_PATTERN_SYNTAX_ERROR

 Translation key "num_of_apples" is invalid:

 [ERROR] Invalid message format (error #65807): msgfmt_create: message formatter creation failed:
         U_DEFAULT_KEYWORD_MISSING
EOF, $display);
    }

    private function createCommand(Translator $translator, array $enabledLocales): Command
    {
        $command = new TranslationLintCommand($translator, $enabledLocales);

        $application = new Application();
        $application->add($command);

        return $command;
    }

    /**
     * Normalize the CommandTester display, by removing trailing spaces for each line.
     */
    private function getNormalizedDisplay(CommandTester $commandTester): string
    {
        return implode("\n", array_map(rtrim(...), explode("\n", $commandTester->getDisplay(true))));
    }
}
