<?php

namespace App\Model\Category;

use App\Model\Product\Product;
use App\Model\SimpleObject;
use DiDom\Document;
use msCategory;

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

    /**
     * Парсит один продукт
     * @param string $link
     * @return Category
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    public function parseProduct(string $link): Category
    {
        $data = [
            'dom' => new Document($link, true),
            'link' => $link,
            'uri' => str_replace('https://split-ovk.com/', '', $link),
            'category' => $this,
            'modx' => $this->modx
        ];

        $product = new Product($this->modx, $data);

        $this->inner[] = $product->parse();

        return $this;
    }

    /**
     * Запускает парсинг продуктов
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    public function parseProducts(): void
    {
        $this->log->info('Запускаю парсинг продуктов');
        foreach ($this->product_links['links'] as $link) {
            $this->log->info('Парсю ', ['link' => $link]);
            $this->parseProduct($link);
        }
    }

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

    /**
     * Модифицирует parent
     * @param int $parent
     * @return bool
     */
    public function beforeSave(int &$parent): bool
    {
        $parentName = $this->parent_name;
        $this->log->info("Проверяю наличие категории [$parentName] в $parent");

        /** @var msCategory|null $obj */
        $obj = $this->modx->getObject('msCategory', [
            'parent' => $parent,
            'pagetitle' => $parentName
        ]);

        if ($obj instanceof msCategory) {
            $this->log->info('Категория найдена, parent изменен на ' . $obj->id);
            $parent = $obj->id;

            //Проверка внутренней категории
            $obj = $this->modx->getObject('msCategory', [
                'parent' => $parent,
                'pagetitle' => $this->name
            ]);

            if ($obj instanceof msCategory) {
                $this->log->error('Внутренняя категория уже есть, прерываю save', ['name' => $this->name]);
                $this->id = $obj->id;
                return false;
            }

            return true;
        }

        $data = [
            'pagetitle' => $this->parent_name,
            'class_key' => 'msCategory',
            'alias' => rand(0, 2000000),
            'published' => 1,
            'template' => 3,
            'isfolder' => 1,
            'parent' => $parent
        ];

        $obj = $this->modx->newObject('msCategory', $data);
        $this->log->info("Начинаю создание новой категории [$parent]");

        if (!$obj->save()) {
            $this->log->error("Не получилось создать категорию [$parent]", $data);
            return false;
        }

        $parent = $obj->id;
        $this->log->info('Категория создана, parent изменен на ' . $obj->id);

        return true;

    }

    public function save(int $parent = 5): SimpleObject
    {
        $this->log->info('Начинаю сохранение категории в БД');

        if (!$this->beforeSave($parent)) {
            $this->log->error('Выход beforeSave');
            return $this;
        }
        $this->parent = $parent;

        $data = [
            'pagetitle' => $this->name,
            'alias' => str_replace('/', '', $this->uri),
            'parent' => $parent,
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

        /** @var msCategory $newObject */
        $newObject = $this->modx->newObject('msCategory', array_merge($defaultData, $data));

        if (!$newObject->save()) {
            $this->log->error('Не создана категория ', $this->toArray());
        }

        $this->log->info('Создана новая категория ' . $this->name);
        $this->id = $newObject->id;

        $newObject->setTVValue('catImg', str_replace(MODX_BASE_PATH, '', $this->real_image));
        $newObject->save();

        return $this;
    }
}