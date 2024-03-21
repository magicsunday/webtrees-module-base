[![Latest version](https://img.shields.io/github/v/release/magicsunday/webtrees-module-base?sort=semver)](https://github.com/magicsunday/webtrees-module-base/releases/latest)
[![License](https://img.shields.io/github/license/magicsunday/webtrees-module-base)](https://github.com/magicsunday/webtrees-module-base/blob/main/LICENSE)
[![CI](https://github.com/magicsunday/webtrees-module-base/actions/workflows/ci.yml/badge.svg)](https://github.com/magicsunday/webtrees-module-base/actions/workflows/ci.yml)


## Development

### Run tests
```shell
composer update

composer ci:test
composer ci:test:php:phpstan
composer ci:test:php:lint
composer ci:test:php:unit
composer ci:test:php:rector
```
