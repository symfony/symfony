.PHONY: help update build
.DEFAULT_GOAL := help

update: ## Update sources
	@composer update
	@curl https://api.github.com/emojis > vendor/github-emojis.json
	@curl https://raw.githubusercontent.com/iamcal/emoji-data/master/emoji.json > vendor/slack-emojis.json
	@curl -L https://unicode.org/Public/emoji/latest/emoji-test.txt > vendor/emoji-test.txt

build: ## Build rules
	@./build.php

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-7s\033[0m %s\n", $$1, $$2}'
