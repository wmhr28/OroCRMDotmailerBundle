<?php

namespace OroCRM\Bundle\DotmailerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use Oro\Bundle\ConfigBundle\DependencyInjection\SettingsBuilder;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('oro_crm_dotmailer');

        SettingsBuilder::append(
            $rootNode,
            [
                'datafields_sync_interval' => ['value' => '1 day'],
                'force_sync_for_virtual_fields' => ['value' => SyncManager::FORCE_SYNC_VIRTUALS_ONLY],
            ]
        );

        return $treeBuilder;
    }
}
