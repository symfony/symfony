<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Debug\Section;

use Symfony\Bundle\FrameworkBundle\Debug\DebugItem;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Symfony\Component\Form\Console\Helper\DescriptorHelper;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Symfony\Component\Form\FormTypeInterface;

/**
 * The "Form" tab of the interactive "debug" command.
 *
 * Reuses the same data sources ({@see FormRegistryInterface} plus the type,
 * extension and guesser lists injected by the FormPass) and the same descriptor
 * ({@see DescriptorHelper}) as the "debug:form" command, so the detail pane shows
 * exactly what "debug:form <class>" already produces.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @experimental
 */
final class FormDebugSection extends AbstractDebugSection
{
    private const string GROUP_CORE_TYPES = 'Core Types';
    private const string GROUP_SERVICE_TYPES = 'Service Types';
    private const string GROUP_EXTENSIONS = 'Type Extensions';
    private const string GROUP_GUESSERS = 'Type Guessers';

    /**
     * @param list<string> $types
     * @param list<string> $extensions
     * @param list<string> $guessers
     */
    public function __construct(
        private readonly FormRegistryInterface $formRegistry,
        private readonly array $types = [],
        private readonly array $extensions = [],
        private readonly array $guessers = [],
        private readonly ?FileLinkFormatter $fileLinkFormatter = null,
    ) {
    }

    public function getLabel(): string
    {
        return 'Form';
    }

    public function getShortLabel(): string
    {
        return 'Form';
    }

    public function describe(DebugItem $item, int $width): string
    {
        $buffer = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $io = new SymfonyStyle(new ArrayInput([]), $buffer);

        if ('type' === $item->type) {
            $resolvedType = $this->formRegistry->getType($item->value);

            (new DescriptorHelper($this->fileLinkFormatter))->describe($io, $resolvedType, [
                'format' => 'txt',
                'show_deprecated' => false,
            ]);

            return $buffer->fetch();
        }

        $io->title($item->value);

        if ('extension' === $item->type) {
            $io->text('Form type extension.');

            if (is_subclass_of($item->value, FormTypeExtensionInterface::class)) {
                $extendedTypes = [];
                foreach ($item->value::getExtendedTypes() as $extendedType) {
                    $extendedTypes[] = $extendedType;
                }

                if ($extendedTypes) {
                    $io->section('Extended types');
                    $io->listing($extendedTypes);
                }
            }
        } else {
            $io->text('Form type guesser.');
        }

        return $buffer->fetch();
    }

    protected function buildItems(): array
    {
        $coreTypes = $this->getCoreTypes();
        $serviceTypes = array_values(array_diff($this->types, $coreTypes));
        sort($serviceTypes);

        $extensions = $this->extensions;
        sort($extensions);

        $guessers = $this->guessers;
        sort($guessers);

        $items = [];
        foreach ($serviceTypes as $class) {
            $items[] = new DebugItem('type', $class, $class, self::GROUP_SERVICE_TYPES);
        }
        foreach ($coreTypes as $class) {
            $items[] = new DebugItem('type', $class, $class, self::GROUP_CORE_TYPES);
        }
        foreach ($extensions as $class) {
            $items[] = new DebugItem('extension', $class, $class, self::GROUP_EXTENSIONS);
        }
        foreach ($guessers as $class) {
            $items[] = new DebugItem('guesser', $class, $class, self::GROUP_GUESSERS);
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private function getCoreTypes(): array
    {
        $coreExtension = new CoreExtension();
        $loadTypesRefMethod = (new \ReflectionObject($coreExtension))->getMethod('loadTypes');
        $coreTypes = $loadTypesRefMethod->invoke($coreExtension);
        $coreTypes = array_map(static fn (FormTypeInterface $type): string => $type::class, $coreTypes);
        sort($coreTypes);

        return $coreTypes;
    }
}
