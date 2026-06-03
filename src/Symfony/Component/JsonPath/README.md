JsonPath Component
==================

The JsonPath component eases JSON navigation using the JSONPath syntax as described in [RFC 9535](https://www.rfc-editor.org/rfc/rfc9535.html).

Getting Started
---------------

```bash
composer require symfony/json-path
```

```php
use Symfony\Component\JsonPath\JsonCrawler;

$json = <<<'JSON'
{"store": {"book": [
    {"category": "reference", "author": "Nigel Rees", "title": "Sayings", "price": 8.95},
    {"category": "fiction", "author": "Evelyn Waugh", "title": "Sword", "price": 12.99}
]}}
JSON;

$crawler = new JsonCrawler($json);

$result = $crawler->find('$.store.book[0].title');
$result = $crawler->find('$.store.book[?match(@.author, "[A-Z].*el.+")]');
$result = $crawler->find("$.store.book[?(@.category == 'fiction')].title");
```

Updating the Compliance Test Suite
----------------------------------

The compliance test suite is gathered from the [JSONPath Test Suite](https://github.com/jsonpath-standard/jsonpath-compliance-test-suite).

When new commits are pushed to the upstream repository, it is necessary to gather them by following these steps:

 * Update the `reference` field of `composer.json` to the latest commit hash of the `jsonpath-standard/jsonpath-compliance-test-suite` package
 * Update the `version` field to the date of the commit
 * Repeat the steps above for the `composer.json` file present at the root level of the `symfony/symfony` repository
 * Run `composer update`
 * Ensure the tests pass

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/json_path.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
