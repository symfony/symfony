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
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 * @author David Badura <d.a.badura@gmail.com>
 */
class UploadValidatorExtension extends AbstractTypeExtension
{
    public function __construct(
        private TranslatorInterface $translator,
        private ?string $translationDomain = null,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $translator = $this->translator;
        $translationDomain = $this->translationDomain;
        $resolver->setNormalizer('upload_max_size_message', static fn (Options $options, $message) => static fn () => $translator->trans($message(), [], $translationDomain));
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
