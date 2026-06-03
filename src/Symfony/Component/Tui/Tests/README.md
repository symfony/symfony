# Tests

## Regression Tests

`RegressionTest` replays recorded interactions against TUI examples and asserts
the raw ANSI output matches. Each example has a JSON fixture in `Fixtures/Steps/`.

To re-record fixtures after a change:

```bash
# Record all examples
php src/Resources/bin/record_fixtures.php

# Record a specific example
php src/Resources/bin/record_fixtures.php select_list
```
