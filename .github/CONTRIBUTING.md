# VVV-Base Contribution

## Setup

Instead of adding this repo to the `vvv-custom.yml` file,
manually clone this repo to a new directory inside your `www`
folder in your VVV directory. You can then add the site to
the `vvv-custom.yml` as usual, without the `repo` parameter.

## Code Style

This repo uses the [PSR-2](http://www.php-fig.org/psr/psr-2/)
style for all PHP code. If you're accustomed to writing code
for WordPress, this is notably different.

## Tests

To run the unit tests, make sure to use the `phpunit` binary
that is installed by Composer:

```bash
./provision/vendor/bin/phpunit
```
