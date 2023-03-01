<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Configurator;

use Symfony\Component\OpenApi\Model\Contact;
use Symfony\Component\OpenApi\Model\Info;
use Symfony\Component\OpenApi\Model\License;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
class InfoConfigurator
{
    use Traits\DescriptionTrait;
    use Traits\ExtensionsTrait;
    use Traits\SummaryTrait;

    private string $title = '';
    private string $version = '';
    private ?string $termsOfService = null;
    private ?Contact $contact = null;
    private ?License $license = null;

    public function build(string $identifier = '', string $version = ''): Info
    {
        return new Info(
            title: $this->title ?: $identifier,
            version: $this->version ?: $version,
            summary: $this->summary,
            description: $this->description,
            termsOfService: $this->termsOfService,
            contact: $this->contact,
            license: $this->license,
            specificationExtensions: $this->specificationExtensions,
        );
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function version(string $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function termsOfService(string $termsOfService): static
    {
        $this->termsOfService = $termsOfService;

        return $this;
    }

    public function contact(string $name = null, string $url = null, string $email = null, array $specificationExtensions = []): static
    {
        $this->contact = new Contact($name, $url, $email, $specificationExtensions);

        return $this;
    }

    public function license(string $name, string $identifier = null, string $url = null, array $specificationExtensions = []): static
    {
        $this->license = new License($name, $identifier, $url, $specificationExtensions);

        return $this;
    }
}
