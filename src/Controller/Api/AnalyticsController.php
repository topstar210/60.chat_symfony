<?php

namespace App\Controller\Api;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api", name="analy")
 */
class AnalyticsController extends BaseApiController
{
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Returns promotion stats.
     *
     * @Route("/promoters", methods={"GET", "POST"}, name="promoters")
     * @param Request $request
     * @return JsonResponse
     */
    public function promotersAction(Request $request)
    {
        $results = $this->userRepository->getPromoterStats();
        if ($results) {
            $stats = array();
            foreach ($results as $result) {
                $promoCode = $result['promo_code'];
                $date = $result['date'];
                $stats[$promoCode][$date] = (int) $result['total'];
            }
        }

        return $this->getSuccessJson(array(
            'stats' => $stats,
        ));
    }
}
