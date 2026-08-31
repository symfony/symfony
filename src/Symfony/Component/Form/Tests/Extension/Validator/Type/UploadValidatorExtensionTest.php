<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Validator\Type;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Validator\Type\UploadValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UploadValidatorExtensionTest extends TypeTestCase
{
    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();

        parent::setUp();
    }

    public function testPostMaxSizeTranslation()
    {
        $extension = new UploadValidatorExtension(new DummyTranslator());

        $resolver = new OptionsResolver();
        $resolver->setDefault('post_max_size_message', 'old max {{ max }}!');
        $resolver->setDefault('upload_max_size_message', static function (Options $options) {
            $postMaxSizeMessage = $options['post_max_size_message'];

            return static fn () => $postMaxSizeMessage;
        });

        $extension->configureOptions($resolver);
        $options = $resolver->resolve();

        $this->assertEquals('translated max {{ max }}!', $options['upload_max_size_message']());
    }

    public function testPostMaxSizeTranslationIsDeferred()
    {
        $translator = new DummyTranslator();
        $extension = new UploadValidatorExtension($translator);

        $resolver = new OptionsResolver();
        $resolver->setDefault('post_max_size_message', 'old max {{ max }}!');
        $resolver->setDefault('upload_max_size_message', static function (Options $options) {
            $postMaxSizeMessage = $options['post_max_size_message'];

            return static fn () => $postMaxSizeMessage;
        });

        $extension->configureOptions($resolver);
        $options = $resolver->resolve();

        $this->assertSame(0, $translator->transCalls, 'The message must not be translated while resolving options.');

        $options['upload_max_size_message']();

        $this->assertSame(1, $translator->transCalls);
    }
}

class DummyTranslator implements TranslatorInterface, LocaleAwareInterface
{
    public int $transCalls = 0;

    public function trans($id, array $parameters = [], $domain = null, $locale = null): string
    {
        ++$this->transCalls;

        return 'translated max {{ max }}!';
    }

    public function setLocale($locale): void
    {
    }

    public function getLocale(): string
    {
        return 'en';
    }
}
