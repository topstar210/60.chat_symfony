<?php

namespace App\Controller\Api;

use App\Controller\Constant;
use App\Entity\MomentRanking;
use App\FayeClient\Adapter\CurlAdapter;
use App\Repository\MomentCommentRepository;
use App\Repository\MomentRankingRepository;
use App\Repository\MomentRepository;
use App\Repository\UserRepository;
use App\Service\S3Wrapper;
use DateInterval;
use DateTime;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Moment;
use App\Entity\MomentComment;
use App\Entity\User;
use App\Utils\PushNotification;
use App\Utils\Mailer;
use App\FayeClient\Client as FayeClient;
/**
 * @Route("/api/moments", name="api_moments_")
 */
class MomentsController extends BaseApiController
{
    private $momentRepository;
    private $momentCommentRepository;
    private $momentRankingRepository;

    private $s3wrapper;

    public function __construct(UserRepository $userRepository, MomentRepository $momentRepository, MomentCommentRepository $momentCommentRepository,
                                MomentRankingRepository $momentRankingRepository, S3Wrapper $s3wrapper)
    {
        $this->userRepository = $userRepository;
        $this->momentRepository = $momentRepository;
        $this->momentCommentRepository = $momentCommentRepository;
        $this->momentRankingRepository = $momentRankingRepository;

        $this->s3wrapper = $s3wrapper;
    }

    /**
     * Returns list of moments.
     *
     * @param Request $request
     * @return JsonResponse List of moments
     *
     * @Route("/search", methods={"GET", "POST"}, name="search")
     * @throws Exception
     */
    public function searchAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $type         = $request->get('type', 'user');
        $query        = $request->get('query');
        $distance     = $request->get('distance');
        $username     = $request->get('username', $GLOBALS[Constant::$user]->getUsername());
        $gender       = $request->get('gender');
        $ageFrom      = $request->get('age_from');
        $ageTo        = $request->get('age_to');
        $ethnicity    = $request->get('ethnicity');
        $country      = $request->get('country');
        $includeBlock = $request->get('include_block', false);
        $sort         = $request->get('sort');
        $page         = $request->get('page', 1);
        $limit        = $request->get('limit', Constant::$conf_limit);


        // remove undefined properties
        $equals = (array) $request->get('equals');
        foreach ($equals as $key => $value) {
            if (!property_exists('\App\Entity\User', $key)) {
                unset($equals[$key]);
            }
        }

        // change type
        if ($includeBlock != -1) $includeBlock = (boolean) $includeBlock;

        // get all blocked moments ids
        $blockedMoments = [];
        if ($includeBlock !== true) {
            foreach ($GLOBALS[Constant::$user]->getBlockedMoments() as $blockedMment) {
                $blockedMoments[] = $blockedMment->getId();
            }
        }

        $moments = [];
        switch ($type) {
            case 'friends':
                $moments = $this->momentRepository->search([
                    'query' => $query,
                    'equals' => $equals,
                    'friends_of' => $GLOBALS[Constant::$user],
                    'latitude' => $GLOBALS[Constant::$user]->getLatitude(),
                    'longitude' => $GLOBALS[Constant::$user]->getLongitude(),
                    'distance' => $distance,
                    'distance_by' => $GLOBALS[Constant::$user]->getDistanceBy(),
                    'gender' => $gender,
                    'age_from' => $ageFrom,
                    'age_to' => $ageTo,
                    'ethnicity' => $ethnicity,
                    'country' => $country,
                    'block_moments' => $blockedMoments,
                    'block_only' => $includeBlock == -1,
                ], $sort, $page, $limit);

                break;

            case 'all':
                $moments = $this->momentRepository->search([
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
                    'block_moments' => $blockedMoments,
                    'block_only' => $includeBlock == -1,
                ], $sort, $page, $limit);

                break;

            case 'user':
            default:
                $moments = $this->momentRepository->search([
                    'query' => $query,
                    'equals' => $equals,
                    'username' => $username,
                    'latitude' => $GLOBALS[Constant::$user]->getLatitude(),
                    'longitude' => $GLOBALS[Constant::$user]->getLongitude(),
                    'distance' => $distance,
                    'distance_by' => $GLOBALS[Constant::$user]->getDistanceBy(),
                    'gender' => $gender,
                    'age_from' => $ageFrom,
                    'age_to' => $ageTo,
                    'ethnicity' => $ethnicity,
                    'country' => $country,
                    'block_moments' => $blockedMoments,
                    'block_only' => $includeBlock == -1,
                ], $sort, $page, $limit);
        }

