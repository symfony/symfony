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

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Translation\Command\TranslationLintCommand;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

final class TranslationLintCommandTest extends TestCase
{
    #[RequiresPhpExtension('intl')]
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

    #[RequiresPhpExtension('intl')]
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
            EOF,
            $display
        );
        $this->assertStringContainsString(\sprintf(<<<EOF
            Errors for locale "en" and domain "messages"
            --------------------------------------------

             Translation key "num_of_apples" is invalid:

             [ERROR] Invalid message format (error #65807): %s: message formatter creation failed:
                     U_DEFAULT_KEYWORD_MISSING
            EOF,
            \PHP_VERSION_ID >= 80500 ? 'MessageFormatter::__construct()' : 'msgfmt_create'
        ), $display);

        if (\PHP_VERSION_ID >= 80500) {
            $this->assertStringContainsString(<<<EOF
                Errors for locale "fr" and domain "messages"
                --------------------------------------------

                 Translation key "hello_name" is invalid:

                 [ERROR] Invalid message format (error #65799): MessageFormatter::__construct(): pattern syntax error (parse error at
                         offset 9, after "Bonjour {", before or at "name !"): U_PATTERN_SYNTAX_ERROR

                 Translation key "num_of_apples" is invalid:

                 [ERROR] Invalid message format (error #65807): MessageFormatter::__construct(): message formatter creation failed:
                         U_DEFAULT_KEYWORD_MISSING
                EOF,
                $display
            );
        } else {
            $this->assertStringContainsString(<<<EOF
                Errors for locale "fr" and domain "messages"
                --------------------------------------------

                 Translation key "hello_name" is invalid:

                 [ERROR] Invalid message format (error #65799): pattern syntax error (parse error at offset 9, after "Bonjour {", before
                         or at "name !"): U_PATTERN_SYNTAX_ERROR

                 Translation key "num_of_apples" is invalid:

                 [ERROR] Invalid message format (error #65807): msgfmt_create: message formatter creation failed:
                         U_DEFAULT_KEYWORD_MISSING
                EOF,
                $display
            );
        }
    }

    #[RequiresPhpExtension('intl')]
    public function testLintMalformedTranslationsWithMarkupInKey()
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            '<comment>invalid_key</comment>' => '{unclosed',
        ], 'en', 'messages+intl-icu');

        $command = $this->createCommand($translator, ['en']);
        $commandTester = new CommandTester($command);

        $this->assertSame(1, $commandTester->execute([], ['decorated' => false]));

        $display = $this->getNormalizedDisplay($commandTester);
        $this->assertStringContainsString('Translation key "<comment>invalid_key</comment>" is invalid:', $display);
    }

    #[RequiresPhpExtension('intl')]
    public function testLintMixedAlphabets()
    {
        $command = $this->createCommand($this->createTranslatorWithMixedAlphabets(), ['uk', 'el', 'mn', 'hy', 'ja', 'zh', 'ko']);
        $commandTester = new CommandTester($command);

        $this->assertSame(1, $commandTester->execute(['--check-mixed-alphabets' => true], ['decorated' => false]));

        $display = $this->getNormalizedDisplay($commandTester);
        $this->assertStringContainsString(<<<EOF
            Errors for locale "uk" and domain "security"
            --------------------------------------------

             Translation key "Cookie has already been used by someone else." is invalid:

             [ERROR] The word "сookie" mixes letters from different alphabets: Cyrillic "с", Latin "ookie".

            Errors for locale "el" and domain "validators"
            ----------------------------------------------

             Translation key "This collection should contain exactly {{ limit }} element." is invalid:

             [ERROR] The word "στοιχείo" mixes letters from different alphabets: Greek "στοιχεί", Latin "o".

            Errors for locale "mn" and domain "validators"
            ----------------------------------------------

             Translation key "This value should be equal to {{ compared_value }}." is invalid:

             [ERROR] The word "тaй" mixes letters from different alphabets: Cyrillic "тй", Latin "a".

            Errors for locale "hy" and domain "messages"
            --------------------------------------------

             Translation key "Sign in with Google" is invalid:

             [ERROR] The word "Gօօgle" mixes letters from different alphabets: Latin "Ggle", Armenian "օօ".
            EOF,
            $display
        );
        $this->assertStringNotContainsString('Errors for locale "ja"', $display);
        $this->assertStringNotContainsString('Errors for locale "zh"', $display);
        $this->assertStringNotContainsString('Errors for locale "ko"', $display);
    }

    #[RequiresPhpExtension('intl')]
    public function testMixedAlphabetsAreNotCheckedByDefault()
    {
        $command = $this->createCommand($this->createTranslatorWithMixedAlphabets(), ['uk', 'el', 'mn', 'hy', 'ja', 'zh', 'ko']);
        $commandTester = new CommandTester($command);

        $commandTester->execute([], ['decorated' => false]);

        $commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('[OK] All translations are valid.', $this->getNormalizedDisplay($commandTester));
    }

    #[RequiresPhpExtension('intl')]
    public function testMixedAlphabetsIgnorePlaceholdersAndMarkup()
    {
        $translator = new Translator('uk');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['cookies' => '{сount, plural, one {сookie} other {# сookies}}'], 'uk', 'messages+intl-icu');
        $translator->addResource('array', ['files' => '<a href="/сookies">%сount% файлів</a><br>files'], 'uk', 'messages');

        $command = $this->createCommand($translator, ['uk']);
        $commandTester = new CommandTester($command);

        $this->assertSame(1, $commandTester->execute(['--check-mixed-alphabets' => true], ['decorated' => false]));

        $display = $this->getNormalizedDisplay($commandTester);
        $this->assertStringContainsString(<<<EOF
             Translation key "cookies" is invalid:

             [ERROR] The word "сookie" mixes letters from different alphabets: Cyrillic "с", Latin "ookie".

             [ERROR] The word "сookies" mixes letters from different alphabets: Cyrillic "с", Latin "ookies".
            EOF,
            $display
        );
        $this->assertStringNotContainsString('"files"', $display);
        $this->assertStringNotContainsString('сount', $display);
    }

    private function createTranslatorWithMixedAlphabets(): Translator
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['Cookie has already been used by someone else.' => 'Хтось інший вже використав цей сookie.'], 'uk', 'security');
        $translator->addResource('array', ['This collection should contain exactly {{ limit }} element.' => 'Αυτή η συλλογή πρέπει να περιέχει ακριβώς {{ limit }} στοιχείo.'], 'el', 'validators');
        $translator->addResource('array', ['This value should be equal to {{ compared_value }}.' => 'Энэ утга {{ compared_value }} -тaй тэнцүү байх ёстой.'], 'mn', 'validators');
        $translator->addResource('array', ['Sign in with Google' => 'Մուտք գործել Gօօgle-ով'], 'hy', 'messages');
        $translator->addResource('array', ['I bought a T-shirt.' => 'Tシャツを買いました。'], 'ja', 'messages');
        $translator->addResource('array', ['Enter your PIN.' => '请输入PIN码。'], 'zh', 'messages');
        $translator->addResource('array', ['Enter your PIN.' => 'PIN을 입력하세요.'], 'ko', 'messages');

        return $translator;
    }

    private function createCommand(Translator $translator, array $enabledLocales): Command
    {
        $command = new TranslationLintCommand($translator, $enabledLocales);

        $application = new Application();
        $application->addCommand($command);

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
