<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator\Type;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface as LegacyTranslatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 * @author David Badura <d.a.badura@gmail.com>
 */
class UploadValidatorExtension extends AbstractTypeExtension
{
    private $translator;
    private $translationDomain;

    /**
     * @param TranslatorInterface $translator
     */
    public function __construct($translator, string $translationDomain = null)
    {
        if (!$translator instanceof LegacyTranslatorInterface && !$translator instanceof TranslatorInterface) {
            throw new \TypeError(sprintf('Argument 1 passed to %s() must be an instance of %s, %s given.', __METHOD__, TranslatorInterface::class, \is_object($translator) ? \get_class($translator) : \gettype($translator)));
        }
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
                return $translator->trans(\call_user_func($message), array(), $translationDomain);
            };
        });
    }

    /**
     * {@inheritdoc}
     */
    public static function getExtendedTypes(): iterable
    {
        return array(FormType::class);
    }
}
