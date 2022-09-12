<?php

namespace App\Controller;

use App\Controller\Api\BaseApiController;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Utils\Mailer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends BaseApiController
{
    private $security;

    public function __construct(UserRepository $userRepository, Security $security)
    {
        $this->userRepository = $userRepository;
        $this->security = $security;
    }

    /**
     * Login.
     *
     * @Route("/login", methods={"GET", "POST"}, name="login")
     * @param Request $request
     * @param AuthenticationUtils $authenticationUtils
     * @return Response
     */
    public function loginAction(Request $request, AuthenticationUtils $authenticationUtils): Response
    {



        // redirect if already logged-in
        if ($this->security->isGranted('ROLE_USER')) {
            return $this->forward('App\Controller\AppController::dashboardAction', $request->request->all());
        }
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();;



        if($error){
            $errorMsg = $error->getMessage();
            if($errorMsg != 'Email could not be found.'){
                $errorMsg = 'Invalid Password';
            }
        }else{
            $errorMsg = '';
        }

//        $error = '';
        // last username entered by the user
        $last_username = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $last_username,
            'error' => $errorMsg
        ]);
    }

    /**
     * Recover password via username, email or phone number.
     *
     * @Route("/recover", methods={"GET", "POST"}, name="recover")
     */
    public function recoverAction(Request $request)
    {
        $error = null;

        if ($request->isMethod('POST')) {
            if ($username = $request->get('username')) {
                if ($user = $this->userRepository->findUserByUsernameOrPhoneNumberOrEmail($username)) {

                    // send message
                    if ($user->getEmail()) {
                        $message = sprintf("
Hi '%s'!

Your password is: %s.

                        ", (string) $user, $user->getPassword());

                        Mailer::send($user->getEmail(), 'ChatApp - Recover Password', $message);
                    }
                    if ($user->getPhoneNumber()) {
                        // todo: sprintf('Your ChatApp password is: %s.', $user->getPassword())
                    }



                    $request->getSession()
                        ->getFlashBag()->add('success', 'Your password has been sent.');

                    return $this->redirect( $this->generateUrl('login'));

                } else {
                    $error = sprintf('User "%s" does not exist.', $username);
                }
            } else {
                $error = 'Can only recover from username, email or phone number.';
            }
        }

        return $this->render('security/recover.html.twig', ['error' => $error]);
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logoutAction()
    {
        throw new \RuntimeException('You must activate the logout in your security firewall configuration.');
    }
}
