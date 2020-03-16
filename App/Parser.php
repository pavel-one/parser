<?php

namespace App;

use App\Model\Category\Category;
use App\Model\SimpleModel;
use DiDom\Document;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * Class Parser
 * @property Logger $log
 * @package App
 */
class Parser
{
    public $links = [
        'https://split-ovk.com/konditsionirovanie',
        'https://split-ovk.com/kotly-otopleniia',
        'https://split-ovk.com/otopitelnoe-oborudovanie',
        'https://split-ovk.com/vodonagrevateli',
        'https://split-ovk.com/nasosy',
        'https://split-ovk.com/ochistka-vozduha',
        'https://split-ovk.com/ochistka-vody',
        'https://split-ovk.com/elektro-benzo-instrument',
        'https://split-ovk.com/kommercheskoe-oborudovanie',
        'https://split-ovk.com/elektrostantsii',
        'https://split-ovk.com/stabilizatory-napriazheniia',
        'https://split-ovk.com/oborudovanie-dlia-blochno-modulnye-kotelnye'
    ];

    protected $result = [];

    public $base_path;
    public $log;

    /**
     * @param string $logName
     * @param array $links
     * @return SimpleModel|Parser
     */
    public static function create(string $logName = 'log.log', array $links = [])
    {
        $obj = new self();

        if (count($links)) {
            $obj->links = $links;
        }

        $obj->base_path = dirname(__DIR__, 1) . '/';
        $obj->log = new Logger('Parser');
        $obj->log->pushHandler(new StreamHandler($obj->base_path . 'logs/parser/' . $logName, Logger::INFO));

        return $obj;
    }

    /**
     * @param bool $product
     * @return Parser
     */
    public function process($product = false): Parser
    {
        foreach ($this->links as $link) {
            if (!$product) {
                $this->parseCategory($link);
            }
        }

        return $this;
    }

    protected function parseCategory($link)
    {
        $document = new Document($link, true);
        $name = $document
            ->first('.breadcrumbs__item.hidden-xs.hidden-sm')
            ->text();
        $elements = $document->find('.category-sections .board-nav__item-2');

        foreach ($elements as $element) {
            $categoryData = [
                'link' => $link,
                'uri' => str_replace('https://split-ovk.com', '', $link),
                'name' => trim($element->first('.catalog-section__caption')->text()),
                'image' => str_replace(['background-image:url(', ')'],
                    '',
                    $element->first('.catalog-section__image')->attr('style')),
            ];

            $category = new Category($categoryData);

            $this->log->info($category);

            $this->result[$name][] = $category;
        }
    }

    public function getResult(): array
    {
        return $this->result;
    }

    /**
     * @param string|array $msg
     * @return Parser
     */
    public function log($msg = ''): Parser
    {

        if (is_array($msg)) {
            $msg = print_r($msg, true);
        }


        $this->log->info($msg);

        return $this;
    }


}