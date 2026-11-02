<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard;

/**
 * @see https://adaptivecards.io/explorer/AdaptiveCard.html
 */
final class AdaptiveCard
{
    private array $options;

    public function __construct(Version $version = Version::Version10)
    {
        $this->options = [
            'type' => 'AdaptiveCard',
            '$schema' => 'https://adaptivecards.io/schemas/adaptive-card.json',
            'version' => $version->value,
        ];
    }

    /**
     * @return $this
     */
    public function refresh(Refresh $refresh): static
    {
        $this->options['refresh'] = $refresh->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function authentication(Authentication $authentication): static
    {
        $this->options['authentication'] = $authentication->toArray();

        return $this;
    }

    /**
     * Adds an element to the body of the card.
     *
     * @return $this
     */
    public function body(ElementInterface $element): static
    {
        $this->options['body'][] = $element->toArray();

        return $this;
    }

    /**
     * Adds an action to the action bar of the card.
     *
     * @return $this
     */
    public function action(ActionInterface $action): static
    {
        $this->options['actions'][] = $action->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function selectAction(SelectActionInterface $selectAction): static
    {
        $this->options['selectAction'] = $selectAction->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function fallbackText(string $fallbackText): static
    {
        $this->options['fallbackText'] = $fallbackText;

        return $this;
    }

    /**
     * @return $this
     */
    public function backgroundImage(BackgroundImage|string $backgroundImage): static
    {
        $this->options['backgroundImage'] = \is_string($backgroundImage) ? $backgroundImage : $backgroundImage->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function metadata(Metadata $metadata): static
    {
        $this->options['metadata'] = $metadata->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function minHeight(string $minHeight): static
    {
        $this->options['minHeight'] = $minHeight;

        return $this;
    }

    /**
     * @return $this
     */
    public function rtl(bool $rtl): static
    {
        $this->options['rtl'] = $rtl;

        return $this;
    }

    /**
     * @return $this
     */
    public function speak(string $speak): static
    {
        $this->options['speak'] = $speak;

        return $this;
    }

    /**
     * @return $this
     */
    public function lang(string $lang): static
    {
        $this->options['lang'] = $lang;

        return $this;
    }

    /**
     * @return $this
     */
    public function verticalContentAlignment(VerticalContentAlignment $verticalContentAlignment): static
    {
        $this->options['verticalContentAlignment'] = $verticalContentAlignment->value;

        return $this;
    }

    /**
     * Sets properties that only Microsoft Teams reads, such as ['width' => 'Full'].
     *
     * @see https://learn.microsoft.com/en-us/microsoftteams/platform/task-modules-and-cards/cards/cards-format
     *
     * @return $this
     */
    public function msTeams(array $msTeams): static
    {
        $this->options['msteams'] = $msTeams;

        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
