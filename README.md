![Latest version](https://img.shields.io/github/v/release/magicsunday/webtrees-module-base?sort=semver)
![License](https://img.shields.io/github/license/magicsunday/webtrees-module-base)
![PHPStan](https://github.com/magicsunday/webtrees-module-base/actions/workflows/phpstan.yml/badge.svg)
![PHPUnit](https://github.com/magicsunday/webtrees-module-base/actions/workflows/phpunit.yml/badge.svg)
![PHPCodeSniffer](https://github.com/magicsunday/webtrees-module-base/actions/workflows/phpcs.yml/badge.svg)


## Development

### Run tests
```
composer update
vendor/bin/phpstan analyse --memory-limit=-1 -c phpstan.neon
vendor/bin/phpcs src/ --standard=PSR12
vendor/bin/phpunit
```