        $result = [];
        if (isset($moments['result'])) {
            $ids = [];

            foreach ($moments['result'] as $moment) {
                // set value user
                $moment = is_array($moment) ? $moment[0] : $moment;

                // get properties
                $result[$moment->getId()] = $this->getProperties($moment);

                // reset unread moments when profile view his moments
                if ($username == $GLOBALS[Constant::$user]->getUsername() && $moment->getUnread() > 0) {
                    $moment->setUnread(0);

                    $this->momentRepository->update($moment);
                }
            }
        }

        $moments['result'] = array_values($result);

        return $this->getSuccessJson([
            'moments' => $moments,
        ]);
    }

    /**
     * Get moment.
     *
     * @param Request $request
     * @return JsonResponse Moment properties
     *
     * @Route("/get", methods={"GET", "POST"}, name="get")
     * @throws Exception
     */
    public function getAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $momentId = $request->get('moment_id');

        if (!$moment = $this->momentRepository->find($momentId)) {
            return $this->getErrorJson(sprintf('Moment "%d" does not exist.', $momentId));
        }

        // get properties
        $properties = $this->getProperties($moment);

        return $this->getSuccessJson([
            'moment' => $properties,
        ]);
    }

    /**
     * Get moment properties.
     *
     * @param Moment $moment
     *
     * @return array
     */
    protected function getProperties(Moment $moment)
    {
        // get comments
        $comments = [];
        foreach ($moment->getComments() as $comment) {
            if ($comment->getUser()) {
                $comments[] = [
                    'id' => $comment->getId(),
                    'user' => $comment->getUser()->getUsername(),
                    'name' => $comment->getUser()->getName(),
                    'photo' => $this->s3wrapper->getObjectUrl($comment->getUser()->getPhoto()),
                    'gender' => $comment->getUser()->getGender(),
                    'comment' => $comment->getComment(),
                    'date_created' => $comment->getDateCreated(),
                ];
            }
        }
        // reverse: new to old
        $comments = array_reverse($comments);

        // get likes
        $likes = [];
        foreach ($moment->getLikes() as $user) {
            $likes[] = [
                'id' => $user->getId(),
                'user' => $user->getUsername(),
                'name' => $user->getName(),
                'photo' => $this->s3wrapper->getObjectUrl($user->getPhoto()),
                'gender' => $user->getGender(),
            ];
        }

        // get mentions
        $mentions = [];
        foreach ($moment->getMentions() as $user) {
            $mentions[] = [
                'id' => $user->getId(),
                'user' => $user->getUsername(),
                'name' => $user->getName(),
                'photo' => $this->s3wrapper->getObjectUrl($user->getPhoto()),
                'gender' => $user->getGender(),
            ];
        }

        $properties = [
            'id' => $moment->getId(),
            'username' => $moment->getUser()->getUsername(),
            'photo' => $this->s3wrapper->getObjectUrl($moment->getUser()->getPhoto()),
            'gender' => $moment->getUser()->getGender(),
            'cover_flag' => (boolean) $moment->getCoverFlag(),
            'name' => $moment->getName(),
            'images' => $this->s3wrapper->getObjectUrl($moment->getImages()),
            'location' => $moment->getLocation(),
            'latitude' => $moment->getLatitude(),
            'longitude' => $moment->getLongitude(),
            'distance' => null,
            'unread' => $moment->getUnread(),
            'comments' => $comments,
            'likes' => $likes,
            'mention' => $mentions,
            'date_created' => $moment->getDateCreated(),
        ];

        // fix distance value
        if ($GLOBALS[Constant::$user]->getLatitude() && $GLOBALS[Constant::$user]->getLatitude()) {
            $distance = null;

            if (is_array($moment) && isset($moment['distance'])) {
                $distance = $moment['distance'];
            } else {
                $distance = $this->userRepository->distance(
                    $GLOBALS[Constant::$user]->getLatitude(),
                    $GLOBALS[Constant::$user]->getLongitude(),
                    $moment->getUser()->getLatitude(),
                    $moment->getUser()->getLongitude(),
                    $GLOBALS[Constant::$user]->getDistanceBy() == User::DISTANCEBY_MILES
                );
            }

            if (!is_nan($distance) && is_numeric($distance)) {
                $properties['distance'] = $distance;
            }
        }

        return $properties;
    }

    /**
     * Adds a moment.
     *
     * @param Request $request
     * @return JsonResponse Moment properties
     *
     * @throws Exception
     * @Route("/add", methods={"GET", "POST"}, name="add")
     */
    public function addAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $name = $request->get('name');
        $mentions = (array) $request->get('mention');
        $location = $request->get('location');
        $latitude = $request->get('latitude');
        $longitude = $request->get('longitude');
        $isCover = $request->get('is_cover');

        // check number of moments created today
        $now = new DateTime();
        if ($this->momentRepository->search([
            'count' => true,
            'username' => $GLOBALS[Constant::$user]->getUsername(),
            'from_date' => $now->format('Y-m-d'),
            'to_date' => $now->add(new DateInterval('P1D'))->format('Y-m-d'),
        ]) === 10) {
            return $this->getErrorJson('You have already posted the maximum amount of moments for today.');
        }

        $moment = new Moment();
        $moment->setUser($GLOBALS[Constant::$user]);
        $moment->setName($name);
        $moment->setLocation($location);
        $moment->setLatitude($latitude);
        $moment->setLongitude($longitude);
        $moment->setCoverFlag(false);

        if ($isCover) {
            $moment->setCoverFlag(true);

            // "uncover" previous "cover"
            $moments = $this->momentRepository->search([
                'all' => true,
                'username' => $GLOBALS[Constant::$user]->getUsername(),
                'cover_flag' => true
            ]);
            foreach ($moments as $m) {
                $m->setCoverFlag(false);

                $this->momentRepository->update($m);
            }
        }

        $photos = (array) $request->get('photos');
        foreach ($photos as $key => $filename) {
            if (!$filename || !$this->s3wrapper->doesObjectExist($filename)) {
                unset($photos[$key]);

                continue;
            }

            // upload photo
            $objectKeys = $this->s3wrapper
                ->addFiles('users', $GLOBALS[Constant::$user]->getTokenById(), [$filename],
                    Constant::$conf_media_sizes['moment']);

            // delete old photo
            $this->s3wrapper->deleteFiles([$filename], Constant::$conf_media_sizes['moment']);

            // assign photo
            $photos[$key] = $objectKeys['origin'];
        }
        if (count($photos) === 0) {
            return $this->getErrorJson('Moment must include at least 1 photo.');
        }
        $moment->setImages($photos);

        foreach ($mentions as $username) {
            if ($user = $this->userRepository->findUserByUsername($username)) {
                $moment->getMentions()->add($user);
            }
        }

        $this->momentRepository->create($moment);

        // get comments
        $comments = [];
        foreach ($moment->getComments() as $comment) {
            $comments[] = [
                'id' => $comment->getId(),
                'user' => $comment->getUser()->getUsername(),
                'comment' => $comment->getComment(),
                'date_created' => $comment->getDateCreated(),
            ];
        }

        // get mention
        $mentions = [];
        foreach ($moment->getMentions() as $user) {
            $mentions[] = [
                'id' => $user->getId(),
                'user' => $user->getUsername(),
            ];
        }

        // get likes
        $likes = [];
        foreach ($moment->getLikes() as $user) {
            $likes[] = [
                'id' => $user->getId(),
                'user' => $user->getUsername(),
            ];
        }

        // get properties
        $properties = $this->getProperties($moment);

        return $this->getSuccessJson([
            'moment' => $properties,
        ]);
    }

    /**
     * Deletes a moment.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/delete", methods={"GET", "POST"}, name="delete")
     * @throws Exception
     */
    public function deleteAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $momentId = $request->get('moment_id');

        // allow admin to delete any moment
        if ($GLOBALS[Constant::$user]->isSuperAdmin()) {
            $moment = $this->momentRepository->find($momentId);
        } else {
            $moment = $this->momentRepository->findOneBy([
                'user' => $GLOBALS[Constant::$user],
                'id' => $momentId,
            ]);
        }

        if (!$moment) {
            return $this->getErrorJson(sprintf('Moment "%d" does not exist.', $momentId));
        }

        // delete all images
        $this->s3wrapper->deleteFiles($moment->getImages(), Constant::$conf_media_sizes['moment']);

        $this->momentRepository->delete($moment);

        return $this->getSuccessJson([]);
    }

    /**
     * Adds a moment comment.
     *
     * @param Request $request
     * @return JsonResponse Moment comment
     *
     * @Route("/add_comment", methods={"GET", "POST"}, name="add_comment")
     * @throws Exception
     */
    public function addCommentAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $momentId = $request->get('moment_id');
        $username = $request->get('username');
        $comment = $request->get('comment');

        if (!$user = $this->userRepository->findUserByUsername($username)) {
            return $this->getErrorJson(sprintf('User "%s" does not exist.', $username));
        }

        if (!$moment = $this->momentRepository->findOneBy([
            'user' => $user,
            'id' => $momentId,
        ])) {
            return $this->getErrorJson(sprintf('Moment "%d" does not exist.', $momentId));
        }

        $momentComment = new MomentComment();
        $momentComment->setMoment($moment);
        $momentComment->setUser($GLOBALS[Constant::$user]);
        $momentComment->setComment($comment);

        $this->momentCommentRepository->create($momentComment);

        // increase the unread count
        $moment->setUnread($moment->getUnread() + 1);

        $this->momentRepository->update($moment);

        // push notification
        PushNotification::send($user, [
            'parameters' => $momentId,
            'from_username' => $GLOBALS[Constant::$user]->getUsername(),
            'title' => 'MOMENT_COMMENT',
            'message' => $comment,
        ]);

        // send message back to creator browser
        try {
            // get properties
            $properties = $this->getProperties($moment);

            $fayeClient = new FayeClient(
                new CurlAdapter(),
                sprintf('%s:3000/faye', $request->getSchemeAndHttpHost())
            );
            $fayeClient->send(
                sprintf('/%s/new_moment_comment', $moment->getUser()->getToken()),
                ['moment' => $properties]
            );
        } catch (Exception $e) {}

        return $this->getSuccessJson([
            'id' => $momentComment->getId(),
            'user' => $momentComment->getUser()->getUsername(),
            'photo' => $this->s3wrapper->getObjectUrl($momentComment->getUser()->getPhoto()),
            'comment' => $momentComment->getComment(),
            'date_created' => $momentComment->getDateCreated(),
        ]);
    }

    /**
     * Deletes a moment comment.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/delete_comment", methods={"GET", "POST"}, name="delete_comment")
     * @throws Exception
     */
    public function deleteCommentAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $momentId = $request->get('moment_id');
        $momentCommentId = $request->get('moment_comment_id');

        // allow admin to delete any moment comment
        if ($GLOBALS[Constant::$user]->isSuperAdmin()) {
            $moment = $this->momentRepository->find($momentId);
        } else {
            $moment = $this->momentRepository->findOneBy([
                'user' => $GLOBALS[Constant::$user],
                'id' => $momentId,
            ]);
        }

        if (!$moment) {
            return $this->getErrorJson(sprintf('Moment "%d" does not exist.', $momentId));
        }

        if (!$momentComment = $this->momentCommentRepository->find($momentCommentId)) {
            return $this->getErrorJson(sprintf('Moment comment "%d" does not exist.', $momentCommentId));
        }

        if ($momentComment->getMoment()->getId() != $moment->getId()) {
            return $this->getErrorJson('Moment comment does not belong to the moment requested.');
        }

        $this->momentCommentRepository->delete($momentComment);

        return $this->getSuccessJson([]);
    }

    /**
     * Mark moment as like.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/like", methods={"GET", "POST"}, name="like")
     * @throws Exception
     */
    public function likeAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $momentId = $request->get('moment_id');

        if (!$moment = $this->momentRepository->find($momentId)) {
            return $this->getErrorJson(sprintf('Moment "%d" does not exist.', $momentId));
        }
        if (!$moment->getLikes()->contains($GLOBALS[Constant::$user])) {
            $moment->getLikes()->add($GLOBALS[Constant::$user]);
        }

        // increase the unread count
        $moment->setUnread($moment->getUnread() + 1);
        $this->momentRepository->update($moment);
        $moment_user = $moment->getUser();
        $liked_user = $this->userRepository->findUserById($moment_user->getId());
        // push notification
        PushNotification::send($liked_user, [
            'parameters' => $momentId,
            'from_username' => $GLOBALS[Constant::$user]->getUsername(),
            'title' => 'MOMENT_LIKE',
            'message' => 'like',
        ]);

        // send message back to creator browser
        try {
            // get images
            $images = $this->s3wrapper->getObjectUrl($moment->getImages());

            $fayeClient = new FayeClient(
                new CurlAdapter(),
                sprintf('%s:3000/faye', $request->getSchemeAndHttpHost())
            );
            $fayeClient->send(
                sprintf('/%s/alert', $moment->getUser()->getToken()),
                ['alert' => [
                    'title' => sprintf('New Like for %s', $moment->getName()),
                    'message' => sprintf('%s has liked you moment "%s"', $GLOBALS[Constant::$user]->getUsername(), $moment->getName()),
                    'image' => $images[0]
                ]]
            );
        } catch (Exception $e) {}

        return $this->getSuccessJson([]);
    }

    /**
     * Unmark moment as like.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/unlike", methods={"GET", "POST"}, name="unlike")
     * @throws Exception
     */
    public function unlikeAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $momentId = $request->get('moment_id');

        if (!$moment = $this->momentRepository->find($momentId)) {
            return $this->getErrorJson(sprintf('Moment "%d" does not exist.', $momentId));
        }

        if ($moment->getLikes()->contains($GLOBALS[Constant::$user])) {
            $moment->getLikes()->removeElement($GLOBALS[Constant::$user]);

            $this->momentRepository->update($moment);
        }

        return $this->getSuccessJson([]);
    }

    /**
     * Mark moment as block.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/block", methods={"GET", "POST"}, name="block")
     * @throws Exception
     */
    public function blockAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $momentId = $request->get('moment_id');

        if (!$moment = $this->momentRepository->find($momentId)) {
            return $this->getErrorJson(sprintf('Moment "%d" does not exist.', $momentId));
        }

        if (!$GLOBALS[Constant::$user]->getBlockedMoments()->contains($moment)) {
            $GLOBALS[Constant::$user]->getBlockedMoments()->add($moment);

            $this->userRepository->update($GLOBALS[Constant::$user]);
        }

        // send message
        if ($moment->getUser()->getEmail()) {
            $message = sprintf("
Hi '%s'!

Some content you have posted on ChatApp was flagged and removed. Per ChatApp terms and
conditions, inappropriate content is not permitted and your account maybe suspended or
removed. Please refrain from posting any inappropriate content on ChatApp in the future.

            ", (string) $moment->getUser());

            Mailer::send($moment->getUser()->getEmail(), 'ChatApp - Inappropriate Content', $message);
        }
        if ($moment->getUser()->getPhoneNumber()) {
            // todo: 'Some content you have posted on ChatApp was flagged and removed. Please refrain from posting any inappropriate content on ChatApp in the future.'
        }

        return $this->getSuccessJson([]);
    }

    /**
     * Unmark moment as block.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/unblock", methods={"GET", "POST"}, name="unblock")
     * @throws Exception
     */
    public function unblockAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $momentId = $request->get('moment_id');

        if (!$moment = $this->momentRepository->find($momentId)) {
            return $this->getErrorJson(sprintf('Moment "%d" does not exist.', $momentId));
        }

        if ($GLOBALS[Constant::$user]->getBlockedMoments()->contains($moment)) {
            $GLOBALS[Constant::$user]->getBlockedMoments()->removeElement($moment);

            $this->userRepository->update($GLOBALS[Constant::$user]);
        }

        return $this->getSuccessJson([]);
    }

    /**
     * rank moment or profile.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/statistics", methods={"GET", "POST"}, name="statistics")
     * @throws Exception
     */
    public function statisticsAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $moment_id = $request->get('moment_id');
        $username = $request->get('username');

        if (!$moment_id && !$username) {
            return $this->getErrorJson( 'The moment id and username can not be empty.');
        }

        if( $moment_id ){
            $rank_info = $this->momentRankingRepository->getRankInfo(1, $moment_id, 0);
        } else{
            if (!$user = $this->userRepository->findUserByUsernameOrPhoneNumberOrEmail($username)) {
                return $this->getErrorJson( sprintf('Username "%s" does not exist.', $username) );
            }
            $rank_info = $this->momentRankingRepository->getRankInfo(2, 0, $user->getId());
        }

        return $this->getSuccessJson([
            'rank_info' => $rank_info,
        ]);
    }
    /**
     * rank moment or profile.
     *
     * @param Request $request
     * @return JsonResponse
     * @Route("/rank", methods={"GET", "POST"}, name="rank")
     * @throws Exception
     */
    public function rankAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $moment_id = $request->get('moment_id');
        $username = $request->get('username');
        $rank = $request->get('rank');

        if (!$moment_id && !$username) {
            return $this->getErrorJson( 'The moment id and username can not be empty.');
        }
        if (!isset($rank)) {
            return $this->getErrorJson( 'The rank can not be empty.');
        }
        if( $rank > 5.0 ){
            return $this->getErrorJson( 'The rank value is invalid.');
        }

        $moment_ranking = new MomentRanking();
        if( $moment_id ){
            if (!$moment = $this->momentRepository->find($moment_id)) {
                return $this->getErrorJson(sprintf('Moment "%d" does not exist.', $moment_id));
            }
            $rank_result = $this->momentRankingRepository->getRank(1, $moment_id, 0, $GLOBALS[Constant::$user]->getId());

            $moment_ranking->setKind(1);
            $moment_ranking->setMomentId($moment_id);
        } else{
            if (!$user = $this->userRepository->findUserByUsernameOrPhoneNumberOrEmail($username)) {
                return $this->getErrorJson( sprintf('Username "%s" does not exist.', $username) );
            }
            $rank_result = $this->momentRankingRepository->getRank(2, 0, $user->getId(), $GLOBALS[Constant::$user]->getId());

            $moment_ranking->setKind(2);
            $moment_ranking->setProfileId($user->getId());
        }
        $moment_ranking->setUserId($GLOBALS[Constant::$user]->getId());
        $moment_ranking->setRate($rank);

        if( count($rank_result) > 0 ){
            if( $rank > 0 ){
                $moment_ranking = $rank_result[0];
                $moment_ranking->setRate($rank);
                $this->momentRankingRepository->update($moment_ranking);
            }
        } else{
            $this->momentRankingRepository->create($moment_ranking);
        }

        if( $moment_id ){
            $rank_info = $this->momentRankingRepository->getRankInfo(1, $moment_id, 0);
        } else{
            $rank_info = $this->momentRankingRepository->getRankInfo(2, 0, $user->getId());
        }

        return $this->getSuccessJson([
            'rank_info' => $rank_info,
        ]);
    }
}
