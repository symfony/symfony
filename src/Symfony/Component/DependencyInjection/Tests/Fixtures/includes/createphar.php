<?php

$file = __DIR__.'/ProjectWithXsdExtensionInPhar.phar';
if (is_file($file)) {
    @unlink($file);
}

$phar = new Phar($file, 0, 'ProjectWithXsdExtensionInPhar.phar');
$phar->addFromString('ProjectWithXsdExtensionInPhar.php', <<<EOT
<?php

class ProjectWithXsdExtensionInPhar extends ProjectExtension
{
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/schema';
    }

    public function getNamespace()
    {
        return 'http://www.example.com/schema/projectwithxsdinphar';
    }

    public function getAlias()
    {
        return 'projectwithxsdinphar';
    }
}
EOT
);
$phar->addFromString('schema/project-1.0.xsd', <<<EOT
<?xml version="1.0" encoding="UTF-8" ?>

<xsd:schema xmlns="http://www.example.com/schema/projectwithxsdinphar"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    targetNamespace="http://www.example.com/schema/projectwithxsdinphar"
    elementFormDefault="qualified">

  <xsd:element name="bar" type="bar" />

  <xsd:complexType name="bar">
    <xsd:attribute name="foo" type="xsd:string" />
  </xsd:complexType>
</xsd:schema>
EOT
);
$phar->setStub('<?php require_once "phar://ProjectWithXsdExtensionInPhar.phar/ProjectWithXsdExtensionInPhar.php"; __HALT_COMPILER(); ?>');
