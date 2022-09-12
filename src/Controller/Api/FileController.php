<?php

namespace App\Controller\Api;

use App\Repository\UserRepository;
use App\Repository\UserTodoPnRepository;
use App\Service\S3Wrapper;
use App\Utils\PushNotification;
use Exception;
use Imagine\Filter\Transformation;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Validation;

/**
 * @Route("/api/file", name="api_file_")
 */
class FileController extends BaseApiController
{
    private $userTodoPnRepository;

    private $s3wrapper;
    private $validator;
    private $imagine;

    public function __construct(UserRepository $userRepository, UserTodoPnRepository $userTodoPnRepository, S3Wrapper $s3wrapper)
    {
        $this->userRepository = $userRepository;
        $this->userTodoPnRepository = $userTodoPnRepository;

        $this->s3wrapper = $s3wrapper;
        $this->validator = Validation::createValidator();
        $this->imagine = new Imagine();
    }

    /**
     * Returns image content.
     *
     * @param Request $request
     * @return Response The image content
     *
     * @Route("/image", methods={"GET", "POST"}, name="image")
     *
     * @deprecated
     */
    public function drawImageAction(Request $request)
    {
        $filename = $request->get('filename');


        if (!$filename = $request->get('filename')) {
            return $this->getErrorJson(sprintf('File "%s" does not exist.', $filename));
        }

        /*if (substr($filename, 0, 45) == 'https:/thechatapp.s3-us-west-2.amazonaws.com/') {*/
        if (substr($filename, 0, 46) == 'https://thechatapp.s3.us-west-2.amazonaws.com/') {
            $filename = substr($filename, 46);
        }

        if (!$this->s3wrapper->doesObjectExist($filename)) {
            return $this->getErrorJson(sprintf('Image "%s" does not exist.', $filename));
        }

        $objectUrl = $this->s3wrapper->getObjectUrl($filename);

        try {
            $image = $this->imagine->open($objectUrl);
        } catch (InvalidArgumentException $e) {
            return $this->getErrorJson(sprintf('Image "%s" does not exist.', $filename));
        }

        // resize image
        if ($request->get('width') || $request->get('height')) {
            $transformation = new Transformation();
            $transformation->thumbnail(new Box($request->get('width'), $request->get('height')));
            $image = $transformation->apply($image);
        }

        $format = pathinfo($objectUrl, PATHINFO_EXTENSION);
        // remove "_" (legacy extension)
        $format = trim($format, '_');

        $response = new Response();
        $response->headers->set('Content-type', 'image/'.$format);
        $response->setContent($image->get($format));

        return $response;
    }

    /**
     * Returns file url path.
     *
     * @param Request $request
     * @return Response The file url
     *
     * @Route("/get_file_url", methods={"GET", "POST"}, name="get_file_url")
     *
     * @deprecated
     */
    public function getFileUrlAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $filename = $request->get('filename');

        if (!$filename = $request->get('filename')) {
            return $this->getErrorJson(sprintf('File "%s" does not exist.', $filename));
        }

        if (substr($filename, 0, 4) == 'http') {
            return new Response($filename);
        }

        if (!$this->s3wrapper->doesObjectExist($filename)) {
            return $this->getErrorJson(sprintf('File "%s" does not exist.', $filename));
        }

        $objectUrl = $this->s3wrapper->getObjectUrl($filename);

        return new Response($objectUrl);
    }

    /**
     * Upload file to server.
     *
     * @param Request $request
     * @return Response The filename
     *
     * @throws Exception
     * @Route("/upload_file", methods={"GET", "POST"}, name="upload_file")
     */
    public function uploadFileAction(Request $request)
    {
        if( $result = $this->checkSecurity( $request ) ) return $result;

        $file = $request->files->get('file');



        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->getErrorJson('Missing file.');
        }

        if (!$data = file_get_contents($file->__toString())) {
            return $this->getErrorJson('Empty file.');
        }

        $fileConst = new File([
            'maxSize'   => '5120k',
            'mimeTypes' => [
                'image/bmp', 'image/gif', 'image/jpeg', 'image/png',
                'audio/m4a', 'audio/mid', 'audio/mp3', 'audio/wav',
                'video/mpeg', 'video/3gpp', 'video/3gpp2', 'video/mp4','video/webm'
            ],
        ]);

        $errors = $this->validator->validate($file, $fileConst);
        if (count($errors) > 0) {
            return $this->getErrorJson('Invalid file.');
        }

        // upload file

        $objectKeys = $this->s3wrapper
            ->addFiles('pending', null, array($file->getClientOriginalName() => $data),
                array(), true);

        // delete uploaded file
        unlink($file->getPathname());

        return $this->getSuccessJson(current($objectKeys));
    }

    /**
     * check push notifications to send.
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @Route("/check", methods={"GET", "POST"}, name="check")
     * @throws Exception
     */
    public function checkAction(Request $request)
    {
//        $this->userTodoPnRepository->minusRemainHours();

        $message = [
            "We noticed you haven’t posted a profile pic yet.  Users that post a profile pic chat with more people.",
            "We noticed you haven’t posted any interests.  Post your 3 interests and users will message you about your interests.",
            "Post a recent moment and get some users to notice you.",
        ];
        $title = [
            "NOT_SET_PROFILE",
            "NOT_SET_INTEREST",
            "NOT_SET_MOMENT",
        ];

        $message_not_login = [
            "Why don’t you check the ChatApp radar and say hi to some new people around you?",
            "Some new clubs are showing up near you, join a new ChatApp club today.",
            "ChatApp users are posting new moments in your neighborhood.",
        ];

        for( $i = 0; $i < 3; $i++ ){
            $result = $this->userTodoPnRepository->getTodoPn($i+1);
            for( $j = 0; $j < count($result); $j++ ){
                $user_pn = $result[$j];
                $user_id = $user_pn->getUserId();
                $user = $this->userRepository->find($user_id);
                if( $user ){
                    PushNotification::send($user, [
                        'parameters' => "",
                        'from_username' => $message[$i],
                        'title' => $title[$i],
                        'message' => "",
                    ]);
                }
            }
        }

        $result = $this->userTodoPnRepository->getLoginPn();
        for( $j = 0; $j < count($result); $j++ ){
            $user_pn = $result[$j];
            $user_id = $user_pn->getUserId();
            $user = $this->userRepository->find($user_id);
            if( $user ){
                $rand = rand(0,2);
                $rand_msg = sprintf("%d", $rand);
                PushNotification::send($user, [
                    'parameters' => "",
                    'from_username' => $message_not_login[$rand],
                    'title' => 'NOT_LOGIN',
                    'message' => $rand_msg,
                ]);
            }
        }

        return $this->getSuccessJson([]);
    }
}
