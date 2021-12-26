<?php

namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Flex\Types\Pages\PageObject;
use RocketTheme\Toolbox\Event\Event;
use Grav\Common\Utils;
use Grav\Plugin\Aura\Aura;

/**
 * Class AuraPlugin
 *
 * @package Grav\Plugin
 */
class AuraPlugin extends Plugin
{

    /**
     * Gives the core a list of events the plugin wants to listen to
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
        ];
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized()
    {
        spl_autoload_register(
            function ($class) {
                if (Utils::startsWith($class, 'Grav\Plugin\Aura\\')) {
                    require_once __DIR__ . '/classes/' . strtolower(basename(str_replace("\\", '/', $class))) . '.php';
                }
            }
        );

        // Admin only events
        if ($this->isAdmin()) {
            $this->enable(
                [
                    'onGetPageBlueprints' => ['onGetPageBlueprints', 0],
                    'onAdminSave'         => ['onAdminSave', 0],
                ]
            );
            return;
        }

        // Frontend events
        $this->enable(
            [
                'onPageInitialized' => ['onPageInitialized', 0],
            ]
        );
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
        $requiredParams = [
            'org-name',
            'org-url',
        ];

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
            $aura->webpage->metadata['description'] = [
                'name'    => 'description',
                'content' => $aura->webpage->description,
            ];
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
            } elseif (array_key_exists('name', $tag)) {
                $metadata[$tag['name']] = $tag['content'];
            }
        }

        $original = $page->getOriginal();

        if ($original && !isset($original->header()->aura) && isset($page->header()->metadata) && is_array($page->header()->metadata)) {
            // Page has not been saved since installation of Aura and includes some custom metadata
            $legacyMetadata = ['metadata' => []];

            foreach ($page->header()->metadata as $key => $val) {
                if (!array_key_exists($key, $metadata)) {
                    // A new value has not been supplied via Aura, salvage existing metadata
                    $metadata[$key]                   = $val;
                    $legacyMetadata['metadata'][$key] = $val;
                }
            }

            $page->header()->aura = array_merge($legacyMetadata, $page->header()->aura ?? []);
        }

        $page->header()->metadata = array_merge($metadata, $page->header()->aura['metadata'] ?? []);
    }

    /**
     * Insert meta tags and structured data to head of each page
     */
    public function onPageInitialized()
    {
        // Structured Data
        if (!$this->grav['config']->get('plugins.aura.output-sd')) {
            return;
        }

        // Don't proceed if required params not set
        $requiredParams = [
            'org-name',
            'org-url',
        ];

        foreach ($requiredParams as $param) {
            $key = 'plugins.aura.' . $param;

            if (!$this->grav['config']->get($key)) {
                return;
            }
        }

        $page   = $this->grav['page'];
        $assets = $this->grav['assets'];

        $aura = new Aura($page);

        // Generate structured data block
        $sd = $aura->generateStructuredData();

        // Drop into JS pipeline
        $assets->addInlineJs($sd, null, null, ['type' => 'application/ld+json']);
    }
}
