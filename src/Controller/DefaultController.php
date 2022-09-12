<?php

namespace App\Controller;

use App\Controller\Api\BaseApiController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends BaseApiController
{
    /**
     * About.
     *
     * @Route("/about", methods={"GET", "POST"}, name="about")
     * @param Request $request
     * @return
     */
    public function aboutAction(Request $request)
    {
        return $this->render('info/about.html.twig');
    }

    /**
     * Terms of Service
     *
     * @Route("/terms-service", methods={"GET", "POST"}, name="tos")
     * @param Request $request
     * @return Response
     */
    public function tosAction(Request $request)
    {
        return $this->render('info/tos.html.twig');
    }
}
