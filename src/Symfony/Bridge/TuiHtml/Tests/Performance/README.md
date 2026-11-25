Run the paired terminal benchmark from the repository root:

```console
env -u NO_COLOR php src/Symfony/Bridge/TuiHtml/Tests/Performance/compare-renderers.php > /tmp/renderer-benchmark.json
```

Run it while other tests and benchmarks are idle. Both paths receive the same HTML and 100 × 40 terminal viewport. The standalone path uses `TerminalTextMeasurer` and the `DocumentWidget` terminal policy; the retained path uses `HtmlBuilder` and `Renderer` defaults. The three workloads preserve the original extraction baseline: 80 paragraphs, 30 wrapping Flexbox cards, and 30 Grid cards.

The report gives median user plus system CPU time for nine alternating samples, including document construction and the first frame, then 20 cached frames per sample. It records production PHP/CSS/package hashes before and after, output hashes per path, and the process peak allocation. The latter includes both paths and is not a per-engine memory comparison. Cached calls deliberately use each public rendering entry point; their cache-validation costs may differ.

Output hashes need not match. A timing difference with different output is a cost comparison, not evidence of an equivalent-output speedup. Use the separate compatibility comparison to assess terminal behavior. Investigate material regressions before drawing conclusions; preserve the PHP version, workload hashes, source receipt and terminal environment with results.
