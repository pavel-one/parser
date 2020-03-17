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
    public $debug;

    /**
     * @param string $logName
     * @param bool $debug
     * @param array $links
     * @return SimpleModel|Parser
     */
    public static function create(string $logName = 'log.log', $debug = false, array $links = [])
    {
        $obj = new self();

        $obj->debug = $debug;

        if (count($links)) {
            $obj->links = $links;
        }

        $obj->base_path = dirname(__DIR__, 1) . '/';
        $obj->log = new Logger('Parser');
        $obj->log->pushHandler(new StreamHandler($obj->base_path . 'logs/parser/' . $logName, Logger::INFO));

        return $obj;
    }

    /**
     * @return Parser
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    public function process(): Parser
    {
        if (!$this->debug) {
            foreach ($this->links as $link) {
                $this->parseCategory($link);
            }
        } else {
            $this->parseCategory($this->links[0]);
            $this->prepare();
        }

        return $this;
    }

    public function prepare()
    {
        foreach ($this->result as $category) {

            foreach ($category as $item) {

                if ($item instanceof Category) {
                    $item->saveImage();
                    $item->preparePage();
                    $item->getProductsLinks();
                }

            }

        }
    }

    /**
     * Парсит категорию
     * @param string $link
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    protected function parseCategory(string $link)
    {
        $document = new Document($link, true);
        $name = $document
            ->first('.breadcrumbs__item.hidden-xs.hidden-sm')
            ->text();
        $elements = $document->find('.category-sections .board-nav__item-2');

        foreach ($elements as $element) {
            $href = $element->first('a')->attr('href');
            $categoryData = [
                'link' => trim($href),
                'uri' => str_replace('https://split-ovk.com', '', $href),
                'name' => trim($element->first('.catalog-section__caption')->text()),
                'image' => str_replace(['background-image:url(', ')'],
                    '',
                    $element->first('.catalog-section__image')->attr('style')),
            ];

            $category = new Category($categoryData);

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