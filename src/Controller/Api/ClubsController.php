<?php

namespace App\Controller\Api;

use App\Controller\Constant;
use App\Repository\ChatMessageRepository;
use App\Repository\ChatRepository;
use App\Repository\ClubParticipantRepository;
use App\Repository\ClubRepository;
use App\Repository\ChatParticipantRepository;
use App\Repository\UserRepository;
use App\Service\S3Wrapper;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Club;
use App\Entity\ClubParticipant;
use App\Entity\ChatParticipant;
use App\Entity\User;
use App\Utils\Mailer;
use App\Utils\PushNotification;
use Symfony\Component\Security\Core\Security;

/**
 * @Route("/api/clubs", name="api_clubs_")
 */
class ClubsController extends BaseApiController
{
    private $clubRepository;
    private $clubParticipantRepository;
    private $chatMessageRepository;
    private $chatRepository;
    private $chatParticipantRepository;


    private $s3wrapper;
    private $security;
    private $users;

    public function __construct(UserRepository $userRepository, ClubRepository $clubRepository, ClubParticipantRepository $clubParticipantRepository,
                                ChatMessageRepository $chatMessageRepository, ChatRepository $chatRepository, ChatParticipantRepository $chatParticipantRepository,
                                S3Wrapper $s3wrapper, Security $security)
    {
        $this->userRepository = $userRepository;
        $this->clubRepository = $clubRepository;
        $this->clubParticipantRepository = $clubParticipantRepository;
        $this->chatMessageRepository = $chatMessageRepository;
        $this->chatRepository = $chatRepository;
        $this->chatParticipantRepository = $chatParticipantRepository;
        $this->s3wrapper = $s3wrapper;
        $this->security = $security;
    }

    /**
     * Returns list of clubs.
     *
     * @param Request $request
     * @return JsonResponse List of clubs
     *
     * @Route("/search", methods={"GET", "POST"}, name="search")
     * @throws Exception
     */
    public function searchAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $username  = $request->get('username');
        $query     = $request->get('query');
        $distance  = $request->get('distance');
        $onlyOwned = $request->get('only_owned', true);
        $sort      = $request->get('sort');
        $page      = $request->get('page', 1);
        $limit     = $request->get('limit', Constant::$conf_limit);

        $filters = [
            'query' => $query,
            'latitude' => $GLOBALS[Constant::$user]->getLatitude(),
            'longitude' => $GLOBALS[Constant::$user]->getLongitude(),
            'distance' => $distance,
            'distance_by' => $GLOBALS[Constant::$user]->getDistanceBy(),
            'only_owned' => $onlyOwned,
        ];

        if ($username) {
            if (!$this->userRepository->findUserByUsername($username)) {
                return $this->getErrorJson(sprintf('User "%s" does not exist.', $username));
            }

            $filters['username'] = $username;
        }

        $clubs = $this->clubRepository->search($filters, $sort, $page, $limit);

        $result = [];

        if (isset($clubs['result'])) {
            $ids = [];

            foreach ($clubs['result'] as $club) {
                // set value user
                $club = is_array($club) ? $club[0] : $club;

                // get properties
                $result[$club->getId()] = $this->getProperties($club);
            }
        }

        $clubs['result'] = array_values($result);

