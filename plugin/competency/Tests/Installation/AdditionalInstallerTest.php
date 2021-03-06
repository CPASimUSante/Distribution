<?php

namespace HeVinci\CompetencyBundle\Installation;

use Claroline\CoreBundle\Entity\Resource\ResourceType;
use Claroline\CoreBundle\Library\Testing\TransactionalTestCase;
use Doctrine\ORM\EntityManagerInterface;

class AdditionalInstallerTest extends TransactionalTestCase
{
    public function testPostInstall()
    {
        $container = $this->client->getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $actionRepo = $em->getRepository('ClarolineCoreBundle:Resource\MenuAction');
        $roleRepo = $em->getRepository('ClarolineCoreBundle:Role');

        //It should already be installed after a claroline:install --env=test.
        $em->remove($actionRepo->findOneByName('manage-competencies'));
        $em->remove($roleRepo->findOneByName('ROLE_COMPETENCY_MANAGER'));
        $em->flush();

        $installer = new AdditionalInstaller();
        $installer->setContainer($container);
        $installer->postInstall();

        $this->assertNotNull($actionRepo->findOneByName('manage-competencies'));
        $this->assertNotNull($roleRepo->findOneByName('ROLE_COMPETENCY_MANAGER'));
    }

    private function createActivityType(EntityManagerInterface $em)
    {
        $activityType = new ResourceType();
        $activityType->setName('activity');
        $em->persist($activityType);
        $em->flush();
    }
}
