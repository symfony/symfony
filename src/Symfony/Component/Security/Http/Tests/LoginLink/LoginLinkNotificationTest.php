<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\LoginLink;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Security\Http\LoginLink\LoginLinkDetails;
use Symfony\Component\Security\Http\LoginLink\LoginLinkNotification;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

class LoginLinkNotificationTest extends TestCase
{
    use ClockSensitiveTrait;

    #[TestWith([600, '10 minutes'])]
    #[TestWith([7200, '2 hours'])]
    public function testSmsMessageTellsWhenTheLinkExpires(int $lifetime, string $expiresIn)
    {
        $now = self::mockTime('2020-01-01 00:00:00 UTC')->now();
        $notification = new LoginLinkNotification(new LoginLinkDetails('https://example.com/login/verify', $now->modify(\sprintf('+%d seconds', $lifetime))), 'Sign in');

        $message = $notification->asSmsMessage(new Recipient('', '+33612345678'));

        $this->assertSame(\sprintf('Click on the link to confirm you want to sign in. This link will expire in %s. https://example.com/login/verify', $expiresIn), $message->getSubject());
    }

    #[TestWith([0, '0 minute'])]
    #[TestWith([59, '0 minute'])]
    #[TestWith([60, '1 minute'])]
    #[TestWith([61, '1 minute'])]
    #[TestWith([120, '2 minutes'])]
    #[TestWith([3599, '59 minutes'])]
    #[TestWith([3600, '1 heure'])]
    #[TestWith([7199, '1 heure'])]
    #[TestWith([7200, '2 heures'])]
    public function testSmsMessageIsTranslated(int $lifetime, string $expiresIn)
    {
        $now = self::mockTime('2020-01-01 00:00:00 UTC')->now();
        $notification = new LoginLinkNotification(new LoginLinkDetails('https://example.com/login/verify', $now->modify(\sprintf('+%d seconds', $lifetime))), 'Sign in', [], $this->createTranslator());

        $message = $notification->asSmsMessage(new Recipient('', '+33612345678'));

        $this->assertSame(\sprintf('Cliquez sur le lien pour vous connecter. Ce lien expirera dans %s. https://example.com/login/verify', $expiresIn), $message->getSubject());
    }

    #[TestWith([60, '1 minute'])]
    #[TestWith([120, '2 minutes'])]
    #[TestWith([3600, '1 heure'])]
    #[TestWith([7200, '2 heures'])]
    public function testEmailMessageIsTranslated(int $lifetime, string $expiresIn)
    {
        $now = self::mockTime('2020-01-01 00:00:00 UTC')->now();
        $notification = new LoginLinkNotification(new LoginLinkDetails('https://example.com/login/verify', $now->modify(\sprintf('+%d seconds', $lifetime))), 'Connexion', [], $this->createTranslator());

        $email = $notification->asEmailMessage(new Recipient('user@example.com'))->getMessage();

        $this->assertSame('Connexion', $email->getSubject());
        $this->assertSame(\sprintf('Cliquez sur le bouton ci-dessous pour vous connecter. Ce lien expirera dans %s.', $expiresIn), $email->getContext()['content']);
        $this->assertSame('Se connecter', $email->getContext()['action_text']);
        $this->assertSame('https://example.com/login/verify', $email->getContext()['action_url']);
    }

    public function testLocaleDoesNotChangeTheTranslatorLocale()
    {
        $now = self::mockTime('2020-01-01 00:00:00 UTC')->now();
        $translator = $this->createTranslator('en');
        $notification = new LoginLinkNotification(new LoginLinkDetails('https://example.com/login/verify', $now->modify('+10 minutes')), 'Sign in', [], $translator, 'fr');

        $email = $notification->asEmailMessage(new Recipient('user@example.com'))->getMessage();
        $sms = $notification->asSmsMessage(new Recipient('', '+33612345678'));

        $this->assertSame('Se connecter', $email->getContext()['action_text']);
        $this->assertStringContainsString('10 minutes', $email->getContext()['content']);
        $this->assertStringStartsWith('Cliquez sur le lien', $sms->getSubject());
        $this->assertSame('en', $translator->getLocale());
    }

