<?php
namespace Grav\Plugin\Aura;

use Grav\Common\Grav;

class Aura
{
    private $org;
    private $website;
    public  $webpage;
    private $person;
    private $grav;

    private $otherPresence = array(
        'facebook-url',
        'instagram-url',
        'linkedin-url',
        'pinterest-url',
        'youtube-url',
        'wikipedia-url',
        'website-url',
    );

    /**
     * Initializes Aura variables for the page
     *
     * @param  object $page
     *
     */
    public function __construct($page)
    {

        $this->grav = $cache = Grav::instance();

        /*
         * Organization
         */
        $this->org = new Organization();

        $this->org->url = (string)$this->grav['config']->get('plugins.aura.org-url');
        $this->org->id = $this->org->url . '#organization';
        $this->org->name = $this->grav['config']->get('plugins.aura.org-name');

        // Org SameAs
        $sameAs = array();
        foreach ($this->otherPresence as $platform) {
            $key = 'plugins.aura.org-' . $platform;
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

        $datePublishedRaw = time();
        if ($page->publishDate()) {
            $datePublishedRaw = $page->publishDate();
        } else if ($page->date()) {
            $datePublishedRaw = $page->date();
        } else if ($page->modified()) {
            $datePublishedRaw = $page->modified();
        }
        $dateModifiedRaw = $page->modified() ? $page->modified() : time();
        $this->webpage->datePublished = date("c", $datePublishedRaw);
        $this->webpage->dateModified = date("c", $dateModifiedRaw);

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
                $this->webpage->image->url = preg_replace('/' . preg_quote($page->urlExtension()) . '$/u', '', $page->url(true)) . '/' . $filename;
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

        // Author
        if (($this->grav['config']->get('plugins.aura-authors.enabled')) && isset($page->header()->aura['author'])) {
            $authors = $this->grav['config']->get('plugins.aura-authors.authors');
            $key = array_search($page->header()->aura['author'], array_column($authors, 'label'));
            if ($key !== false) {

                $author = $authors[$key];

                $this->person = new Person();

                $this->person->id = $this->org->url . '#person/' . $author['label'];
                $this->person->name = $author['name'];
                $this->person->description = $author['description'];

                // Person SameAs
                $sameAs = array();
                foreach ($this->otherPresence as $platform) {
                    $key = 'person-' . $platform;
                    if (isset($author[$key]) && $author[$key] != '') {
                        $sameAs[] = $author[$key];
                    }
                }
                $key = 'person-twitter-user';
                if (isset($author[$key]) && $author[$key] != '') {
                    $this->person->twitterUser = $author[$key];
                    $sameAs[] = 'https://twitter.com/' . $author[$key];
                }
                if (!empty($sameAs)) {
                    $this->person->sameAs = $sameAs;
                }

                // Person Image
                if ((isset($author['image'])) && (!empty($author['image']))) {
                    $firstImage = reset($author['image']);
                    $imagePath = ROOT_DIR . $firstImage['path'];
                    if (file_exists($imagePath)) {
                        $size = getimagesize($imagePath);
                        $this->person->image = new Image();
                        $this->person->image->url = $this->grav['base_url_absolute'] . '/' . $firstImage['path'];
                        $this->person->image->id = $this->org->url . '#personimage/' . $author['label'];
                        $this->person->image->width = $size[0];
                        $this->person->image->height = $size[1];
                        $this->person->image->caption = $author['name'];
                        $this->person->image->type = $size['mime'];
                    }
                }

            }
        }

    }

    public function generateOpenGraphMeta() {
        $data = array(
            'og:url' => $this->webpage->url,
            'og:type' => $this->webpage->type,
            'og:title' => $this->webpage->title,
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
        if ($this->person) {
            $data['og:author'] = $this->person->name;
        } else {
            $data['og:author'] = $this->org->name;
        }
        foreach ($data as $property => $content) {
            $this->webpage->metadata[$property] = array(
                'property' => $property,
                'content' => htmlentities($content),
            );
        }
    }

    public function generateTwitterMeta() {
        $data = array(
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $this->webpage->title,
        );
        if ($this->webpage->description) {
            $data['twitter:description'] = $this->webpage->description;
        }
        if ($this->grav['config']->get('plugins.aura.org-twitter-user')) {
            $data['twitter:site'] = '@' . $this->grav['config']->get('plugins.aura.org-twitter-user');
        }
        if ($this->person && $this->person->twitterUser) {
            $data['twitter:creator'] = '@' . $this->person->twitterUser;
        } else {
            if ($this->grav['config']->get('plugins.aura.org-twitter-user')) {
                $data['twitter:creator'] = '@' . $this->grav['config']->get('plugins.aura.org-twitter-user');
            }
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

    public function generateLinkedInMeta() {
        $data = array(
            'article:published_time' => $this->webpage->datePublished,
            'article:modified_time' => $this->webpage->dateModified,
        );
        if ($this->person) {
            $data['article:author'] = $this->person->name;
        } else {
            $data['article:author'] = $this->org->name;
        }
        foreach ($data as $property => $content) {
            $this->webpage->metadata[$property] = array(
                'property' => $property,
                'content' => htmlentities($content),
            );
        }
    }

    public function generateStructuredData() {
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

            // Add Image
            if ($this->webpage->image) {
                $article['image'] = array(
                    '@id' => $this->webpage->image->id,
                );
            }

            // Add Author
            if ($this->person) {
                // Use Person (if defined)
                $person = array(
                    '@type' => 'Person',
                    '@id' => $this->person->id,
                    'name' => $this->person->name,
                );

                // Add Person description (if defined)
                if ($this->person->description) {
                    $person['description'] = $this->person->description;
                }

                // Add Person sameAs (if defined)
                if ($this->person->sameAs) {
                    $person['sameAs'] = $this->person->sameAs;
                }

                // Add Person image (if defined)
                if ($this->person->image) {
                    $person['image'] = array(
                        '@type' => 'ImageObject',
                        '@id' => $this->person->image->id,
                        'url' => $this->person->image->url,
                        'width' => $this->person->image->width,
                        'height' => $this->person->image->height,
                        'caption' => $this->person->image->caption,
                    );
                }
                $article['author'] = array(
                    '@id' => $this->person->id,
                );
            } else {
                // Use Organization
                $article['author'] = array(
                    '@id' => $this->org->id,
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
        if (isset($person)) {
            $data['@graph'][] = $person;
        }

        return json_encode($data, JSON_UNESCAPED_SLASHES);

    }

}