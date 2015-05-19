<?php

namespace OroCRM\Bundle\DotmailerBundle\Tests\Functional\Fixtures;

use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use Oro\Bundle\SegmentBundle\Entity\Segment;
use Oro\Bundle\SegmentBundle\Entity\SegmentType;

class LoadSegmentData extends AbstractFixture implements DependentFixtureInterface
{
    /**
     * @var array Channels configuration
     */
    protected $data = [
        [
            'type' => 'dynamic',
            'name' => 'Test ML Segment',
            'description' => 'description',
            'entity' => 'OroCRM\Bundle\ContactBundle\Entity\Contact',
            'owner' => 'orocrm_dotmailer.business_unit.foo',
            'organization' => 'orocrm_dotmailer.organization.foo',
            'definition' => [
                'columns' => [
                    [
                        'name' => 'primaryEmail',
                        'label' => 'Primary Email',
                        'sorting' => '',
                        'func' => null,
                    ],
                ],
                'filters' => [
                    [
                        'columnName' => 'lastName',
                        'criterion' =>
                            [
                                'filter' => 'string',
                                'data' =>
                                    [
                                        'value' => 'Case',
                                        'type' => '1',
                                    ],
                            ],
                    ],
                ],
            ],
            'reference' => 'orocrm_dotmailer.segment.first',
        ],
    ];

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        foreach ($this->data as $data) {
            $entity = new Segment();
            $this->resolveReferenceIfExist($data, 'owner');
            $this->resolveReferenceIfExist($data, 'organization');
            $data['definition'] = json_encode($data['definition']);
            $data['type'] = $manager
                ->getRepository('OroSegmentBundle:SegmentType')
                ->find($data['type']);
            $this->setEntityPropertyValues($entity, $data, ['reference']);

            $this->addReference($data['reference'], $entity);

            $manager->persist($entity);
        }

        $manager->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return [
            'OroCRM\Bundle\DotmailerBundle\Tests\Functional\Fixtures\LoadUserData'
        ];
    }
}