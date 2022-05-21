<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Mapping\Factory;

use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\VarExporter\VarExporter;

/**
 * @author Fabien Bourigault <bourigaultfabien@gmail.com>
 */
final class ClassMetadataFactoryCompiler
{
    /**
     * @param ClassMetadataInterface[] $classMetadatas
     */
    public function compile(array $classMetadatas): string
    {
        return <<<EOF
<?php

// This file has been auto-generated by the Symfony Serializer Component.

return [{$this->generateDeclaredClassMetadata($classMetadatas)}
];
EOF;
    }

    /**
     * @param ClassMetadataInterface[] $classMetadatas
     */
    private function generateDeclaredClassMetadata(array $classMetadatas): string
    {
        $compiled = '';

        foreach ($classMetadatas as $classMetadata) {
            $attributesMetadata = [];
            foreach ($classMetadata->getAttributesMetadata() as $attributeMetadata) {
                $attributesMetadata[$attributeMetadata->getName()] = [
                    $attributeMetadata->getGroups(),
                    $attributeMetadata->getMaxDepth(),
                    $attributeMetadata->getSerializedNames(),
                    $attributeMetadata->getSerializedPath(),
                ];
            }

            $classDiscriminatorMapping = $classMetadata->getClassDiscriminatorMapping() ? [
                $classMetadata->getClassDiscriminatorMapping()->getTypeProperty(),
                $classMetadata->getClassDiscriminatorMapping()->getTypesMapping(),
            ] : null;

            $compiled .= sprintf("\n'%s' => %s,", $classMetadata->getName(), VarExporter::export([
                $attributesMetadata,
                $classDiscriminatorMapping,
            ]));
        }

        return $compiled;
    }
}
