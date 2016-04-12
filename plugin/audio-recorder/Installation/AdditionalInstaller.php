<?php

namespace Innova\AudioRecorderBundle\Installation;

use Claroline\InstallationBundle\Additional\AdditionalInstaller as BaseInstaller;
use Innova\AudioRecorderBundle\DataFixtures\DefaultData;

class AdditionalInstaller extends BaseInstaller
{

  public function postInstall()
  {
      $default = new DefaultData();
      $default->load($this->container->get('claroline.persistence.object_manager'));
  }

  /*public function postUninstall()
  {
    // should delete table
    $em = $this->container->get('doctrine.orm.entity_manager');
    $sql = 'DROP TABLE innova_audio_recorder_configuration;';
    $connection = $em->getConnection();
    $stmt = $connection->prepare($sql);
    $stmt->execute();
    $stmt->closeCursor();
  }*/

}
