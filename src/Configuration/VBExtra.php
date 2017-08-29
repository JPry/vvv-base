<?php

namespace JPry\VVVBase\Configuration;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Config for the "vvvbase" key in the vvv-custom.yml file.
 *
 * @package JPry\VVVBase\Configuration
 */
class VBExtra implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $builder = new TreeBuilder();
        $base    = $builder->root('vvvbase');

        $base
            ->ignoreExtraKeys()
            ->children()
                ->arrayNode('vvvbase')
                ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('db')
                        ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('host')->defaultValue('localhost')->end()
                                ->scalarNode('user')->defaultValue('root')->end()
                                ->scalarNode('pass')->defaultValue('root')->end()
                            ->end()
                        ->end()
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
                    ->end()
                ->end()
            ->end()
        ;

        return $builder;
    }
}
