<?php

namespace App\Controller;

use App\Controller\Api\BaseApiController;
use App\Repository\RegionRepository;
use App\Repository\UserRepository;
use App\Security\LoginFormAuthenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;

class RegistrationController extends BaseApiController
{
    private $security;
    private $regionRepository;

    public function __construct(UserRepository $userRepository, RegionRepository $regionRepository, Security $security)
    {
        $this->userRepository = $userRepository;
        $this->regionRepository = $regionRepository;
        $this->security = $security;
    }

    /**
     * Register.
     *
     * @Route("/register", methods={"GET", "POST"}, name="register")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function registerAction(Request $request, LoginFormAuthenticator $authenticator, GuardAuthenticatorHandler $guardHandler)
    {
        // redirect if already logged-in
        if ($this->security->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('dashboard');
        }


        $error = null;
        if ($request->isMethod('post')) {
            $response = $this->forward('App\Controller\Api\AuthController::registerAction', $request->request->all());
            $response = json_decode($response->getContent(), true);
            if (isset($response['success']) && $response['success']) {
                // get user
                if ($user = $this->userRepository->findUserByToken($response['data']['token'])) {
                    // log user in
//                    $token = new UsernamePasswordToken($user, $user->getPassword(), 'main', $user->getRoles());
//                    $this->security->setToken($token);
                    $GLOBALS["token"] = $response['data']['token'];
                    return $guardHandler->authenticateUserAndHandleSuccess(
                        $user,          // the User object you just created
                        $request,
                        $authenticator, // authenticator whose onAuthenticationSuccess you want to use
                        'main'          // the name of your firewall in security.yaml
                    );
//                    return $this->redirectToRoute('dashboard');
                }
            }
        }

        if (isset($response['error']) && $response['error']) {
            $error = $response['data']['message'] ?: true;
        }

        // get countries and regions
        $countries = Countries::getNames($request->getLocale());
        $regions   = $this->regionRepository->groupByCountry();

        return $this->render('registration/register.html.twig', [
                'error'     => $error,
                'countries' => $countries,
                'regions'   => $regions
            ]);
    }

    /**
     * Verify the captcha code.
     *
     * @Route("/verify", methods={"GET", "POST"}, name="verify")
     * @param Request $request
     * @return mixed
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function verifyAction(Request $request)
    {
        if ($request->isMethod('POST')) {

//            $subRequest = Request::create('/api/auth/verify', 'POST', $request->query->all());

//            $response = $app->handle($subRequest, HttpKernelInterface::SUB_REQUEST, true);
//            $response = json_decode($response->getContent(), true);

            $response = $this->forward('App\Controller\Api\AuthController::verifyAction', $request->request->all());
            $response = json_decode($response->getContent(), true);

            if (isset($response['success']) && $response['success']) {
                // get user
                if ($user = $this->userRepository->findUserByToken($response['data']['token'])) {
                    // log user in
                    $token = new UsernamePasswordToken($user, $user->getPassword(), 'main', $user->getRoles());
                    $this->security->setToken($token);

                    $request->getSession()
                        ->getFlashBag()->add('success', 'Your code as been verified.');

                    return $this->redirect($this->generateUrl('dashboard'));
                }
            }
        }

        if (isset($response['error']) && $response['error']) {
            $request->getSession()
                ->getFlashBag()->add('error', $response['data']['message']);
        } else {
            $request->getSession()
                ->getFlashBag()->add('error', 'Invalid verification code.');
        }

        return $this->redirect($this->generateUrl('homepage'));
    }
}
