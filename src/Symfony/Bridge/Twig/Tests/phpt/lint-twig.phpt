--TEST--
Test twig-lint binary
--FILE--
<?php
$filename = tempnam(sys_get_temp_dir(), 'sf-');
file_put_contents($filename, "{{ foo }}");
passthru('php '.__DIR__.'/../../Resources/bin/twig-lint '.$filename);
@unlink($filename);
?>
--EXPECT--
[OK] All 1 Twig files contain valid syntax.
