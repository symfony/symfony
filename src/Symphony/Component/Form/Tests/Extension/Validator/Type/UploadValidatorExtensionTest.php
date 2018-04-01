<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests\Extension\Validator\Type;

use Symphony\Component\Form\Extension\Validator\Type\UploadValidatorExtension;
use Symphony\Component\Form\Test\TypeTestCase;
use Symphony\Component\OptionsResolver\OptionsResolver;
use Symphony\Component\OptionsResolver\Options;

class UploadValidatorExtensionTest extends TypeTestCase
{
    public function testPostMaxSizeTranslation()
    {
        $translator = $this->getMockBuilder('Symphony\Component\Translation\TranslatorInterface')->getMock();

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
