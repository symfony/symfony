.PHONY: help update build
.DEFAULT_GOAL := help

update: ## Update sources
	@composer update

build: ## Build rules
	@./build.php

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-7s\033[0m %s\n", $$1, $$2}'
