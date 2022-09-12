<?php

namespace App\Controller\Api;

use App\Controller\Constant;
use DateTime;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;

class BaseApiController extends AbstractController
{
    protected $userRepository;

    /**
     * @param $data
     * @return JsonResponse
     */
    protected function getSuccessJson($data)
    {
        return new JsonResponse(array(
            'success' => true,
            'data' => $data,
        ));
    }

    /**
     * @param $error_message
     * @return JsonResponse
     */
    protected function getErrorJson($error_message)
    {
        return new JsonResponse([
            'error' => true,
            'data' => [
                'message' => $error_message,
            ],
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse|void
     * @throws Exception
     */
    protected function checkSecurity(Request $request)
    {
        if(isset($GLOBALS[Constant::$token]) && $GLOBALS[Constant::$token])
            return;

        $user = $this->getUser();
        if( $user ){
            if (!$token = $user->getToken()) {
                return $this->getErrorJson('No token found.');
            }
        } else{
            if (!$token = $request->get('token')) {
                return $this->getErrorJson('No token found.');
            }
        }

        if (!$user = $this->userRepository->findUserByToken($token)) {
            return $this->getErrorJson(sprintf('Token "%s" does not exist.', $token));
        }
        if (!$user->getEnabled()) {
            return $this->getErrorJson('Access denied.');
        }

        // store latest API call
        if (strpos($route = $request->get('_route'), '_api_') !== false) {
            // get all request parameters
            $params = array_merge($request->query->all(), $request->request->all());

            unset($params['token']);

            $user->setLastApiRoute($route);
            $user->setLastApiParams($params);
            $user->setLastApiCalledAt(new DateTime());

            $this->userRepository->update($user);
        }

        $GLOBALS[Constant::$user] = $user;
        $GLOBALS[Constant::$token] = $token;
    }

    protected function getSecurityLastError(Request $request)
    {
        $error = Security::AUTHENTICATION_ERROR;

        if ($request->attributes->has($error)) {
            return $request->attributes->get($error)->getMessage();
        }

        $session = $request->getSession();
        if ($session && $session->has($error)) {
            $message = $session->get($error)->getMessage();
            $session->remove($error);

            return $message;
        }
    }
}
