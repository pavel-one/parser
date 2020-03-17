<?php

namespace App\Model\Category;

use App\Model\Product\Product;
use App\Model\SimpleObject;
use DiDom\Document;

/**
 * Class Category
 *
 * @property string $link
 * @property string $uri
 * @property string $name
 * @property string $image
 * @property string $real_image
 * @property string $parent_name
 * @property array $product_links
 * @property int $id
 * @property int $parent
 * @property Product[] $inner
 * @property Document $dom
 *
 * @package App\Model\Category
 */
class Category extends SimpleObject
{
    protected $properties = [
        'link',
        'uri',
        'name',
        'image',
        'real_image',
        'id',
        'parent',
        'parent_name',
        'product_links',
        'inner',
        'dom'
    ];

    public function saveImage()
    {

        if (!$this->image) {
            return false;
        }

        $image = file_get_contents($this->image);
        $info = pathinfo($this->image);
        $filename = $info['basename'];
        $path = $this->base_path . 'files/' . $filename;

        file_put_contents($path, $image);
        $this->real_image = $path;

        return $this;
    }

    public function parseProduct(): void
    {
        if (!$this->dom instanceof Document) {
            $this->prepareDocument();
        }


    }

    public function prepareDocument(): void
    {
        $this->dom = new Document($this->link, true);
    }

    public function preparePage()
    {
        if (!$this->dom instanceof Document) {
            $this->prepareDocument();
        }
        $this->log->info('Начинаю поиск максимального количества страниц в категории', ['link' => $this->link]);

        $paginators = $this->dom->find('.content__pagination .paginator__item');

        if (!count($paginators)) {
            $this->log->error('Пагинация не найдена, ставлю значения по умолчанию', ['link' => $this->link]);

            $this->product_links = [
                'page' => [
                    'currentPage' => 1,
                    'min' => 1,
                    'max' => 1
                ]
            ];
            return false;
        }

        $last = $paginators[count($paginators) - 1];
        $lastText = $last->text();

        if (!$lastText) {
            $this->log->error('Окончательная стрница не найдена, сдвигаю', ['link' => $this->link]);
            $last = $paginators[count($paginators) - 2];
        }

        $this->product_links = [
            'page' => [
                'currentPage' => 1,
                'min' => (int)$this->dom->first('.paginator__item--active')->text(),
                'max' => (int)str_replace('... ', '', trim($last->text()))
            ]
        ];

        $this->log->info(
            'Найдены максимальные и минимальные значения',
            array_merge(['link' => $this->link], $this->product_links)
        );

        return $this;
    }

    /**
     * Устанавливает имя категории
     * @param string $name
     * @return Category
     */
    public function setParentName(string $name): Category
    {
        $this->parent_name = $name;

        return $this;
    }

    public function getProductsLinks(): bool
    {
        if (!$this->dom instanceof Document) {
            $this->prepareDocument();
        }

        $this->log->info('Начинаю парсить', ['link' => $this->link]);

        $productsDOM = $this->dom->find('.products-wrapper .hits-item');

        if (!count($productsDOM)) {
            $this->log->error('Не найдены продукты', ['link' => $this->link]);
            return false;
        }

        foreach ($productsDOM as $productDOM) {
            $this->product_links['links'][] = $productDOM->first('.product-cut__title-link')
                ->attr('href');
        }

        if ($this->product_links['page']['currentPage'] === $this->product_links['page']['max']) {
            $this->log->error('Парсинг текущей категории окончен', ['link' => $this->link]);
            return true;
        }

        $this->product_links['page']['currentPage'] += 1;

        $per_page = $this->product_links['page']['currentPage'] * 12 - 12;
        $this->link = $this->attributes['link'] . '?per_page=' . $per_page;
        $this->prepareDocument();

        $this->getProductsLinks();

        return true;
    }

    public function beforeSave(int &$parent): bool
    {

    }

    public function save(int $parent = 5): SimpleObject
    {
        if (!$this->beforeSave($parent)) { //TODO: Проверка существует ли такой
            return $this;
        }

        $data = [
            'pagetitle' => $this->name,
            'alias' => str_replace('/', '', $this->uri),
            'parent' => '?',
            'uri' => str_replace('/', '', $this->uri),
        ];
        $defaultData = [
            'type' => 'document',
            'contentType' => 'text/html',
            'description' => '',
            'alias_visible' => 1,
            'published' => 1,
            'isfolder' => 1, //TODO: Change
            'richtext' => 1,
            'template' => 3, //TODO: Change
            'searchable' => 1,
            'cacheable' => 1,
            'createdby' => 1,
            'createdon' => time(),
            'editedby' => 1,
            'editedon' => time(),
            'publishedon' => time(),
            'publishedby' => 1,
            'class_key' => 'msCategory', //TODO: Change
            'context_key' => 'web',
            'content_type' => 1,
            'uri_override' => 1, //TODO: Change
            'hide_children_in_tree' => 0,
            'show_in_tree' => 1,
        ];

        return $this;
    }
}