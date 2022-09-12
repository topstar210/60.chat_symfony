<?php

namespace App\Repository;

class UserDeviceRepository extends Base
{
    /**
     * {@inheritDoc}
     */
    public function getRepositoryName()
    {
        return 'App\Entity\UserDevice';
    }

    /**
     * Find a device by an id.
     *
     * @param string $id
     *
     * @return array|null
     */
    public function findDeviceById($id)
    {
        return $this->getRepository()->findBy(array('device_id' => $id));
    }
}
