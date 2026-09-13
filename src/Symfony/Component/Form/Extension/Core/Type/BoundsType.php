<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Renders a lower and an upper bound of the same inner type.
 *
 * @author Sébastien Jean <sebastien.jean76@gmail.com>
 */
class BoundsType extends AbstractType
{
    public function __construct(
        private ?TranslatorInterface $translator = null,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fromOptions = $toOptions = [
            'invalid_message' => $options['invalid_message'],
            'invalid_message_parameters' => $options['invalid_message_parameters'],
        ];

        // when the form is compound the entries of the array are ignored in favor of children
        // data, so we need to handle the cascade setting here. A bound the parent says
        // nothing about keeps the empty data of the inner type. An empty data that is not
        // an array describes the range as a whole, the way the one FormType derives from
        // "data_class" does, and a lazy empty data per bound goes through
        // "from_options" / "to_options".
        $emptyData = $builder->getEmptyData();

        if (\is_array($emptyData)) {
            if (isset($emptyData['from'])) {
                $fromOptions['empty_data'] = $emptyData['from'];
            }
            if (isset($emptyData['to'])) {
                $toOptions['empty_data'] = $emptyData['to'];
            }
        }

        // Append generic carry-along options. A bound that bubbles its errors moves them to
        // the range, which "bounds_row" does not render, so error_bubbling goes down too.
        foreach (['required', 'translation_domain', 'error_bubbling'] as $passOpt) {
            $fromOptions[$passOpt] = $toOptions[$passOpt] = $options[$passOpt];
        }

        $builder
            ->add('from', $options['type'], array_merge($fromOptions, $options['options'], $options['from_options']))
            ->add('to', $options['type'], array_merge($toOptions, $options['options'], $options['to_options']))
            ->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event): void {
                if (null === $event->getData()) {
                    return;
                }

                foreach (['from', 'to'] as $name) {
                    if (!self::isEmptyBound($event->getForm()->get($name)->getData())) {
                        return;
                    }
                }

                $event->setData(null);
            })
        ;

        if (false !== $options['compare']) {
            $compare = true === $options['compare'] ? self::compare(...) : $options['compare'];
            $messageTemplate = $options['compare_message'];
            $translator = $this->translator;

            $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event) use ($compare, $messageTemplate, $translator): void {
                $from = $event->getForm()->get('from');
                $to = $event->getForm()->get('to');

                // the normalized data, not the model one: it is the shape the inner
                // type reasons in, so a DateType is ordered as a date whatever its
                // "input" option turns it into
                $lower = $from->getNormData();
                $upper = $to->getNormData();

                // a half-open range has nothing to order, and a bound that failed
                // to transform already reports an error of its own
                if (self::isEmptyBound($lower) || self::isEmptyBound($upper)) {
                    return;
                }

                if (0 >= $compare($lower, $upper)) {
                    return;
                }

                $message = $translator?->trans($messageTemplate, [], 'validators') ?? $messageTemplate;

                // report it on the lower bound, the one that has to move to make the range valid
                $from->addError(new FormError($message, $messageTemplate));
            });
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'type' => TextType::class,
            'options' => [],
            'from_options' => [],
            'to_options' => [],
            'compare' => false,
            'compare_message' => 'The lower bound must not be greater than the upper bound.',
            'error_bubbling' => false,
            'invalid_message' => 'Please enter a valid range.',
        ]);

        $resolver->setAllowedTypes('type', 'string');
        $resolver->setAllowedTypes('options', 'array');
        $resolver->setAllowedTypes('from_options', 'array');
        $resolver->setAllowedTypes('to_options', 'array');
        $resolver->setAllowedTypes('compare', ['bool', 'callable']);
        $resolver->setAllowedTypes('compare_message', 'string');
        $resolver->setAllowedValues('compound', true);

        $resolver->setInfo('type', 'The form type rendered for both bounds.');
        $resolver->setInfo('options', 'The options passed to both bounds.');
        $resolver->setInfo('from_options', 'The options passed to the lower bound only. Merged over "options".');
        $resolver->setInfo('to_options', 'The options passed to the upper bound only. Merged over "options".');
        $resolver->setInfo('compare', 'Whether to check that the lower bound is not greater than the upper one. The bounds are compared in their normalized form: scalars, DateTimeInterface and enums are ordered natively, enums following their declaration order. Pass a callable returning an integer less than, equal to or greater than zero for any other inner type.');
        $resolver->setInfo('compare_message', 'The message reported on the lower bound when the bounds are out of order.');
    }

    public function getBlockPrefix(): string
    {
        return 'bounds';
    }

    private static function isEmptyBound(mixed $bound): bool
    {
        return null === $bound || '' === $bound || [] === $bound;
    }

    private static function compare(mixed $from, mixed $to): int
    {
        if ($from instanceof \DateTimeInterface && $to instanceof \DateTimeInterface) {
            return $from <=> $to;
        }

        // enums are ordered the way they are declared, which is the order the
        // inner type lists them in; this covers pure enums as well as backed ones
        if ($from instanceof \UnitEnum && $to instanceof \UnitEnum && $from::class === $to::class) {
            $cases = $from::cases();

            return array_search($from, $cases, true) <=> array_search($to, $cases, true);
        }

        foreach ([$from, $to] as $bound) {
            if (!\is_scalar($bound)) {
                throw new LogicException(\sprintf('The "compare" option cannot order bounds of type "%s", pass a callable comparing them instead.', get_debug_type($bound)));
            }
        }

        return $from <=> $to;
    }
}
