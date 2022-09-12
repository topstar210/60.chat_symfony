<?php

namespace App\Service;


use App\Controller\Constant;
use App\Utils\Inflector;
use Aws\Credentials\Credentials;
use Aws\S3\S3Client;

class S3Wrapper
{
    /**
     * @var S3Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $bucket;

    /**
     * @param S3Client $client
     * @param string   $bucket
     */
    public function __construct()
    {
        $credentials = new Credentials(Constant::$conf_aws_config['key'] , Constant::$conf_aws_config['secret']);
        // Instantiate an Amazon S3 client.
        $s3 = new S3Client([
            'version' => Constant::$conf_aws_config['version'],
            'region' => Constant::$conf_aws_config['region'],
            'credentials' => $credentials,
            'bucket' => Constant::$conf_aws_config['bucket']
        ]);

        $this->client = $s3;
        $this->bucket = Constant::$conf_aws_config['bucket'];
    }

    /**
     * Determines whether or not an object exists by name.
     *
     * @param string $key The key of the object
     * @param array $options Additional options to add to the executed command
     * @return bool
     */
    public function doesObjectExist($key, array $options = array())
    {
        return $key ? $this->client->doesObjectExist($this->bucket, $key, $options) : false;
    }

    /**
     * Returns the URL to an object identified by its bucket and key. If an expiration time
     * is provided, the URL will be signed and set to expire at the provided time.
     *
     * @param string $key The key of the object
     * @param string $expires The time at which the URL should expire
     * @param array $args Arguments to the GetObject command. Additionally you can specify
     *                        a "Scheme" if you would like the URL to use a different scheme
     *                        than what the client is configured to use
     * @return array|mixed|null
     */
    public function getObjectUrl($key, $expires = null, array $args = array())
    {
        $urls = array();

        if (!$isArray = is_array($key)) {
            $key = array($key);
        }

        foreach ($key as $k) {
            if ($k) {
                $urls[] = $this->client->getObjectUrl($this->bucket, $k, $args);
            }
        }

        if ($isArray) return $urls;

        return count($urls) > 0 ? $urls[0] : null;
    }

    /**
     * Deletes file(s) from a bucket.
     *
     * @param array $files
     * @param array $sizes
     */
    public function deleteFiles(array $files, array $sizes = array())
    {
        $objects = array();

        foreach ($files as $filename) {
            if ($filename) {
                if (count($sizes)) {
                    foreach (array_keys($sizes) as $size) {
                        $objects[] = array('Key' => str_replace('/origin/', "/$size/", $filename));
                    }
                } else {
                    $objects[] = array('Key' => $filename);
                }
            }
        }


        if ($objects) {
            $keys = $this->client->listObjects([
                'Bucket' => $this->bucket
            ]) ->getPath('Contents/*/Key');

            if($keys){
                $this->client->deleteObjects([
                    'Bucket'  => $this->bucket,
                    'Delete' => [
                        'Objects' => array_map(function ($key) {
                            return ['Key' => $key];
                        }, $keys)
                    ],
                ]);
            }
        }




//        if ($objects) {
//            $this->client->deleteObjects(array(
//                'Bucket'  => $this->bucket,
//                'Delete' => $objects
//            ));
//        }
    }

    /**
     * Adds file(s) to a bucket.
     *
     * @param string  $rootKey
     * @param string  $subKey
     * @param array   $files
     * @param array   $sizes
     * @param boolean $includeData
     *
     * @return array
     */
    public function addFiles($rootKey, $subKey, array $files, array $sizes = array(), $includeData = false)
    {
        $result = array();

        foreach ($files as $key => $value) {
            $data = null;

            // uploaded file
            if ($includeData) {
                // set file data
                $data = $value;

                // assign filename
                $value = $key;
            }

            // pending file (already exists in S3)
            else {
                if (!$this->client->doesObjectExist($this->bucket, $value)) {
                    continue;
                }

                $data = $this->client->getObject(array('Bucket' => $this->bucket, 'Key' => $value));
                $data = $data['Body']->__toString();
            }

            // guess extension
            if ($extension = pathinfo($value, PATHINFO_EXTENSION)) {

                // force extension
                switch ($extension) {
                    case 'bmp':
                    case 'gif':
                    case 'jpeg':
                    case 'jpg':
                    case 'png':
                        $extension = 'jpeg';
                        break;
                }

                $extension = '.'.$extension;
            }

            // create random name
            $basename = Inflector::getRandomString(16).$extension;

            if (!$extension) {
                $basename .= '.jpeg';
            }

            if (count($sizes)) {

                foreach ($sizes as $k => $v) {
                    $width  = $v[0];
                    $height = $v[1];

                    // resize image
                    $imagine = new \Imagine\Gd\Imagine();
                    $image = $imagine->load($data);
                    if ($width && $height) {
                        $image->resize(new \Imagine\Image\Box($width, $height));
                    } elseif ($width) {
                        $image->resize($image->getSize()->widen($width));
                    } elseif ($height) {
                        $image->resize($image->getSize()->heighten($height));
                    }

                    $filename = $subKey
                        ? sprintf('%s/%s/%s/%s', $rootKey, $subKey, $k, $basename)
                        : sprintf('%s/%s/%s', $rootKey, $k, $basename);

                    $this->client->putObject(array(
                        'Bucket' => $this->bucket,
                        'Key'    => $filename,
                        'Body'   => $image->__toString(),
                        'ACL'    => 'public-read'
                    ));

                    $result[$k] = $filename;
                }
            } else {
                $filename = $subKey
                    ? sprintf('%s/%s/%s', $rootKey, $subKey, $basename)
                    : sprintf('%s/%s', $rootKey, $basename);

                $this->client->putObject(array(
                    'Bucket' => $this->bucket,
                    'Key'    => $filename,
                    'Body'   => $data,
                    'ACL'    => 'public-read'
                ));

                $result[] = $filename;
            }
        }

        return $result;
    }
}
