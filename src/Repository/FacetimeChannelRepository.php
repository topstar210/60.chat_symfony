<?php

namespace App\Repository;

use App\Entity\User;

class FacetimeChannelRepository extends Base
{
    /**
     * {@inheritDoc}
     */
    public function getRepositoryName()
    {
        return 'App\Entity\FacetimeChannel';
    }


    public function matchPeople()
    {
        $conn = $this->getEntityManager()
            ->getConnection();
        $sql = 'SELECT * FROM facetime_channel WHERE client_1 is null OR client_2 is null ORDER BY rand();';
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $room = $stmt->fetchAll();
        if($room){
            return $room[0];
        }else{
            return null;
        }

    }

    public function getMyChannel($hash){
        $conn = $this->getEntityManager()
            ->getConnection();
        $sql = "SELECT * FROM facetime_channel WHERE client_1 ='$hash' OR client_2 = '$hash'";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $room = $stmt->fetchAll();
        if($room){
            return $room[0];
        }else{
            return null;
        }
    }

    public function removeOldChannel(){
        $conn = $this->getEntityManager()
            ->getConnection();
        $sql = 'DELETE  FROM facetime_channel WHERE created < DATE_SUB( CURRENT_TIME(), INTERVAL 30 MINUTE);';
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return null;
    }
}
