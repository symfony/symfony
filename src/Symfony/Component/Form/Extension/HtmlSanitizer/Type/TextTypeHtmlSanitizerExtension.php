<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\HtmlSanitizer\Type;

use Psr\Container\ContainerInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class TextTypeHtmlSanitizerExtension extends AbstractTypeExtension
{
    public function __construct(
        private ContainerInterface $sanitizers,
        private string $defaultSanitizer = 'default',
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [TextType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(['sanitize_html' => false, 'sanitizer' => null])
            ->setAllowedTypes('sanitize_html', 'bool')
            ->setAllowedTypes('sanitizer', ['string', 'null'])
        ;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$options['sanitize_html']) {
            return;
        }

        $sanitizers = $this->sanitizers;
        $sanitizer = $options['sanitizer'] ?? $this->defaultSanitizer;

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            static function (FormEvent $event) use ($sanitizers, $sanitizer) {
                if (\is_scalar($data = $event->getData()) && '' !== trim($data)) {
                    $event->setData($sanitizers->get($sanitizer)->sanitize($data));
                }
            },
            10000 /* as soon as possible */
        );
    }
}