    public function testCustomContentAndSubjectAreNotTranslated()
    {
        $now = self::mockTime('2020-01-01 00:00:00 UTC')->now();
        $notification = new LoginLinkNotification(new LoginLinkDetails('https://example.com/login/verify', $now->modify('+10 minutes')), 'Sign in', [], $this->createTranslator());
        $notification->content('My custom content');

        $email = $notification->asEmailMessage(new Recipient('user@example.com'))->getMessage();

        $this->assertSame('Sign in', $email->getSubject());
        $this->assertSame('My custom content', $email->getContext()['content']);
        $this->assertSame('Se connecter', $email->getContext()['action_text']);
    }

    public function testEmailMessageWithoutTranslatorKeepsItsDefaultContent()
    {
        $now = self::mockTime('2020-01-01 00:00:00 UTC')->now();
        $notification = new LoginLinkNotification(new LoginLinkDetails('https://example.com/login/verify', $now->modify('+10 minutes')), 'Sign in');

        $email = $notification->asEmailMessage(new Recipient('user@example.com'))->getMessage();

        $this->assertSame('Click on the button below to confirm you want to sign in. This link will expire in 10 minutes.', $email->getContext()['content']);
        $this->assertSame('Sign in', $email->getContext()['action_text']);
    }

    public function testTranslatorCanBeDecoratedToCustomizeTheAction()
    {
        $translator = new class($this->createTranslator()) implements TranslatorInterface {
            public function __construct(private TranslatorInterface $inner)
            {
            }

            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return 'Sign in' === $id ? 'Accéder à mon compte' : $this->inner->trans($id, $parameters, $domain, $locale);
            }

            public function getLocale(): string
            {
                return $this->inner->getLocale();
            }
        };
        $now = self::mockTime('2020-01-01 00:00:00 UTC')->now();
        $notification = new LoginLinkNotification(new LoginLinkDetails('https://example.com/login/verify', $now->modify('+10 minutes')), 'Connexion', [], $translator);

        $email = $notification->asEmailMessage(new Recipient('user@example.com'))->getMessage();

