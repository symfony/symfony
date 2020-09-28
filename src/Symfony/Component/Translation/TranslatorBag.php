<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

use Symfony\Component\Translation\Catalogue\TargetOperation;

final class TranslatorBag implements TranslatorBagInterface
{
    /** @var MessageCatalogue[] */
    private $catalogues = [];

    public function addCatalogue(MessageCatalogue $catalogue): void
    {
        if (null !== $existingCatalogue = $this->getCatalogue($catalogue->getLocale())) {
            $catalogue->addCatalogue($existingCatalogue);
        }

        $this->catalogues[$catalogue->getLocale()] = $catalogue;
    }

    public function addBag(self $bag): void
    {
        foreach ($bag->getCatalogues() as $catalogue) {
            $this->addCatalogue($catalogue);
        }
    }

    public function getDomains(): array
    {
        $domains = [];

        foreach ($this->catalogues as $catalogue) {
            $domains += $catalogue->getDomains();
        }

        return array_unique($domains);
    }

    public function all(): array
    {
        $messages = [];
        foreach ($this->catalogues as $locale => $catalogue) {
            $messages[$locale] = $catalogue->all();
        }

        return $messages;
    }

    public function getCatalogue(string $locale = null): ?MessageCatalogue
    {
        if (null === $locale) {
            return null;
        }

        return $this->catalogues[$locale] ?? null;
    }

    /**
     * @return MessageCatalogueInterface[]
     */
    public function getCatalogues(): array
    {
        return array_values($this->catalogues);
    }

    public function diff(self $diffBag): self
    {
        $diff = new self();

        foreach ($this->catalogues as $locale => $catalogue) {
            if (null === $diffCatalogue = $diffBag->getCatalogue($locale)) {
                $diff->addCatalogue($catalogue);

                continue;
            }

            $operation = new TargetOperation($catalogue, $diffCatalogue);
            $operation->moveMessagesToIntlDomainsIfPossible('obsolete');
            $diff->addCatalogue($operation->getResult());
        }

        return $diff;
    }

    public function intersect(self $intersectBag): self
    {
        $diff = new self();

        foreach ($this->catalogues as $locale => $catalogue) {
            if (null === $intersectCatalogue = $intersectBag->getCatalogue($locale)) {
                continue;
            }

            $operation = new TargetOperation($catalogue, $intersectCatalogue);
            $operation->moveMessagesToIntlDomainsIfPossible('obsolete');

            $diff->addCatalogue($operation->getResult());
        }

        return $diff;
    }
}
