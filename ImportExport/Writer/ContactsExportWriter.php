<?php

namespace OroCRM\Bundle\DotmailerBundle\ImportExport\Writer;

use Akeneo\Bundle\BatchBundle\Entity\StepExecution;
use Akeneo\Bundle\BatchBundle\Step\StepExecutionAwareInterface;

use DotMailer\Api\Exception;

use Psr\Log\LoggerInterface;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;

use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use Oro\Bundle\ImportExportBundle\Context\ContextInterface;
use Oro\Bundle\ImportExportBundle\Context\ContextRegistry;
use Oro\Bundle\ImportExportBundle\Writer\CsvEchoWriter;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\EntityExtendBundle\Entity\AbstractEnumValue;

use OroCRM\Bundle\DotmailerBundle\ImportExport\DataConverter\ContactDataConverter;
use OroCRM\Bundle\DotmailerBundle\Entity\AddressBookContactsExport;
use OroCRM\Bundle\DotmailerBundle\Provider\Transport\DotmailerTransport;
use OroCRM\Bundle\DotmailerBundle\Provider\Transport\Iterator\RemovedContactsExportIterator;
use OroCRM\Bundle\DotmailerBundle\Model\ImportExportLogHelper;

class ContactsExportWriter extends CsvEchoWriter implements StepExecutionAwareInterface
{
    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @var DotmailerTransport
     */
    protected $transport;

    /**
     * @var StepExecution
     */
    protected $stepExecution;

    /**
     * @var ContextInterface
     */
    protected $context;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ImportExportLogHelper
     */
    protected $logHelper;

    /**
     * @var ContextRegistry
     */
    protected $contextRegistry;

    /**
     * @param ManagerRegistry       $registry
     * @param DotmailerTransport    $transport
     * @param ContextRegistry       $contextRegistry
     * @param LoggerInterface       $logger
     * @param ImportExportLogHelper $logHelper
     */
    public function __construct(
        ManagerRegistry $registry,
        DotmailerTransport $transport,
        ContextRegistry $contextRegistry,
        LoggerInterface $logger,
        ImportExportLogHelper $logHelper
    ) {
        $this->registry = $registry;
        $this->transport = $transport;
        $this->contextRegistry = $contextRegistry;
        $this->logger = $logger;
        $this->logHelper = $logHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function write(array $items)
    {
        /** @var EntityManager $manager */
        $manager = $this->registry->getManager();
        try {
            $manager->beginTransaction();
            $addressBookItems = [];
            $addressBookContactIds = [];

            foreach ($items as $item) {
                $addressBookOriginId = $item[RemovedContactsExportIterator::ADDRESS_BOOK_KEY];
                if (!isset($addressBookItems[$addressBookOriginId])) {
                    $addressBookItems[$addressBookOriginId] = [];
                }
                $addressBookContactIds[$addressBookOriginId][] = $item[ContactDataConverter::ADDRESS_BOOK_CONTACT_ID];

                unset($item[ContactDataConverter::ADDRESS_BOOK_CONTACT_ID]);
                unset($item[RemovedContactsExportIterator::ADDRESS_BOOK_KEY]);

                $addressBookItems[$addressBookOriginId][] = $item;

                $this->context->incrementReplaceCount();
            }
            foreach ($addressBookItems as $addressBookOriginId => $items) {
                $this->updateAddressBookContacts(
                    $items,
                    $addressBookContactIds[$addressBookOriginId],
                    $addressBookOriginId
                );
            }

            $manager->flush();
            $manager->commit();
            $manager->clear();
        } catch (\Exception $exception) {
            $manager->rollback();
            if (!$manager->isOpen()) {
                $this->registry->resetManager();
            }

            throw $exception;
        }
    }

    /**
     * @param array         $items
     * @param array         $addressBookContactIds
     * @param int           $addressBookOriginId
     */
    protected function updateAddressBookContacts(array $items, array $addressBookContactIds, $addressBookOriginId)
    {
        $manager = $this->registry->getManager();

        ob_start();
        parent::write($items);
        $csv = ob_get_contents();
        ob_end_clean();

        /**
         * Reset CsfFileStreamWriter
         */
        $this->fileHandle = null;
        $this->header = null;
        try {
            $importStatus = $this->transport->exportAddressBookContacts($csv, $addressBookOriginId);
        } catch (Exception $e) {
            $this->logger
                ->warning(
                    "Export Contacts to Address Book {$addressBookOriginId} failed. Message: {$e->getMessage()}"
                );

            return;
        }

        $exportEntity = new AddressBookContactsExport();
        $importId = (string)$importStatus->id;
        $exportEntity->setImportId($importId);

        $channel = $this->getChannel();
        $addressBook = $manager->getRepository('OroCRMDotmailerBundle:AddressBook')
            ->findOneBy(['originId' => $addressBookOriginId, 'channel' => $channel]);
        $exportEntity->setAddressBook($addressBook);

        $className = ExtendHelper::buildEnumValueClassName('dm_import_status');
        /** @var AbstractEnumValue $status */
        $status = $manager->find($className, (string)$importStatus->status);
        $exportEntity->setStatus($status);
        $exportEntity->setChannel($channel);

        $manager->getRepository('OroCRMDotmailerBundle:AddressBookContact')
            ->bulkUpdateAddressBookContactsExportId($addressBookContactIds, $importId);

        $manager->persist($exportEntity);

        $this->logBatchInfo($items, $addressBookOriginId);
    }

    /**
     * @param array $items
     * @param int   $addressBookOriginId
     */
    protected function logBatchInfo(array $items, $addressBookOriginId)
    {
        $itemsCount = count($items);

        $stepExecutionTime = $this->logHelper->getFormattedTimeOfStepExecution($this->stepExecution);
        $memoryUsed = $this->logHelper->getMemoryConsumption();

        $message = "$itemsCount Contacts exported to Dotmailer Address Book with Id: $addressBookOriginId.";
        $message .= " Elapsed Time: {$stepExecutionTime}. Memory used: $memoryUsed MB.";

        $this->logger->info($message);
    }

    /**
     * @param StepExecution $stepExecution
     */
    public function setStepExecution(StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;
        $this->context = $this->contextRegistry->getByStepExecution($stepExecution);
        $this->transport->init($this->getChannel()->getTransport());

        $this->setImportExportContext($this->context);
    }

    /**
     * @return Channel
     */
    protected function getChannel()
    {
        return $this->registry->getRepository('OroIntegrationBundle:Channel')
            ->getOrLoadById($this->context->getOption('channel'));
    }
}
