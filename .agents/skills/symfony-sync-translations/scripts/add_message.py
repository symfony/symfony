#!/usr/bin/env python3
"""Insert one translation message into every locale of an xlf catalog.

Adds a <trans-unit> with the given id and English source to each
``*.xlf`` file in a catalog directory: the English catalog gets the
source as its target (no state), every other locale gets its translation
with ``state="needs-review-translation"``. Idempotent: a locale that
already has the id is left untouched.

Usage:
  add_message.py --dir DIR --id ID --source TEXT --translations FILE

  --dir           Catalog directory holding the <domain>.<locale>.xlf files
  --id            Numeric trans-unit id (must be free in every locale)
  --source        English source text, already XML-ready (escape & < >)
  --translations  JSON file mapping every non-en locale to its translated
                  target, e.g. {"fr": "...", "de": "..."}

Exit codes: 0 success, 2 bad arguments, 3 locale/translation mismatch.
"""
import argparse
import json
import os
import re
import sys

ANCHOR = "        </body>"


def unit(id_, source, target, state):
    attr = f' state="{state}"' if state else ""
    return (
        f'            <trans-unit id="{id_}">\n'
        f"                <source>{source}</source>\n"
        f"                <target{attr}>{target}</target>\n"
        f"            </trans-unit>\n"
    )


def locale_of(filename):
    # <domain>.<locale>.xlf  ->  <locale>
    return filename.rsplit(".", 2)[1]


def main():
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("--dir", required=True)
    p.add_argument("--id", required=True)
    p.add_argument("--source", required=True)
    p.add_argument("--translations", required=True)
    args = p.parse_args()

    if not os.path.isdir(args.dir):
        sys.exit(f"Error: --dir is not a directory: {args.dir}")

    files = sorted(f for f in os.listdir(args.dir) if f.endswith(".xlf"))
    if not files:
        sys.exit(f"Error: no .xlf files in {args.dir}")

    with open(args.translations, encoding="utf-8") as fh:
        translations = json.load(fh)

    locales = {locale_of(f) for f in files}
    non_en = locales - {"en"}
    missing = sorted(non_en - translations.keys())
    extra = sorted(translations.keys() - non_en)
    if missing or extra:
        msg = []
        if missing:
            msg.append(f"missing translations for: {', '.join(missing)}")
        if extra:
            msg.append(f"unknown locales in translations file: {', '.join(extra)}")
        print("Error: " + "; ".join(msg), file=sys.stderr)
        sys.exit(3)

    id_marker = f'<trans-unit id="{args.id}">'
    added, skipped = [], []
    for f in files:
        path = os.path.join(args.dir, f)
        with open(path, encoding="utf-8") as fh:
            content = fh.read()
        loc = locale_of(f)
        if id_marker in content:
            skipped.append(loc)
            continue
        if ANCHOR not in content:
            sys.exit(f"Error: anchor '</body>' not found in {path}")
        if loc == "en":
            block = unit(args.id, args.source, args.source, None)
        else:
            block = unit(args.id, args.source, translations[loc], "needs-review-translation")
        with open(path, "w", encoding="utf-8") as fh:
            fh.write(content.replace(ANCHOR, block + ANCHOR, 1))
        added.append(loc)

    print(json.dumps({"id": args.id, "added": sorted(added), "skipped": sorted(skipped)}))


if __name__ == "__main__":
    main()
