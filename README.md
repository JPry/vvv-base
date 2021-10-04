# vvv-base

[![circle ci status badge](https://circleci.com/gh/JPry/vvv-base/tree/develop.svg?style=shield&circle-token=2a4b06e9259652a98d26b701ab76636f38d95cc8)](https://circleci.com/gh/JPry/vvv-base/tree/master)
[![Average time to resolve an issue](http://isitmaintained.com/badge/resolution/JPry/vvv-base.svg)](http://isitmaintained.com/project/JPry/vvv-base "Average time to resolve an issue")
[![Percentage of issues still open](http://isitmaintained.com/badge/open/JPry/vvv-base.svg)](http://isitmaintained.com/project/JPry/vvv-base "Percentage of issues still open")

---
# THIS REPO HAS BEEN ARCHIVED

It has been many years since I have used or worked on VVV, so I am archiving this repo.
---

Base repository for new VVV2 sites

Please [report any issues](https://github.com/JPry/vvv-base/issues) you may find.

## Overview

As of [version 2.0.0](https://varyingvagrantvagrants.org/blog/2017/03/13/varying-vagrant-vagrants-2-0-0.html) of VVV, 
a `vvv-custom.yml` file can be created to control the configuration of sites. One of the config file [options](https://varyingvagrantvagrants.org/docs/en-US/vvv-config/)
is the ability to define a repo that can be cloned to provide consistent site configuration.

This project is intended to be a generic base repo for use with VVV2. All customizations should be done in the
`custom` array for the site in the `vvv-custom.yml` file. Here's an example for one site, showing all available [options](#options):

```yml
sites:
    mysite:
        repo: https://github.com/JPry/vvv-base.git
        hosts:
            - mysite.local
            - subdomain.mysite.local
        custom:
            admin_user: mysite
            admin_password: mysite_password
            admin_email: mysite@localhost.local
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
    mysite:
        repo: https://github.com/JPry/vvv-base.git
```

Once that has been added to your `vvv-custom.yml` file, simply run a provision. It's easiest to provision only the site you just created using this command:

```bash
vagrant provision --provision-with site-mysite
```

Vagrant will generate the files in the filesystem for you, and configure the site based on the options below.

## Options

None of the options for the `custom` array are required. If any option is not provided, then the default will be used.
Options with ~strikethrough~ have been deprecated, or their use is otherwise discouraged.

Option | Notes | Default 
:----: | ----- | ------- 
`admin_user` | This is the username to use for the primary admin user. | `admin` 
`admin_password` | This is the password to use for the primary admin user. | `password` 
`admin_email` | This is the email address to use for the primary admin user. | `admin@localhost.local`
`title` | This is the title to use for the site. | `My Awesome VVV site`
`db_prefix`<br>~`prefix`<br>`dbprefix`~ | <span>The database prefix to use. | `wp_`
`multisite` | Whether to create a multisite installation. There are three valid values:<ul><li>`false` - A non-multisite WordPress site will be created.</li><li>`true` - Create a **subdirectory** multisite.</li><li>`subdomain` or `subdomains` - Create a **subdomain** multisite.</li></ul><br>**Note:** Any truthy value will create a subdirectory multisite. | `false` 
`xipio` | Whether to enable support for [xip.io](http://xip.io). This will set up the Nginx config to include the Xip.io version of the first domain. | `true` 
`version` | The WordPress version to install. | `latest` 
`locale` | The locale to use when installing WordPress. | `en_US` 
`plugins` | A list of plugins to install using WP CLI. There are two ways to specify what plugins to install:<ol><li>Use the plugin slug, zip, or url. This value can be anything [accepted by WP CLI](https://developer.wordpress.org/cli/commands/plugin/install/)</li><li>Specify an array of options for a plugin. These options are specific to WP CLI. Available options are:<ul><li>`plugin` - The plugin slug, the path to a local zip file, or URL to a remote zip file.</li><li>`version` - The particular version of the plugin to install. *(Note: This may only work for plugins from the wordpress.org repository)*</li><li>`force` - Overwrite an existing installed version of the plugin.</li><li>`activate` - Activate the plugin after installation.</li><li>`activate-network` - *(Multisite only)* Network activate the plugin after installation.</li></ul></li></ol>| Empty array 
`themes` | A list of themes to install using WP CLI. There are two ways to specify what themes to install:<ol><li>Use the theme slug, zip, or url. This value can be anything [accepted by WP CLI](https://developer.wordpress.org/cli/commands/theme/install/)</li><li>Specify an array of options for a theme. These options are specific to WP CLI. Available options are:<ul><li>`theme` - The theme slug, the path to a local zip file, or URL to a remote zip file.</li><li>`version` - The particular version of the theme to install. *(Note: This may only work for themes from the wordpress.org repository)*</li><li>`force` - Overwrite an existing installed version of the theme.</li><li>`activate` - Activate the theme after installation.</li></ul></li></ol> | Empty array
`delete_default_plugins` | Delete the default plugins. Currently "Akismet" and "Hello Dolly" are removed. | `false`
`delete_default_themes` | Delete the default themes. Currently the following themes are removed with this set:<ul><li>`twentytwelve`</li><li>`twentythirteen`</li><li>`twentyfourteen`</li><li>`twentyfifteen`</li><li>`twentysixteen`</li><li>`twentyseventeen`</li></ul> | `false`
`wp_content`<br>~`wp-content`~ | Set a git repo to clone as the `htdocs/wp-content` directory. Using this option prevent the following options from having any effect:<ul><li>`plugins`</li><li>`themes`</li><li>`delete_default_plugins`</li><li>`delete_default_themes`</li></ul> | `false`
`htdocs` | Similar to the `wp_content` setting, use this option to set a Git repo to clone as the root `htdocs/` directory. Using this option prevent the following options from having any effect:<ul><li>`wp_content`</li><li>`plugins`</li><li>`themes`</li><li>`delete_default_plugins`</li><li>`delete_default_themes`</li><li>`wp`</li></ul> | `false`
`download_wp` | Whether to download the core WordPress files. | `true`
`wp` | Whether to do any WordPress setup whatsoever.<br><br>If you're going to be building a non-WordPress local site, or if you have a very custom WordPress setup to install, this will skip the automation around downloading, configuring, and installing WordPress. | `true` (naturally)
`skip_plugins` | A list of plugins to **skip** installing. The plugins in this list are ones that you have defined in the [Global Settings](#global-settings) that you do not want for a particular site.<br><br>**Note:** Unlike the `plugins` setting, this setting is a list of **only the plugin slug**. As an example, if you have Jetpack in your global plugin list as `- { plugin: jetpack, activate: true }`, in this list you only need `- jetpack`. | Empty array.

## Global Settings

In addition to the site-specific settings listed above, there are some global settings that can be used to affect
all of the sites in your config. These options are all keyed under a single global key, `vvvbase`. Here's an example
of what your `vvv-custom.yml` might look like with this key in place:

```yml
sites:
    mysite:
        repo: https://github.com/JPry/vvv-base.git

    # Other sites defined here

vvvbase:
    db:
        host: localhost
        user: root
        pass: root

    plugins:
        - jetpack

    themes:
        - hestia
```

Here are all of the Global Settings and how they work:

Setting | Notes
:-----: | -----
`db` | Settings for the database. Using these settings allow you to define a custom database connection for your sites. Keys include:<ul><li>`host` - The database host name</li><li>`user` - The database username</li><li>`pass` - The database password</li></ul>
`plugins` | This is a list of plugins that should be installed for **all** of your sites. This works exactly the same way as the `plugins` setting for an individual site. Refer to the options table above for exact usage details.
`themes` | This is a list of themes that should be installed for **all** of your sites. This works exactly the same way as the `themes` setting for an individual site. Refer to the options table above for exact usage details.

## Contributing

Contributions are welcome! Please see our [Contribution guidelines](https://github.com/JPry/vvv-base/blob/develop/.github/CONTRIBUTING.md).
