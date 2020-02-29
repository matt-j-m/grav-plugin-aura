<?php
namespace Grav\Plugin;

use Grav\Common\Data;
use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;
use Grav\Plugin\Aura\Organization;
use Grav\Plugin\Aura\WebSite;
use Grav\Plugin\Aura\WebPage;
use Grav\Plugin\Aura\Image;
use Grav\Common\Utils;


/**
 * Class AuraPlugin
 * @package Grav\Plugin
 */
class AuraPlugin extends Plugin
{

    private $org;
    private $website;
    private $webpage;

    /**
     * Initializes Aura variables for the page
     *
     * @param  object $page
     *
     * @return $this
     */
    private function init($page)
    {

        /*
         * Organization
         */
        $this->org = new Organization();

        $this->org->url = (string)$this->grav['config']->get('plugins.aura.org-url');
        $this->org->id = $this->org->url . '#organization';
        $this->org->name = $this->grav['config']->get('plugins.aura.org-name');

        // Org SameAs
        $sameAs = array();
        $otherPresence = array(
            'org-facebook-url',
            'org-instagram-url',
            'org-linkedin-url',
            'org-pinterest-url',
            'org-youtube-url',
            'org-wikipedia-url',
        );
        foreach ($otherPresence as $platform) {
            $key = 'plugins.aura.' . $platform;
            if ($this->grav['config']->get($key)) {
                $sameAs[] = $this->grav['config']->get($key);
            }
        }
        $key = 'plugins.aura.' . 'org-twitter-user';
        if ($this->grav['config']->get($key)) {
            $sameAs[] = 'https://twitter.com/' . $this->grav['config']->get($key);
        }
        if (!empty($sameAs)) {
            $this->org->sameAs = $sameAs;
        }

        // Org Logo
        if ($this->grav['config']->get('plugins.aura.org-logo')) {
            $imageArray = $this->grav['config']->get('plugins.aura.org-logo');
            $firstImage = reset($imageArray);
            $imagePath = ROOT_DIR . $firstImage['path'];
            if (file_exists($imagePath)) {
                $size = getimagesize($imagePath);
                $this->org->logo = new Image();
                $this->org->logo->url = $this->grav['base_url_absolute'] . '/' . $firstImage['path'];
                $this->org->logo->id = $this->org->url . '#logo';
                $this->org->logo->width = $size[0];
                $this->org->logo->height = $size[1];
                $this->org->logo->caption = $this->org->name;
                $this->org->logo->type = $size['mime'];
            }
        }
        
        /*
         * Website
         */
        $this->website = new WebSite;

        $this->website->url = $this->grav['base_url_absolute'];
        $this->website->id = $this->website->url . '#website';
        $this->website->name = $this->grav['config']->get('site.title');
        
        
        /*
         * Webpage
         */
        $this->webpage = new WebPage;

        $this->webpage->url = $page->url(true);
        $this->webpage->id = $this->webpage->url . '#webpage';
        $this->webpage->title = $page->title() . ' | ' . $this->grav['config']->get('site.title');
        $header = $page->header();
        if ((isset($header->aura['description'])) && ($header->aura['description'] != '')) {
            $this->webpage->description = (string)$header->aura['description'];
        }
        if ((isset($header->language)) and ($header->language != '')) {
            $this->webpage->language = $header->language;
        } else {
            $this->webpage->language = $this->grav['language']->getActive();
            if (!$this->webpage->language) {
                $this->webpage->language = $this->grav['config']->get('site.default_lang');
            }
        }
        $this->webpage->datePublished = date("c", $page->date());
        $this->webpage->dateModified = date("c", $page->modified());
        $this->webpage->metadata = $page->metadata();
        
        // Webpage Image
        $filename = false;
        if ((isset($header->aura['image'])) && ($header->aura['image'] != '')) {
            $filename = $header->aura['image'];
        } else if (isset($header->media_order) && ($header->media_order != '')) {
            $images = explode(',', $header->media_order);
            if ((is_array($images)) && (!empty($images))) {
                $filename = $images[0];
            }
        }

        if ($filename) {
            $imagePath = $page->path() . '/' . $filename;
            if (file_exists($imagePath)) {
                $size = getimagesize($imagePath);
                $this->webpage->image = new Image();
                $this->webpage->image->url = $page->url(true) . '/' . $filename;
                $this->webpage->image->id = $this->webpage->url . '#primaryimage';
                $this->webpage->image->width = $size[0];
                $this->webpage->image->height = $size[1];
                $this->webpage->image->caption = $this->webpage->title;
                $this->webpage->image->type = $size['mime'];
            }
        }

        if ((isset($header->aura['pagetype'])) && ($header->aura['pagetype'] != '')) {
            $this->webpage->type = $header->aura['pagetype'];
        }

        return $this;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            'onBlueprintCreated' => ['onBlueprintCreated', 0],
            'onPageInitialized' => ['onPageInitialized', 0]
        ];
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized()
    {

        // Autoloader
        spl_autoload_register(function ($class) {
            if (Utils::startsWith($class, 'Grav\Plugin\Aura\\')) {
                require_once __DIR__ .'/classes/' . strtolower(basename(str_replace("\\", '/', $class))) . '.php';
            }
        });

        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        // Don't proceed if php ext-json is not available
        if (!function_exists('json_encode')) {
            return;
        }

    }

