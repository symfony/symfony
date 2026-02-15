<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (!file_exists(__DIR__.'/src')) {
    exit(0);
}

$fileHeaderParts = [
    <<<'EOF'
        This file is part of the Symfony package.

        (c) Fabien Potencier <fabien@symfony.com>

        EOF,
    <<<'EOF'

        For the full copyright and license information, please view the LICENSE
        file that was distributed with this source code.
        EOF,
];

return (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRules([
        '@PHP8x1Migration' => true, // take lowest version from `git grep -h '"php"' **/composer.json | uniq | sort`
        '@PHP8x1Migration:risky' => true,
        '@PHPUnit9x1Migration:risky' => true, // take version from src/Symfony/Bridge/PhpUnit/phpunit.xml.dist#L4
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'header_comment' => [
            'header' => implode('', $fileHeaderParts),
            'validator' => implode('', [
                '/',
                preg_quote($fileHeaderParts[0], '/'),
                '(?P<EXTRA>.*)??',
                preg_quote($fileHeaderParts[1], '/'),
                '/s',
            ]),
        ],
        'declare_strict_types' => false, // part of PHP?x?Migration:risky, awaits https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/pull/9384
        'php_unit_attributes' => true,
        'void_return' => [
            'fix_lambda' => false,
        ],
    ])
    ->setRuleCustomisationPolicy(new class implements PhpCsFixer\Config\RuleCustomisationPolicyInterface {
        public function getPolicyVersionForCache(): string
        {
            return hash_file('xxh128', __FILE__);
        }

        public function getRuleCustomisers(): array
        {
            return [
                'php_unit_attributes' => static function (SplFileInfo $file) {
                    // temporary hack due to bug: https://github.com/symfony/symfony/issues/62734
                    if (!$file instanceof Symfony\Component\Finder\SplFileInfo) {
                        return false;
                    }

                    $relativePathname = $file->getRelativePathname();

                    // For packages/namespaces that are part of public API and
                    // as such are not bound to any specific PHPUnit version,
                    // we have to keep both annotations and attributes.
                    if (
                        str_starts_with($relativePathname, 'Symfony/Bridge/PhpUnit/')
                        || str_starts_with($relativePathname, 'Symfony/Contracts/')
                        || str_contains($relativePathname, '/Test/') // public namespace, do not mistake it with `/Tests/`
                    ) {
                        $fixer = new PhpCsFixer\Fixer\PhpUnit\PhpUnitAttributesFixer();
                        $fixer->configure(['keep_annotations' => true]);

                        return $fixer;
                    }

                    // Keep the default configuration for other files
                    return true;
                },
                'void_return' => static function (SplFileInfo $file) {
                    // temporary hack due to bug: https://github.com/symfony/symfony/issues/62734
                    if (!$file instanceof Symfony\Component\Finder\SplFileInfo) {
                        return false;
                    }

                    $relativePathname = $file->getRelativePathname();

                    if (
                        str_contains($relativePathname, '/Tests/') // don't touch test files, as massive change with little benefit - as outside of public contract anyway
                           || str_contains($relativePathname, '/Test/') // public namespace not following the rule, do not mistake it with `/Tests/`
                           || str_starts_with($relativePathname, 'Symfony/Contracts/') // rule not yet followed in current MAJOR
                    ) {
                        return false;
                    }

                    return true;
                },
            ];
        }
    })
    ->setRiskyAllowed(true)
    ->setFinder(
        (new PhpCsFixer\Finder())
            ->in(__DIR__.'/src')
            ->append([__FILE__])
            ->notPath('#/Fixtures/#')
            ->exclude([
                'Symfony/Component/Emoji/Resources/',
                'Symfony/Component/Intl/Resources/data/',
            ])
            // Support for older PHPunit version
            ->notPath('#Symfony/Bridge/PhpUnit/.*Mock\.php#')
            ->notPath('#Symfony/Bridge/PhpUnit/.*Legacy#')
            // auto-generated proxies
            ->notPath('#Symfony/Component/Cache/Traits/Re.*Proxy\.php#')
            // svg
            ->notPath('Symfony/Component/ErrorHandler/Resources/assets/images/symfony-ghost.svg.php')
            // HTML templates
            ->notPath('#Symfony/.*\.html\.php#')
    )
;
