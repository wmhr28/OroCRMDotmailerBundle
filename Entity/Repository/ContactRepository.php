<?php

namespace Oro\Bundle\DotmailerBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\DotmailerBundle\Entity\AddressBook;
use Oro\Bundle\DotmailerBundle\Entity\Contact;
use Oro\Bundle\MarketingListBundle\Entity\MarketingList;

class ContactRepository extends EntityRepository
{
    /**
     * @param AddressBook $addressBook
     *
     * @return QueryBuilder
     */
    public function getScheduledForExportByChannelQB(AddressBook $addressBook)
    {
        $qb = $this->createQueryBuilder('contact');
        $expr = $qb->expr();
        $joinCondition = $expr->andX()
            ->add('addressBookContacts.addressBook =:addressBook')
            ->add('addressBookContacts.scheduledForExport = TRUE');

        return $qb
            ->select(
                [
                    'addressBookContacts.id as addressBookContactId',
                    'contact.email',
                    'contact.originId',
                    'contact.firstName',
                    'contact.lastName',
                    'contact.gender',
                    'contact.fullName',
                    'contact.postcode',
                    'opt_in_type.id as optInType',
                    'email_type.id as emailType',
                ]
            )
            ->leftJoin('contact.opt_in_type', 'opt_in_type')
            ->leftJoin('contact.email_type', 'email_type')
            ->innerJoin(
                'contact.addressBookContacts',
                'addressBookContacts',
                Join::WITH,
                $joinCondition
            )
            ->setParameter('addressBook', $addressBook);
    }

    /**
     * @param array         $emails
     * @param MarketingList $marketingList
     *
     * @return bool
     */
    public function isUnsubscribedFromAddressBookByMarketingList(array $emails, MarketingList $marketingList)
    {
        $qb = $this->createQueryBuilder('contact');
        $expr = $qb->expr();
        $subscribedStatuses = [Contact::STATUS_SUBSCRIBED, Contact::STATUS_SOFTBOUNCED];

        $qb->select('COUNT(contact.id)')
            ->leftJoin('contact.addressBookContacts', 'addressBookContacts')
            ->leftJoin('addressBookContacts.addressBook', 'addressBook')
            ->where(
                $expr->eq('addressBook.marketingList', ':marketingList')
            )
            ->andWhere($expr->in('contact.email', $emails))
            ->andWhere(
                $expr->orX()
                    ->add($expr->notIn('contact.status', $subscribedStatuses))
                    ->add($expr->notIn('addressBookContacts.status', $subscribedStatuses))
            )->setParameters(
                [
                    'marketingList' => $marketingList
                ]
            );

        return (bool)$qb->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param array $emails
     *
     * @return array associative array with emails as key and dotmailer contact ID as a value
     */
    public function getContactIdsByEmails(array $emails)
    {
        $qb = $this->createQueryBuilder('contact');

        $result = $qb
            ->select('contact.originId, contact.email')
            ->where($qb->expr()->in('contact.email', ':emails'))
            ->setParameter('emails', $emails)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($result as $record) {
            $map[$record['email']] = $record['originId'];
        }

        return $map;
    }

    /**
     * @param Channel $channel
     */
    public function bulkRemoveNotExportedContacts(Channel $channel)
    {
        $qb = $this->createQueryBuilder('contact');
        $qb->delete()
            ->where('contact.channel = :channel')
            ->andWhere('contact.originId IS NULL')
            ->getQuery()
            ->execute(['channel' => $channel]);
    }

    /**
     * Get contacts with data fields updates, which should be synced into entities
     *
     * @param Channel $channel
     * @return QueryBuilder
     */
    public function getScheduledForEntityFieldsUpdateQB(Channel $channel)
    {
        $qb = $this->createQueryBuilder('contact');

        return $qb
            ->select(
                [
                    'contact.id as contactId',
                    'contact.originId',
                    'contact.email',
                    'contact.dataFields',
                    'addressBookContact.marketingListItemClass as entityClass',
                    'addressBookContact.marketingListItemId as entityId',
                ]
            )
            ->innerJoin('contact.addressBookContacts', 'addressBookContact')
            ->innerJoin('addressBookContact.addressBook', 'addressBook')
            ->where('addressBookContact.marketingListItemId is NOT NULL OR addressBook.isCreateEntities = :isCreateEntities')
            ->setParameter('isCreateEntities', true)
            ->andWhere('addressBookContact.marketingListItemClass is NOT NULL')
            ->andWhere('contact.scheduledForFieldsUpdate = :isScheduled')
            ->setParameter('isScheduled', true)
            ->andWhere('contact.channel = :channel')
            ->setParameter('channel', $channel);
    }

    /**
     * @param array $contactIds
     */
    public function resetScheduledForEntityFieldUpdateFlag($contactIds)
    {
        $qb = $this->createQueryBuilder('contact');
        $qb->update()
           ->where($qb->expr()->in('contact.id', ':contactIds'))
           ->set('contact.scheduledForFieldsUpdate', ':scheduledForFieldsUpdate')
           ->getQuery()
           ->execute(['contactIds' => $contactIds, 'scheduledForFieldsUpdate' => false]);
    }
}
