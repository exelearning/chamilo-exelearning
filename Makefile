# chamilo-exelearning — development tasks. `make help` lists every target.

SHELL := /bin/bash
.DEFAULT_GOAL := help

PLUGIN_NAME := ExeLearning
COMPOSE := docker compose
EXEC := $(COMPOSE) exec -T chamilo
CHAMILO_DIR := /var/www/html

EDITOR_SUBMODULE_PATH := exelearning
EDITOR_REPO_DEFAULT := https://github.com/exelearning/exelearning.git
EDITOR_REF_DEFAULT := $(shell cat .editor-version 2>/dev/null || echo main)
EDITOR_OUTPUT_DIR := $(CURDIR)/dist/static

# Coverage ratchet: raise it when coverage grows, never lower it.
MIN_COVERAGE ?= 91

VERSION ?=
PACKAGE_DIR := build
FILTER ?=

PHPUNIT := vendor/bin/phpunit
ECS := vendor/bin/ecs

.PHONY: help check-docker check-bun up upd down pull build shell logs clean destroy seed \
	install-deps lint fix phpmd test test-coverage test-integration test-js test-e2e \
	fetch-editor-source build-editor build-editor-if-needed build-editor-no-update clean-editor \
	i18n check-untranslated architecture-records architecture-check check-version package check-package

