<?php

namespace JPry\VVVBase\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Define the configuration for an individual site.
 *
 * @package JPry\VVVBase\Configuration
 */
class Site implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     *
     * @author Jeremy Pry
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $site        = $treeBuilder->root('site');

        // Set up the site config options
        $site
            ->ignoreExtraKeys(false) // Counter-intuitive: ignore extra keys, and don't delete them
            ->children()
                ->scalarNode('repo')->end()
                ->scalarNode('branch')->end()
                ->scalarNode('vm_dir')->end()
                ->scalarNode('local_dir')->end()
            ->booleanNode('skip_provisioning')->end()
            ->booleanNode('allow_customfile')->end()
            ->scalarNode('nginx_upstream')->end()
            ->arrayNode('hosts')
            ->defaultValue(array())
            ->prototype('scalar')
            ->end()
            ->end()
            ->arrayNode('custom')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('admin_user')->defaultValue('admin')->end()
            ->scalarNode('admin_password')->defaultValue('password')->end()
            ->scalarNode('admin_email')->defaultValue('admin@localhost.local')->end()
            ->scalarNode('title')->defaultValue('My Awesome VVV Site')->end()
            ->scalarNode('db_prefix')->defaultValue('wp_')->end()
            ->booleanNode('multisite')->defaultFalse()->end()
            ->booleanNode('xipio')->defaultTrue()->end()
            ->scalarNode('version')->defaultValue('latest')->end()
                        ->scalarNode('locale')->defaultValue('en_US')->end()
                        ->arrayNode('plugins')
                            ->prototype('array')
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(function ($v) {return array('plugin' => $v);})
                                ->end()
                                ->children()
                                    ->scalarNode('plugin')->isRequired()->end()
                                    ->scalarNode('version')->end()
                                    ->booleanNode('force')->defaultFalse()->end()
                                    ->booleanNode('activate')->defaultFalse()->end()
                                    ->booleanNode('activate-network')->defaultFalse()->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('themes')
                            ->prototype('array')
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(function ($v) {return array('theme' => $v);})
                                ->end()
                                ->children()
                                    ->scalarNode('theme')->isRequired()->end()
                                    ->scalarNode('version')->end()
                                    ->booleanNode('force')->defaultFalse()->end()
                                    ->booleanNode('activate')->defaultFalse()->end()
                                ->end()
                            ->end()
                        ->end()
                        ->booleanNode('delete_default_plugins')->defaultFalse()->end()
                        ->booleanNode('delete_default_themes')->defaultFalse()->end()
                        ->scalarNode('wp_content')->defaultNull()->end()
                        ->booleanNode('wp')->defaultTrue()->end()
                        ->booleanNode('download_wp')->defaultTrue()->end()
                        ->scalarNode('htdocs')->defaultNull()->end()
                        ->arrayNode('skip_plugins')
                            ->defaultValue(array())
                            ->prototype('scalar')
                            ->end()
                        ->end()

                        // These are old config values that aren't used anymore.
                        ->scalarNode('wp-content')
                            ->info('This option is deprecated. Use "wp_content" instead.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('prefix')
                            ->info('This option is deprecated. Use "db_prefix" instead.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('dbprefix')
                            ->info('This option is deprecated. Use "db_prefix" instead.')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
