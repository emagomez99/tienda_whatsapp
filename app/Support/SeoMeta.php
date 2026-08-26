<?php

namespace App\Support;

/**
 * Metadatos SEO resueltos para una página (título, descripción, imagen, etc.),
 * ya aplicado el fallback correspondiente. Ver SeoService::metaTags().
 */
class SeoMeta
{
    public $title;
    public $description;
    public $keywords;
    public $image;
    public $type;
    public $noindex;
    public $url;

    public function __construct($title, $description, $keywords, $image, $type, $noindex, $url)
    {
        $this->title = $title;
        $this->description = $description;
        $this->keywords = $keywords;
        $this->image = $image;
        $this->type = $type;
        $this->noindex = $noindex;
        $this->url = $url;
    }

    public function robotsContent()
    {
        return $this->noindex ? 'noindex, follow' : 'index, follow';
    }

    public function tieneKeywords()
    {
        return $this->keywords !== '';
    }
}