    /**
     * Insert meta tags and structured data to head of each page
     *
     * @param Event $e
     */
    public function onPageInitialized()
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        // Don't proceed if required params not set
        $requiredParams = array(
            'org-name',
            'org-url',
        );
        foreach ($requiredParams as $param) {
            $key = 'plugins.aura.' . $param;
            if (!$this->grav['config']->get($key)) {
                return;
            }
        }

        $page = $this->grav['page'];
        $assets = $this->grav['assets'];

        $this->init($page);

        // Meta Description
        if ($this->webpage->description) {
            // Append description to page metadata
            $this->webpage->metadata['description'] = array(
                'name' => 'description',
                'content' => htmlentities($this->webpage->description),
            );
        }

        // Open Graph
        if ($this->grav['config']->get('plugins.aura.output-og')) {
            $this->generateOpenGraphMeta();
        }

        // Twitter
        if ($this->grav['config']->get('plugins.aura.output-twitter')) {
            $this->generateTwitterMeta();
        }

        // LinkedIn
        if ($this->grav['config']->get('plugins.aura.output-linkedin')) {
            $this->generateLinkedInMeta();
        }

        // Output updated metadata
        $page->metadata($this->webpage->metadata);


        // Structured Data
        if ($this->grav['config']->get('plugins.aura.output-sd')) {
            // Generate structured data block
            $sd = $this->generateStructuredData();
            // Drop into JS pipeline
            $type = array('type' => 'application/ld+json');
            if (version_compare(GRAV_VERSION, '1.6.0', '<')) {
                $type = 'application/ld+json';
            }
            $assets->addInlineJs($sd, null, null, $type);
        }

    }

    /**
     * Extend page blueprints with additional configuration options.
     *
     * @param Event $event
     */
    public function onBlueprintCreated(Event $event)
    {
        static $inEvent = false;

        /** @var Data\Blueprint $blueprint */
        $blueprint = $event['blueprint'];
        if (!$inEvent && $blueprint->get('form/fields/tabs', null, '/')) {
            $inEvent = true;
            $blueprints = new Data\Blueprints(__DIR__ . '/blueprints/');
            $extends = $blueprints->get('aura');
            $blueprint->extend($extends, true);
            $inEvent = false;
        }
    }

    private function generateOpenGraphMeta() {
        $data = array(
            'og:url' => $this->webpage->url,
            'og:type' => $this->webpage->type,
            'og:title' => $this->webpage->title,
            'og:author' => $this->org->name,
        );
        if ($this->webpage->description) {
            $data['og:description'] = $this->webpage->description;
        }
        if ($this->webpage->image) {
            $data['og:image'] = $this->webpage->image->url;
            $data['og:image:type'] = $this->webpage->image->type;
            $data['og:image:width'] = $this->webpage->image->width;
            $data['og:image:height'] = $this->webpage->image->height;
        }
        if ($this->grav['config']->get('plugins.aura.org-facebook-appid')) {
            $data['fb:app_id'] = $this->grav['config']->get('plugins.aura.org-facebook-appid');
        }
        foreach ($data as $property => $content) {
            $this->webpage->metadata[$property] = array(
                'property' => $property,
                'content' => htmlentities($content),
            );
        }
    }

    private function generateTwitterMeta() {
        $data = array(
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $this->webpage->title,
        );
        if ($this->webpage->description) {
            $data['twitter:description'] = $this->webpage->description;
        }
        if ($this->grav['config']->get('plugins.aura.org-twitter-user')) {
            $data['twitter:site'] = '@' . $this->grav['config']->get('plugins.aura.org-twitter-user');
            $data['twitter:creator'] = '@' . $this->grav['config']->get('plugins.aura.org-twitter-user');
        }
        if ($this->webpage->image) {
            $data['twitter:image'] = $this->webpage->image->url;
        }
        foreach ($data as $name => $content) {
            $this->webpage->metadata[$name] = array(
                'name' => $name,
                'content' => htmlentities($content),
            );
        }
    }

    private function generateLinkedInMeta() {
        $data = array(
            'article:published_time' => $this->webpage->datePublished,
            'article:modified_time' => $this->webpage->dateModified,
            'article:author' => $this->org->name,
        );
        foreach ($data as $property => $content) {
            $this->webpage->metadata[$property] = array(
                'property' => $property,
                'content' => htmlentities($content),
            );
        }
    }

    private function generateStructuredData() {
        $organization = array(
            '@type' => 'Organization',
            '@id' => $this->org->id,
            'name' => $this->org->name,
            'url' => $this->org->url,
        );

        $website = array(
            '@type' => 'WebSite',
            '@id' => $this->website->id,
            'url' => $this->website->url,
            'name' => $this->website->name,
            'publisher' => array(
                '@id' => $this->org->id,
            ),
        );

        $webpage = array(
            '@type' => 'WebPage',
            '@id' => $this->webpage->id,
            'url' => $this->webpage->url,
            'inLanguage' => $this->webpage->language,
            'name' => $this->webpage->title,
            'isPartOf' => array(
                '@id' => $this->website->id,
            ),
            'datePublished' => $this->webpage->datePublished,
            'dateModified' => $this->webpage->dateModified,
        );

        // Add Organization sameAs (if defined)
        if ($this->org->sameAs) {
            $organization['sameAs'] = $this->org->sameAs;
        }

        // Add logo (if defined)
        if ($this->org->logo) {
            $organization['logo'] = array(
                '@type' => 'ImageObject',
                '@id' => $this->org->logo->id,
                'url' => $this->org->logo->url,
                'width' => $this->org->logo->width,
                'height' => $this->org->logo->height,
                'caption' => $this->org->logo->caption,
            );
            $organization['image'] = array(
                '@id' => $this->org->logo->id,
            );
        }

        // Add page description (if defined)
        if ($this->webpage->description) {
            $webpage['description'] = $this->webpage->description;
        }

        // Add page image (if defined)
        if ($this->webpage->image) {
            $webpageImage = array(
                '@type' => 'ImageObject',
                '@id' => $this->webpage->image->id,
                'url' => $this->webpage->image->url,
                'width' => $this->webpage->image->width,
                'height' => $this->webpage->image->height,
                'caption' => $this->webpage->image->caption,
            );
            $webpage['primaryImageOfPage'] = array(
                '@id' => $this->webpage->image->id,
            );
        }

        // Additional based on page type i.e. article
        if ($this->webpage->type == 'article') {
            $article = array(
                '@type' => 'Article',
                '@id' => $this->webpage->url . '#article',
                'isPartOf' => array(
                    '@id' => $this->webpage->id,
                ),
                'author' => array(
                    '@id' => $this->org->id,
                ),
                'headline' => $this->webpage->title,
                'datePublished' => $this->webpage->datePublished,
                'dateModified' => $this->webpage->dateModified,
                'mainEntityOfPage' => array(
                    '@id' => $this->webpage->id,
                ),
                'publisher' => array(
                    '@id' => $this->org->id,
                ),
            );
            if ($this->webpage->image) {
                $article['image'] = array(
                    '@id' => $this->webpage->image->id,
                );
            }
        }

        // Build the empty structured data block
        $data = array(
            '@context' => 'https://schema.org',
            '@graph' => array(),
        );

        // Add the elements in order
        $data['@graph'][] = $organization;
        $data['@graph'][] = $website;
        if (isset($webpageImage)) {
            $data['@graph'][] = $webpageImage;
        }
        $data['@graph'][] = $webpage;
        if (isset($article)) {
            $data['@graph'][] = $article;
        }

        return json_encode($data, JSON_UNESCAPED_SLASHES);

    }

}
