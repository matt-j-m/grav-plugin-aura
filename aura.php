<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Flex\Types\Pages\PageObject;
use RocketTheme\Toolbox\Event\Event;
use Grav\Common\Utils;
use Grav\Plugin\Aura\Aura;


/**
 * Class AuraPlugin
 * @package Grav\Plugin
 */
class AuraPlugin extends Plugin
{

    /**
     * Gives the core a list of events the plugin wants to listen to
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
				if ($page->route()!='/'){$route=$page->route()."/";}else{$route=$page->rawroute()."/";}
				$this->webpage->image->url = $this->grav['uri']->rootUrl(true)."/".$page->language().$route.$filename;
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
            'onPluginsInitialized' => ['onPluginsInitialized', 0]
        ];
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized()
    {

        spl_autoload_register(function ($class) {
            if (Utils::startsWith($class, 'Grav\Plugin\Aura\\')) {
                require_once __DIR__ .'/classes/' . strtolower(basename(str_replace("\\", '/', $class))) . '.php';
            }
        });

        // Admin only events
        if ($this->isAdmin()) {
            $this->enable([
                'onGetPageBlueprints' => ['onGetPageBlueprints', 0],
                'onAdminSave' => ['onAdminSave', 0],
            ]);
            return;
        }

        // Frontend events
        $this->enable([
            'onPageInitialized' => ['onPageInitialized', 0]
        ]);
    }

    /**
     * Extend page blueprints with additional configuration options.
     *
     * @param Event $event
     */
    public function onGetPageBlueprints($event)
    {
      $types = $event->types;
      $types->scanBlueprints('plugins://' . $this->name . '/blueprints');
    }

    public function onAdminSave(Event $event)
    {

        if (!$event['object'] instanceof PageObject) {
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

        $page = $event['object'];
        $aura = new Aura($page);

        // Meta Description
        if ($aura->webpage->description) {
            // Append description to page metadata
            $aura->webpage->metadata['description'] = array(
                'name' => 'description',
                'content' => htmlentities($aura->webpage->description),
            );
        }

        // Open Graph
        if ($this->grav['config']->get('plugins.aura.output-og')) {
            $aura->generateOpenGraphMeta();
        }

        // Twitter
        if ($this->grav['config']->get('plugins.aura.output-twitter')) {
            $aura->generateTwitterMeta();
        }

        // LinkedIn
        if ($this->grav['config']->get('plugins.aura.output-linkedin')) {
            $aura->generateLinkedInMeta();
        }

        // Generate Aura metadata
        $metadata = [];
        foreach ($aura->webpage->metadata as $tag) {
            if (array_key_exists('property', $tag)) {
                $metadata[$tag['property']] = $tag['content'];
            } else if (array_key_exists('name', $tag)) {
                $metadata[$tag['name']] = $tag['content'];
            }
        }

        $original = $page->getOriginal();
        if (!isset($original->header()->aura) && isset($page->header()->metadata) && is_array($page->header()->metadata)) {
            // Page has not been saved since installation of Aura and includes some custom metadata
            foreach ($page->header()->metadata as $key => $val) {
                if (!array_key_exists($key, $metadata)) {
                    // A new value has not been supplied via Aura, salvage existing metadata
                    $metadata[$key] = $val;
                    $page->header()->aura['metadata'] = array($key => $val);
                }
            }
        }

        $page->header()->metadata = array_merge($metadata, isset($page->header()->aura['metadata']) ? $page->header()->aura['metadata'] : []);

    }

    /**
     * Insert meta tags and structured data to head of each page
     *
     * @param Event $e
     */
    public function onPageInitialized()
    {
        // Structured Data
        if ($this->grav['config']->get('plugins.aura.output-sd')) {

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

            $aura = new Aura($page);

            // Generate structured data block
            $sd = $aura->generateStructuredData();
            // Drop into JS pipeline
            $type = array('type' => 'application/ld+json');
            if (version_compare(GRAV_VERSION, '1.6.0', '<')) {
                $type = 'application/ld+json';
            }
            $assets->addInlineJs($sd, null, null, $type);
        }

    }

}