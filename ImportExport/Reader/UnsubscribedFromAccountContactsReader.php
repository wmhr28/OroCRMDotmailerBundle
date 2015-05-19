<?php

namespace OroCRM\Bundle\DotmailerBundle\ImportExport\Reader;

use Oro\Bundle\IntegrationBundle\Entity\Status;

use OroCRM\Bundle\DotmailerBundle\Provider\Connector\AbstractDotmailerConnector;
use OroCRM\Bundle\DotmailerBundle\Provider\Connector\UnsubscribedContactsConnector;
use OroCRM\Bundle\DotmailerBundle\Provider\Transport\DotmailerTransport;

class UnsubscribedFromAccountContactsReader extends AbstractReader
{
    protected function afterInitialize()
    {
        /** @var DotmailerTransport $transport */
        $transport = $this->contextMediator->getInitializedTransport($this->getChannel());
        $lastSyncDate = $this->getLastSyncDate();
        $iterator = $transport->getUnsubscribedFromAccountsContacts($lastSyncDate);
        $this->setSourceIterator($iterator);
    }

    /**
     * @return \DateTime|null
     */
    protected function getLastSyncDate()
    {
        $repository = $this->managerRegistry->getRepository('OroIntegrationBundle:Status');

        /** @var Status $status */
        $status = $repository->findOneBy(
            [
                'code'      => Status::STATUS_COMPLETED,
                'channel'   => $this->getChannel(),
                'connector' => UnsubscribedContactsConnector::TYPE
            ],
            [
                'date' => 'DESC'
            ]
        );

        if (!$status) {
            return null;
        }

        $data = $status->getData();
        if (empty($data) || empty($data[AbstractDotmailerConnector::LAST_SYNC_DATE_KEY])) {
            return null;
        }

        return new \DateTime($data[AbstractDotmailerConnector::LAST_SYNC_DATE_KEY], new \DateTimeZone('UTC'));
    }
}