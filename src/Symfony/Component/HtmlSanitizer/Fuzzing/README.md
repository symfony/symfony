# HtmlSanitizer Fuzzing

Run locally from `src/Symfony/Component/HtmlSanitizer`:

```terminal
composer install
composer global require nikic/php-fuzzer
php-fuzzer fuzz Fuzzing/target.php Fuzzing/Corpus
```

The target loads `xss.dict` itself, so the dictionary is used automatically by PHP-Fuzzer.
