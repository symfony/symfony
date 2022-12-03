<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\Input;

/**
 * @author Edouard Lescot <edouard.lescot@gmail.com>
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
abstract class AbstractInput implements InputInterface
{
    private array $options = [];

    /**
     * @return $this
     */
    public function id(string $id): static
    {
        $this->options['id'] = $id;

        return $this;
    }

    /**
     * @return $this
     */
    public function isRequired(bool $required): static
    {
        $this->options['isRequired'] = $required;

        return $this;
    }

    /**
     * @return $this
     */
    public function title(string $title): static
    {
        $this->options['title'] = $title;

        return $this;
    }

    /**
     * @return $this
     */
    public function value(string $value): static
    {
        $this->options['value'] = $value;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
