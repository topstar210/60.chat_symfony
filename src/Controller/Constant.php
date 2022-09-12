<?php

namespace App\Controller;

class Constant
{
    // configure your app for the production environment
    public static $conf_nodejs_path = '/usr/bin/nodejs';

    public static $forward = 'FORWARD';
    public static $user = 'user';
    public static $token = 'token';

    public static $conf_limit = 20;

    public static $conf_media_sizes = [
        'profile' => [
            'origin' => [null, null],
            'large' => [238, null],
            'medium' => [140, 140],
            'small' => [32, 32],
            'mini' => [30, 30],
        ],
        'moment' => [
            'origin' => [null, null],
            'large' => [600, null],
            'small' => [236, null],
            'mini' => [51, 51],
        ]
    ];

    public static $conf_db_options = [
        'driver' => 'pdo_mysql',
        'host' => 'localhost',
        'dbname' => 'chatapp',
        'user' => 'chatapp_user',
        'password' => 'jIW-l32Tu1c6349F',
        'charset' => 'utf8',
    ];

    public static $conf_endroid_gcm_api_key = 'AIzaSyBPUwjz3UIcDGIi5SjYb525JhmRhtOWqmg';

    public static $conf_ios_push_notification = [
        'ONE_SIGNAL_API' => 'https://onesignal.com/api/v1/notifications',
        'API_CONTENT_TYPE' => 'Content-Type: application/json; charset=utf-8',
        'ONE_SIGNAL_AUTHORIZATION' => 'Authorization: Basic ZWNjYTIyZjAtMjgzOS00OWMwLTgxNWItMzQ3NzhmYzNmZmJi',
        'ONE_SIGNAL_APP_ID' => '38fd6cf2-42f3-4ea0-8599-fd86230f96ab',


        'passphrase' => '6666',
        'host' => 'gateway.push.apple.com',
        'port' => 2195,
        'cert' => __DIR__.'/PEM/prod.pem',
        'dev' => [
            'host' => 'gateway.sandbox.push.apple.com',
            'port' => 2195,
            'cert' => __DIR__.'/PEM/prod.pem',
        ]
    ];




    /*
    // Test = "ChatApp - Local"
    public static $conf_facebook = [
        'key' => '1540710329483068',
        'secret' => 'eb10d9229d90dce243295b304337ea9b',
    );

    // LIVE - "ChatApp"
    */
    public static $conf_oauth_services = [
        'facebook' => [
            'key' => '1381358492084920',
            'secret' => '0151e0b214cd57e2e4bdb269603b6cb3',
            'scope' => ['email'],
            'user_endpoint' => 'https://graph.facebook.com/me',
        ],
    ];

    public static $conf_aws_config = [
        'version' => 'latest',
        'key' => 'AKIAJF74EJUFHSHJG7XQ',
        'secret' => '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',
        'region' => 'us-west-2',
        'bucket' => 'thechatapp'
    ];


    /** RequireJS */
    public static $require_js = [
        'web_root' => __DIR__ . '/../web',
        'js_engine' => 'node',
        'build_path' => 'static/js/app.min.js',
        'building_timeout' => 3600,
        'build' => [
            'optimize' => 'uglify2',
            'preserveLicenseComments' => true,
            'paths' => []
        ],
        'config' => [
            'shim' => [
                'underscore' => [
                    'exports' => '_'
                ],
                'bootstrap' => [
                    'deps' => [
                        'jquery'
                    ]
                ],
                'masonry' => [
                    'deps' => [
                        'jquery'
                    ]
                ],
                'imagesloaded' => [
                    'deps' => [
                        'jquery'
                    ]
                ],
                'igrowl' => [
                    'deps' => [
                        'jquery'
                    ]
                ],
                'jquery.bridget' => [
                    'deps' => [
                        'jquery'
                    ]
                ],
                'jquery.infinitescroll' => [
                    'deps' => [
                        'jquery'
                    ]
                ],
                'jquery.autosize' => [
                    'deps' => [
                        'jquery'
                    ]
                ],
                'jquery.quicksearch' => [
                    'deps' => [
                        'jquery'
                    ]
                ],
                'jquery.view' => [
                    'deps' => [
                        'jquery'
                    ]
                ],
                'jquery.uploader' => [
                    'deps' => [
                        'jquery'
                    ]
                ],
            ],
            'paths' => [
                'faye' => 'lib/faye/include.min.js',
                'underscore' => 'lib/underscore/underscore-min.js',
                'jquery' => 'lib/jquery/dist/jquery.min.js',
                'bootstrap' => 'lib/bootstrap/dist/js/bootstrap.min.js',
                'masonry' => 'lib/masonry/dist/masonry.pkgd.min.js',
                'imagesloaded' => 'lib/imagesloaded/imagesloaded.pkgd.min.js',
                'igrowl' => 'lib/igrowl/dist/js/igrowl.min.js',

                // jQuery:
                'jquery.bridget' => 'lib/jquery-bridget/jquery.bridget.js',
                'jquery.infinitescroll' => 'lib/jquery-infinite-scroll/jquery.infinitescroll.min.js',
                'jquery.autosize' => 'lib/jquery-autosize/jquery.autosize.min.js',
                'jquery.quicksearch' => 'lib/quicksearch/dist/jquery.quicksearch.min.js',
                'jquery.view' => 'lib/jquery-plugins/jquery.view.min.js',
                'jquery.uploader' => 'lib/jquery-plugins/jquery.uploader.min.js',
            ]
        ],
    ];


}