        $this->assertSame('Accéder à mon compte', $email->getContext()['action_text']);
        $this->assertStringStartsWith('Cliquez sur le bouton', $email->getContext()['content']);
    }

    public function testTranslatorWithoutMessagesUsesEnglishSourceMessages()
    {
        $now = self::mockTime('2020-01-01 00:00:00 UTC')->now();
        $notification = new LoginLinkNotification(new LoginLinkDetails('https://example.com/login/verify', $now->modify('+10 minutes')), 'Sign in', [], new Translator('en'));

        $email = $notification->asEmailMessage(new Recipient('user@example.com'))->getMessage();
        $sms = $notification->asSmsMessage(new Recipient('', '+33612345678'));

        $this->assertSame('Click on the button below to confirm you want to sign in. This link will expire in 10 minutes.', $email->getContext()['content']);
        $this->assertSame('Click on the link to confirm you want to sign in. This link will expire in 10 minutes. https://example.com/login/verify', $sms->getSubject());
    }

    #[TestWith([120, '2 минуты'])]
    #[TestWith([300, '5 минут'])]
    #[TestWith([1260, '21 минута'])]
    public function testSmsMessageUsesTheLocalePluralForms(int $lifetime, string $expiresIn)
    {
        $translator = $this->createTranslator('ru');
        $translator->addResource('array', [
            'Click on the link to confirm you want to sign in. This link will expire in %count% minute.|Click on the link to confirm you want to sign in. This link will expire in %count% minutes.' => 'Ссылка действует %count% минута.|Ссылка действует %count% минуты.|Ссылка действует %count% минут.',
        ], 'ru', 'security');
        $now = self::mockTime('2020-01-01 00:00:00 UTC')->now();
        $notification = new LoginLinkNotification(new LoginLinkDetails('https://example.com/login/verify', $now->modify(\sprintf('+%d seconds', $lifetime))), 'Вход', [], $translator);

        $this->assertSame('Ссылка действует '.$expiresIn.'. https://example.com/login/verify', $notification->asSmsMessage(new Recipient('', '+33612345678'))->getSubject());
    }

    #[RequiresPhpExtension('intl')]
    public function testSmsMessageSupportsIcuTranslations()
    {
        $translator = $this->createTranslator();
        $translator->addResource('array', [
            'Click on the link to confirm you want to sign in. This link will expire in %count% minute.|Click on the link to confirm you want to sign in. This link will expire in %count% minutes.' => '{count, plural, one {Lien valable une minute.} other {Lien valable # minutes.}}',
        ], 'fr', 'security+intl-icu');
        $now = self::mockTime('2020-01-01 00:00:00 UTC')->now();
        $notification = new LoginLinkNotification(new LoginLinkDetails('https://example.com/login/verify', $now->modify('+2 minutes')), 'Connexion', [], $translator);

        $this->assertSame('Lien valable 2 minutes. https://example.com/login/verify', $notification->asSmsMessage(new Recipient('', '+33612345678'))->getSubject());
    }

    public function testGeneratedMessagesCanBeSerializedWithoutTheTranslator()
    {
        $translator = new class implements TranslatorInterface {
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return 'translated';
            }

            public function getLocale(): string
            {
                return 'fr';
            }
        };
        $now = self::mockTime('2020-01-01 00:00:00 UTC')->now();
        $notification = new LoginLinkNotification(new LoginLinkDetails('https://example.com/login/verify', $now->modify('+10 minutes')), 'Connexion', [], $translator);

        $email = unserialize(serialize($notification->asEmailMessage(new Recipient('user@example.com'))))->getMessage();
        $sms = unserialize(serialize($notification->asSmsMessage(new Recipient('', '+33612345678'))));

        $this->assertSame('translated', $email->getContext()['content']);
        $this->assertSame('translated', $email->getContext()['action_text']);
        $this->assertSame('translated https://example.com/login/verify', $sms->getSubject());
    }

    private function createTranslator(string $locale = 'fr'): Translator
    {
        $translator = new Translator($locale);
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'Sign in' => 'Se connecter',
            'Click on the button below to confirm you want to sign in. This link will expire in %count% minute.|Click on the button below to confirm you want to sign in. This link will expire in %count% minutes.' => 'Cliquez sur le bouton ci-dessous pour vous connecter. Ce lien expirera dans %count% minute.|Cliquez sur le bouton ci-dessous pour vous connecter. Ce lien expirera dans %count% minutes.',
            'Click on the button below to confirm you want to sign in. This link will expire in %count% hour.|Click on the button below to confirm you want to sign in. This link will expire in %count% hours.' => 'Cliquez sur le bouton ci-dessous pour vous connecter. Ce lien expirera dans %count% heure.|Cliquez sur le bouton ci-dessous pour vous connecter. Ce lien expirera dans %count% heures.',
            'Click on the link to confirm you want to sign in. This link will expire in %count% minute.|Click on the link to confirm you want to sign in. This link will expire in %count% minutes.' => 'Cliquez sur le lien pour vous connecter. Ce lien expirera dans %count% minute.|Cliquez sur le lien pour vous connecter. Ce lien expirera dans %count% minutes.',
            'Click on the link to confirm you want to sign in. This link will expire in %count% hour.|Click on the link to confirm you want to sign in. This link will expire in %count% hours.' => 'Cliquez sur le lien pour vous connecter. Ce lien expirera dans %count% heure.|Cliquez sur le lien pour vous connecter. Ce lien expirera dans %count% heures.',
        ], 'fr', 'security');

        return $translator;
    }
}
