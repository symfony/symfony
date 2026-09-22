<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Form\Tests\Fixtures\TranslatableTextAlign;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

class ChoiceTypeSortChoicesTest extends TypeTestCase
{
    private Translator $translator;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();

        $this->translator = new Translator('fr');
        $this->translator->addLoader('array', new ArrayLoader());
        $this->translator->addResource('array', [
            'animal.bee' => 'Abeille',
            'animal.bear' => 'ours',
            'animal.zebra' => 'Zèbre',
            'animal.summer' => 'Été',
            'group.wild' => 'Sauvage',
            'group.farm' => 'Ferme',
            'group.pets' => 'Animaux de compagnie',
            'level' => 'Niveau %level%',
            'Left' => 'Gauche',
            'Center' => 'Centre',
            'Right' => 'Droite',
        ], 'fr');
        $this->translator->addResource('array', [
            'animal.bee' => 'Zabeille',
            'animal.bear' => 'Aours',
        ], 'fr', 'animals');

        parent::setUp();
    }

    protected function getExtensions(): array
    {
        return array_merge(parent::getExtensions(), [new CoreExtension(null, null, $this->translator)]);
    }

    public function testChoicesAreNotSortedByDefault()
    {
        $view = $this->factory->create(ChoiceType::class, null, [
            'choices' => $this->getAnimals(),
        ])->createView();

        $this->assertSame(['animal.zebra', 'animal.summer', 'animal.bee', 'animal.bear'], $this->getLabels($view->vars['choices']));
    }

    #[RequiresPhpExtension('intl')]
    public function testChoicesAreSortedByTranslatedLabelWithLocaleCollation()
    {
        $view = $this->factory->create(ChoiceType::class, null, [
            'choices' => $this->getAnimals(),
            'sort_choices' => true,
        ])->createView();

        // A byte comparison would give "Abeille", "Zèbre", "ours", "Été"
        $this->assertSame(['animal.bee', 'animal.summer', 'animal.bear', 'animal.zebra'], $this->getLabels($view->vars['choices']));
    }

    #[RequiresPhpExtension('intl')]
    public function testCollationFollowsTheTranslatorLocale()
    {
        $choices = ['Zorro' => 'z', 'Österreich' => 'o', 'Oslo' => 's'];

        $this->translator->setLocale('de');
        $view = $this->factory->create(ChoiceType::class, null, ['choices' => $choices, 'sort_choices' => true])->createView();
        $this->assertSame(['Oslo', 'Österreich', 'Zorro'], $this->getLabels($view->vars['choices']));

        // In Swedish, "Ö" is a distinct letter sorted after "Z"
        $this->translator->setLocale('sv');
        $view = $this->factory->create(ChoiceType::class, null, ['choices' => $choices, 'sort_choices' => true])->createView();
        $this->assertSame(['Oslo', 'Zorro', 'Österreich'], $this->getLabels($view->vars['choices']));
    }

    #[RequiresPhpExtension('intl')]
    public function testCollationFollowsTheChoiceTranslationLocale()
    {
        $this->translator->setLocale('sv');

        $view = $this->factory->create(CountryType::class, null, [
            'choice_translation_locale' => 'de',
            'choice_filter' => static fn (?string $country): bool => \in_array($country, ['PT', 'AT', 'NZ'], true),
            'sort_choices' => true,
        ])->createView();

        $this->assertSame(['Neuseeland', 'Österreich', 'Portugal'], $this->getLabels($view->vars['choices']));
    }

    #[RequiresPhpExtension('intl')]
    public function testNumbersAreComparedByTheirValue()
    {
        $view = $this->factory->create(ChoiceType::class, null, [
            'choices' => ['Level 10' => 10, 'Level 2' => 2, 'Level 1' => 1],
            'choice_translation_domain' => false,
            'sort_choices' => true,
        ])->createView();

        $this->assertSame(['Level 1', 'Level 2', 'Level 10'], $this->getLabels($view->vars['choices']));
    }

    #[RequiresPhpExtension('intl')]
    public function testCaseDoesNotTakePrecedenceOverLetters()
    {
        $view = $this->factory->create(ChoiceType::class, null, [
            'choices' => ['cherry' => 'c', 'Banana' => 'b', 'apple' => 'a'],
            'choice_translation_domain' => false,
            'sort_choices' => true,
        ])->createView();

        // A byte comparison would put "Banana" first
        $this->assertSame(['apple', 'Banana', 'cherry'], $this->getLabels($view->vars['choices']));
    }

    #[RequiresPhpExtension('intl')]
    public function testChoicesUseTheChoiceTranslationDomain()
    {
        $view = $this->factory->create(ChoiceType::class, null, [
            'choices' => ['animal.bee' => 'bee', 'animal.bear' => 'bear'],
            'choice_translation_domain' => 'animals',
            'sort_choices' => true,
        ])->createView();

        $this->assertSame(['animal.bear', 'animal.bee'], $this->getLabels($view->vars['choices']));
    }

    #[RequiresPhpExtension('intl')]
    public function testChoicesAreTranslatedWithTheirTranslationParameters()
    {
        $view = $this->factory->create(ChoiceType::class, null, [
            'choices' => [10, 2, 1],
            'choice_label' => static fn () => 'level',
            'choice_translation_parameters' => static fn (int $choice) => ['%level%' => $choice],
            'sort_choices' => true,
        ])->createView();

        $this->assertSame(['1', '2', '10'], array_values(array_map(static fn (ChoiceView $choice) => $choice->value, $view->vars['choices'])));
    }

    #[RequiresPhpExtension('intl')]
    public function testSameChoicesAreSortedAgainForAnotherTranslationDomain()
    {
        $choices = ['animal.bee' => 'bee', 'animal.bear' => 'bear'];

        $view = $this->factory->create(ChoiceType::class, null, ['choices' => $choices, 'choice_translation_domain' => 'animals', 'sort_choices' => true])->createView();
        $this->assertSame(['animal.bear', 'animal.bee'], $this->getLabels($view->vars['choices']));

        $view = $this->factory->create(ChoiceType::class, null, ['choices' => $choices, 'sort_choices' => true])->createView();
        $this->assertSame(['animal.bee', 'animal.bear'], $this->getLabels($view->vars['choices']));
    }

    #[RequiresPhpExtension('intl')]
    public function testChoicesSharedByCollectionEntriesAreTranslatedOnce()
    {
        $translator = new class($this->translator) implements TranslatorInterface {
            public int $calls = 0;

            public function __construct(private TranslatorInterface $translator)
            {
            }

            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                ++$this->calls;

                return $this->translator->trans($id, $parameters, $domain, $locale);
            }

            public function getLocale(): string
            {
                return $this->translator->getLocale();
            }
        };
        $factory = Forms::createFormFactoryBuilder()->addExtension(new CoreExtension(null, null, $translator))->getFormFactory();

        $view = $factory->create(CollectionType::class, ['bee', 'bear', 'zebra'], [
            'entry_type' => ChoiceType::class,
            'entry_options' => ['choices' => $this->getAnimals(), 'sort_choices' => true],
        ])->createView();

        $this->assertSame(4, $translator->calls);
        foreach ($view as $entry) {
            $this->assertSame(['animal.bee', 'animal.summer', 'animal.bear', 'animal.zebra'], $this->getLabels($entry->vars['choices']));
        }
    }

    #[RequiresPhpExtension('intl')]
    public function testChoicesUseTheTranslationDomainInheritedFromTheParent()
    {
        $view = $this->factory->createNamedBuilder('parent', FormType::class, null, ['translation_domain' => 'animals'])
            ->add('animal', ChoiceType::class, [
                'choices' => ['animal.bee' => 'bee', 'animal.bear' => 'bear'],
                'sort_choices' => true,
            ])
            ->getForm()
            ->createView();

        $this->assertSame(['animal.bear', 'animal.bee'], $this->getLabels($view['animal']->vars['choices']));
    }

    #[RequiresPhpExtension('intl')]
    public function testChoicesAreNotTranslatedWhenTranslationIsDisabled()
    {
        $view = $this->factory->create(ChoiceType::class, null, [
            'choices' => $this->getAnimals(),
            'choice_translation_domain' => false,
            'sort_choices' => true,
        ])->createView();

        $this->assertSame(['animal.bear', 'animal.bee', 'animal.summer', 'animal.zebra'], $this->getLabels($view->vars['choices']));
    }

    #[RequiresPhpExtension('intl')]
    public function testTranslatableLabelsAreSorted()
    {
        $view = $this->factory->create(EnumType::class, null, [
            'class' => TranslatableTextAlign::class,
            'sort_choices' => true,
        ])->createView();

        $this->assertSame(['Center', 'Right', 'Left'], array_map(static fn (ChoiceView $choice) => $choice->data->name, array_values($view->vars['choices'])));
    }

    public function testSortChoicesMustBeABoolean()
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory->create(ChoiceType::class, null, ['sort_choices' => static fn (): int => 0]);
    }

    public function testSortingChoicesRequiresIntl()
    {
        if (\extension_loaded('intl')) {
            $this->markTestSkipped('This test requires the intl extension to be missing.');
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "sort_choices" option requires the "intl" PHP extension.');

        $this->factory->create(ChoiceType::class, null, ['sort_choices' => true]);
    }

    #[RequiresPhpExtension('intl')]
    public function testPreferredChoicesAreSortedSeparately()
    {
        $view = $this->factory->create(ChoiceType::class, null, [
            'choices' => $this->getAnimals(),
            'preferred_choices' => ['zebra', 'bear'],
            'sort_choices' => true,
        ])->createView();

        $this->assertSame(['animal.bear', 'animal.zebra'], $this->getLabels($view->vars['preferred_choices']));
        $this->assertSame(['animal.bee', 'animal.summer', 'animal.bear', 'animal.zebra'], $this->getLabels($view->vars['choices']));
    }

    #[RequiresPhpExtension('intl')]
    public function testGroupsAndTheirChoicesAreSorted()
    {
        $view = $this->factory->create(ChoiceType::class, null, [
            'choices' => [
                'group.wild' => ['animal.zebra' => 'zebra', 'animal.bear' => 'bear'],
                'group.farm' => ['animal.summer' => 'summer', 'animal.bee' => 'bee'],
            ],
            'sort_choices' => true,
        ])->createView();

        $choices = $view->vars['choices'];

        $this->assertSame(['group.farm', 'group.wild'], array_keys($choices));
        $this->assertInstanceOf(ChoiceGroupView::class, $choices['group.farm']);
        $this->assertSame(['animal.bee', 'animal.summer'], $this->getLabels($choices['group.farm']->choices));
        $this->assertSame(['animal.bear', 'animal.zebra'], $this->getLabels($choices['group.wild']->choices));
    }

    #[RequiresPhpExtension('intl')]
    public function testGroupsAreSortedByTheirTranslatedLabel()
    {
        $view = $this->factory->create(ChoiceType::class, null, [
            'choices' => [
                'group.farm' => ['animal.bee' => 'bee'],
                'group.pets' => ['animal.bear' => 'bear'],
            ],
            'sort_choices' => true,
        ])->createView();

        $this->assertSame(['group.pets', 'group.farm'], array_keys($view->vars['choices']));
    }

    #[RequiresPhpExtension('intl')]
    public function testEmptyChoiceStaysFirst()
    {
        $view = $this->factory->create(ChoiceType::class, null, [
            'choices' => ['animal.zebra' => 'zebra', 'animal.summer' => '', 'animal.bee' => 'bee'],
            'sort_choices' => true,
        ])->createView();

        $this->assertSame(['animal.summer', 'animal.bee', 'animal.zebra'], $this->getLabels($view->vars['choices']));
    }

    #[RequiresPhpExtension('intl')]
    public function testExpandedChildrenFollowTheSortedChoices()
    {
        $view = $this->factory->create(ChoiceType::class, null, [
            'choices' => $this->getAnimals(),
            'expanded' => true,
            'required' => false,
            'placeholder' => 'None',
            'sort_choices' => true,
        ])->createView();

        $this->assertSame(['placeholder', '2', '1', '3', '0'], array_map('strval', array_keys($view->children)));
        $this->assertSame(['None', 'animal.bee', 'animal.summer', 'animal.bear', 'animal.zebra'], array_map(static fn (FormView $child) => $child->vars['label'], array_values($view->children)));
    }

    #[RequiresPhpExtension('intl')]
    public function testExpandedChildrenWithPreferredChoicesAndGroupsFollowTheSortedChoices()
    {
        $view = $this->factory->create(ChoiceType::class, null, [
            'choices' => [
                'group.wild' => ['animal.zebra' => 'zebra', 'animal.bear' => 'bear'],
                'group.farm' => ['animal.summer' => 'summer', 'animal.bee' => 'bee'],
            ],
            'preferred_choices' => ['zebra'],
            'duplicate_preferred_choices' => false,
            'expanded' => true,
            'multiple' => true,
            'sort_choices' => true,
        ])->createView();

        $this->assertSame(['animal.zebra', 'animal.bee', 'animal.summer', 'animal.bear'], array_map(static fn (FormView $child) => $child->vars['label'], array_values($view->children)));
    }

    #[RequiresPhpExtension('intl')]
    public function testSubmittingSortedExpandedChoices()
    {
        $form = $this->factory->create(ChoiceType::class, null, [
            'choices' => $this->getAnimals(),
            'expanded' => true,
            'multiple' => true,
            'sort_choices' => true,
        ]);
        $form->createView();

        $form->submit(['bee', 'zebra']);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame(['zebra', 'bee'], $form->getData());
    }

    private function getAnimals(): array
    {
        return [
            'animal.zebra' => 'zebra',
            'animal.summer' => 'summer',
            'animal.bee' => 'bee',
            'animal.bear' => 'bear',
        ];
    }

    /**
     * @param array<ChoiceView> $choices
     */
    private function getLabels(array $choices): array
    {
        return array_values(array_map(static fn (ChoiceView $choice) => $choice->label, $choices));
    }
}
