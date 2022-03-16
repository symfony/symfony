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

use Symfony\Component\Form\Extension\Validator\Type\UploadValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UploadValidatorExtensionTest extends TypeTestCase
{
    public function testPostMaxSizeTranslation()
    {
        $extension = new UploadValidatorExtension(new DummyTranslator());

        $resolver = new OptionsResolver();
        $resolver->setDefault('post_max_size_message', 'old max {{ max }}!');
        $resolver->setDefault('upload_max_size_message', function (Options $options) {
            return function () use ($options) {
                return $options['post_max_size_message'];
            };
        });

        $extension->configureOptions($resolver);
        $options = $resolver->resolve();

        $this->assertEquals('translated max {{ max }}!', $options['upload_max_size_message']());
    }
}

class DummyTranslator implements TranslatorInterface, LocaleAwareInterface
{
    public function trans($id, array $parameters = [], $domain = null, $locale = null): string
    {
        return 'translated max {{ max }}!';
    }

    public function setLocale($locale)
    {
    }

    public function getLocale(): string
    {
        return 'en';
    }
}
