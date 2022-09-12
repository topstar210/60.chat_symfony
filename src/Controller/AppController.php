<?php

namespace App\Controller;

use App\Controller\Api\BaseApiController;
use App\Entity\Facetime;
use App\Entity\FacetimeChannel;
use App\Entity\Party;
use App\Entity\PartyChannel;
use App\Entity\PartyMember;
use App\Repository\FriendRepository;
use App\Repository\RegionRepository;
use App\Repository\UserRepository;
use App\Repository\FacetimeRepository;
use App\Repository\FacetimeChannelRepository;
use App\Repository\PartyRepository;
use App\Service\S3Wrapper;
use App\Utils\Mailer;
use App\Utils\Timezone;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class AppController extends BaseApiController
{
    private $regionRepository;
    private $friendRepository;
    private $session;
    private $s3wrapper;
    private $validator;

    /**
     * @var FacetimeRepository
     */
    private $facetimeRepository;
    private $partyRepository;
    private $partymember;
    private $partyChannel;

    public function __construct(UserRepository $userRepository,
                                FacetimeRepository $facetimeRepository, FacetimeChannelRepository $facetimeChannelRepository,
                                 
                                PartyRepository $partyRepository,
                                SessionInterface $session, RegionRepository $regionRepository,
                                FriendRepository $friendRepository, S3Wrapper $s3wrapper)
    {
        $this->userRepository = $userRepository;
        $this->regionRepository = $regionRepository;
        $this->friendRepository = $friendRepository;
        $this->facetimeRepository = $facetimeRepository;
        $this->facetimeChannelRepository = $facetimeChannelRepository;
        $this->partyRepository = $partyRepository;
        $this->session = $session;

        $this->validator = Validation::createValidator();
        $this->s3wrapper = $s3wrapper;
    }

    /**
     * Dashboard.
     *
     * @Route("/dashboard", methods={"GET", "POST"}, name="dashboard")
     * @param Request $request
     * @return Response
     */
    public function dashboardAction(Request $request)
    {
        //if not login goto homepage
        if (!$this->getUser()) {
            return $this->redirect('/');
        }

        $parameters = [
            'only_photo' => true,
            'limit' => 8
        ];
        // get recent contacts
        $contacts = $this->forward('App\Controller\Api\ContactsController::searchAction', $parameters);
        $contacts = json_decode($contacts->getContent(), true);
        $contacts = $contacts['data']['contacts']['result'];

        // get recent moments
        $parameters = [
            'type' => 'all',
            'limit' => 8
        ];
        $moments = $this->forward('App\Controller\Api\MomentsController::searchAction', $parameters);
        $moments = json_decode($moments->getContent(), true);
        $moments = $moments['data']['moments']['result'];

        return $this->render('app/dashboard.html.twig', [
            'contacts' => $contacts,
            'moments' => $moments,
        ]);
    }

    /**
     * Moments.
     *
     * @Route("/moments", methods={"GET", "POST"}, name="moments")
     * @param Request $request
     * @return
     */
    public function momentsAction(Request $request)
    {
        //if not login goto homepage
        if (!$this->getUser()) {
            return $this->redirect('/');
        }

        // get countries
        $countries = Countries::getNames($request->getLocale());

        // update user location
        $latitude = $request->get('latitude');
        $longitude = $request->get('longitude');
        if ($latitude && $longitude) {
            $this->getUser()->setLatitude($latitude);
            $this->getUser()->setLongitude($longitude);
            $this->userRepository->update($this->getUser());
        }

        // init default search (query) parameters
        $userCountry = $this->getUser()->getCountryName();
        if (!$userCountry) $userCountry = $this->getUser()->getRegionName();
        if (!$userCountry) $userCountry = $countries['US'];

        if ($request->get('search') !== 'process') {
            $request->query->set('distance', $request->query->get('distance', '2500'));
            $request->query->set('gender', $request->query->get('gender', $this->getUser()->getGender() == 'Male' ? 'f' : 'm'));
            $request->query->set('country', $request->query->get('country', $userCountry));
        }

        return $this->render('app/moments.html.twig', array(
            'countries' => $countries
        ));
    }

    /**
     * Radar.
     *
     * @Route("/radar", methods={"GET", "POST"}, name="radar")
     */
    public function radarAction(Request $request)
    {
        //if not login goto homepage
        if (!$this->getUser()) {
            return $this->redirect('/');
        }

        // get countries
        $countries = Countries::getNames($request->getLocale());

        // update user location
        $latitude = $request->get('latitude');
        $longitude = $request->get('longitude');
        if ($latitude && $longitude) {
            $this->getUser()->setLatitude($latitude);
            $this->getUser()->setLongitude($longitude);
            $this->userRepository->update($this->getUser());
        }

        $userCountry = $this->getUser()->getCountryName();
        if (!$userCountry) $userCountry = $this->getUser()->getRegionName();
        if (!$userCountry) $userCountry = $countries['US'];

        // init default search (query) parameters
        if ($request->get('search') !== 'process') {
            $request->query->set('distance', $request->query->get('distance', '2500'));
            $request->query->set('gender', $request->query->get('gender', $this->getUser()->getGender() == 'Male' ? 'f' : 'm'));
            $request->query->set('only_photo', !($request->get('search') == 'process' && !$request->query->has('only_photo')));
            $request->query->set('sort', $request->query->get('sort', 'recent'));
            $request->query->set('country', $request->query->get('country', $userCountry));
        }

        return $this->render('app/radar.html.twig', array(
            'countries' => $countries
        ));
    }

    /**
     * Clubs.
     *
     * @Route("/clubs", methods={"GET", "POST"}, name="clubs")
     */
    public function clubsAction(Request $request)
    {
        //if not login goto homepage
        if (!$this->getUser()) {
            return $this->redirect('/');
        }
        // init default search (query) parameters
        if ($request->get('search') !== 'process') {
            $request->query->set('distance', $request->query->get('distance', '2500'));
            $request->query->set('sort', $request->query->get('sort', 'distance'));
        }

        return $this->render('app/clubs.html.twig');
    }

    /**
     * Clubs.
     *
     * @Route("/my-clubs", methods={"GET", "POST"}, name="my-clubs")
     */
    public function myclubsAction(Request $request)
    {
        //if not login goto homepage
        if (!$this->getUser()) {
            return $this->redirect('/');
        }
        // init default search (query) parameters
        if ($request->get('search') !== 'process') {
            $request->query->set('distance', $request->query->get('distance', '2500'));
            $request->query->set('sort', $request->query->get('sort', 'distance'));
        }

        return $this->render('app/my_clubs.html.twig');
    }

    /**
     * Clubs.
     *
     * @Route("/my-clubs/add", methods={"GET", "POST"}, name="add-clubs")
     */
    public function addmyclubsAction(Request $request)
    {
        //if not login goto homepage
        if (!$this->getUser()) {
            return $this->redirect('/');
        }

        $response = $this->forward('App\Controller\Api\ClubsController::saveClub', $request->query->all());
//        $response = json_decode($response->getContent(), true);
        return $this->render('app/json.html.twig', array(
            'response' => $response
        ));

    }


    /**
     * Profile settings.
     *
     * @Route("/settings", methods={"GET", "POST"}, name="settings")
     * @throws \Exception
     */
    public function settingsAction(Request $request)
    {
        //if not login goto homepage
        if (!$this->getUser()) {
            return $this->redirect('/');
        }

        $error = $request->get('error');
        $message = '';

        if ($request->isMethod('POST')) {
            $resendVerifyCode = false;

            $formData = $request->request->get('profile');

            // validate email
            if (!$formData['email'] || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
                $error = 'Email address is invalid.';
            } else {
                $check = $this->userRepository->findUserByEmail($formData['email']);

                // check that email not used by someone else
                if ($check && $check->getId() != $this->getUser()->getId()) {
                    $error = 'Email already exists.';
                } // valid
                else {
                    if ($formData['email'] != $this->getUser()->getEmail()) {
                        $resendVerifyCode = true;

                        $request->request->set('email', $formData['email']);
                    }

                    $this->getUser()->setEmail($formData['email']);
                }
            }

            // validate phone number
            if (!$formData['phone_number'] || count($this->validator->validate($formData['phone_number'], new Assert\Type(array('type' => 'digit')))) > 0) {
                $error = 'Phone number is invalid (numeric characters only).';
            } else {
                $check = $this->userRepository->findUserByPhoneNumber($formData['phone_number']);

                // check that phone number not used by someone else
                if ($check && $check->getId() != $this->getUser()->getId()) {
                    $error = 'Phone number already exists.';
                } // valid
                else {
                    if ($formData['phone_number'] != $this->getUser()->getPhoneNumber()) {
                        $resendVerifyCode = true;

                        $request->request->set('phone_number', $formData['phone_number']);
                    }

                    $this->getUser()->setPhoneNumber($formData['phone_number']);
                }
            }

            // validate passwords
            if (!$error && $formData['password_old']) {
                // check if old password match
                if ($formData['password_old'] != $this->getUser()->getPassword()) {
                    $error = 'Your old password was entered incorrectly. Please enter it again.';
                } // confirm new password
                elseif ($formData['password_new'] != $formData['password_confirm']) {
                    $error = 'New passwords do not match.';
                }
            }

            // upload photo
            $profilePhoto = $request->files->get('profile_photo');
            if (!$error && $profilePhoto) {

                if (!$profilePhoto instanceof UploadedFile || !$profilePhoto->isValid()) {
                    $error = 'Missing file.';
                } else {
                    $fileConst = new \Symfony\Component\Validator\Constraints\File(array(
                        'maxSize' => '5120k',
                        'mimeTypes' => array(
                            'image/bmp', 'image/gif', 'image/jpeg', 'image/png',
                        ),
                    ));

                    $errors = $this->validator->validate($profilePhoto, $fileConst);
                    if (count($errors) > 0) {
                        $error = 'Invalid file.';
                    } else {

                        if ($data = file_get_contents($profilePhoto->getPathname())) {

                            // upload photo
                            $objectKeys = $this->s3wrapper
                                ->addFiles('users', $this->getUser()->getTokenById(), array($profilePhoto->getClientOriginalName() => $data),
                                    Constant::$conf_media_sizes['profile'], true);

                            // delete old photo
                            $this->s3wrapper->deleteFiles(array($this->getUser()->getPhoto()), Constant::$conf_media_sizes['profile']);

                            // assign photo
                            $this->getUser()->setPhoto($objectKeys['origin']);
                        }

                        // delete uploaded file
                        unlink($profilePhoto->getPathname());
                    }
                }
            }

            // validate bithday
            if (!$error && $formData['birthday']) {
                $birthday = sprintf('%04d-%02d-%02d', $formData['birthday']['year'], $formData['birthday']['month'], $formData['birthday']['day']);
                if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $birthday)) {
                    $birthday = new \DateTime($birthday);
                    if ($birthday->diff(new \DateTime())->format('%y') < 18) {
                        $error = 'User must be older than 18 years old.';
                    }

                    $this->getUser()->setBirthday($birthday);
                } else {
                    $error = 'Birthday is invalid.';
                }
            }

            if (!$error) {
                $this->getUser()->setName($formData['name']);
                if (isset($formData['gender']) && $formData['gender']) {
                    $this->getUser()->setGender($formData['gender']);
                }
                $this->getUser()->setEthnicity($formData['ethnicity']);
                $this->getUser()->setRegion($formData['region']);
                $this->getUser()->setDistanceBy($formData['distance_by']);
                $this->getUser()->setInterest($formData['interest']);
                $this->getUser()->setAboutme($formData['aboutme']);
                $this->getUser()->setGreeting($formData['greeting']);

                if ($formData['password_new']) {
                    $this->getUser()->setPassword($formData['password_new']);
                }

                if ($this->getUser()->isVerifiedEmail()) {
                    $this->getUser()->setNotifyViaEmail(isset($formData['notify_via_email']) && $formData['notify_via_email']);
                }
                if ($this->getUser()->isVerifiedPhoneNumber()) {
                    $this->getUser()->setNotifyViaSms(isset($formData['notify_via_sms']) && $formData['notify_via_sms']);
                }

                $this->userRepository->update($this->getUser());

                if ($resendVerifyCode) {
                    $this->forward('App\Controller\Api\AuthController::resendCodeAction', $request->query->all());
                }
                $message = 'Data has been updated successfully.';
            }
        }

        // get countries and regions
        $countries = Countries::getNames($request->getLocale());
        $regions = $this->regionRepository->groupByCountry();

        return $this->render('app/settings.html.twig', array(
            'error' => $error,
            'message' => $message,
            'countries' => $countries,
            'regions' => $regions
        ));
    }

    /**
     * Connect with social network.
     *
     * @Route("/connect", methods={"GET", "POST"}, name="connect")
     */
    public function connectAction(Request $request)
    {
        if (!$service = $request->get('service')) {
            return $this->forward('App\Controller\AppController::settingsAction', [
                'error' => 'Missing connect service.'
            ]);
        }

        // re-register
        $oAuthServiceRegistry = new OAuthServiceRegistry(
            $app['oauth.factory'],
            $app['oauth.storage'],
            $app['oauth.url_generator'],
            array($service => $app['oauth.services'][$service]),
            array('callback_route' => 'connect')
        );

        // process request
        if ($facebookCode = $request->get('code')) {
            if (!$facebookCode) {
                return $this->forward('App\Controller\AppController::settingsAction', [
                    'error' => 'The Facebook code can not be empty.'
                ]);
            }

            // get facebook user data
            try {
                $app['security.services']['facebook']->setOAuthServiceRegistry($oAuthServiceRegistry);

                $facebookUser = $app['security.services']['facebook']->getUser(array(
                    'code' => $facebookCode
                ));
            } catch (\Exception $e) {
                return $this->forward('App\Controller\AppController::settingsAction', [
                    'error' => $e->getMessage()
                ]);
            }

            // make sure uid is not used by another user
            if ($user = $this->userRepository->findUserByFacebookUid($facebookUser['id'])) {
                if ($user->getId() != $this->getUser()->getId()) {
                    return $this->forward('App\Controller\AppController::settingsAction', [
                        'error' => 'A different user is using this id.'
                    ]);
                }
            }

            $this->getUser()->setFacebookUid($facebookUser['id']);

            $this->userRepository->update($this->getUser());

            return $this->redirectToRoute('settings');
        }

        return $this->redirectToRoute($oAuthServiceRegistry->getService($service)->getAuthorizationUri()->getAbsoluteUri());
    }

    /**
     * Profile.
     *
     * @Route("/profile/{username}", methods={"GET", "POST"}, name="profile")
     * @Route("/profile/{username}/{type}", methods={"GET", "POST"}, name="profileType")
     */
    public function profileAction(Request $request)
    {
        //if not login goto homepage
        if (!$this->getUser()) {
            return $this->redirect('/');
        }

        if (!$username = $request->get('username')) {
            throw new \Exception('Invalid username.');
        }
        if (!$user = $this->userRepository->findUserByUsernameOrPhoneNumberOrEmail($username)) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
        }

        $type = $request->get('type', 'moments');
        if (!in_array($type, array('moments', 'contacts'))) {
            $type = 'moments';
        }

        if ($this->getUser()->getId() != $user->getId() && $type == 'contacts' && !$this->getUser()->isSuperAdmin()) {
            return $this->forward('App\Controller\AppController::profileAction', [
                'username' => $username
            ]);
        }

        $isFriend = $this->friendRepository->isBefriended($this->getUser(), $user);
