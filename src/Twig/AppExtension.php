<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Twig;

use App\Service\S3Wrapper;
use App\Utils\RequireJSConfig;
use App\Utils\Markdown;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Intl\Locales;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use App\Controller\Constant;
/**
 * This Twig extension adds a new 'md2html' filter to easily transform Markdown
 * contents into HTML contents inside Twig templates.
 *
 * See https://symfony.com/doc/current/templating/twig_extension.html
 *
 * @author Ryan Weaver <weaverryan@gmail.com>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 * @author Julien ITARD <julienitard@gmail.com>
 */
class AppExtension extends AbstractExtension
{
    private $parser;
    private $localeCodes;
    private $locales;

    private $s3wrapper;
    private $urlHelper;

    public function __construct(Markdown $parser, string $locales, S3Wrapper $s3wrapper, UrlHelper $urlHelper)
    {
        $this->parser = $parser;

        $localeCodes = explode('|', $locales);
        sort($localeCodes);
        $this->localeCodes = $localeCodes;

        $this->s3wrapper = $s3wrapper;
        $this->urlHelper = $urlHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('md2html', [$this, 'markdownToHtml'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('locales', [$this, 'getLocales']),
            new TwigFunction('s3url', [$this, 'getS3URL']),
            new TwigFunction('get_requirejs_config', [$this, 'getRequirejsConfig']),
            new TwigFunction('get_requirejs_build_path', [$this, 'getRequirejsBuildPath']),
            new TwigFunction('requirejs_build_exists', [$this, 'getRequirejsBuildExists']),
        ];
    }

    /**
     * Transforms the given Markdown content into HTML content.
     */
    public function markdownToHtml(string $content): string
    {
        return $this->parser->toHtml($content);
    }

    /**
     * Takes the list of codes of the locales (languages) enabled in the
     * application and returns an array with the name of each locale written
     * in its own language (e.g. English, Français, Español, etc.).
     */
    public function getLocales(): array
    {
        if (null !== $this->locales) {
            return $this->locales;
        }

        $this->locales = [];
        foreach ($this->localeCodes as $localeCode) {
            $this->locales[] = ['code' => $localeCode, 'name' => Locales::getName($localeCode, $localeCode)];
        }

        return $this->locales;
    }

    public function getS3URL($asset = null, $folder = 'origin', $isUser = false, $expires = null)
    {
        if (!$asset && $isUser) {
            return str_replace('index_dev.php', '', $this->urlHelper->getRelativePath('/static/images/1x1px.png'));
        }

        $asset = str_replace('/origin/', "/$folder/", $asset);

        return $this->s3wrapper->getObjectUrl($asset, $expires);
    }

    public function getRequirejsConfig()
    {
        $requireJS = new RequireJSConfig();
        return $requireJS->generateMainConfig();
    }

    public function getRequirejsBuildPath()
    {
        return Constant::$require_js['build_path'];
    }

    public function getRequirejsBuildExists()
    {
        return file_exists(Constant::$require_js['web_root'] . DIRECTORY_SEPARATOR . Constant::$require_js['build_path']);
    }



}
