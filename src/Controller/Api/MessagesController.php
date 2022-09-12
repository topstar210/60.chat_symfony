<?php

namespace App\Controller\Api;

use App\Controller\Constant;
use App\FayeClient\Adapter\CurlAdapter;
use App\Repository\ChatMessageRepository;
use App\Repository\ChatParticipantRepository;
use App\Repository\ChatRepository;
use App\Repository\ClubRepository;
use App\Repository\UserRepository;
use App\Service\S3Wrapper;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Chat;
use App\Entity\ChatMessage;
use App\Entity\ChatParticipant;
use App\Utils\PushNotification;
use App\Utils\Mailer;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Validation;
use App\FayeClient\Client as FayeClient;

/**
 * @Route("/api/messages", name="api_messages_")
 */
class MessagesController extends BaseApiController
{
    private $clubRepository;
    private $chatParticipantRepository;
    private $chatRepository;
    private $chatMessageRepository;

    private $s3wrapper;
    private $validator;
    private $security;

    public function __construct(UserRepository $userRepository, ClubRepository $clubRepository, ChatParticipantRepository $chatParticipantRepository,
                                ChatRepository $chatRepository, ChatMessageRepository $chatMessageRepository, S3Wrapper $s3wrapper, Security $security)
    {
        $this->userRepository = $userRepository;
        $this->clubRepository = $clubRepository;
        $this->chatParticipantRepository = $chatParticipantRepository;
        $this->chatRepository = $chatRepository;
        $this->chatMessageRepository = $chatMessageRepository;
        $this->s3wrapper = $s3wrapper;
        $this->validator = Validation::createValidator();
        $this->security = $security;
    }

    /**
     * Returns all "open" chat messages users.
     *
     * @param Request $request
     * @return JsonResponse List of chats
     *
     * @Route("/open_chats", methods={"GET", "POST"}, name="open_chats")
     * @throws Exception
     */
    public function openChatsAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $username    = $request->get('username', $GLOBALS[Constant::$user]->getUsername());
        $clubId      = $request->get('club_id');
        $includeClub = $request->get('include_club', false);
        $page        = $request->get('page', 1);
        $limit       = $request->get('limit', Constant::$conf_limit);

        // change type
        if ($includeClub != -1) $includeClub = (boolean) $includeClub;

        // override with specific club
        if ($clubId && !$includeClub = $this->clubRepository->find($clubId)) {
            return $this->getErrorJson(sprintf('Club "%d" does not exist.', $clubId));
        }

        // reset the user when on club is set
        if ($username && !$user = $this->userRepository->findUserByUsername($username)) {
            return $this->getErrorJson(sprintf('User "%s" does not exist.', $username));
        }
        if ($clubId) {
            $user = null;
        }

        $chats = $this->chatParticipantRepository->findOpenChats($user, null, $includeClub, $page, $limit);

        foreach ($chats['result'] as &$chat) {
            $chat['last_message']['files'] = $this->s3wrapper->getObjectUrl($chat['last_message']['files']);

            foreach ($chat['participants'] as &$participant) {
                $participant['photo'] = $this->s3wrapper->getObjectUrl($participant['photo']);
            }

            if (isset($chat['club']['photo']) && $chat['club']['photo']) {
                $chat['club']['photo'] = $this->s3wrapper->getObjectUrl($chat['club']['photo']);
            }
        }

