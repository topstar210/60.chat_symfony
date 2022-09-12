<?php

namespace App\Controller\Api;

use App\Controller\Api\BaseApiController;
use App\Controller\Constant;
use App\Repository\UserRepository;
use App\Service\S3Wrapper;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\User;
use App\Utils\Inflector;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/facebook/auth", name="api_facebook_")
 */
class FacebookAuthController extends BaseApiController
{
    private $clientRegistry;
    private $s3wrapper;

    public function __construct(UserRepository $userRepository, ClientRegistry $clientRegistry, S3Wrapper $s3wrapper)
    {
        $this->userRepository = $userRepository;
        $this->clientRegistry = $clientRegistry;
        $this->s3wrapper = $s3wrapper;
    }

    /**
     * Facebook login.
     *
     * @param Request $request
     * @return string The user token
     *
     * @Route("/login", methods={"GET", "POST"}, name="login")
     */
    public function loginAction(Request $request)
    {
        $facebookUid = $request->get('uid');
        $accessToken = $request->get('access_token');

        if (!$facebookUid || !$accessToken) {
            return $this->getErrorJson('The Facebook user id and access token can not be empty.');;
        }

        $client = $this->clientRegistry->getClient('facebook_main');

        // get facebook user data
        if (!$facebookUser = $client->fetchUserFromToken($accessToken)) {
            return $this->getErrorJson('Invalid access token');
        }

        // validate user
        if (
            $this->userRepository->findUserByUsername($facebookUser['username']) ||
            $this->userRepository->findUserByEmail($facebookUser['email'])
        ) {
            return $this->getErrorJson('A different user with your username or email already exists.');
        }

        // set user object
        $user = new User();
        $user->setFacebookUid($facebookUser['id']);
        $user->setUsername($facebookUser['username']);
        $user->setPassword(Inflector::getRandomString(8));
        $user->setEmail($facebookUser['email']);
        $user->setName($facebookUser['name']);

        if (isset($facebookUser['gender'])) {
            $user->setGender($facebookUser['gender']);
        }
        if (isset($facebookUser['location']['name'])) {
            $user->setRegion($facebookUser['location']['name']);
        }
        if (isset($facebookUser['bio'])) {
            $user->setAboutme($facebookUser['bio']);
        }

        $user->setNotifyViaEmail(true);
        $user->setNotifyViaSms(false);
        $user->setEnabled(true);

        // create user
        $this->userRepository->create($user);

        // store user photo and assign to user
        if (isset($facebookUser['picture']) && !$facebookUser['picture']) {
            if ($data = file_get_contents($facebookUser['picture'])) {

                // upload photo
                $objectKeys = $this->s3wrapper
                    ->addFiles('users', $user->getTokenById(), array($facebookUser['picture'] => $data),
                        Constant::$conf_media_sizes['profile'], true);

                // assign photo
                $user->setPhoto($objectKeys['origin']);

                // update user
                $this->userRepository->update($user);
            }
        }

        return $this->getSuccessJson(array(
            'token' => $user->getToken(),
        ));
    }

    /**
     * Facebook register.
     *
     * @param Request $request
     * @return string The user token
     *
     * @Route("/register", methods={"GET", "POST"}, name="register")
     *
     * @deprecated
     */
    public function registerAction(Request $request)
    {
        $facebookUid = $request->get('uid');
        $accessToken = $request->get('access_token');
        $username = $request->get('username');
        $password = $request->get('password');

        if (!$facebookUid || !$accessToken || !$username || !$password) {
            return $this->getErrorJson('Missing required fields.');
        }

        if ($this->userRepository->findUserByUsername($username)) {
            return $this->getErrorJson('Username already exists');
        }

        $client = $this->clientRegistry->getClient('facebook_main');

        // get facebook user data
        if (!$facebookUser = $client->fetchUserFromToken($accessToken)) {
            return $this->getErrorJson('Invalid access token');
        }

        $email = $facebookUser->getEmail();
        if ($this->userRepository->findUserByEmail($email)) {
            return $this->getErrorJson('Email already registered.');
        }

        // set user object
        $user = new User();
        $user->setFacebookUid($facebookUid);
        $user->setUsername($username);
        $user->setPassword($password);
        $user->setEmail($facebookUser['email']);
        $user->setName($facebookUser['name']);

        if (isset($facebookUser['gender'])) {
            $user->setGender($facebookUser['gender']);
        }
        if (isset($facebookUser['location']['name'])) {
            $user->setRegion($facebookUser['location']['name']);
        }
        if (isset($facebookUser['bio'])) {
            $user->setAboutme($facebookUser['bio']);
        }

        $user->setNotifyViaEmail(true);
        $user->setNotifyViaSms(false);
        $user->setEnabled(true);

        if ($promoCode = $request->get('promo_code')) {
            $user->setPromoCode($promoCode);
        }

        // create user
        $this->userRepository->create($user);

        // store user photo and assign to user
        if (isset($facebookUser['picture']) && !$facebookUser['picture']) {
            if ($data = file_get_contents($facebookUser['picture'])) {

                // upload photo
                $objectKeys = $this->s3wrapper
                    ->addFiles('users', $user->getTokenById(), array($facebookUser['picture'] => $data),
                        Constant::$conf_media_sizes['profile'], true);

                // assign photo
                $user->setPhoto($objectKeys['origin']);

                // update user
                $this->userRepository->update($user);
            }
        }

        return $this->getSuccessJson(array(
            'token' => $user->getToken(),
        ));
    }
}
