<?php

namespace OroCRM\Bundle\DotmailerBundle\Provider\Connector;

class CampaignsConnector extends AbstractDotmailerConnector
{
    const TYPE = 'campaign';

    /**
     * {@inheritdoc}
     */
    protected function getConnectorSource()
    {
        return new \EmptyIterator();
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel()
    {
        return 'orocrm.dotmailer.connector.campaign.label';
    }

    /**
     * {@inheritdoc}
     */
    public function getImportJobName()
    {
        // TODO: Implement getImportJobName() method.
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return self::TYPE;
    }
}
