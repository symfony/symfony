<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Translation\Catalogue\MergeOperation;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Translator;

/**
 * Helps finding unused or missing translation messages in a given locale
 * and comparing them with the fallback ones.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
class TranslationDebugCommand extends ContainerAwareCommand
{
    const MESSAGE_MISSING = 0;
    const MESSAGE_UNUSED = 1;
    const MESSAGE_EQUALS_FALLBACK = 2;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('debug:translation')
            ->setAliases(array(
                'translation:debug',
            ))
            ->setDefinition(array(
                new InputArgument('locale', InputArgument::REQUIRED, 'The locale'),
                new InputArgument('bundle', InputArgument::REQUIRED, 'The bundle name'),
                new InputOption('domain', null, InputOption::VALUE_OPTIONAL, 'The messages domain'),
                new InputOption('only-missing', null, InputOption::VALUE_NONE, 'Displays only missing messages'),
                new InputOption('only-unused', null, InputOption::VALUE_NONE, 'Displays only unused messages'),
            ))
            ->setDescription('Displays translation messages informations')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command helps finding unused or missing translation
messages and comparing them with the fallback ones by inspecting the
templates and translation files of a given bundle.

You can display information about bundle translations in a specific locale:

  <info>php %command.full_name% en AcmeDemoBundle</info>

You can also specify a translation domain for the search:

  <info>php %command.full_name% --domain=messages en AcmeDemoBundle</info>

You can only display missing messages:

  <info>php %command.full_name% --only-missing en AcmeDemoBundle</info>

You can only display unused messages:

  <info>php %command.full_name% --only-unused en AcmeDemoBundle</info>

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (false !== strpos($input->getFirstArgument(), ':d')) {
            $output->writeln('<comment>The use of "translation:debug" command is deprecated since version 2.7 and will be removed in 3.0. Use the "debug:translation" instead.</comment>');
        }

        $locale = $input->getArgument('locale');
        $domain = $input->getOption('domain');
        $bundle = $this->getContainer()->get('kernel')->getBundle($input->getArgument('bundle'));
        $loader = $this->getContainer()->get('translation.loader');

        // Extract used messages
        $extractedCatalogue = new MessageCatalogue($locale);
        $this->getContainer()->get('translation.extractor')->extract($bundle->getPath().'/Resources/views', $extractedCatalogue);

        // Load defined messages
        $currentCatalogue = new MessageCatalogue($locale);
        if (is_dir($bundle->getPath().'/Resources/translations')) {
            $loader->loadMessages($bundle->getPath().'/Resources/translations', $currentCatalogue);
        }

        // Merge defined and extracted messages to get all message ids
        $mergeOperation = new MergeOperation($extractedCatalogue, $currentCatalogue);
        $allMessages = $mergeOperation->getResult()->all($domain);
        if (null !== $domain) {
            $allMessages = array($domain => $allMessages);
        }

        // No defined or extracted messages
        if (empty($allMessages) || null !== $domain && empty($allMessages[$domain])) {
            $outputMessage = sprintf('<info>No defined or extracted messages for locale "%s"</info>', $locale);

            if (null !== $domain) {
                $outputMessage .= sprintf(' <info>and domain "%s"</info>', $domain);
            }

            $output->writeln($outputMessage);

            return;
        }

        // Load the fallback catalogues
        $fallbackCatalogues = array();
        $translator = $this->getContainer()->get('translator');
        if ($translator instanceof Translator) {
            foreach ($translator->getFallbackLocales() as $fallbackLocale) {
                if ($fallbackLocale === $locale) {
                    continue;
                }

                $fallbackCatalogue = new MessageCatalogue($fallbackLocale);
                $loader->loadMessages($bundle->getPath().'/Resources/translations', $fallbackCatalogue);
                $fallbackCatalogues[] = $fallbackCatalogue;
            }
        }

        /** @var \Symfony\Component\Console\Helper\Table $table */
        $table = new Table($output);

        // Display header line
        $headers = array('State(s)', 'Id', sprintf('Message Preview (%s)', $locale));
        foreach ($fallbackCatalogues as $fallbackCatalogue) {
            $headers[] = sprintf('Fallback Message Preview (%s)', $fallbackCatalogue->getLocale());
        }
        $table->setHeaders($headers);

        // Iterate all message ids and determine their state
        foreach ($allMessages as $domain => $messages) {
            foreach (array_keys($messages) as $messageId) {
                $value = $currentCatalogue->get($messageId, $domain);
                $states = array();

                if ($extractedCatalogue->defines($messageId, $domain)) {
                    if (!$currentCatalogue->defines($messageId, $domain)) {
                        $states[] = self::MESSAGE_MISSING;
                    }
                } elseif ($currentCatalogue->defines($messageId, $domain)) {
                    $states[] = self::MESSAGE_UNUSED;
                }

                if (!in_array(self::MESSAGE_UNUSED, $states) && true === $input->getOption('only-unused')
                    || !in_array(self::MESSAGE_MISSING, $states) && true === $input->getOption('only-missing')) {
                    continue;
                }

                foreach ($fallbackCatalogues as $fallbackCatalogue) {
                    if ($fallbackCatalogue->defines($messageId, $domain) && $value === $fallbackCatalogue->get($messageId, $domain)) {
                        $states[] = self::MESSAGE_EQUALS_FALLBACK;

                        break;
                    }
                }

                $row = array($this->formatStates($states), $this->formatId($messageId), $this->sanitizeString($value));
                foreach ($fallbackCatalogues as $fallbackCatalogue) {
                    $row[] = $this->sanitizeString($fallbackCatalogue->get($messageId, $domain));
                }

                $table->addRow($row);
            }
        }

        $table->render();

        $output->writeln('');
        $output->writeln('<info>Legend:</info>');
        $output->writeln(sprintf(' %s Missing message', $this->formatState(self::MESSAGE_MISSING)));
        $output->writeln(sprintf(' %s Unused message', $this->formatState(self::MESSAGE_UNUSED)));
        $output->writeln(sprintf(' %s Same as the fallback message', $this->formatState(self::MESSAGE_EQUALS_FALLBACK)));
    }

    private function formatState($state)
    {
        if (self::MESSAGE_MISSING === $state) {
            return '<fg=red>x</>';
        }

        if (self::MESSAGE_UNUSED === $state) {
            return '<fg=yellow>o</>';
        }

        if (self::MESSAGE_EQUALS_FALLBACK === $state) {
            return '<fg=green>=</>';
        }

        return $state;
    }

    private function formatStates(array $states)
    {
        $result = array();
        foreach ($states as $state) {
            $result[] = $this->formatState($state);
        }

        return implode(' ', $result);
    }

    private function formatId($id)
    {
        return sprintf('<fg=cyan;options=bold>%s</fg=cyan;options=bold>', $id);
    }

    private function sanitizeString($string, $length = 40)
    {
        $string = trim(preg_replace('/\s+/', ' ', $string));

        if (function_exists('mb_strlen') && false !== $encoding = mb_detect_encoding($string)) {
            if (mb_strlen($string, $encoding) > $length) {
                return mb_substr($string, 0, $length - 3, $encoding).'...';
            }
        } elseif (strlen($string) > $length) {
            return substr($string, 0, $length - 3).'...';
        }

        return $string;
    }
}
