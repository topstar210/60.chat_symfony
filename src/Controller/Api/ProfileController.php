<?php

namespace App\Controller\Api;

use App\Controller\Constant;
use App\Repository\ChatParticipantRepository;
use App\Repository\MomentRepository;
use App\Repository\UserDeviceRepository;
use App\Repository\UserRepository;
use App\Service\S3Wrapper;
use App\Utils\Mailer;
use DateTime;
use Exception;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\User;
use App\Entity\UserDevice;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/profile", name="api_profile_")
 */
class ProfileController extends BaseApiController
{
    private $userDeviceRepository;
    private $momentRepository;
    private $chatParticipantRepository;

    private $s3wrapper;
    private $validator;
    private $security;




    /**
     * List of properties allowed to get/set.
     *
     * @var array
     */
    protected $allowedProperties = [
        'username',
        'email',
        'phoneNumber',
        'password',
        'name',
        'gender',
        'birthday',
        'ethnicity',
        'region',
        'interest',
        'aboutme',
        'greeting',
        'latitude',
        'longitude',
        'distance_by',
        'endroidGcmId',
        'iosDeviceId',
        'photo',
        'background',
    ];

    /**
     * List of properties restricted to get.
     *
     * @var array
     */
    protected $restrictedProperties = [
        'password',
        'endroidGcmId',
        'iosDeviceId',
    ];

    public function __construct(UserRepository $userRepository, UserDeviceRepository $userDeviceRepository, MomentRepository $momentRepository,
                                ChatParticipantRepository $chatParticipantRepository, S3Wrapper $s3wrapper, Security $security)
    {
        $this->userRepository = $userRepository;
        $this->userDeviceRepository = $userDeviceRepository;
        $this->momentRepository = $momentRepository;
        $this->chatParticipantRepository = $chatParticipantRepository;
        $this->s3wrapper = $s3wrapper;
        $this->validator = Validation::createValidator();
        $this->security = $security;
    }

    /**
     * Deletes user and all related data.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @Route("/delete", methods={"GET", "POST"}, name="delete")
     */
    public function deleteAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        // allow admin to delete any profile
        if ($GLOBALS[Constant::$user]->isSuperAdmin()) {
            if ($username = $request->get('username')) {
                if (!$user = $this->userRepository->findUserByUsernameOrPhoneNumberOrEmail($username)) {
                    return $this->getErrorJson(sprintf('Username "%s" does not exist.', $username));
                }
            } else{
                return $this->getErrorJson( 'The username can not be empty.');
            }
        } else{
            return $this->getErrorJson( 'You can not delete the user.');
        }

        // delete all clubs
        foreach ($user->getClubs() as $club) {
            $parameters = [
                'club_id' => $club->getId()
            ];
            $this->forward('App\Controller\Api\ClubsController::deleteAction', $parameters);
        }

        // delete all moments
        foreach ($user->getMoments() as $moment) {
            $this->s3wrapper->deleteFiles($moment->getImages(), Constant::$conf_media_sizes['moment']);
            $this->momentRepository->delete($moment);
        }

        // unlink all related files
        $this->s3wrapper->deleteFiles([
            'users/'.$user->getTokenById()
        ]);

        $this->userRepository->delete($user);

