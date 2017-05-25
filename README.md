# vvv-base

Base repository for new VVV2 sites

**Warning:** This is still a work-in-progress. Use at your own risk. Please report any issues you may find.

## Overview

As of [version 2.0.0](https://varyingvagrantvagrants.org/blog/2017/03/13/varying-vagrant-vagrants-2-0-0.html) of VVV, 
a `vvv-custom.yml` file can be created to control the configuration of sites. One of the config file [options](https://varyingvagrantvagrants.org/docs/en-US/vvv-config/)
is the ability to define a repo that can be cloned to provide consistent site configuration.

This project is intended to be a generic base repo for use with VVV2. All customizations should be done in the
`custom` array for the site in the `vvv-custom.yml` file. Here's an example for one site, showing all available [options](#options):

```yml
sites:
    example-site:
        repo: https://github.com/JPry/vvv-base.git
        hosts:
            - example.local
            - subdomain.example.local
        custom:
            admin_user: example
            admin_password: example_password
            admin_email: example@localhost.local
            title: An Awesome Example Site
            db_prefix: ex_
            multisite: false
            xipio: true
            version: latest
            locale: en_US
            plugins:
                - jetpack
                - { plugin: "redirect-emails-on-staging", activate: true }
                - { plugin: "https://github.com/afragen/github-updater/archive/6.3.1.zip", force: true, activate: true }
            themes:
                - twentyeleven
            delete_default_plugins: true
            delete_default_themes: true
            wp_content: https://github.com/jquery/jquery-wp-content.git
            wp: true
            htdocs: https://github.com/salcode/example-project-w-gitignore.git

```

## Getting started

To use this repository with VVV2, [create a new site in `vvv-custom.yml`](https://varyingvagrantvagrants.org/docs/en-US/vvv-config/). For the `repo` setting, specify this repository (`https://github.com/JPry/vvv-base.git`). This is all you need to get started:

```yml
sites:
    example-site:
        repo: https://github.com/JPry/vvv-base.git
```

Once that has been added to your `vvv-custom.yml` file, simply run a provision. It's easiest to provision only the site you just created using this command:

```bash
vagrant provision --provision-with site-example-site
```

Vagrant will generate the files in the filesystem for you, and configure the site based on the options below.

## Options

None of the options for the `custom` array are required. If any option is not provided, then the default will be used.

### `admin_user`

This is the username to use for the primary admin user. Default is `admin`.

### `admin_password`

This is the password to use for the primary admin user. Default is `password`.

### `admin_email`

This is the email address to use for the primary admin user. Default is `admin@localhost.local`

### `title`

This is the title to use for the site. Default is `My Awesome VVV site`.

### `db_prefix`

The database prefix to use. Defaults to `wp_`.

### `multisite`

Whether to create a multisite installation. There are three valid values:

* `false` - This is the default option. A non-multisite WordPress site will be created.
* `true` - Create a **subdirectory** multisite.
* `subdomain` or `subdomains` - Create a **subdomain** multisite.

**Note:** Any truthy value will create a subdirectory multisite.

### `xipio`

Whether to enable support for [xip.io](http://xip.io). This will set up the Nginx config to include the Xip.io version of the first domain. Defaults to `true`.

### `version`

The WordPress version to install. Defaults to `latest`.

### `locale`

The locale to use when installing WordPress. Defaults to `en_US`.

### `plugins`

A list of plugins to install using WP CLI. There are two ways to specify what plugins to install:

1. Use the plugin slug, zip, or url. This value can be anything [accepted by WP CLI](https://developer.wordpress.org/cli/commands/plugin/install/)
1. Specify an array of options for a plugin. These options are specific to WP CLI. Available options are:
 * `plugin` - The plugin slug, the path to a local zip file, or URL to a remote zip file.
 * `version` - The particular version of the plugin to install. *(Note: This may only work for plugins from the wordpress.org repository)*
 * `force` - Overwrite an existing installed version of the plugin.
 * `activate` - Activate the plugin after installation.
 * `activate-network` - *(Multisite only)* Network activate the plugin after installation.

Defaults to an empty array.

### `themes`

A list of themes to install using WP CLI. There are two ways to specify what themes to install:

1. Use the theme slug, zip, or url. This value can be anything [accepted by WP CLI](https://developer.wordpress.org/cli/commands/theme/install/)
1. Specify an array of options for a theme. These options are specific to WP CLI. Available options are:
 * `theme` - The theme slug, the path to a local zip file, or URL to a remote zip file.
 * `version` - The particular version of the theme to install. *(Note: This may only work for themes from the wordpress.org repository)*
 * `force` - Overwrite an existing installed version of the theme.
 * `activate` - Activate the theme after installation.

Defaults to an empty array.

### `delete_default_plugins`

Delete the default plugins. Defaults to `false`. Currently "Akismet" and "Hello Dolly" are removed. 

### `delete_default_themes`

Delete the default themes. Defaults to `false`. Currently the following themes are removed with this set:

* `twentytwelve`
* `twentythirteen`
* `twentyfourteen`
* `twentyfifteen`
* `twentysixteen`
* `twentyseventeen`

### `wp-content`

Deprecated. Use `wp_content`.

### `wp_content`

Set a git repo to clone as the `htdocs/wp-content` directory. Defaults to `false`.

Using this option prevent the following options from having any effect:
* `plugins`
* `themes`
* `delete_default_plugins`
* `delete_default_themes`

### `htdocs`

Similar to the `wp_content` setting, use this option to set a Git repo to clone as the root `htdocs/` directory. Defaults to `false`.

Using this option prevent the following options from having any effect:
* `wp_content`
* `plugins`
* `themes`
* `delete_default_plugins`
* `delete_default_themes`
* `wp`

### `wp`

Whether to do any WordPress setup whatsoever. Defaults (naturally) to `true`.

If you're going to be building a non-WordPress local site, or if you have a very custom WordPress setup to install, this will skip the automation around downloading, configuring, and installing WordPress.
