# ICU freshness fixtures

The before fixtures come from Symfony Translation’s `Tests/Fixtures/resourcebundle/dat/resources.dat` and `Tests/Fixtures/resourcebundle/dat/en.res` at Symfony commit `8bf3b847e1034f2ae2a02142adf567a7753f92cb`. They contain the English message `symfony: Symfony 2 is great`.

The after fixtures replace the UTF-16LE string `great` with the equally long string `super`. The replacement preserves the compiled resource offsets and the package directory. To regenerate each after file from its matching before file:

```python
from pathlib import Path
source = Path("before/dat/resources.dat")
data = source.read_bytes()
assert "great".encode("utf-16-le") in data
Path("after/dat/resources.dat").write_bytes(data.replace("great".encode("utf-16-le"), "super".encode("utf-16-le")))
```

Repeat for `res/en.res`. Tests copy these committed files to a scenario-owned directory. Running the tests needs Intl, but no ICU compilation tool and no installed package’s test files.
