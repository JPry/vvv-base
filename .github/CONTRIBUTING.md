# VVV-Base Contribution

## Pull Requests

**All Pull Requests should be made against the `develop` branch!**

## Branch Structure

This repo uses the [Git Flow](http://nvie.com/posts/a-successful-git-branching-model/)
model for development. The `master` branch is intended to always be in a stable
condition.

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

If you are interested in seeing Code Coverage information,
you will probably need to run PHPUnit from within Vagrant.
Otherwise, you'll likely end up with this message:

```
Error:         No code coverage driver is available
```

If you don't care to see Code Coverage information, just ignore
this message.
