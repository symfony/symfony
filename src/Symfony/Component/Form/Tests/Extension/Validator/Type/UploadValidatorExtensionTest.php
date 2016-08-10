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
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\Options;

class UploadValidatorExtensionTest extends TypeTestCase
{
    public function testPostMaxSizeTranslation()
    {
        $translator = $this->getMock('Symfony\Component\Translation\TranslatorInterface');

        $translator->expects($this->any())
            ->method('trans')
            ->with($this->equalTo('old max {{ max }}!'))
            ->willReturn('translated max {{ max }}!');

        $extension = new UploadValidatorExtension($translator);

        $resolver = new OptionsResolver();
        $resolver->setDefault('post_max_size_message', 'old max {{ max }}!');
        $resolver->setDefault('upload_max_size_message', function (Options $options, $message) {
            return function () use ($options) {
                return $options['post_max_size_message'];
            };
        });

        $extension->configureOptions($resolver);
        $options = $resolver->resolve();

        $this->assertEquals('translated max {{ max }}!', call_user_func($options['upload_max_size_message']));
    }
}
