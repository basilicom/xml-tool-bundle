<?php

namespace Basilicom\XmlToolBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('basilicom_xml_tool');
        $rootNode
            ->children()
                ->arrayNode('api')
                    ->fixXmlConfig('endpoint')
                    ->children()
                        ->scalarNode('enabled')->defaultValue('false')->end()
                        ->arrayNode('endpoints')->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('path')->end()
                                    ->scalarNode('root')->defaultValue('root')->end()
                                    ->scalarNode('token')->defaultValue('')->end()
                                    ->scalarNode('xslt')->defaultValue('')->end()
                                    ->scalarNode('include_variants')->defaultValue('false')->end()
                                    ->scalarNode('omit_relation_object_fields')->defaultValue('false')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end() // twitter
            ->end();
        return $treeBuilder;
    }
}