        return $this->getSuccessJson([]);
    }

    /**
     * Reports user.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @Route("/report", methods={"GET", "POST"}, name="report")
     */
    public function reportAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        if (!$username = $request->get('username')) {
            return $this->getErrorJson('Invalid username.');
        }
        if (!$user = $this->userRepository->findUserByUsernameOrPhoneNumberOrEmail($username)) {
            return $this->getErrorJson(sprintf('Username "%s" does not exist.', $username));
        }

        $user->setReported(true);

        $this->userRepository->update($user);

        // send message
        if ($user->getEmail()) {
            $message = sprintf("
Hi '%s'!

Some content you have posted on ChatApp was flagged and removed. Per ChatApp terms and
conditions, inappropriate content is not permitted and your account maybe suspended or
removed. Please refrain from posting any inappropriate content on ChatApp in the future.

            ", (string) $user);

            Mailer::send($user->getEmail(), 'ChatApp - Inappropriate Content', $message);
        }
        if ($user->getPhoneNumber()) {
            // todo: 'Some content you have posted on ChatApp was flagged and removed. Please refrain from posting any inappropriate content on ChatApp in the future.'
        }

        return $this->getSuccessJson([]);
    }

    /**
     * Get infos
     *
     * @param Request $request List of user names to retrieve profile info
     *
     * @Route("/get_infos", methods={"GET", "POST"}, name="get_infos")
     * @return JsonResponse
     * @throws Exception
     */
    public function getInfosAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $usernames = (array) $request->get('usernames');

        if (count($usernames) === 0) {
            return $this->getErrorJson('Invalid usernames.');
        }

        $users = $this->userRepository->findUsersByUsername($usernames);

        // get all properties
        $properties = [];
        foreach ($users as &$user) {
            foreach (array_diff($this->allowedProperties, $this->restrictedProperties) as $property) {
                $properties[$property] = in_array($property, ['photo', 'background'])
                    ? $this->s3wrapper->getObjectUrl($user->__get($property))
                    : $user->__get($property)
                ;
            }

            // get distance
            $properties['distance'] = $this->userRepository->distance(
                $GLOBALS[Constant::$user]->getLatitude(),
                $GLOBALS[Constant::$user]->getLongitude(),
                $user->getLatitude(),
                $user->getLongitude(),
                $GLOBALS[Constant::$user]->getDistanceBy() == User::DISTANCEBY_MILES
            );

            $user = $properties;
        }

        return $this->getSuccessJson([
            'users' => $users,

            /** @deprecated since version 1.2, to be removed in 2.0. */
            'properties' => $properties,
        ]);
    }

    /**
     * Gets all user property and related data.
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @throws Exception
     * @Route("/get_info", methods={"GET", "POST"}, name="get_info")
     */
    public function getInfoAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $username = $request->get('username', $GLOBALS[Constant::$user]->getUsername());

        if (!$user = $this->userRepository->findUserByUsernameOrPhoneNumberOrEmail($username)) {
            return $this->getErrorJson(sprintf('Username "%s" does not exist.', $username));
        }

        // get all properties
        $properties = [];
        foreach (array_diff($this->allowedProperties, $this->restrictedProperties) as $property) {
            $properties[$property] = in_array($property, ['photo', 'background'])
                ? $this->s3wrapper->getObjectUrl($user->__get($property))
                : $user->__get($property)
            ;
        }

        // get distance
        $properties['distance'] = $this->userRepository->distance(
            $GLOBALS[Constant::$user]->getLatitude(),
            $GLOBALS[Constant::$user]->getLongitude(),
            $user->getLatitude(),
            $user->getLongitude(),
            $GLOBALS[Constant::$user]->getDistanceBy() == User::DISTANCEBY_MILES
        );

        // get all contacts
        $parameters = [
            'username' => $user->getUsername(),
            'type' => 'friends',
            'page' => -1,
            'limit' => -1
        ];
        $contacts = $this->forward('App\Controller\Api\ContactsController::searchAction', $parameters);
        $contacts = json_decode($contacts->getContent(), true);
        $contacts = $contacts['data']['contacts']['result'];

        // get all moments
        $parameters = [
            'username' => $user->getUsername(),
            'page' => -1,
            'limit' => -1
        ];
        $moments = $this->forward('App\Controller\Api\MomentsController::searchAction', $parameters);

        $moments = json_decode($moments->getContent(), true);
        $moments = $moments['data']['moments']['result'];

        return $this->getSuccessJson([
            'properties' => $properties,
            'contacts' => $contacts,
            'moments' => $moments,
            'unread_chats' => $this->chatParticipantRepository->totalUnreadMessages($user),
            'unread_clubs_chats' => $this->chatParticipantRepository->totalUnreadMessages($user, true),
        ]);
    }

    /**
     * Gets user property value.
     *
     * @param Request $request
     * @return JsonResponse Property value
     *
     * @Route("/get_property", methods={"GET", "POST"}, name="get_property")
     * @throws Exception
     */
    public function getPropertyAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        if (!$property = $request->get('property')) {
            return $this->getErrorJson('Invalid property.');
        }

        if (!in_array($property, $this->allowedProperties)) {
            return $this->getErrorJson('Invalid property.');
        }

        return $this->getSuccessJson([
            'property' => in_array($property, ['photo', 'background'])
                ? $this->s3wrapper->getObjectUrl($GLOBALS[Constant::$user]->__get($property))
                : $GLOBALS[Constant::$user]->__get($property),
        ]);
    }

    /**
     * Sets the user property with a value.
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @throws Exception
     * @Route("/set_property", methods={"GET", "POST"}, name="set_property")
     */
    public function setPropertyAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $property = $request->get('property');
        $value = $request->get('value');

        $resendVerifyCode = false;

        if (!in_array($property, $this->allowedProperties)) {
            return $this->getErrorJson('Invalid property.');
        }

        switch ($property) {
            case 'username':
                if (count($this->validator->validate($value, new Assert\Regex(['pattern' => '/^[a-zA-Z]+([_-]?[a-zA-Z])*$/']))) > 0) {
                    return $this->getErrorJson('Username may only contain alphanumeric characters.');
                }
                if (count($this->validator->validate($value, new Assert\Length(['max' => 15]))) > 0) {
                    return $this->getErrorJson('Username is too long (maximum is 15 characters) and may only contain alphanumeric characters.');
                }

                $check = $this->userRepository->findUserByUsername($value);
                if ($check && $check->getId() != $GLOBALS[Constant::$user]->getId()) {
                    return $this->getErrorJson(sprintf('Username %s already taken', $value));;
                }

                break;

            case 'email':
                if (count($this->validator->validate($value, new Assert\Email())) > 0) {
                    return $this->getErrorJson('Invalid email.');
                }

                $check = $this->userRepository->findUserByEmail($value);
                if ($check && $check->getId() != $GLOBALS[Constant::$user]->getId()) {
                    return $this->getErrorJson(sprintf('Email "%s" already exist.', $value));
                }

                if ($value != $GLOBALS[Constant::$user]->getEmail()) {
                    $resendVerifyCode = true;

                    $request->request->set('email', $value);
                }

                break;

            case 'phoneNumber':
                if (count($this->validator->validate($value, new Assert\Regex(['pattern' => '/^[0-9]+([_ -]?[0-9])*$/']))) > 0) {
                    return $this->getErrorJson('Phone number may only contain numeric characters.');
                }

                $check = $this->userRepository->findUserByPhoneNumber($value);
                if ($check && $check->getId() != $GLOBALS[Constant::$user]->getId()) {
                    return $this->getErrorJson(sprintf('Phone number "%s" already exist.', $value));
                }

                if ($value != $GLOBALS[Constant::$user]->getPhoneNumber()) {
                    $resendVerifyCode = true;

                    $request->request->set('phone_number', $value);
                }

                break;

            case 'birthday':
                if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $value)) {
                    $value = new DateTime($value);
                    if ($value->diff(new DateTime())->format('%y') < 18) {
                        return $this->getErrorJson('User must be older than 18 years old.');
                    }
                } else {
                    return $this->getErrorJson(sprintf('Birthday "%s" is invalid.', $value));
                }

                break;

            case 'background':
                $request->request->set('is_background', true);

            case 'photo':
                $request->request->set('photo', $value);

                $this->uploadPhotoAction($request);

                $value = $property == 'background'
                    ? $GLOBALS[Constant::$user]->getBackground()
                    : $GLOBALS[Constant::$user]->getPhoto()
                ;

                break;

            case 'gender':
                switch (strtolower($value)) {
                    case 'female':
                        $value = 'f';
                        break;

                    case 'male':
                        $value = 'm';
                        break;

                    default:
                         $value = strtolower(substr($value, 0, 1));
                }

                break;

            default:
                break;

        }

        $GLOBALS[Constant::$user]->__set($property, $value);
        $this->userRepository->update($GLOBALS[Constant::$user]);

        if ($resendVerifyCode) {
            $this->forward('App\Controller\Api\AuthController::resendCodeAction', $request->request->all());
        }

        return $this->getSuccessJson([
            'property' => in_array($property, ['photo', 'background'])
                ? $this->s3wrapper->getObjectUrl($GLOBALS[Constant::$user]->__get($property))
                : $GLOBALS[Constant::$user]->__get($property),
        ]);
    }

    /**
     * Updated user password.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/update_password", methods={"GET", "POST"}, name="update_password")
     * @throws Exception
     */
    public function updatePasswordAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $current = $request->get('current');
        $new = $request->get('new');

        if ($GLOBALS[Constant::$user]->getPassword() != $current) {
            return $this->getErrorJson('Invalid Password.');
        }

        $GLOBALS[Constant::$user]->setPassword($new);
        $this->userRepository->update($GLOBALS[Constant::$user]);

        return $this->getSuccessJson([]);
    }

    /**
     * Uploads a user photo.
     *
     * @param Request $request
     * @return string The saved photo filename
     *
     * @Route("/upload_photo", methods={"GET", "POST"}, name="upload_photo")
     * @throws Exception
     */
    public function uploadPhotoAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $filename = $request->get('photo');
        $isBackground = $request->get('is_background');

        if (!$filename || !$this->s3wrapper->doesObjectExist($filename)) {
            return $this->getErrorJson('Missing file.');
        }

        // upload photo
        $objectKeys = $this->s3wrapper
            ->addFiles('users', $GLOBALS[Constant::$user]->getTokenById(), [$filename],
                Constant::$conf_media_sizes['profile']);

        // delete old photo/background
        $this->s3wrapper->deleteFiles([$filename], Constant::$conf_media_sizes['profile']);

        // assign photo
        if ($isBackground) {
            $GLOBALS[Constant::$user]->setBackground($objectKeys['origin']);
        } else {
            $GLOBALS[Constant::$user]->setPhoto($objectKeys['origin']);
        }

        $this->userRepository->update($GLOBALS[Constant::$user]);

        return $this->getSuccessJson([
            'image' => $this->s3wrapper->getObjectUrl($objectKeys['origin']),
        ]);
    }

    /**
     * Resets the user photo.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @Route("/reset_photo", methods={"GET", "POST"}, name="reset_photo")
     */
    public function resetPhotoAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        // allow admin to delete any profile
        if ($GLOBALS[Constant::$user]->isSuperAdmin()) {
            if ($username = $request->get('username')) {
                if (!$user = $this->userRepository->findUserByUsernameOrPhoneNumberOrEmail($username)) {
                    return $this->getErrorJson(sprintf('Username "%s" does not exist.', $username));
                }
            } else{
                return $this->getErrorJson( 'The username can not be empty.');
            }
        } else{
            return $this->getErrorJson( 'You can not reset the photo.');
        }

        $isBackground = $request->get('is_background');

        // delete photo/background
        if ($filename = $isBackground ? $user->getBackground() : $user->getPhoto()) {
            $this->s3wrapper->deleteFiles([$filename], Constant::$conf_media_sizes['profile']);
        }

        if ($isBackground) {
            $user->setBackground(null);
        } else {
            $user->setPhoto(null);
        }
        $this->userRepository->update($user);

        return $this->getSuccessJson([]);
    }

    /**
     * Gets list of profile devices.
     *
     * @Route("/get_devices", methods={"GET", "POST"}, name="get_devices")
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function getDevicesAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $devices = [];

        foreach ($GLOBALS[Constant::$user]->getDevices() as $device) {
            $devices[] = [
                'device_id' => $device->getDeviceId(),
                'android' => $device->isAndroid(),
                'ios' => $device->isIos(),
                'enabled' => $device->isEnabled(),
            ];
        }

        return $this->getSuccessJson([
            'devices' => $devices,
        ]);
    }

    /**
     * Adds device.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/add_device", methods={"GET", "POST"}, name="add_device")
     * @throws Exception
     */
    public function addDeviceAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $deviceId = $request->get('device_id');
        $deviceType = $request->get('device_type');

        if (!$deviceId) {
            return $this->getErrorJson('Missing device.');
        }

        if (!in_array($deviceType, ['ios', 'android'])) {
            return $this->getErrorJson('Invalid device type. Must be ether ios or android.');
        }

        if (!$GLOBALS[Constant::$user]->getDevice($deviceId)) {
            $device = new UserDevice();
            $device->setUser($GLOBALS[Constant::$user]);
            $device->setDeviceId($deviceId);
            $device->setAndroid($deviceType == 'android');
            $device->setIos($deviceType == 'ios');
            $device->setEnabled(true);

            $this->userDeviceRepository->create($device);
        }

        // disabled the device for all other users (only one user at a time)
        if ($result = $this->userDeviceRepository->findDeviceById($deviceId)) {
            foreach ($result as $value) {
                if ($value->getId() != $device->getId()) {
                    $value->setEnabled(false);

                    $this->userDeviceRepository->update($value);
                }
            }
        }

        return $this->getSuccessJson([]);
    }

    /**
     * Removes device.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @Route("/remove_device", methods={"GET", "POST"}, name="remove_device")
     */
    public function removeDeviceAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $deviceId = $request->get('device_id');

        if (!$deviceId || !$device = $GLOBALS[Constant::$user]->getDevice($deviceId)) {
            throw new \Exception('Missing device.');
        }

        $this->userDeviceRepository->delete($device);

        return $this->getSuccessJson([]);
    }

    /**
     * Changes the device status.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/toggle_device", methods={"GET", "POST"}, name="toggle_device")
     * @throws Exception
     */
    public function toggleDeviceAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $deviceId = $request->get('device_id');
        $status = (boolean) $request->get('device_status');

        if (!$deviceId || !$device = $GLOBALS[Constant::$user]->getDevice($deviceId)) {
            return $this->getErrorJson('Missing device.');
        }

        $device->setEnabled($status);

        $this->userDeviceRepository->update($device);

        // disabled the device for all other users (only one user at a time)
        // note: run only when enabeling a device
        if ($status) {
            if ($result = $this->userDeviceRepository->findDeviceById($deviceId)) {
                foreach ($result as $value) {
                    if ($value->getId() != $device->getId()) {
                        $value->setEnabled(false);

                        $this->userDeviceRepository->update($value);
                    }
                }
            }
        }

        return $this->getSuccessJson([]);
    }
}
