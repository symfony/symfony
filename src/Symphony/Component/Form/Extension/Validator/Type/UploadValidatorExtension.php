<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Extension\Validator\Type;

use Symphony\Component\Form\AbstractTypeExtension;
use Symphony\Component\OptionsResolver\Options;
use Symphony\Component\OptionsResolver\OptionsResolver;
use Symphony\Component\Translation\TranslatorInterface;

/**
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 * @author David Badura <d.a.badura@gmail.com>
 */
class UploadValidatorExtension extends AbstractTypeExtension
{
    private $translator;
    private $translationDomain;

    public function __construct(TranslatorInterface $translator, string $translationDomain = null)
    {
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $translator = $this->translator;
        $translationDomain = $this->translationDomain;
        $resolver->setNormalizer('upload_max_size_message', function (Options $options, $message) use ($translator, $translationDomain) {
            return function () use ($translator, $translationDomain, $message) {
                return $translator->trans(call_user_func($message), array(), $translationDomain);
            };
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return 'Symphony\Component\Form\Extension\Core\Type\FormType';
    }
}