        return $this->getSuccessJson([
            'clubs' => $clubs,
        ]);
    }

    /**
     * Get club.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/get", methods={"GET", "POST"}, name="get")
     * @throws Exception
     */
    public function getAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $clubId = $request->get('club_id');

        if (!$club = $this->clubRepository->find($clubId)) {
            return $this->getErrorJson(sprintf('Club "%d" does not exist.', $clubId));
        }

        // get properties
        $properties = $this->getProperties($club);

        return $this->getSuccessJson([
            'club' => $properties,
        ]);
    }

    /**
     * Get moment properties.
     *
     * @param Club $club
     *
     * @return array
     */
    protected function getProperties(Club $club)
    {
        // get participants
        $participants = [];
        foreach ($club->getParticipants() as $participant) {
            $participants[] = [
                'id' => $participant->getUser()->getId(),
                'user' => $participant->getUser()->getUsername(),
                'username' => $participant->getUser()->getUsername(),
                'name' => $participant->getUser()->getName(),
                'photo' => $this->s3wrapper->getObjectUrl($participant->getUser()->getPhoto()),
                'gender' => $participant->getUser()->getGender(),
                'enabled' => $participant->getEnabled(),
            ];
        }

        // calculate distance
        $distance = null;
        if ($GLOBALS[Constant::$user]->getLatitude() && $GLOBALS[Constant::$user]->getLatitude()) {
            if (is_array($club) && isset($club['distance'])) {
                $distance = $club['distance'];
            } else {
                $distance = $this->userRepository->distance(
                    $GLOBALS[Constant::$user]->getLatitude(),
                    $GLOBALS[Constant::$user]->getLongitude(),
                    $club->getLatitude(),
                    $club->getLongitude(),
                    $GLOBALS[Constant::$user]->getDistanceBy() == User::DISTANCEBY_MILES
                );
            }

            if (!is_nan($distance) && is_numeric($distance)) {
                $result[$club->getId()]['distance'] = $distance;
            }
        }

        // get all open messages
        $parameters = [
            'club_id' => $club->getId(),
            'page' => -1,
            'limit' => -1
        ];
        $chats = $this->forward('App\Controller\Api\MessagesController::openChatsAction', $parameters);
        $chats = json_decode($chats->getContent(), true);
        $chats = $chats['data']['chats']['result'];

        return [
            'id' => $club->getId(),
            'name' => $club->getName(),
            'description' => $club->getDescription(),
            'photo' => $this->s3wrapper->getObjectUrl($club->getPhoto()),
            'background' => $this->s3wrapper->getObjectUrl($club->getBackground()),
            'latitude' => $club->getLatitude(),
            'longitude' => $club->getLongitude(),
            'distance' => $distance,
            'owner' => [
                'id' => $club->getUser()->getId(),
                'user' => $club->getUser()->getUsername(),
                'name' => $club->getUser()->getName(),
                'photo' => $this->s3wrapper->getObjectUrl($club->getUser()->getPhoto()),
                'gender' => $club->getUser()->getGender(),
            ],
            'participants' => $participants,
            'chats' => $chats,
            'date_created' => $club->getDateCreated(),
        ];
    }

    /**
     * Adds a club.
     *
     * @Route("/add", methods={"GET", "POST"}, name="add")
     *
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @see saveAction
     */
    public function addAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        return $this->saveClub($request);
    }

    /**
     * Updates a club.
     *
     * @Route("/edit", methods={"GET", "POST"}, name="edit")
     *
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @see saveAction
     */
    public function editAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        return $this->saveClub($request);
    }

    /**
     * Adds or updates a club.
     *
     * @param Request $request
     * @return JsonResponse Saved club
     *
     * @Route("/save", methods={"GET", "POST"}, name="save")
     */
    protected function saveClub(Request $request)
    {
        $clubId = $request->get('club_id');
        $name = $request->get('name');
        $description = $request->get('description');
        $photo = $request->get('photo');
        $background = $request->get('background');
        $latitude = $request->get('latitude');
        $longitude = $request->get('longitude');
        $enabled = $request->get('enabled', false);

        // update
        if ($clubId) {
            if (!$club = $this->clubRepository->find($clubId)) {
                return $this->getErrorJson(sprintf('Club "%d" does not exist.', $clubId));
            }
        }
        // new
        else {
            $club = new Club();
            $club->setUser($GLOBALS[Constant::$user]);
        }

        // block non-owner
        if (!$GLOBALS[Constant::$user]->isSuperAdmin() && $club->getUser()->getId() != $GLOBALS[Constant::$user]->getId()) {
            return $this->getErrorJson('Permission denied.');
        }

        $club->setName($name);
        $club->setDescription($description);
        $club->setLatitude($latitude);
        $club->setLongitude($longitude);
        $club->setEnabled($enabled);

        $images = [
            'photo' => $photo,
            'background' => $background
        ];

        foreach ($images as $key => $image) {
            if ($image && $this->s3wrapper->doesObjectExist($image)) {

                // upload file
                $objectKeys = $this->s3wrapper
                    ->addFiles('clubs', null, [$image]);

                // delete old file
                $this->s3wrapper->deleteFiles([$image]);

                // assign file
                if ($objectKeys) {
                    // remove old
                    $method = 'get'.ucfirst($key);
                    if ($club->$method()) {
                        $this->s3wrapper->deleteFiles([$club->$method()]);
                    }

                    // set new
                    $method = 'set'.ucfirst($key);
                    $club->$method(current($objectKeys));
                }
            }
        }

        // update
        if ($clubId) {
            $this->clubRepository->update($club);
        }
        // new
        else {
            $this->clubRepository->create($club);
        }

        // add owner as participant
        if (!$participant = $club->getParticipantByUser($GLOBALS[Constant::$user])) {
            $participant = new ClubParticipant();
            $participant->setUser($GLOBALS[Constant::$user]);
            $participant->setClub($club);
            $participant->setEnabled(true);

            $this->clubParticipantRepository->create($participant);

            $club->getParticipants()->add($participant);
        }

        // get properties
        $properties = $this->getProperties($club);

        return $this->getSuccessJson([
            'club' => $properties,
        ]);
    }

    /**
     * Deletes a club.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/delete", methods={"GET", "POST"}, name="delete")
     * @throws Exception
     */
    public function deleteAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $clubId = $request->get('club_id');

        // allow admin to delete any club
        if ($GLOBALS[Constant::$user]->isSuperAdmin()) {
            $club = $this->clubRepository->find($clubId);
        } else {
            $club = $this->clubRepository->findOneBy([
                'user' => $GLOBALS[Constant::$user],
                'id' => $clubId,
            ]);
        }

        if (!$club) {
            return $this->getErrorJson(sprintf('Club "%d" does not exist.', $clubId));
        }

        // block non-owner
        if (!$GLOBALS[Constant::$user]->isSuperAdmin() && $club->getUser()->getId() != $GLOBALS[Constant::$user]->getId()) {
            return $this->getErrorJson('Permission denied.');
        }

        // delete all files
        foreach ($club->getChats() as $chat) {

            // remove all participants
            foreach ($chat->getParticipants() as $participant) {
                $chat->getParticipants()->removeElement($participant);

                $this->clubParticipantRepository->delete($participant);
            }

            // remove all messages
            foreach ($chat->getMessages() as $message) {
                foreach ($message->getFiles() as $filename) {
                    $this->s3wrapper->deleteFiles([$filename]);
                }

                $chat->getMessages()->removeElement($message);

                $this->chatMessageRepository->delete($message);
            }

            $this->chatRepository->delete($chat);
        }

        // delete photo/background
        $this->s3wrapper->deleteFiles([$club->getPhoto(), $club->getBackground()]);

        $this->clubRepository->delete($club);

        return $this->getSuccessJson([]);
    }

    /**
     * Invites (request) a user to the club.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/invite", methods={"GET", "POST"}, name="invite")
     * @throws Exception
     */
    public function inviteAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $username = $request->get('username');
        $clubId = $request->get('club_id');
        $accept = $request->get('accept');

        if (!$user = $this->userRepository->findUserByUsername($username)) {
            return $this->getErrorJson(sprintf('User "%s" does not exist.', $username));
        }
        if (!$club = $this->clubRepository->find($clubId)) {
            return $this->getErrorJson(sprintf('Club "%d" does not exist.', $clubId));
        }

        // get participant
        $participant = $club->getParticipantByUser($user);

        switch ($accept) {
            // request
            case null:
                if ($participant) {
                    return $this->getErrorJson(sprintf('User "%s" is already one of the participants of the club.', $user->getUsername()));
                }

                $participant = new ClubParticipant();
                $participant->setUser($user);
                $participant->setClub($club);
                //$participant->setEnabled(false);

                $this->clubParticipantRepository->create($participant);

                $club->getParticipants()->add($participant);

                // send message to club owner
                PushNotification::send($club->getUser(), [
                    'parameters' => $club->getId(),
                    'from_username' => $GLOBALS[Constant::$user]->getUsername(),
                    'title' => 'INVITE_CLUB_REQUEST',
                    'message' => sprintf('%s request to be invited to club "%s".', $GLOBALS[Constant::$user]->getUsername(), $club->getName()),
                ]);

                // send message
                if ($club->getUser()->getEmail()) {
                    $message = sprintf("
Hi '%s'!

%s has requested to join your club '%s'. Please approve or reject in ChatApp.

                    ", (string) $club->getUser(), (string) $GLOBALS[Constant::$user], $club->getName());

                    Mailer::send($club->getUser(), 'ChatApp - Club Join Request', $message);
                }
                if ($club->getUser()->getPhoneNumber()) {
                    // todo: sprintf('%s has requested to join your ChatApp club "%s".', (string) $GLOBALS[Constant::$user], $club->getName())
                }

                break;

            // approve
            case 1:
            case true:

                if (!$participant) {
                    return $this->getErrorJson(sprintf('User "%s" have not requested to join the club.', $user->getUsername()));
                }

                $participant->setEnabled(true);

                $this->clubParticipantRepository->update($participant);

                // add participant to all club chats
                $p = new ChatParticipant();
                $p->setUser($participant->getUser());
                $p->setOpen(true);
                $p->setUnread(1);

                foreach ($club->getChats() as $chat) {
                    $p->setChat($chat);

                    $this->chatParticipantRepository->create($p);

                    $chat->getParticipants()->add($p);

                    $this->chatRepository->update($chat);
                }

                // send message to participant
                PushNotification::send($participant->getUser(), [
                    'parameters' => $club->getId(),
                    'from_username' => $GLOBALS[Constant::$user]->getUsername(),
                    'title' => 'INVITE_CLUB_APPROVE',
                    'message' => sprintf('%s has approved your invitation to club "%s".', $GLOBALS[Constant::$user]->getUsername(), $club->getName()),
                ]);

                // send message
                if ($participant->getUser()->getEmail()) {
                    $message = sprintf("
Hi '%s'!

You have been approved to join the club '%s'. Open ChatApp to begin a discussion with the club.

                    ", (string) $participant->getUser(), $club->getName());

                    Mailer::send($participant->getUser(), sprintf('ChatApp - Club "%s" Approved', $club->getName()), $message);
                }
                if ($participant->getUser()->getPhoneNumber()) {
                    // todo: sprintf('You have been approved to join the club "%s" in ChatApp.', $club->getName())
                }

                break;

            // reject
            case 0:
            case false:
                if (!$participant) {
                    return $this->getErrorJson(sprintf('User "%s" have not requested to join the club.', $user->getUsername()));
                }

                $club->getParticipants()->removeElement($participant);

                $this->clubParticipantRepository->delete($participant);

                // remove participant from club chats
                foreach ($club->getChats() as $chat) {
                    if ($p = $chat->getParticipantByUser($participant->getUser())) {
                        $chat->getParticipants()->removeElement($p);

                        $this->chatParticipantRepository->delete($p);

                        $this->chatRepository->update($chat);
                    }
                }

                break;

        }

        return $this->getSuccessJson([]);
    }

    /**
     * Removes a user from a club.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/remove_participant", methods={"GET", "POST"}, name="remove_participant")
     * @throws Exception
     */
    public function removeParticipantAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $username = $request->get('username');
        $clubId = $request->get('club_id');

        if (!$user = $this->userRepository->findUserByUsername($username)) {
            return $this->getErrorJson(sprintf('User "%s" does not exist.', $username));
        }
        if (!$club = $this->clubRepository->find($clubId)) {
            return $this->getErrorJson(sprintf('Club "%d" does not exist.', $clubId));
        }

        if (!$participant = $club->getParticipantByUser($user)) {
            return $this->getErrorJson(sprintf('User "%s" have not requested to join the club.', $user->getUsername()));
        }

        $club->getParticipants()->removeElement($participant);

        $this->clubParticipantRepository->delete($participant);

        // remove participant from club chats
        foreach ($club->getChats() as $chat) {
            if ($p = $chat->getParticipantByUser($participant->getUser())) {
                $chat->getParticipants()->removeElement($p);

                $this->chatParticipantRepository->delete($p);

                $this->chatRepository->update($chat);
            }
        }

        return $this->getSuccessJson([]);
    }
}
