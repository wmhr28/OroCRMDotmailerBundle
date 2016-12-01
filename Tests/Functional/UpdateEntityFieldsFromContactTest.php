<?php

namespace Oro\Bundle\DotmailerBundle\Tests\Functional;

use DotMailer\Api\DataTypes\ApiContactList;

use Oro\Bundle\DotmailerBundle\Provider\Connector\ContactConnector;

/**
 * @dbIsolation
 */
class UpdateEntityFieldsFromContactTest extends AbstractImportExportTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->loadFixtures(
            [
                'Oro\Bundle\DotmailerBundle\Tests\Functional\Fixtures\LoadDotmailerContactData',
                'Oro\Bundle\DotmailerBundle\Tests\Functional\Fixtures\LoadDataFieldMappingData'
            ]
        );
    }

    public function testImport()
    {
        $entity = new ApiContactList();
        $entity[] = [
                'id' => 200,
                'email' => 'john.doe@example.com',
                'datafields' => [
                    [
                        'key'   => 'FIRSTNAME',
                        'value' => ['John Changed']
                    ],
                    [
                        'key'   => 'LASTNAME',
                        'value' => ['Doe Changed']
                    ],
                    [
                        'key'   => 'FULLNAME',
                        'value' => null
                    ],
                    [
                        'key'   => 'GENDER',
                        'value' => ['male']
                    ],
                    [
                        'key'   => 'LASTSUBSCRIBED',
                        'value' => ['2015-01-01T00:00:00z']
                    ],
                ]
            ];

        $this->resource->expects($this->any())
            ->method('GetAddressBookContacts')
            ->will($this->returnValue($entity));

        $channel = $this->getReference('oro_dotmailer.channel.first');
        $result = $this->runImportExportConnectorsJob(
            self::SYNC_PROCESSOR,
            $channel,
            ContactConnector::TYPE,
            [],
            $jobLog
        );
        $log = $this->formatImportExportJobLog($jobLog);
        $this->assertTrue($result, "Job Failed with output:\n $log");

        $contact = $this->getReference('oro_dotmailer.orocrm_contact.john.doe');
        $updatedContact = $this->managerRegistry->getRepository('Oro\Bundle\ContactBundle\Entity\Contact')
            ->find($contact->getId());
        //firstname left unchanged
        $this->assertEquals('John', $updatedContact->getFirstName());
        //lastname was changed based on mapping
        $this->assertEquals('Doe Changed', $updatedContact->getLastName());
    }
}