//        dd($user->friends);

        return $this->render('app/profile.html.twig', array(
            'user' => $user,
            'type' => $type,
            'isFriend' => $isFriend
        ));
    }

    /**
     * New Home
     *
     * @Route("/home", methods={"GET", "POST"}, name="home")
     * @throws \Exception
     */
    public function home(Request $request)
    {
        //if not login goto homepage
        if (!$this->getUser()) {
            return $this->redirect('/');
        }

        $parameters = [
            'only_photo' => true,
            'limit' => 8
        ];
        // get recent contacts
        $contacts = $this->forward('App\Controller\Api\ContactsController::searchAction', $parameters);
        $contacts = json_decode($contacts->getContent(), true);
        $contacts = $contacts['data']['contacts']['result'];

        // get recent moments
        $parameters = [
            'type' => 'all',
            'limit' => 8
        ];
        $moments = $this->forward('App\Controller\Api\MomentsController::searchAction', $parameters);
        $moments = json_decode($moments->getContent(), true);
        $moments = $moments['data']['moments']['result'];

        return $this->render('app/home.html.twig', [
            'contacts' => $contacts,
            'moments' => $moments,
        ]);
    }

    /**
     * Facetime
     *
     * @Route("/facetimeMember", methods={"GET", "POST"}, name="facetime_register_chatapp")
     * @throws \Exception
     */
    public function facetimeMember(Request $request)
    {
        $session = $this->get('session');
        $session->start();
        $hash = $session->get('myHash');


        if (!$hash) {
            $hash = $session->getId();
            $session->set('myHash', $hash);
        }

        $people = $this->getDoctrine()
            ->getRepository('App:Facetime')
            ->findOneBy(["hash" => $hash]);
        if($people){
            $year = date("Y",strtotime($people->getBirthday()));
            $month = intval(date("m",strtotime($people->getBirthday())));
            $day = intval(date("d",strtotime($people->getBirthday())));
            $birthdate = ['year'=>$year,'month'=>$month,'day'=>$day];
        }else{
            $people = null;
            $birthdate = ['year'=>0,'month'=>0,'day'=>0];
        }
        $error = '';
        // get countries and regions
        $countries = Countries::getNames($request->getLocale());
        $regions   = $this->regionRepository->groupByCountry();


        return $this->render('app/facetime_register_chatapp.html.twig',
            ['people'=>$people,
                'birthdate'=>$birthdate,
                'error'=>$error,
                'countries' => $countries,
                'regions'   => $regions,
                'hash'=>$hash]);
    }


    /**
     * Facetime
     *
     * @Route("/60", methods={"GET", "POST"}, name="facetime")
     * @throws \Exception
     */
    public function facetime(Request $request)
    {
        $session = $this->get('session');
        $session->start();
        $hash = $session->get('myHash');
        $this->session->set('myMeet',null);

        if (!$hash) {
            $hash = $session->getId();
            $session->set('myHash', $hash);
        }

        $people = $this->getDoctrine()
            ->getRepository('App:Facetime')
            ->findOneBy(["hash" => $hash]);

        if($people){
            //return $this->redirect('/facetimeWaiting');
            $year = date("Y",strtotime($people->getBirthday()));
            $month = intval(date("m",strtotime($people->getBirthday())));
            $day = intval(date("d",strtotime($people->getBirthday())));
            $birthdate = ['year'=>$year,'month'=>$month,'day'=>$day];
        }else{
            $user = $this->getUser();
            if($user){
                $name = $user->getName();
                $email = $user->getEmail();
                $gender = ($user->getGender()=='m')?'Male':($user->getGender()=='f')?'Female':'Unknown';
                $birthday = $user->getBirthday();

                $people = new Facetime();
                $people->setHash($hash);
                $people->setName($name);
                $people->setEmail($email);
                $people->setGender($gender);

                $avatar = '/static/facetime/'.strtolower($gender).'_'.rand(1,6).'.png';
                $people->setAvatar($avatar);

                $people->setDate_created(new \DateTime());
                $people->setBirthday(date("F j, Y", strtotime($birthday->format('Y-m-d'))));
                $people->setStatus(0);

                $this->facetimeRepository->create($people);
                return $this->redirect('/facetimeWaiting?hash='.$hash);
            }
            $birthdate = ['year'=>0,'month'=>0,'day'=>0];
        }

        return $this->render('app/facetimejuly.html.twig', ['hash'=>$hash,'people'=>$people,'birthdate'=>$birthdate]);
    }
 
	 
	/**
     * Facetime
     *
     * @Route("/july2020", methods={"GET", "POST"}, name="facetime2")
     * @throws \Exception
     */
    public function facetime_july(Request $request)
    {
        $session = $this->get('session');
        $session->start();
        $hash = $session->get('myHash');


        if (!$hash) {
            $hash = $session->getId();
            $session->set('myHash', $hash);
        }

        $people = $this->getDoctrine()
            ->getRepository('App:Facetime')
            ->findOneBy(["hash" => $hash]);

        if($people){
            //return $this->redirect('/facetimeWaiting');
            $year = date("Y",strtotime($people->getBirthday()));
            $month = intval(date("m",strtotime($people->getBirthday())));
            $day = intval(date("d",strtotime($people->getBirthday())));
            $birthdate = ['year'=>$year,'month'=>$month,'day'=>$day];
        }else{
            $user = $this->getUser();
            if($user){
                $name = $user->getName();
                $email = $user->getEmail();
                $gender = ($user->getGender()=='m')?'Male':($user->getGender()=='f')?'Female':'Unknown';
                $birthday = $user->getBirthday();

                $people = new Facetime();
                $people->setHash($hash);
                $people->setName($name);
                $people->setEmail($email);
                $people->setGender($gender);

                $avatar = '/static/facetime/'.strtolower($gender).'_'.rand(1,6).'.png';
                $people->setAvatar($avatar);

                $people->setDate_created(new \DateTime());
                $people->setBirthday(date("F j, Y", strtotime($birthday->format('Y-m-d'))));
                $people->setStatus(0);

                $this->facetimeRepository->create($people);
                return $this->redirect('/facetimeWaiting?hash='.$hash);
            }
            $birthdate = ['year'=>0,'month'=>0,'day'=>0];
        }

        return $this->render('app/facetime.html.twig', ['hash'=>$hash,'people'=>$people,'birthdate'=>$birthdate]);
    }
	
	 
    /**
     * Facetime Register
     *
     * @Route("/facetimeWaiting", methods={"GET", "POST"}, name="facetime_register")
     * @throws \Exception
     */ 
    public function facetime_register(Request $request)
    {

//        $this->facetimeRepository->removeOldUser();

        $name = $request->get('name');
        $email = $request->get('email');
        $gender = $request->get('gender');
        $birthday = $request->get('birthday', []);

        $session = $this->get('session');
        $session->start();
        $hash = $session->get('myHash');

        if (!$hash) {
            $hash = $session->getId();
            $session->set('myHash', $hash);
        }



        $people = $this->getDoctrine()
            ->getRepository('App:Facetime')
            ->findOneBy(["hash" => $hash]);

        if ($people) {
            if ($name && $gender && $birthday) {
                $people->setName($name);
                $people->setEmail($email);
                $people->setGender($gender);
                $avatar = '/static/facetime/'.strtolower($gender).'_'.rand(1,6).'.png';
                $people->setAvatar($avatar);
                if($birthday['year'] && $birthday['month'] && $birthday['day']){
                    $my_birthday = date("F j, Y", strtotime(implode('-', $birthday)));
                    $people->setBirthday($my_birthday);
                }
                $people->setDate_created(new \DateTime());
                $people->setLast_modified(new \DateTime());
                $this->facetimeRepository->update($people);
            }
        } else {

            if (!$name || !$gender || !$birthday) {
                return $this->redirect('/facetime');
            }

            $people = new Facetime();
            $people->setHash($hash);
            $people->setName($name);
            $people->setEmail($email);
            $people->setGender($gender);
            $avatar = '/static/facetime/'.strtolower($gender).'_'.rand(1,6).'.png';
            $people->setAvatar($avatar);
            $people->setDate_created(new \DateTime());
            $people->setLast_modified(new \DateTime());
            if($birthday['year'] && $birthday['month'] && $birthday['day']){
                $people->setBirthday(implode('-', $birthday));
            }else{
                $people->setBirthday(null);
            }

            $people->setStatus(0);

            $this->facetimeRepository->create($people);
        }

        return $this->render('app/facetime_register.html.twig', array('people' => $people,'hash'=> $hash ));
    }

    /**
     * Call Video
     *
     * @Route("/meetup", methods={"GET", "POST"}, name="meetup")
     * @throws \Exception
     */
    public function meetup(Request $request)
    {
        $session = $this->get('session');
        $session->start();
        $hash = $session->get('myHash');

        if (!$hash) {
            $hash = $session->getId();
            $session->set('myHash', $hash);
        }

        $me = $this->getDoctrine()
            ->getRepository('App:Facetime')
            ->findOneBy(["hash" => $hash]);

        if(!$me ){
            return $this->redirect('/60');
        }
        return $this->render('app/meetup.html.twig', ['people' => $me,'partner'=>null, 'hash'=>$hash]);
    }


    /**
     * Facetime Register
     *
     * @Route("/update_channel", methods={"GET", "POST"}, name="update_chat_facetime")
     * @throws \Exception
     */
    public function update_channel(Request $request)
    {
        $session = $this->get('session');
        $session->start();

        header('Content-Type: application/json');

        $data = array('ok' => true, 'channel' => '','swap'=>0);
		
		$myMeet = $session->get('myMeet');

        $hash = $session->get('myHash');
        if(!$hash){
            $hash = $request->get('hash');
        }

        $me = $this->getDoctrine()
            ->getRepository('App:Facetime')
            ->findOneBy(["hash" => $hash]);
		$me->setLast_modified(new \DateTime());
		$this->facetimeRepository->update($me);	
			
        //remove Old channel
        $this->facetimeRepository->removeOldChannel();

        $myChannel = $this->getDoctrine()
            ->getRepository('App:FacetimeChannel')
            ->findOneBy(["client_1" => $hash]);
        if(!$myChannel){
            $myChannel = $this->getDoctrine()
                ->getRepository('App:FacetimeChannel')
                ->findOneBy(["client_2" => $hash]);
        }

        if(!$myChannel){
            //create new with new people
			
            $partner = $this->facetimeRepository->matchPeople($me,$myMeet);
            if(!$partner){
				$myMeet = array();
                $this->session->set('myMeet',$myMeet);
            }
			
            if($partner) {

                $myChannel = new FacetimeChannel();
                $myChannel->setChannel($hash);
                $myChannel->setGender($me->getGender());
                $myChannel->setClient_1($hash);
                $myChannel->setClient1_time(new \DateTime());

                $myChannel->setClient_2($partner['hash']);
                $myChannel->setClient2_time(new \DateTime());

                $myChannel->setStatus(0);

                $myChannel->setCreated(new \DateTime());
                $this->partyRepository->create($myChannel);

                $data['channel'] = ['id' => $myChannel->getId(),
                    'channel' => $myChannel->getChannel(),
                    'client_1' => $myChannel->getClient_1(),
                    'client_2' => $myChannel->getClient_2(),
                    'client1_time' => $myChannel->getClient1_time(),
                    'client2_time' => $myChannel->getClient2_time()
                ];

                if ($myChannel->getClient_1()) {
                    $tmp = $this->getDoctrine()
                        ->getRepository('App:Facetime')
                        ->findOneBy(["hash" => $myChannel->getClient_1()]);

                    $data['channel']['client_1'] = [
                        'name' => $tmp->getName(),
                        'email' => $tmp->getEmail(),
                        'gender' => $tmp->getGender(),
                        'birthday' => $tmp->getBirthday(),
                        'avatar' => $tmp->getAvatar(),
                        'hash' => $tmp->getHash()
                    ];
                }

                if ($myChannel->getClient_2()) {
                    $tmp = $this->getDoctrine()
                        ->getRepository('App:Facetime')
                        ->findOneBy(["hash" => $myChannel->getClient_2()]);
                    $data['channel']['client_2'] = [
                        'name' => $tmp->getName(),
                        'email' => $tmp->getEmail(),
                        'gender' => $tmp->getGender(),
                        'birthday' => $tmp->getBirthday(),
                        'avatar' => $tmp->getAvatar(),
                        'hash' => $tmp->getHash()
                    ];
                }

                if($hash == $myChannel->getClient_2()){
                    $data['partner'] = $data['channel']['client_1'];
                    if($data['channel']['client_1']){
                        $myMeet[$data['channel']['client_1']['name']] = 1;
                    }
                }elseif($hash == $myChannel->getClient_1()){
                    $data['partner'] = $data['channel']['client_2'];
                    if($data['channel']['client_2']){
                        $myMeet[$data['channel']['client_2']['name']] = 1;
                    }
                }

            }
        }else{
            $data['channel'] = ['id' => $myChannel->getId(),
                'channel' => $myChannel->getChannel(),
                'client_1' => $myChannel->getClient_1(),
                'client_2' => $myChannel->getClient_2(),
                'client1_time' => $myChannel->getClient1_time(),
                'client2_time' => $myChannel->getClient2_time()
            ];

            if ($myChannel->getClient_1()) {
                $tmp = $this->getDoctrine()
                    ->getRepository('App:Facetime')
                    ->findOneBy(["hash" => $myChannel->getClient_1()]);

                $data['channel']['client_1'] = [
                    'name' => $tmp->getName(),
                    'email' => $tmp->getEmail(),
                    'gender' => $tmp->getGender(),
                    'birthday' => $tmp->getBirthday(),
                    'avatar' => $tmp->getAvatar(),
                    'hash' => $tmp->getHash()
                ];
            }

            if ($myChannel->getClient_2()) {
                $tmp = $this->getDoctrine()
                    ->getRepository('App:Facetime')
                    ->findOneBy(["hash" => $myChannel->getClient_2()]);
                $data['channel']['client_2'] = [
                    'name' => $tmp->getName(),
                    'email' => $tmp->getEmail(),
                    'gender' => $tmp->getGender(),
                    'birthday' => $tmp->getBirthday(),
                    'avatar' => $tmp->getAvatar(),
                    'hash' => $tmp->getHash()
                ];
            }

            if($hash == $myChannel->getClient_2()){
                $data['partner'] = $data['channel']['client_1'];
                if($data['channel']['client_1']){
                    $myMeet[$data['channel']['client_1']['name']] = 1;
                }
            }elseif($hash == $myChannel->getClient_1()){
                $data['partner'] = $data['channel']['client_2'];
                if($data['channel']['client_2']){
                    $myMeet[$data['channel']['client_2']['name']] = 1;
                }
            }
        }

        $this->session->set('myMeet',$myMeet);
        $data['my_hash'] = $hash;
        $data['myMeet'] = $myMeet;
        $data['online'] = $this->facetimeRepository->getOnlinePeople();


        echo json_encode($data);
        die();
    }

    /**
     * Facetime Register
     *
     * @Route("/end_chat", methods={"GET", "POST"}, name="end_chat")
     * @throws \Exception
     */
    public function end_chat(Request $request)
    {
        header('Content-Type: application/json');

        $data = array('ok' => true, 'channel' => '','swap'=>0);
        $channel_id = $request->get('room');

        $session = $this->get('session');
        $session->start();
        $hash = $session->get('myHash');


        $channel = $this->getDoctrine()
            ->getRepository('App:FacetimeChannel')
            ->findOneBy(["id" => $channel_id]);

        if ($channel) {
            $this->facetimeRepository->delete($channel);
        }

//        $session->set('myHash', '');
        echo json_encode($data);
        die();
    }


    /**
     * Facetime count online
     *
     * @Route("/60online", methods={"GET", "POST"}, name="count_online")
     * @throws \Exception
     */
    public function facetime_60_online(Request $request)
    {
        header('Content-Type: application/json');
        $data['status'] = 1;
        $data['online'] = $this->facetimeRepository->getOnlinePeople();
        echo json_encode($data);
        die();
    }

    /**
     * facetime_party
     *
     * @Route("/party", methods={"GET", "POST"}, name="facetime_party")
     * @throws \Exception
     */
    public function facetime_party(Request $request)
    {
//        $date = date("Y-m-d H:i:s");
//        $timezone = intval("+7");
//        echo 'Server:'.$date.'<br>';
//        $local = Timezone::server2local($date,$timezone);
//        echo  'local:'.$local.'<br>';
//        $server = Timezone::local2server($local,$timezone);
//        echo  'Server:'.$server.'<br>';
//
//        echo "diff:".Timezone::get_diff_timezone($timezone).'<br>';
//        die(" Timezone:");
        return $this->render('party/facetime_party.html.twig', []);
    }


    /**/
    /**
     * Facetime party_register
     *
     * @Route("/party_register", methods={"GET", "POST"}, name="party_register")
     * @throws \Exception
     */
    public function party_register(Request $request)
    {


        $subject = $request->get('subject');


        $timezone =  $request->get('timezone');

        $type = $request->get('type','now');

        if($type== 'now'){
            $event_date =  date("Y-m-d",strtotime($request->get('date_now')));
            $starttime = $request->get('date_now_start');
            $endtime = $request->get('date_now_end');
        }else {
            //schedule
            $event_date = date("Y-m-d",strtotime($request->get('date')?$request->get('date'):date("Y-m-d")));
            $starttime = $request->get('hour_start') . ':' . $request->get('minute_start') . ' ' . $request->get('time_start');
            $endtime = $request->get('hour_end') . ':' . $request->get('minute_end') . ' ' . $request->get('time_end');
        }

        $name = $request->get('name');
        $email = $request->get('email','');
        $phone = $request->get('phone','');
        $background = $request->get('background','');

        $host =  md5(uniqid(time()));
        $party = null;
        if($subject &&  $starttime && $name){
            $party = new Party();
            $party->setSubject($subject);
            $party->setHost($host);
            $party->setEventdate($event_date);
            $party->setStarttime($starttime);
            $party->setEndtime($endtime);
            $party->setName($name);
            $party->setEmail($email);
            $party->setPhone($phone);
            $party->setBackground($background);
            $party->setTimezone($timezone);

            $party->setDate_created(new \DateTime());

            $this->partyRepository->create($party);
            return $this->redirect('/partySuccess?key='.$party->getHost());
        }



    }

    /**/
    /**
     * Facetime party_success
     *
     * @Route("/partySuccess", methods={"GET", "POST"}, name="party_success")
     * @throws \Exception
     */
    public function partySuccess(Request $request)
    {

        $id = $request->get('key');

        $event = $this->getDoctrine()
            ->getRepository('App:Party')
            ->findOneBy(["host" => $id]);


        return $this->render('party/party_success.html.twig', ['party'=>$event,'hash'=>$id]);

    }

    /**
     * Facetime party_member
     *
     * @Route("/party_member", methods={"GET", "POST"}, name="party_member")
     * @throws \Exception
     */
    public function party_member(Request $request)
    {

        $name = $request->get('name');
        $email = $request->get('email');
        $avatar = rand(1,24).'.jpg';

        $group = $request->get('group','');

        $session = $this->get('session');
        $session->start();
        $hash = 'client_'.uniqid();
        $event = $this->getDoctrine()
            ->getRepository('App:Party')
            ->findOneBy(["host" => $group]);

        $partyMember = $this->getDoctrine()
            ->getRepository('App:PartyMember')
            ->findOneBy(["host" => $group,"email"=>$email]);

        if (!$partyMember) {
            $partyMember = new PartyMember();
            $partyMember->setName($name);
            $partyMember->setHost($group);
            $partyMember->setEmail($email);
            $partyMember->setAvatar($avatar);
            $partyMember->setBirthday("");
            $partyMember->setDate_created(new \DateTime());
            $partyMember->setHash($hash);
            $partyMember->setStatus(0);
            $this->partyRepository->create($partyMember);
        }else{
            $partyMember->setName($name);
            $partyMember->setHash($hash);
            $partyMember->setAvatar($avatar);
            $this->partyRepository->update($partyMember);
            $hash = $partyMember->getHash();
        }

        $data['hash'] = $hash;
        $data['id'] = $partyMember->getId();
        $data['meetup'] = '/partyChannel?id='.$hash;
        echo json_encode($data);
        die();

    }

    /**/
    /**
     * Facetime party_channel
     *
     * @Route("/partyChannel", methods={"GET", "POST"}, name="party_channel")
     * @throws \Exception
     */
    public function party_channel(Request $request)
    {

        $id = $request->get('id');


        $partyMember = $this->getDoctrine()
            ->getRepository('App:PartyMember')
            ->findOneBy(["hash" => $id]);

        if($partyMember){
            $event = $this->getDoctrine()
                ->getRepository('App:Party')
                ->findOneBy(["host" => $partyMember->getHost()]);
        }else{
            $event = null;
        }
        $isTime = 0;
        if($event){
            $now = date("Y-m-d H:i");
            if($now < date("Y-m-d H:i",strtotime($event->getEventdate().' '.$event->getStarttime()))){
                $isTime = 1;
            }else if($now > date("Y-m-d H:i",strtotime($event->getEventdate().' '.$event->getEndtime()))){
                $isTime = 3;
            }else{
                $isTime =2 ;
            }
        }

        return $this->render('party/party_channel.html.twig', ['partyMember'=>$partyMember,'party'=>$event,'hash'=>$id,'isTime'=>$isTime]);

    }

    /**/
    /**
     * Facetime partyChat
     *
     * @Route("/partyChat", methods={"GET", "POST"}, name="party_group")
     * @throws \Exception
     */
    public function party_group(Request $request)
    {
        $session = $this->get('session');
        $session->start();
        $group = $request->get('group');

        $name = $request->get('name');
        $hash = $request->get('hash','');
        $email = $request->get('email');
        $avatar = rand(1,24).'.jpg';
        if($name && $email){
            $hash = 'client_'.uniqid();

            $partyMember = $this->getDoctrine()
                ->getRepository('App:PartyMember')
                ->findOneBy(["host" => $group,"email"=>$email]);

            if (!$partyMember) {
                $partyMember = new PartyMember();
                $partyMember->setName($name);
                $partyMember->setHost($group);
                $partyMember->setEmail($email);
                $partyMember->setAvatar($avatar);
                $partyMember->setBirthday("");
                $partyMember->setDate_created(new \DateTime());
                $partyMember->setHash($hash);
                $partyMember->setStatus(0);
                $this->partyRepository->create($partyMember);
            }else{
                $partyMember->setName($name);
                $partyMember->setHash($hash);
                $partyMember->setAvatar($avatar);
                $this->partyRepository->update($partyMember);
            }
            return $this->redirectToRoute('partMeetup',array('group'=>$group,'me'=>$hash));
        }

        $host = $request->get('group');
        $channel = $this->getDoctrine()
            ->getRepository('App:Party')
            ->findOneBy(["host" => $host]);
        if($channel){
            $party = $channel;
        }else{
            $party = null;
        }


        return $this->render('party/party_group.html.twig', ['party'=>$party,'hash'=>$hash,'group'=>$group]);

    }


    /**
     * Call Video
     *
     * @Route("/partMeetup", methods={"GET", "POST"}, name="partMeetup")
     * @throws \Exception
     */
    public function partMeetup(Request $request)
    {
        $session = $this->get('session');
        $session->start();

        $group = $request->get('group');
        $client_id = $request->get('me');

        $event = $this->getDoctrine()
            ->getRepository('App:Party')
            ->findOneBy(["host" => $group]);

        if (!$group) {
            return $this->redirect('/partyChannel?id='.$group);
        }

        $me = $this->getDoctrine()
            ->getRepository('App:PartyMember')
            ->findOneBy(["hash" => $client_id]);

        if(!$me ){
            return $this->redirect('/partyChat?group='.$group);
        }


        return $this->render('party/party_meetup.html.twig', ['people' => $me,'party' => $event, 'channel' => [], 'partner' => [],'group'=>$group,'hash'=>$client_id]);
    }


    /**
     * Facetime Register
     *
     * @Route("/party_update_channel", methods={"GET", "POST"}, name="party_update_chat_facetime")
     * @throws \Exception
     */
    public function party_update_channel(Request $request)
    {
        $session = $this->get('session');
        $session->start();

        header('Content-Type: application/json');

        $data = array('ok' => true, 'channel' => '','swap'=>0);
        $group = $request->get('group');

        $myMeet_key = 'myMeet_'.$group;

        $myMeet = $session->get($myMeet_key);

        $hash = $request->get('hash');

        $me = $this->getDoctrine()
            ->getRepository('App:PartyMember')
            ->findOneBy(["hash" => $hash]);
        $me->setLast_modified(new \DateTime());
        $this->partyRepository->update($me);


        //remove Old channel
        $this->partyRepository->removeOldChannel($group);

        $myChannel = $this->getDoctrine()
            ->getRepository('App:PartyChannel')
            ->findOneBy(["client_1" => $hash]);
        if(!$myChannel){
            $myChannel = $this->getDoctrine()
                ->getRepository('App:PartyChannel')
                ->findOneBy(["client_2" => $hash]);
        }

        if(!$myChannel){
            //create new with new people

            $partner = $this->partyRepository->matchPeople($group,$me,$myMeet);
            if(!$partner){
                $myMeet = array();
                $this->session->set($myMeet_key,$myMeet);
            }

            if($partner) {

                $myChannel = new PartyChannel();
                $myChannel->setChannel($hash);
                $myChannel->setGender($me->getGender());
                $myChannel->setHost($group);
                $myChannel->setClient_1($hash);
                $myChannel->setClient1_time(new \DateTime());

                $myChannel->setClient_2($partner['hash']);
                $myChannel->setClient2_time(new \DateTime());

                $myChannel->setStatus(0);

                $myChannel->setCreated(new \DateTime());
                $this->partyRepository->create($myChannel);

                $data['channel'] = ['id' => $myChannel->getId(),
                    'channel' => $myChannel->getChannel(),
                    'client_1' => $myChannel->getClient_1(),
                    'client_2' => $myChannel->getClient_2(),
                    'client1_time' => $myChannel->getClient1_time(),
                    'client2_time' => $myChannel->getClient2_time()
                ];

                if ($myChannel->getClient_1()) {
                    $tmp = $this->getDoctrine()
                        ->getRepository('App:PartyMember')
                        ->findOneBy(["hash" => $myChannel->getClient_1()]);

                    $data['channel']['client_1'] = [
                        'name' => $tmp->getName(),
                        'email' => $tmp->getEmail(),
                        'gender' => $tmp->getGender(),
                        'birthday' => $tmp->getBirthday(),
                        'avatar' => $tmp->getAvatar(),
                        'hash' => $tmp->getHash()
                    ];
                }

                if ($myChannel->getClient_2()) {
                    $tmp = $this->getDoctrine()
                        ->getRepository('App:PartyMember')
                        ->findOneBy(["hash" => $myChannel->getClient_2()]);
                    $data['channel']['client_2'] = [
                        'name' => $tmp->getName(),
                        'email' => $tmp->getEmail(),
                        'gender' => $tmp->getGender(),
                        'birthday' => $tmp->getBirthday(),
                        'avatar' => $tmp->getAvatar(),
                        'hash' => $tmp->getHash()
                    ];
                }

                if($hash == $myChannel->getClient_2()){
                    $data['partner'] = $data['channel']['client_1'];
                    if($data['channel']['client_1']){
                        $myMeet[$data['channel']['client_1']['name']] = 1;
                    }
                }elseif($hash == $myChannel->getClient_1()){
                    $data['partner'] = $data['channel']['client_2'];
                    if($data['channel']['client_2']){
                        $myMeet[$data['channel']['client_2']['name']] = 1;
                    }
                }

            }
        }else{
            $data['channel'] = ['id' => $myChannel->getId(),
                'channel' => $myChannel->getChannel(),
                'client_1' => $myChannel->getClient_1(),
                'client_2' => $myChannel->getClient_2(),
                'client1_time' => $myChannel->getClient1_time(),
                'client2_time' => $myChannel->getClient2_time()
            ];

            if ($myChannel->getClient_1()) {
                $tmp = $this->getDoctrine()
                    ->getRepository('App:PartyMember')
                    ->findOneBy(["hash" => $myChannel->getClient_1()]);

                $data['channel']['client_1'] = [
                    'name' => $tmp->getName(),
                    'email' => $tmp->getEmail(),
                    'gender' => $tmp->getGender(),
                    'birthday' => $tmp->getBirthday(),
                    'avatar' => $tmp->getAvatar(),
                    'hash' => $tmp->getHash()
                ];
            }

            if ($myChannel->getClient_2()) {
                $tmp = $this->getDoctrine()
                    ->getRepository('App:PartyMember')
                    ->findOneBy(["hash" => $myChannel->getClient_2()]);
                $data['channel']['client_2'] = [
                    'name' => $tmp->getName(),
                    'email' => $tmp->getEmail(),
                    'gender' => $tmp->getGender(),
                    'birthday' => $tmp->getBirthday(),
                    'avatar' => $tmp->getAvatar(),
                    'hash' => $tmp->getHash()
                ];
            }

            if($hash == $myChannel->getClient_2()){
                $data['partner'] = $data['channel']['client_1'];
                if($data['channel']['client_1']){
                    $myMeet[$data['channel']['client_1']['name']] = 1;
                }
            }elseif($hash == $myChannel->getClient_1()){
                $data['partner'] = $data['channel']['client_2'];
                if($data['channel']['client_2']){
                    $myMeet[$data['channel']['client_2']['name']] = 1;
                }
            }
        }

        $this->session->set($myMeet_key,$myMeet);
        $data['my_hash'] = $hash;
        $data['myMeet'] = $myMeet;
        $data['online'] = $this->partyRepository->getOnlinePeople($group);


        echo json_encode($data);
        die();


    }

    /**
     * Facetime Register
     *
     * @Route("/party_end_chat", methods={"GET", "POST"}, name="party_end_chat")
     * @throws \Exception
     */
    public function party_end_chat(Request $request)
    {
        header('Content-Type: application/json');

        $data = array('ok' => true, 'channel' => '','swap'=>0);
        $channel_id = $request->get('room');

        $session = $this->get('session');
        $session->start();
        $hash = $session->get('myHash');


        $channel = $this->getDoctrine()
            ->getRepository('App:PartyChannel')
            ->findOneBy(["id" => $channel_id]);

        if ($channel) {
            $this->facetimeRepository->delete($channel);
        }

//        $session->set('myHash', '');
        echo json_encode($data);
        die();
    }
}
