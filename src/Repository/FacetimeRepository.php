<?php

namespace App\Repository;

use App\Entity\User;

class FacetimeRepository extends Base
{
    /**
     * {@inheritDoc}
     */
    public function getRepositoryName()
    {
        return 'App\Entity\Facetime';
    }


    public function matchPeople($me,$myMeet)
    {
        $gender = ($me->getGender() == 'Male')?'Female':'Male';

        $conn = $this->getEntityManager()
            ->getConnection();


        $sql ='SELECT COUNT(*) as total FROM facetime f 
        WHERE f.status = 0
        AND f.last_modified >="'.date("Y-m-d H:i:s",strtotime("-2 minutes")).'"';

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $count = $stmt->fetchAll();
        $total_online = $count[0]['total'];

        if($total_online> 4){
            $sql ='SELECT * FROM facetime f 
            LEFT JOIN facetime_channel fc ON ((f.hash = fc.client_1) OR f.hash = fc.client_2)
            WHERE f.hash <>"'.$me->getHash().'" 
            AND f.status = 0
            AND fc.id is null
            AND (fc.gender="'.$gender.'" OR fc.gender="Unknown")
            AND f.last_modified >="'.date("Y-m-d H:i:s",strtotime("-2 minutes")).'"';
        }else{
            $sql ='SELECT * FROM facetime f 
            LEFT JOIN facetime_channel fc ON ((f.hash = fc.client_1) OR f.hash = fc.client_2)
            WHERE f.hash <>"'.$me->getHash().'" 
            AND fc.id is null
            AND f.status = 0
            AND f.last_modified >="'.date("Y-m-d H:i:s",strtotime("-2 minutes")).'"';
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $partner_online = $stmt->fetchAll();
		
        if(!empty($myMeet)){
			
            $filter = $partner_online;
            foreach ($filter as $k=>$v){
                if(array_key_exists(trim($filter[$k]['name']),$myMeet)){
                    unset($filter[$k]);
                }
            }
			
            if(!empty($filter)){
                $partner_online = $filter;
            }
        }

        if($partner_online) {
            $random_key = array_rand($partner_online, 1);
        }else{
            $random_key = -1;
        }
        if($random_key>=0){
            $partner = $partner_online[$random_key];
        }else{
            $partner = null;
        }

        return $partner;

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

        $date = date("y-m-d H:i:s",strtotime("-10 minutes"));
        $conn = $this->getEntityManager()
            ->getConnection();
        $sql = 'DELETE  FROM facetime_channel WHERE created < "'.$date.'";';
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return null;
    }

    public function removeOldUser(){

        $date = date("y-m-d H:i:s",strtotime("-30 days"));
        $conn = $this->getEntityManager()
            ->getConnection();
        $sql = 'DELETE  FROM facetime WHERE date_created < "'.$date.'";';
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return null;
    }

    public function getOnlinePeople(){

        $date = date("Y-m-d H:i:s",strtotime("-2 minutes"));

        $conn = $this->getEntityManager()
            ->getConnection();

        $sql = 'SELECT count(*) as total FROM facetime fc 
                WHERE last_modified > "'.$date.'"';

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $room = $stmt->fetchAll();

        if(!empty($room[0])){
            return $room[0]['total'];
        }else{
            return 0;
        }

    }
}
