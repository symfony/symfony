override hash := 05f6cac786bf0cce95437e6f1adedc3186d54a71

.PHONY: cts.json
cts.json:
	curl -f https://raw.githubusercontent.com/jsonpath-standard/jsonpath-compliance-test-suite/$(hash)/cts.json -o cts.json

.PHONY: clean
clean:
	rm -f cts.json
