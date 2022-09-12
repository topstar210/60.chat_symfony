<?php

namespace App\Controller\Api;

use App\Controller\Constant;
use App\Repository\FriendRepository;
use App\Repository\MomentRepository;
use App\Repository\UserRepository;
use App\Service\S3Wrapper;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Friend;
use App\Entity\User;

/**
 * @Route("/api/contacts", name="api_contacts_")
 */
class ContactsController extends BaseApiController
{
    private $friendRepository;

    private $s3wrapper;

    public function __construct(UserRepository $userRepository, MomentRepository $momentRepository, FriendRepository $friendRepository, S3Wrapper $s3wrapper)
    {
        $this->userRepository = $userRepository;
        $this->friendRepository = $friendRepository;

        $this->s3wrapper = $s3wrapper;
    }

    /**
     * Searches for contacts.
     *
     * @param Request $request
     * @return JsonResponse List of contacts (contact: {username, phone, about me})
     *
     * @Route("/search", methods={"GET", "POST"}, name="search")
     * @throws Exception
     */
    public function searchAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $type         = $request->get('type', 'all');
        $query        = $request->get('query');
        $distance     = $request->get('distance');
        $username     = $request->get('username');
        $gender       = $request->get('gender');
        $ageFrom      = $request->get('age_from');
        $ageTo        = $request->get('age_to');
        $ethnicity    = $request->get('ethnicity');
        $country      = $request->get('country');
        $includeBlock = $request->get('include_block', false);
        $onlyPhoto    = $request->get('only_photo', false);
        $sort         = $request->get('sort');
        $page         = $request->get('page', 1);
        $limit        = $request->get('limit', Constant::$conf_limit);
        $equals = (array) $request->get('equals');

        $user = $this->userRepository->findUserByUsername($username);

        // get user
        if (!$user = $this->userRepository->findUserByUsername($username)) {
            $user = $GLOBALS[Constant::$user];
        }

        // remove undefined properties
        foreach ($equals as $key => $value) {
            if (!property_exists('\App\Entity\User', $key)) {
                unset($equals[$key]);
            }
        }

        // change type
        if ($includeBlock != -1) $includeBlock = (boolean) $includeBlock;
        $onlyPhoto = (boolean) $onlyPhoto;

        // get all blocked users ids
        $blockedUsers = [];
        if ($includeBlock !== true) {
            foreach ($GLOBALS[Constant::$user]->getBlockedUsers() as $blockedUser) {
                $blockedUsers[] = $blockedUser->getId();
            }
        }

        // always remove logged-in user from results
        $blockedUsers[] = $GLOBALS[Constant::$user]->getId();

        $contacts = [];

        switch ($type) {
            case 'friends':
                $contacts = $this->friendRepository->search([
                    'user' => $user,
                    'query' => $query,
                    'equals' => $equals,
                    'latitude' => $GLOBALS[Constant::$user]->getLatitude(),
                    'longitude' => $GLOBALS[Constant::$user]->getLongitude(),
                    'distance' => $distance,
                    'distance_by' => $GLOBALS[Constant::$user]->getDistanceBy(),
                    'gender' => $gender,
                    'age_from' => $ageFrom,
                    'age_to' => $ageTo,
                    'ethnicity' => $ethnicity,
                    'country' => $country,
                    'block_users' => $blockedUsers,
                    'block_only' => $includeBlock == -1,
                    'only_photo' => $onlyPhoto,
                ], $sort, $page, $limit);

                break;

            case 'usernames':
                $contacts = $this->userRepository->search([
                    'username' => $query,
                    'equals' => $equals,
                    'latitude' => $GLOBALS[Constant::$user]->getLatitude(),
                    'longitude' => $GLOBALS[Constant::$user]->getLongitude(),
                    'distance' => $distance,
                    'distance_by' => $GLOBALS[Constant::$user]->getDistanceBy(),
                    'gender' => $gender,
                    'age_from' => $ageFrom,
                    'age_to' => $ageTo,
                    'ethnicity' => $ethnicity,
                    'country' => $country,
                    'block_users' => $blockedUsers,
                    'block_only' => $includeBlock == -1,
                    'only_photo' => $onlyPhoto,
                ], 'username', $page, $limit);

                break;

            case 'all':
            default:
                $contacts = $this->userRepository->search([
                    'query' => $query,
                    'equals' => $equals,
                    'latitude' => $GLOBALS[Constant::$user]->getLatitude(),
                    'longitude' => $GLOBALS[Constant::$user]->getLongitude(),
                    'distance' => $distance,
                    'distance_by' => $GLOBALS[Constant::$user]->getDistanceBy(),
                    'gender' => $gender,
                    'age_from' => $ageFrom,
                    'age_to' => $ageTo,
                    'ethnicity' => $ethnicity,
                    'country' => $country,
                    'block_users' => $blockedUsers,
                    'block_only' => $includeBlock == -1,
                    'only_photo' => $onlyPhoto,
                ], $sort, $page, $limit);
        }