help: ## List every target
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-24s\033[0m %s\n", $$1, $$2}'

# --- Environment -------------------------------------------------------------

check-docker: ## Fail unless Docker is running
	@docker info > /dev/null 2>&1 || { echo "Docker is not running."; exit 1; }

check-bun: ## Fail unless Bun is installed
	@command -v bun > /dev/null 2>&1 || { echo "Bun is required: https://bun.sh"; exit 1; }

.env:
	@cp .env.dist .env

up: check-docker .env ## Start Chamilo (foreground until healthy), install, enable and seed the plugin
	$(COMPOSE) up -d --build --wait
	@$(MAKE) --no-print-directory seed
	@echo "Chamilo: http://localhost:$${CHAMILO_PORT:-8080} (admin/admin-exelearning, teacher/teacher, student/student)"

upd: check-docker .env ## Start containers in the background without waiting or seeding
	$(COMPOSE) up -d --build

down: check-docker ## Stop containers, keep data
	$(COMPOSE) down

pull: check-docker ## Pull base images
	$(COMPOSE) pull db
	docker pull ghcr.io/erseco/alpine-chamilo:$${CHAMILO_VERSION:-3.0.2}

build: check-docker .env ## Build the Chamilo image (CHAMILO_VERSION from .env)
	$(COMPOSE) build chamilo

shell: check-docker ## Open a shell in the Chamilo container
	$(COMPOSE) exec chamilo sh

logs: check-docker ## Follow container logs
	$(COMPOSE) logs -f

clean: check-docker ## Stop containers and remove all data volumes (Chamilo and its database go together)
	$(COMPOSE) down -v

destroy: check-docker ## Remove containers, volumes and the built image
	$(COMPOSE) down -v --rmi local

seed: check-docker ## Install + enable the plugin, create demo users, course and sample package
	$(EXEC) sh $(CHAMILO_DIR)/public/plugin/$(PLUGIN_NAME)/scripts/seed.sh

# --- Quality -----------------------------------------------------------------

install-deps: ## Install PHP and JS development dependencies
	composer install --no-interaction --no-progress
	npm install --no-audit --no-fund

lint: ## Check coding style (read-only)
	$(ECS) check
	npx --no-install eslint resources/js tests/js tests/e2e

fix: ## Apply coding-style fixes
	$(ECS) check --fix
	npx --no-install eslint --fix resources/js tests/js tests/e2e

phpmd: ## Run PHP Mess Detector
	php -d error_reporting='E_ALL & ~E_DEPRECATED' -d display_errors=stderr vendor/bin/phpmd analyze --no-progress --format text --ruleset phpmd.xml src start.php view.php content.php editor.php edit.php save.php download.php config.php plugin.php

test: ## Run PHPUnit unit tests (FILTER=pattern)
	$(PHPUNIT) --testsuite unit $(if $(FILTER),--filter '$(FILTER)')

test-coverage: ## Run unit tests with coverage and enforce MIN_COVERAGE
	XDEBUG_MODE=coverage $(PHPUNIT) --testsuite unit --coverage-clover coverage.xml --coverage-text=coverage.txt
	@php scripts/check-coverage.php coverage.xml $(MIN_COVERAGE)

test-integration: ## Run integration checks inside the Chamilo container (ARGS=--uninstall to include uninstall)
	$(EXEC) php -d memory_limit=-1 $(CHAMILO_DIR)/public/plugin/$(PLUGIN_NAME)/tests/Integration/run.php --url="$${CHAMILO_URL:-http://localhost:8080}" $(ARGS)

test-js: ## Run Vitest
	npx --no-install vitest run

test-e2e: ## Run Playwright against `make up`
	npx --no-install playwright test

# --- Editor ------------------------------------------------------------------

fetch-editor-source: ## Shallow-fetch the editor at EXELEARNING_EDITOR_REF (default: .editor-version)
	@set -e; \
	get_env() { \
		if [ -f .env ]; then \
			grep -E "^$$1=" .env | tail -n1 | cut -d '=' -f2-; \
		fi; \
	}; \
	REPO_URL="$${EXELEARNING_EDITOR_REPO_URL:-$$(get_env EXELEARNING_EDITOR_REPO_URL)}"; \
	REF="$${EXELEARNING_EDITOR_REF:-$$(get_env EXELEARNING_EDITOR_REF)}"; \
	REF_TYPE="$${EXELEARNING_EDITOR_REF_TYPE:-$$(get_env EXELEARNING_EDITOR_REF_TYPE)}"; \
	if [ -z "$$REPO_URL" ]; then REPO_URL="$(EDITOR_REPO_DEFAULT)"; fi; \
	if [ -z "$$REF" ]; then REF="$(EDITOR_REF_DEFAULT)"; fi; \
	if [ -z "$$REF_TYPE" ]; then REF_TYPE="auto"; fi; \
	echo "Fetching editor source from $$REPO_URL (ref=$$REF, type=$$REF_TYPE)"; \
	mkdir -p $(EDITOR_SUBMODULE_PATH); \
	find $(EDITOR_SUBMODULE_PATH) -mindepth 1 -delete; \
	git init -q $(EDITOR_SUBMODULE_PATH); \
	git -C $(EDITOR_SUBMODULE_PATH) remote add origin "$$REPO_URL"; \
	case "$$REF_TYPE" in \
		tag) \
			git -C $(EDITOR_SUBMODULE_PATH) fetch --depth 1 origin "refs/tags/$$REF:refs/tags/$$REF"; \
			git -C $(EDITOR_SUBMODULE_PATH) checkout -q "tags/$$REF"; \
			;; \
		branch) \
			git -C $(EDITOR_SUBMODULE_PATH) fetch --depth 1 origin "$$REF"; \
			git -C $(EDITOR_SUBMODULE_PATH) checkout -q FETCH_HEAD; \
			;; \
		auto) \
			if git -C $(EDITOR_SUBMODULE_PATH) fetch --depth 1 origin "refs/tags/$$REF:refs/tags/$$REF" > /dev/null 2>&1; then \
				echo "Resolved $$REF as tag"; \
				git -C $(EDITOR_SUBMODULE_PATH) checkout -q "tags/$$REF"; \
			else \
				echo "Resolved $$REF as branch"; \
				git -C $(EDITOR_SUBMODULE_PATH) fetch --depth 1 origin "$$REF"; \
				git -C $(EDITOR_SUBMODULE_PATH) checkout -q FETCH_HEAD; \
			fi; \
			;; \
		*) \
			echo "Error: EXELEARNING_EDITOR_REF_TYPE must be one of: auto, branch, tag"; \
			exit 1; \
			;; \
	esac

