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

use Symfony\Bundle\FrameworkBundle\Command\BuildDebugContainerTrait;
use Symfony\Bundle\FrameworkBundle\Console\Helper\DescriptorHelper;
use Symfony\Bundle\FrameworkBundle\Debug\DebugItem;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The "Container" tab of the interactive "debug" command.
 *
 * Reuses the same data source ({@see BuildDebugContainerTrait}) and the same
 * descriptors ({@see DescriptorHelper}) as the "debug:container" command, so the
 * detail pane shows exactly what "debug:container <id>", "--parameter" and
 * "--tag" already produce.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @experimental
 */
final class ContainerDebugSection extends AbstractDebugSection
{
    use BuildDebugContainerTrait;

    private const string GROUP_SERVICES = 'Services';
    private const string GROUP_PARAMETERS = 'Parameters';
    private const string GROUP_TAGS = 'Tags';

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly ?FileLinkFormatter $fileLinkFormatter = null,
    ) {
    }

    public function getLabel(): string
    {
        return 'Container';
    }

    public function getShortLabel(): string
    {
        return 'DI';
    }

    public function describe(DebugItem $item, int $width): string
    {
        $container = $this->getContainerBuilder($this->kernel);

        $options = match ($item->type) {
            'service' => ['id' => $item->value],
            'parameter' => ['parameter' => $item->value],
            'tag' => ['tag' => $item->value],
            default => [],
        };

        $buffer = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $io = new SymfonyStyle(new ArrayInput([]), $buffer);

        $options['format'] = 'txt';
        $options['output'] = $io;
        $options['is_debug'] = $this->kernel->isDebug();
        $options['show_hidden'] = false;

        (new DescriptorHelper($this->fileLinkFormatter))->describe($io, $container, $options);

        return $buffer->fetch();
    }

    protected function buildItems(): array
    {
        $container = $this->getContainerBuilder($this->kernel);

        $services = array_filter(
            $container->getServiceIds(),
            static fn (string $id): bool => !str_starts_with($id, '.'),
        );
        sort($services);

        $parameters = array_keys($container->getParameterBag()->all());
        sort($parameters);

        $tags = $container->findTags();
        sort($tags);

        $items = [];
        foreach ($services as $id) {
            $items[] = new DebugItem('service', $id, $id, self::GROUP_SERVICES);
        }
        foreach ($parameters as $name) {
            $items[] = new DebugItem('parameter', $name, '%'.$name.'%', self::GROUP_PARAMETERS);
        }
        foreach ($tags as $tag) {
            $items[] = new DebugItem('tag', $tag, '#'.$tag, self::GROUP_TAGS);
        }

        return $items;
    }
}