        if (isset($contacts['result'])) {
            foreach ($contacts['result'] as $key => $value) {
                // set value user
                $user = is_array($value) ? $value[0] : $value;

                // get friend
                if ($type == 'friends') {
                    $user = $user->getFriend();
                }

                $contacts['result'][$key] = [
                    'username' => $user->getUsername(),
                    'name' => $user->getName(),
                    'gender' => $user->getGender(),
                    'birthday' => $user->getBirthday(),
                    'ethnicity' => $user->getEthnicity(),
                    'region' => $user->getRegion(),
                    'interest' => explode('|', $user->getInterest()),
                    'aboutme' => $user->getAboutme(),
                    'greeting' => $user->getGreeting(),
                    'latitude' => $user->getLatitude(),
                    'longitude' => $user->getLongitude(),
                    'distance' => null,
                    'photo' => $this->s3wrapper->getObjectUrl($user->getPhoto()),
                    'background' => $this->s3wrapper->getObjectUrl($user->getBackground()),
                    'is_friended' => $this->friendRepository->isBefriended($GLOBALS[Constant::$user], $user),
                ];

                // fix distance value
                if ($GLOBALS[Constant::$user]->getLatitude() && $GLOBALS[Constant::$user]->getLatitude()) {
                    $distance = null;

                    if (is_array($value) && isset($value['distance'])) {
                        $distance = $value['distance'];
                    } else {
                        $distance = $this->userRepository->distance(
                            $GLOBALS[Constant::$user]->getLatitude(),
                            $GLOBALS[Constant::$user]->getLongitude(),
                            $user->getLatitude(),
                            $user->getLongitude(),
                            $GLOBALS[Constant::$user]->getDistanceBy() == User::DISTANCEBY_MILES
                        );
                    }

                    if (!is_nan($distance) && is_numeric($distance)) {
                        $contacts['result'][$key]['distance'] = $distance;
                    }
                }

                // get most recent moments
                $parameters = [
                    'username' => $user->getUsername(),
                ];
                $moments = $this->forward('App\Controller\Api\MomentsController::searchAction', $parameters);
                $moments = json_decode($moments->getContent(), true);

                $contacts['result'][$key]['moments'] = $moments['data']['moments']['result'];
            }
        }