        return $this->getSuccessJson([
            'chats' => $chats,
        ]);
    }

    /**
     * Close chat.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/close_chat", methods={"GET", "POST"}, name="close_chat")
     * @throws Exception
     */
    public function closeAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $chatId = $request->get('chat_id');
        $username = $request->get('username', $GLOBALS[Constant::$user]->getUsername());

        if (!$chat = $this->chatRepository->find($chatId)) {
            return $this->getErrorJson(sprintf('Chat "%d" does not exist.', $chatId));
        }
        if (!$user = $this->userRepository->findUserByUsername($username)) {
            return $this->getErrorJson(sprintf('User "%s" does not exist.', $username));
        }
        if (!$chat->getParticipantByUser($user)) {
            return $this->getErrorJson('You are not part of this chat.');
        }
        if ($chat->getClub() && $chat->getClub()->getUser()->getId() != $GLOBALS[Constant::$user]->getId()) {
            return $this->getErrorJson('Only club owner can close a chat.');
        }

        foreach ($chat->getParticipants() as $participant) {
            if (
                // delete all participants
                // note: this will lead to delete the entire chat
                $chat->getClub() ||

                // delete only user
                $participant->getUser()->getId() == $user->getId()
            ) {
                $chat->getParticipants()->removeElement($participant);

                $this->chatParticipantRepository->delete($participant);

                $this->chatRepository->update($chat);
            }
        }

        if ($chat->getParticipants()->count() === 0) {

            // delete all files
            foreach ($chat->getMessages() as $message) {
                foreach ($message->getFiles() as $filename) {
                    $this->s3wrapper->deleteFiles([$filename]);
                }

                $chat->getMessages()->removeElement($message);

                $this->chatMessageRepository->delete($message);
            }

            $this->chatRepository->delete($chat);
        }

        return $this->getSuccessJson([]);
    }

    /**
     * Get chat.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/get", methods={"GET", "POST"}, name="get")
     * @throws Exception
     */
    public function getAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $chatId = $request->get('chat_id');

        if (!$chat = $this->chatRepository->find($chatId)) {
            return $this->getErrorJson(sprintf('Chat "%d" does not exist.', $chatId));
        }

        $chats = $this->chatParticipantRepository->findOpenChats(null, $chat, true, 1, 1);

        if ($chats['count'] === 1) {
            return $this->getSuccessJson([
                'chat' => $chats['result'][0],
            ]);
        }

        return $this->getErrorJson(sprintf('Chat "%d" does not exist.', $chatId));
    }

    /**
     * Searches messages history.
     *
     * @param Request $request
     * @return JsonResponse List of messages
     *
     * @Route("/history", methods={"GET", "POST"}, name="history")
     * @throws Exception
     */
    public function historyAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $chatId = $request->get('chat_id');
        $fromId = $request->get('from_id');
        $fromDate = $request->get('from_date');
        $page = $request->get('page', 1);
        $limit = $request->get('limit', Constant::$conf_limit);

        if (!$chat = $this->chatRepository->find($chatId)) {
            return $this->getErrorJson(sprintf('Chat "%d" does not exist.', $chatId));
        }

        $filters = [
            'chat' => $chat
        ];

        if ($fromId) $filters['from_id'] = $fromId;
        if ($fromDate) $filters['from_date'] = $fromDate;

        $messages = $this->chatMessageRepository->search($filters, null, $page, $limit);

        if (isset($messages['result'])) {
            foreach ($messages['result'] as $key => $value) {
                $to = [];
                foreach ($chat->getParticipants() as $participant) {
                    if ($participant->getUser()->getId() == $GLOBALS[Constant::$user]->getId()) {
                        if ($participant->getUnread() > 0 && !$request->get('ignore_unread')) {
                            $participant->setUnread(0);

                            $this->chatParticipantRepository->update($participant);
                        }
                    }

                    if ($participant->getUser()->getId() != $value->getUser()->getId()) {
                        $to[] = $participant->getUser()->getUsername();
                    }
                }

                $messages['result'][$key] = [
                    'chat_id' => $value->getChat()->getId(),
                    'club_id' => null,
                    'from_username' => $value->getUser()->getUsername(),
                    'from_name' => $value->getUser()->getName(),
                    'from_gender' => $value->getUser()->getGender(),
                    'from_photo' => $this->s3wrapper->getObjectUrl($value->getUser()->getPhoto()),
                    'to_usernames' => $to,
                    'message_id' => $value->getId(),
                    'message' => $value->getMessage(),
                    'files' => $this->s3wrapper->getObjectUrl($value->getFiles()),
                    'date_created' => $value->getDateCreated(),
                    'is_me' => $value->getUser()->getId() == $GLOBALS[Constant::$user]->getId(),
                ];

                // set the club id
                if ($value->getChat()->getClub()) {
                    $messages['result'][$key]['club_id'] = $value->getChat()->getClub()->getId();
                }
            }
        }

        return $this->getSuccessJson([
            'messages' => $messages,
        ]);
    }

    /**
     * Sends start/stop typing to user.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/typing", methods={"GET", "POST"}, name="typing")
     * @throws Exception
     */
    public function typingAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $command = $request->get('command');
        $chatId = $request->get('chat_id');

        if (!$chat = $this->chatRepository->find($chatId)) {
            return $this->getErrorJson(sprintf('Chat "%d" does not exist.', $chatId));
        }

        $pushNotification = [];

        /*foreach ($chat->getParticipants() as $participant) {
            $user = $participant->getUser();

            if ($user->getId() == $GLOBALS[Constant::$user]->getId()) continue;

            switch ($command) {
                case 'start':
                    $pushNotification[$user->getUsername()] = PushNotification::send($user, [
                        'parameters' => $chat->getId(),
                        'from_username' => $GLOBALS[Constant::$user]->getUsername(),
                        'title' => 'START_TYPING',
                        'message' => null,
                    ]);

                    break;

                case 'stop':
                    $pushNotification[$user->getUsername()] = PushNotification::send($user, [
                        'parameters' => $chat->getId(),
                        'from_username' => $GLOBALS[Constant::$user]->getUsername(),
                        'title' => 'STOP_TYPING',
                        'message' => null,
                    ]);


                    break;
            }
        }*/

        return $this->getSuccessJson([
            'push_notification' => $pushNotification,
        ]);
    }

    /**
     * Adds a message.
     *
     * @param Request $request
     * @return JsonResponse Saved message
     *
     * @Route("/add", methods={"GET", "POST"}, name="add")
     * @throws Exception
     */
    public function addAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $chatId = $request->get('chat_id');
        $clubId = $request->get('club_id');
        $subject = $request->get('subject');
        $text = $request->get('text');
        $participants = (array) $request->get('participants');

        // convert string to object
        foreach ($participants as $key => $username) {
            if (
                // no me
                $username != $GLOBALS[Constant::$user]->getUsername() &&

                // found user object
                ($user = $this->userRepository->findUserByUsername($username))
            ) {
                // throw an exception on blocked user
                if ($GLOBALS[Constant::$user]->getBlockedUsers()->contains($user)) {
                    return $this->getErrorJson(sprintf('User "%s" is blocked, and cannot be massaged too.', $user->getUsername()));
                }
                if ($user->getBlockedUsers()->contains($GLOBALS[Constant::$user])) {
                    return $this->getErrorJson(sprintf('User "%s" has blocked you, and cannot receive your massage.', $user->getUsername()));
                }

                $participants[$key] = $user;
            } else {
                unset($participants[$key]);
            }
        }

        // add logged-in user
        $participants[] = $GLOBALS[Constant::$user];

        // get club
        $club = null;
        if ($clubId && !$club = $this->clubRepository->find($clubId)) {
            return $this->getErrorJson(sprintf('Club "%d" does not exist.', $clubId));
        }

        // create a new chat if doesn't exists
        if (!$chat = $this->chatRepository->find($chatId)) {
            $chat = new Chat();
            $chat->setSubject($subject);

            // assign club to chat
            if ($club) {
                $chat->setClub($club);
            }

            $this->chatRepository->create($chat);
        }

        // get chat club
        $club = $chat->getClub() ?: null;

        // override with club participants
        if ($club) {
            $participants = [];

            // add only enabled participants
            foreach ($club->getParticipants() as $participant) {
                if ($participant->getEnabled()) {
                    $participants[$participant->getUser()->getId()] = $participant->getUser();
                }
            }

            // prevent non-club participants from adding message
            if (!in_array($GLOBALS[Constant::$user]->getId(), array_keys($participants))) {
                return $this->getErrorJson('You are not part of this club.');
            }
        }

        // assign participants
        foreach ($participants as $user) {
            $found = false;
            foreach ($chat->getParticipants() as $participant) {
                if ($user->getId() == $participant->getUser()->getId()) {
                    $found = true;

                    break;
                }
            }
            if (!$found) {
                $participant = new ChatParticipant();
                $participant->setUser($user);
                $participant->setChat($chat);
                $participant->setOpen(true);
                $participant->setUnread(0);

                $this->chatParticipantRepository->create($participant);

                $chat->getParticipants()->add($participant);
            }
        }

        // remove participants who aren't part of the club
        if ($club) {
            foreach ($chat->getParticipants() as $participant) {
                if (!$club->getParticipantByUser($participant->getUser())) {
                    $chat->getParticipants()->removeElement($participant);

                    $this->chatParticipantRepository->delete($participant);
                }
            }
        }

        // making sure chat include more than one participant
        if (!$chat && $chat->getParticipants()->count() <= 1) {
            return $this->getErrorJson('You must provide at least two participants (including sender) when creating a new chat.');
        }

        $message = new ChatMessage();
        $message->setChat($chat);
        $message->setUser($GLOBALS[Constant::$user]);
        $message->setMessage($text);


        $files = [$request->get('file')];

        foreach ($files as $key => $fileJson) {
            if($fileJson){
//                $file = json_decode($fileJson);
//                $filename = $file->data;
                $filename = $fileJson;
            }else{
                $filename = '';
            }
            if (!$filename || !$this->s3wrapper->doesObjectExist($filename)) {
                unset($files[$key]);

                continue;
            }

            // upload file
            $objectKeys = $this->s3wrapper
                ->addFiles('chats', null, [$filename]);

            // delete old file
            $this->s3wrapper->deleteFiles([$filename]);

            // assign file
            if ($objectKeys) {
                $files[$key] = current($objectKeys);
            } else {
                unset($files[$key]);
            }
        }
        $message->setFiles($files);

        $this->chatMessageRepository->create($message);

        $chat->getMessages()->add($message);

        // set "to" usernames
        $to = [];
        foreach ($chat->getParticipants() as $participant) {
            if ($participant->getUser()->getId() == $GLOBALS[Constant::$user]->getId()) {
                $participant->setUnread(0);
            } else {
                $to[] = $participant->getUser()->getUsername();

                $participant->setUnread($participant->getUnread() + 1);
            }

            if (!$participant->getOpen()) {
                $participant->setOpen(true);
            }

            $this->chatParticipantRepository->update($participant);
        }

        // set response
        $message = [
            'chat_id' => $chat->getId(),
            'club_id' => null,
            'from_username' => $GLOBALS[Constant::$user]->getUsername(),
            'from_name' => $GLOBALS[Constant::$user]->getName(),
            'from_gender' => $GLOBALS[Constant::$user]->getGender(),
            'from_photo' => $this->s3wrapper->getObjectUrl($GLOBALS[Constant::$user]->getPhoto()),
            'to_usernames' => $to,
            'message_id' => $message->getId(),
            'message' => $message->getMessage(),
            'files' => $this->s3wrapper->getObjectUrl($message->getFiles()),
            'date_created' => $message->getDateCreated(),
            'is_me' => true,
        ];

        // set the club id
        if ($club) {
            $message['club_id'] = $club->getId();
        }

        // send message to friend(s)
        $pushNotification = [];

        foreach ($chat->getParticipants() as $participant) {
            $user = $participant->getUser();

            if ($user->getId() == $GLOBALS[Constant::$user]->getId()) continue;

            $pushNotification[$user->getUsername()] = PushNotification::send($user, [
                'parameters' => $chat->getId(),
                'from_username' => $GLOBALS[Constant::$user]->getUsername(),
                'title' => $club ? 'NEW_CLUB_MESSAGE' : 'NEW_MESSAGE',
                'message' => $message,
            ]);

            // send message back to friend browser
            try {
                $fayeClient = new FayeClient(
                    new CurlAdapter(),
                    sprintf('%s:3000/faye', $request->getSchemeAndHttpHost())
                );
                $fayeClient->send(sprintf('/%s/messages', $user->getToken()), ['message' => $message]);

            } catch (\Exception $e) {}

            // send message
            if ($user->getEmail()) {
                $emailMessage = sprintf("
Hi '%s'!

You have unread messages on ChatApp!

                ", (string) $user);

                Mailer::send($user, 'ChatApp - You have unread messages', $emailMessage);
            }
            if ($user->getPhoneNumber()) {
                // todo: sprintf('"%s" You have unread messages on ChatApp!.', (string) $user)
            }
        }

        return $this->getSuccessJson([
            'message' => $message,
            'push_notification' => $pushNotification,
        ]);
    }

    /**
     * Mark a message as read.
     *
     * Note: we only count unread messages, to actually tag each message as read..
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/read", methods={"GET", "POST"}, name="read")
     * @throws Exception
     */
    public function readAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $chatId = $request->get('chat_id');

        if (!$chat = $this->chatRepository->find($chatId)) {
            return $this->getErrorJson(sprintf('Chat "%d" does not exist.', $chatId));
        }

        foreach ($chat->getParticipants() as $participant) {
            if (
                $participant->getUser()->getId() == $GLOBALS[Constant::$user]->getId()
                &&
                $participant->getUnread() > 0
            ) {
                $participant->setUnread(0);

                $this->chatParticipantRepository->update($participant);
            }
        }

        return $this->getSuccessJson([]);
    }

    /**
     * Updates a message (only change text).
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/edit", methods={"GET", "POST"}, name="edit")
     * @throws Exception
     */
    public function editAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $chatId = $request->get('chat_id');
        $messageId = $request->get('message_id');
        $text = $request->get('text');
        $subject = $request->get('subject');

        if (!$chat = $this->chatRepository->find($chatId)) {
            return $this->getErrorJson(sprintf('Chat "%d" does not exist.', $chatId));
        }

        // update chat subject
        if ($subject) {
            $chat->setSubject($subject);

            $this->chatRepository->update($chat);
        }

        // update chat message
        if ($messageId) {
            if (!$message = $this->chatMessageRepository->find($messageId)) {
                return $this->getErrorJson(sprintf('Chat Message "%d" does not exist.', $messageId));
            }
            if ($message->getChat()->getId() != $chat->getId()) {
                return $this->getErrorJson('Invalid Chat Message.');
            }
            if ($message->getUser()->getId() != $GLOBALS[Constant::$user]->getId()) {
                return $this->getErrorJson('You can only edit messages you created.');
            }

            $message->setMessage($text);

            $this->chatMessageRepository->update($message);
        }

        return $this->getSuccessJson([]);
    }

    /**
     * Deletes a message.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/delete", methods={"GET", "POST"}, name="delete")
     * @throws Exception
     */
    public function deleteAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $chatId = $request->get('chat_id');
        $messageId = $request->get('message_id');

        if (!$chat = $this->chatRepository->find($chatId)) {
            return $this->getErrorJson(sprintf('Chat "%d" does not exist.', $chatId));
        }
        if (!$message = $this->chatMessageRepository->find($messageId)) {
            return $this->getErrorJson(sprintf('Chat Message "%d" does not exist.', $messageId));
        }
        if ($message->getChat()->getId() != $chat->getId()) {
            return $this->getErrorJson('Invalid Chat Message.');
        }
        if ($message->getUser()->getId() != $GLOBALS[Constant::$user]->getId()) {
            return $this->getErrorJson('You can only edit messages you created.');
        }

        // delete all files
        foreach ($message->getFiles() as $filename) {
            $this->s3wrapper->deleteFiles([$filename]);
        }

        $chat->getMessages()->removeElement($message);

        $this->chatMessageRepository->delete($message);

        $this->chatRepository->update($chat);

        return $this->getSuccessJson([]);
    }

    /**
     * Invites (add) a user to the chat.
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
        if (!$user = $this->userRepository->findUserByUsername($username)) {
            return $this->getErrorJson(sprintf('User "%s" does not exist.', $username));
        }
        PushNotification::send($user, [
            'parameters' => "test parameter",
            'from_username' => $GLOBALS[Constant::$user]->getUsername(),
            'title' => 'INVITE',
            'message' => sprintf('%s invited %s to the group chat.', $GLOBALS[Constant::$user]->getUsername(), $user->getUsername()),
        ]);
        return $this->getSuccessJson([]);


        $username = $request->get('username');
        $chatId = $request->get('chat_id');

        if (!$user = $this->userRepository->findUserByUsername($username)) {
            return $this->getErrorJson(sprintf('User "%s" does not exist.', $username));
        }
        if ($GLOBALS[Constant::$user]->getBlockedUsers()->contains($user)) {
            return $this->getErrorJson(sprintf('User "%s" is blocked, and cannot be massaged too.', $user->getUsername()));
        }
        if (!$chat = $this->chatRepository->find($chatId)) {
            return $this->getErrorJson(sprintf('Chat "%d" does not exist.', $chatId));
        }
        if ($chat->getClub()) {
            return $this->getErrorJson('Invalid request.');
        }

        foreach ($chat->getParticipants() as $participant) {
            if ($participant->getUser()->getId() == $user->getId()) {
                return $this->getErrorJson(sprintf('User "%s" is already one of the participants of the chat.', $user->getUsername()));
            }
        }

        $participant = new ChatParticipant();
        $participant->setUser($user);
        $participant->setChat($chat);
        $participant->setOpen(true);

        $this->chatParticipantRepository->create($participant);

        $chat->getParticipants()->add($participant);

        // send message to friend(s)
        $pushNotification = [];

        foreach ($chat->getParticipants() as $participant) {
            if ($participant->getUser()->getId() == $GLOBALS[Constant::$user]->getId()) continue;

            $pushNotification[$participant->getUser()->getUsername()] = PushNotification::send($participant->getUser(), [
                'parameters' => $chat->getId(),
                'from_username' => $GLOBALS[Constant::$user]->getUsername(),
                'title' => 'INVITE',
                'message' => sprintf('%s invited %s to the group chat.', $GLOBALS[Constant::$user]->getUsername(), $user->getUsername()),
            ]);
        }

        return $this->getSuccessJson([
            'push_notification' => $pushNotification,
        ]);
    }
}