build-editor: check-bun fetch-editor-source ## Build the static editor into dist/static/
	@echo "Building eXeLearning static editor..."
	rm -rf $(EDITOR_OUTPUT_DIR)
	@set -e; \
	get_env() { \
		if [ -f .env ]; then \
			grep -E "^$$1=" .env | tail -n1 | cut -d '=' -f2-; \
		fi; \
	}; \
	APP_VER="$${APP_VERSION:-$${EXELEARNING_EDITOR_REF:-$$(get_env EXELEARNING_EDITOR_REF)}}"; \
	if [ -z "$$APP_VER" ] || [ "$$APP_VER" = "main" ] || [ "$$APP_VER" = "master" ]; then \
		EXACT_TAG="$$(git -C $(EDITOR_SUBMODULE_PATH) describe --tags --exact-match 2>/dev/null || true)"; \
		if [ -n "$$EXACT_TAG" ]; then APP_VER="$$EXACT_TAG"; fi; \
	fi; \
	echo "Editor version input: $${APP_VER:-(default -> alpha)}"; \
	cd $(EDITOR_SUBMODULE_PATH) && bun install && OUTPUT_DIR=$(EDITOR_OUTPUT_DIR) APP_VERSION="$$APP_VER" bun run build:static
	@git -C $(EDITOR_SUBMODULE_PATH) rev-parse HEAD > $(EDITOR_OUTPUT_DIR)/.build-commit
	@echo "Static editor built at dist/static/ ($$(cat $(EDITOR_OUTPUT_DIR)/.build-commit))"

build-editor-if-needed: ## Build the editor only when dist/static/ is missing
	@if [ -f $(EDITOR_OUTPUT_DIR)/index.html ] && [ -f $(EDITOR_OUTPUT_DIR)/.build-commit ]; then \
		echo "dist/static/ present (commit $$(cat $(EDITOR_OUTPUT_DIR)/.build-commit)), skipping build"; \
	else \
		$(MAKE) --no-print-directory build-editor; \
	fi

build-editor-no-update: build-editor ## Alias of build-editor

clean-editor: ## Remove the editor checkout and dist/static/
	@# Emptied, not removed: docker-compose.yml mounts a volume on it.
	-find $(EDITOR_SUBMODULE_PATH) -mindepth 1 -delete
	rm -rf dist/static

# --- i18n --------------------------------------------------------------------

i18n: check-untranslated ## Alias of check-untranslated

check-untranslated: ## Fail if a lang file misses or has extra keys compared to en_US
	@php scripts/check-untranslated.php

# --- Records -----------------------------------------------------------------

architecture-records: ## Regenerate the architecture records index
	node scripts/architecture-records.mts index

architecture-check: ## Validate the architecture records
	node scripts/architecture-records.mts check

# --- Release -----------------------------------------------------------------

check-version: ## Check that plugin.php, the plugin class and CHANGELOG agree (VERSION=X.Y.Z optional)
	@php scripts/check-version.php $(VERSION)

package: ## Build build/ExeLearning-$(VERSION).zip (VERSION=X.Y.Z required)
	@if [ -z "$(VERSION)" ]; then echo "Usage: make package VERSION=X.Y.Z"; exit 1; fi
	@echo "$(VERSION)" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$$' || { echo "VERSION must be X.Y.Z"; exit 1; }
	@php scripts/check-version.php $(VERSION)
	@test -f dist/static/index.html || { echo "dist/static/ missing: run make build-editor"; exit 1; }
	@cp .editor-version dist/static/.editor-version
	rm -rf $(PACKAGE_DIR)/$(PLUGIN_NAME) $(PACKAGE_DIR)/$(PLUGIN_NAME)-$(VERSION).zip
	mkdir -p $(PACKAGE_DIR)/$(PLUGIN_NAME)
	rsync -a --exclude-from=.distignore ./ $(PACKAGE_DIR)/$(PLUGIN_NAME)/
	cd $(PACKAGE_DIR) && zip -qr $(PLUGIN_NAME)-$(VERSION).zip $(PLUGIN_NAME)
	cd $(PACKAGE_DIR) && shasum -a 256 $(PLUGIN_NAME)-$(VERSION).zip > $(PLUGIN_NAME)-$(VERSION).zip.sha256
	@# Never leave the staging copy: the repository is mounted under public/plugin
	@# and Chamilo's classmap would load its stale classes instead of ours.
	rm -rf $(PACKAGE_DIR)/$(PLUGIN_NAME)
	@echo "Built $(PACKAGE_DIR)/$(PLUGIN_NAME)-$(VERSION).zip"

check-package: ## Inspect the built ZIP (VERSION=X.Y.Z required)
	@php scripts/check-package.php $(PACKAGE_DIR)/$(PLUGIN_NAME)-$(VERSION).zip
