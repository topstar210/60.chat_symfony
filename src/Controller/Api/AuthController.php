<?php

namespace App\Controller\Api;

use App\Entity\UserTodoPn;
use App\Repository\UserRepository;
use App\Entity\User;
use App\Repository\UserTodoPnRepository;
use App\Utils\Mailer;
use App\Utils\Inflector;
use DateTime;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Route as RealRoute;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;



/**
 * @Route("/api/auth", name="api_auth_")
 */
class AuthController extends BaseApiController
{
    private $userTodoPnRepository;
    private $url_generator;

    public function __construct(UserRepository $userRepository, UserTodoPnRepository $userTodoPnRepository)
    {
        $this->userRepository = $userRepository;
        $this->userTodoPnRepository = $userTodoPnRepository;

        $route = new RealRoute('/verify');
        $routes = new RouteCollection();
        $routes->add('verify', $route);
        $context = new RequestContext('/api/auth');
        $this->url_generator = new UrlGenerator($routes, $context);
    }

    /**
     * @Route("/login", methods={"GET", "POST"}, name="login")
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function loginAction( Request $request ){
        $username = $request->get('username');
        $password = $request->get('password');

        if (!$username || !$password) {
            return $this->getErrorJson( 'The username and password can not be empty.');
        }

        if (!$user = $this->userRepository->findUserByUsernameOrPhoneNumberOrEmail($username)) {
            return $this->getErrorJson( sprintf('Username "%s" does not exist.', $username) );
        }

        if ($user->getPassword() != $password) {
            return $this->getErrorJson('Bad credentials.');
        }

        return $this->getSuccessJson([
            'token' => $user->getToken(),
        ]);
    }

    /**
     * Register via email or phone number.
     *
     * @param Request $request
     * @return string The user token
     *
     * @throws Exception
     * @Route("/register", methods={"GET", "POST"}, name="register")
     */
    public function registerAction(Request $request)
    {
        $user = new User();
        $user->setName($request->get('name'));
        $user->setRegion($request->get('region'));
        $user->setEthnicity($request->get('ethnicity'));
        $user->setGender($request->get('gender'));

        if (!$password = $request->get('password')) {
            $password = Inflector::getRandomString(6);
        }
        $user->setPassword($password);

        if ($promoCode = $request->get('promo_code')) {
            $user->setPromoCode($promoCode);
        }

        $validator = Validation::createValidator();

        // set username
        if ($username = $request->get('username')) {
            if (count($validator->validate($username, new Assert\Regex(['pattern' => '/^[a-zA-Z]+([_-]?[a-zA-Z])*$/']))) > 0) {
                return $this->getErrorJson('Username may only contain alphabetic characters.');
            }
            if (count($validator->validate($username, new Assert\Length(['max' => 15]))) > 0) {
                return $this->getErrorJson('Username is too long (maximum is 15 characters) and may only contain alphanumeric characters.');
            }
            if ($this->userRepository->findUserByUsername($username)) {
                return $this->getErrorJson(sprintf('Username %s already taken', $username));
            }

            $user->setUsername($username);
        } else {
            return $this->getErrorJson('Missing username.');
        }


        // set email
        if ($email = $request->get('email')) {
            if (count($validator->validate($email, new Assert\Email())) > 0) {
                return $this->getErrorJson('Invalid email.');
            }
            if ($this->userRepository->findUserByEmail($email)) {
                return $this->getErrorJson(sprintf('Email "%s" already exist.', $email));
            }

            $user->setEmail($email);
        } else {
            return $this->getErrorJson('Missing email.');
        }

        // set phone
        if ($phone_number = $request->get('phone_number')) {
            if (count($validator->validate($phone_number, new Assert\Regex(['pattern' => '/^[0-9]+([_ -]?[0-9])*$/']))) > 0) {
                return $this->getErrorJson('Phone number may only contain numeric characters.');
            }
            $valid_phone_number = preg_replace("([_ -]?)", "", $phone_number);
            if ($this->userRepository->findUserByPhoneNumber($valid_phone_number)) {
                return $this->getErrorJson(sprintf('Phone number "%s" already exist.', $phone_number));
            }

            $user->setPhoneNumber($valid_phone_number);
        }

        // set birthday
        if ($birthday = $request->get('birthday')) {
            $birthday = sprintf('%04d-%02d-%02d', $birthday['year'], $birthday['month'], $birthday['day']);
            if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $birthday)) {
                $birthday = new DateTime($birthday);
                if ($birthday->diff(new DateTime())->format('%y') < 18) {
                    return $this->getErrorJson('User must be older than 18 years old.');
                }

                $user->setBirthday($birthday);
            } else {
                return $this->getErrorJson(sprintf('Birthday "%s" is invalid.', $birthday));
            }
        }


        $user->setNotifyViaEmail(true);
        $user->setNotifyViaSms(false);
        $user->setEnabled(true);

        $this->userRepository->create($user);

        $new_user = $this->userRepository->findUserByUsername($user->getUsername());
        $user_todo_pn = new UserTodoPn();
        $user_todo_pn->setUserId($new_user->getId());
        $user_todo_pn->setKind(1); // profile image
        $user_todo_pn->setRemainHours(2*24);
        $this->userTodoPnRepository->create($user_todo_pn);

        $user_todo_pn = new UserTodoPn();
        $user_todo_pn->setUserId($new_user->getId());
        $user_todo_pn->setKind(1); // profile image 2th
        $user_todo_pn->setRemainHours(10*24);
        $this->userTodoPnRepository->create($user_todo_pn);

        $user_todo_pn = new UserTodoPn();
        $user_todo_pn->setUserId($new_user->getId());
        $user_todo_pn->setKind(1); // profile image 3th
        $user_todo_pn->setRemainHours(20*24);
        $this->userTodoPnRepository->create($user_todo_pn);

        $user_todo_pn = new UserTodoPn();
        $user_todo_pn->setUserId($new_user->getId());
        $user_todo_pn->setKind(2); // interest
        $user_todo_pn->setRemainHours(5*24);
        $this->userTodoPnRepository->create($user_todo_pn);

        $user_todo_pn = new UserTodoPn();
        $user_todo_pn->setUserId($new_user->getId());
        $user_todo_pn->setKind(3); // moment
        $user_todo_pn->setRemainHours(8*24);
        $this->userTodoPnRepository->create($user_todo_pn);

        $user_todo_pn = new UserTodoPn();
        $user_todo_pn->setUserId($new_user->getId());
        $user_todo_pn->setKind(10); // last login
        $user_todo_pn->setRemainHours(3*24);
        $this->userTodoPnRepository->create($user_todo_pn);

        // send message
        if ($email) {
            $message = sprintf("
Hi '%s'!

Thanks for registering to use ChatApp!

Your username is: %s
Your password is: %s

With ChatApp you can chat with your friends and find new friends. Share special pictures and status updates with the Moment updates! Find new friends to talk with by accessing the Radar feature.

ChatApp is the new way to chat!

            ", (string) $user, $user->getUsername(), $user->getPassword());

            Mailer::send($email, 'Welcome to ChatApp', $message);
        }
        if ($phone_number) {
            // todo: sprintf('"%s" Welcome ChatApp! Your password is: %s.', (string) $user, $user->getPassword())
        }



        // send verify code
        $this->resendCodeAction($request);

        if (isset($user) && $user instanceof User) {
            return $this->getSuccessJson([
                'token' => $user->getToken(),
            ]);
        }

        return $this->getErrorJson('You must provide ether an email address or a phone number to register.');
    }

    /**
     * Resend registration code.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @Route("/resend_code", methods={"GET", "POST"}, name="resend_code")
     */
    public function resendCodeAction(Request $request)
    {
        // using email
        if ($email = $request->get('email')) {
            if (!$user = $this->userRepository->findUserByEmail($email)) {
                return $this->getErrorJson(sprintf('Email "%s" does not exist.', $email));
            }

            // only effective for 1 day
            $expireAt = new \DateTime();
            $expireAt->add(new \DateInterval('P1D'));

            $captcha = $user->getCaptcha();
            $captcha['email'] = [
                'value' => $this->userRepository->generateCaptcha(),
                'expire_at' => $expireAt,
                'expired' => false
            ];

            $user->setCaptcha($captcha);

            $this->userRepository->update($user);

            // send email
            $message = sprintf("
Hi '%s'!

To start receiving email notification and other ChatApp email related features, please confirm your verification code:

http://127.0.0.1%s

            ", (string) $user, $this->url_generator->generate('verify', ['captcha' => $captcha['email']['value']]));

            Mailer::send($user->getEmail(), 'ChatApp - Verification Code', $message);
        }

        // using phone number
        if ($phone_number = $request->get('phone_number')) {
            if (!$user = $this->userRepository->findUserByPhoneNumber($phone_number)) {
                return $this->getErrorJson(sprintf('Phone number "%s" does not exist.', $phone_number));
            }

            // only effective for 1 day
            $expireAt = new \DateTime();
            $expireAt->add(new \DateInterval('P1D'));

            $captcha = $user->getCaptcha();
            $captcha['phone_number'] = [
                'value' => $this->userRepository->generateCaptcha(),
                'expire_at' => $expireAt,
                'expired' => false
            ];
            $user->setCaptcha($captcha);

            $this->userRepository->update($user);

            //send sms
//             todo: sprintf('ChatApp - Verification Code - http://chatapp.mobi%s', $app['url_generator']->generate('verify', ['captcha' => $captcha['phone_number']['value']]))
        }

        if (isset($user) && $user instanceof User) {
            return $this->getSuccessJson([]);
        }

        return $this->getErrorJson('You must provide either an email address or a phone number to resend registration code.');
    }

    /**
     * Verify the captcha code.
     *
     * @param Request $request
     * @return string The user token
     *
     * @throws Exception
     * @Route("/verify", methods={"GET", "POST"}, name="verify")
     */
    public function verifyAction(Request $request)
    {
        if (!$captcha = $request->get('captcha')) {
            return $this->getErrorJson('No captcha found.');
        }

        if (!$user = $this->userRepository->findUserByCaptcha($captcha)) {
            return $this->getErrorJson(sprintf('Captcha "%s" does not exist.', $captcha));
        }

        $userCaptcha = $user->getCaptcha();

        if (isset($userCaptcha['email']['value']) && $userCaptcha['email']['value'] == $captcha) {
            if ($user->isCaptchaNonExpired('email')) {
                unset($userCaptcha['email']);
            }
            // expire the user (email) captcha
            else {
                $userCaptcha['email']['expired'] = true;

                $user->setCaptcha($userCaptcha);

                $this->userRepository->update($user);

                return $this->getErrorJson(sprintf('Captcha "%s" has expired.', $userCaptcha['email']['value']));
            }
        }

        if (isset($userCaptcha['phone_number']['value']) && $userCaptcha['phone_number']['value'] == $captcha) {
            if ($user->isCaptchaNonExpired('phone_number')) {
                unset($userCaptcha['phone_number']);
            }
            // expire the user (phone_number) captcha
            else {
                $userCaptcha['phone_number']['expired'] = true;

                $user->setCaptcha($userCaptcha);

                $this->userRepository->update($user);

                return $this->getErrorJson(sprintf('Captcha "%s" has expired.', $userCaptcha['phone_number']['value']));
            }
        }

        if (count($userCaptcha) === 0) {
            $userCaptcha = null;
        }

        $user->setCaptcha($userCaptcha);

        $this->userRepository->update($user);

        return $this->getSuccessJson([
            'token' => $user->getToken(),
        ]);
    }

    /**
     * Recover password via username, email or phone number.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/recover", methods={"GET", "POST"}, name="recover")
     */
    public function recoverAction(Request $request)
    {
        // using username
        if ($username = $request->get('username')) {
            if (!$user = $this->userRepository->findUserByUsername($username)) {
                return $this->getErrorJson(sprintf('User "%s" does not exist.', $username));
            }
        }
        // using email
        elseif ($email = $request->get('email')) {
            if (!$user = $this->userRepository->findUserByEmail($email)) {
                return $this->getErrorJson(sprintf('Email "%s" does not exist.', $email));
            }
        }
        // using phone number
        elseif ($phone_number = $request->get('phone_number')) {
            if (!$user = $this->userRepository->findUserByPhoneNumber($phone_number)) {
                return $this->getErrorJson(sprintf('Phone "%s" does not exist.', $phone_number));
            }
        }
        // throw error
        else {
            return $this->getErrorJson('Can only recover from username, email or phone number.');
        }

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

        return $this->getSuccessJson([]);
    }
}
