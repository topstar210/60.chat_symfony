<?php

namespace App\Utils;


use App\Controller\Constant;

class RequireJSConfig
{
    /**
     * @var Application
     */
    protected $app;

    /**
     */
    public function __construct()
    {}

    /**
     * Generates main config for require.js
     *
     * @return string
     */
    public function generateMainConfig()
    {
        $requirejs = Constant::$require_js;
        $config = $requirejs['config'];
        if (!empty($config['paths']) && is_array($config['paths'])) {
            foreach ($config['paths'] as &$path) {
                if (substr($path, 0, 7) === 'static/') {
                    $path = substr($path, 7);
                }
                if (substr($path, -3) === '.js') {
                    $path = substr($path, 0, -3);
                }
            }
        }
        return sprintf('require(%s);', json_encode($config));
    }

    /**
     * Generates build config for require.js
     *
     * @param string $configPath path to require.js main config
     *
     * @return array
     */
    public function generateBuildConfig($configPath)
    {
        $config = $this->app['require_js'];

        $config['build']['baseUrl'] = './static';
        $config['build']['out'] = './' . $config['build_path'];
        $config['build']['mainConfigFile'] = './' . $configPath;

        $paths = array(
            // build-in configuration
            'require-config' => '../' . substr($configPath, 0, -3),
            // build-in require.js lib
            'require-lib' => 'lib/r.js/require',
        );

        $config['build']['paths'] = array_merge($config['build']['paths'], $paths);
        $config['build']['include'] = array_merge(
            array_keys($paths),
            array_keys($config['config']['paths'])
        );

        return $config['build'];
    }
}