        return $this->getSuccessJson([
            'type' => $type,
            'contacts' => $contacts,
        ]);
    }

    /**
     * Adds a friend.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/add", methods={"GET", "POST"}, name="add_username")
     * @throws Exception
     */
    public function addAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $username = $request->get('username');

        if (!$friend = $this->userRepository->findUserByUsername($username)) {
            if (!$friend = $this->userRepository->findUserByEmail($username)) {
                return $this->getErrorJson(sprintf('Friend "%s" does not exist.', $username));
            }
        }

        if ($this->friendRepository->isBefriended($GLOBALS[Constant::$user], $friend)) {
            return $this->getErrorJson(sprintf('Friend "%s" already your friend.', $username));
        }

        $contact = new Friend();
        $contact->setUser($GLOBALS[Constant::$user]);
        $contact->setFriend($friend);

        $this->friendRepository->create($contact);

        return $this->getSuccessJson([]);
    }

    /**
     * Deletes a friend.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/delete", methods={"GET", "POST"}, name="delete_username")
     * @throws Exception
     */
    public function deleteAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $username = $request->get('username');

        if (!$friend = $this->userRepository->findUserByUsername($username)) {
            return $this->getErrorJson(sprintf('Friend "%s" does not exist.', $username));
        }

        if (!$contact = $this->friendRepository->isBefriended($GLOBALS[Constant::$user], $friend)) {
            return $this->getErrorJson(sprintf('Friend "%s" is not your friend.', $username));
        }

        $this->friendRepository->delete($contact);

        return $this->getSuccessJson([]);
    }

    /**
     * Adds a user to my like list.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/like", methods={"GET", "POST"}, name="like")
     * @throws Exception
     */
    public function likeAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $username = $request->get('username');

        if (!$user = $this->userRepository->findUserByUsername($username)) {
            return $this->getErrorJson(sprintf('Username "%s" does not exist.', $username));
        }

        if (!$GLOBALS[Constant::$user]->getLikes()->contains($user)) {
            $GLOBALS[Constant::$user]->getLikes()->add($user);

            $this->userRepository->update($GLOBALS[Constant::$user]);
        }

        return $this->getSuccessJson([]);
    }

    /**
     * Removes a user from my like list.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/unlike", methods={"GET", "POST"}, name="unlike")
     * @throws Exception
     */
    public function unlikeAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $username = $request->get('username');

        if (!$user = $this->userRepository->findUserByUsername($username)) {
            return $this->getErrorJson(sprintf('Username "%s" does not exist.', $username));
        }

        if ($GLOBALS[Constant::$user]->getLikes()->contains($user)) {
            $GLOBALS[Constant::$user]->getLikes()->removeElement($user);

            $this->userRepository->update($GLOBALS[Constant::$user]);
        }

        return $this->getSuccessJson([]);
    }

    /**
     * Mark friend as favorite.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/favorite", methods={"GET", "POST"}, name="favorite")
     * @throws Exception
     */
    public function favoriteAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $username = $request->get('username');

        if (!$friend = $this->userRepository->findUserByUsername($username)) {
            return $this->getErrorJson(sprintf('Friend "%s" does not exist.', $username));
        }

        if (!$contact = $this->friendRepository->isBefriended($GLOBALS[Constant::$user], $friend)) {
            return $this->getErrorJson(sprintf('Friend "%s" is not your friend.', $username));
        }

        $contact->setFavorite(true);

        $this->friendRepository->update($contact);

        return $this->getSuccessJson([]);
    }

    /**
     * Unmark friend as favorite.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/unfavorite", methods={"GET", "POST"}, name="unfavorite")
     * @throws Exception
     */
    public function unfavoriteAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $username = $request->get('username');

        if (!$friend = $this->userRepository->findUserByUsername($username)) {
            return $this->getErrorJson(sprintf('Friend "%s" does not exist.', $username));
        }

        if (!$contact = $this->friendRepository->isBefriended($GLOBALS[Constant::$user], $friend)) {
            return $this->getErrorJson(sprintf('Friend "%s" is not your friend.', $username));
        }

        $contact->setFavorite(false);

        $this->friendRepository->update($contact);

        return $this->getSuccessJson([]);
    }

    /**
     * Mark user as block.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/block", methods={"GET", "POST"}, name="block")
     * @throws Exception
     */
    public function blockAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $username = $request->get('username');

        if (!$user = $this->userRepository->findUserByUsername($username)) {
            return $this->getErrorJson(sprintf('User "%s" does not exist.', $username));
        }
        if ($user->getId() == $GLOBALS[Constant::$user]->getId()) {
            return $this->getErrorJson('You can not block your self.');
        }

        if (!$GLOBALS[Constant::$user]->getBlockedUsers()->contains($user)) {
            $GLOBALS[Constant::$user]->getBlockedUsers()->add($user);

            $this->userRepository->update($GLOBALS[Constant::$user]);
        }

        return $this->getSuccessJson([]);
    }

    /**
     * Unmark user as block.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/unblock", methods={"GET", "POST"}, name="unblock")
     * @throws Exception
     */
    public function unblockActionunblockAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $username = $request->get('username');

        if (!$user = $this->userRepository->findUserByUsername($username)) {
            return $this->getErrorJson(sprintf('User "%s" does not exist.', $username));
        }

        if ($GLOBALS[Constant::$user]->getBlockedUsers()->contains($user)) {
            $GLOBALS[Constant::$user]->getBlockedUsers()->removeElement($user);

            $this->userRepository->update($GLOBALS[Constant::$user]);
        }

        return $this->getSuccessJson([]);
    }

    /**
     * Gives friend an alias name.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/alias", methods={"GET", "POST"}, name="alias")
     * @throws Exception
     */
    public function aliasAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $username = $request->get('username');
        $alias = $request->get('alias');

        if (!$friend = $this->userRepository->findUserByUsername($username)) {
            return $this->getErrorJson(sprintf('Friend "%s" does not exist.', $username));
        }

        if (!$contact = $this->friendRepository->isBefriended($GLOBALS[Constant::$user], $friend)) {
            return $this->getErrorJson(sprintf('Friend "%s" is not your friend.', $username));
        }

        $contact->setAlias($alias);

        $this->friendRepository->update($contact);

        return $this->getSuccessJson([]);
    }
}
