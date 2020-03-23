<?php

namespace App\Model\Product;

use App\Model\Category\Category;
use App\Model\SimpleObject;
use DiDom\Document;
use modResource;
use modX;
use msOption;
use msProduct;
use msVendor;

/**
 * Class Product
 * @property string $link
 * @property string $uri
 * @property string $content
 * @property Document $dom
 * @property string $name
 * @property string $image
 * @property string $vendor
 * @property string $country
 * @property string $title
 * @property string $description
 * @property int $parent
 * @property int $price
 * @property int $id
 * @property Category $category
 * @property array $options
 * @property modX $modx
 * @package App\Model\Product
 */
class Product extends SimpleObject
{
    protected $properties = [
        'link',
        'uri',
        'dom',
        'name',
        'image',
        'parent',
        'category',
        'modx',
        'price',
        'content',
        'vendor',
        'country',
        'options',
        'id',
        'title',
        'description',
    ];

    /**
     * @return Product
     * @throws \DiDom\Exceptions\InvalidSelectorException
     */
    public function parse(): Product
    {
        $this->log->info('Начинаю парсить продукт', ['link' => $this->link]);

        $name = $this->dom->first('h1.content__title')->text();
        $name = trim($name);

        $price = (int)str_replace(' ', '', trim($this->dom->first('.product-price__main-value')->text()));
        $content = $this->dom->first('#tab-1 .product-fullinfo__inner .typo');
        if ($content) {
            $content = $content->html();
        } else {
            $content = null;
        }

        $image = $this->dom->first('a.product-photo__item');
        if (!$image) {
            $image = null;
            $this->log->error('Изображение товара не найдено');
        } else {
            $image = $image->attr('href');
            $imageInfo = pathinfo($image);
            $image = file_get_contents($image);

            $filename = $imageInfo['basename'];
            $path = $this->base_path . 'files/product-' . $filename;
            file_put_contents($path, $image);
            $this->image = $path;

            $this->log->info('Изображение найдено и сохранено', ['path' => $this->image]);
        }

        $optionsDom = $this->dom->find('#tab-2 .properties__item');

        $options = [];
        if (count($optionsDom)) {
            foreach ($optionsDom as $option) {
                $nameOptions = $option->first('.properties__title .tooltip__label')->text();
                $valueOptions = $option->first('.properties__value')->text();
                $aliasOptions = modResource::filterPathSegment($this->modx, $nameOptions);
                $aliasOptions = str_replace([
                    ',', '.', '-', ' ', '(', ')', '[', ']', '/', '?'
                ], '_', $aliasOptions);
                $options[] = [
                    'name' => $nameOptions,
                    'value' => $valueOptions,
                    'alias' => $aliasOptions
                ];
            }

            $this->log->info('Опции успешно спарсены');
        } else {
            $this->log->error('Опции товара не найдены');
        }

        $data = [
            'name' => $name,
            'price' => $price,
            'parent' => $this->category->id,
            'content' => $content,
            'options' => $options
        ];

        $this->fill($data);
        $this->findVendor();
        $this->findCountry();

        $this->parent = $this->parent ?: 5; //Fix
        $this->title = $this->dom->first('title')->text();
        $tvDescription = $this->dom->first('meta[name=description]');
        if ($tvDescription) {
            $this->description = $tvDescription;
        }

        $this->saveOptions();

        $this->save();

        return $this;
    }

    /**
     * Запускает подготовку опций
     * @return Product
     */
    protected function saveOptions(): Product
    {
        if (!count($this->options)) {
            $this->log->error('Не найдены опции');
            return $this;
        }

        foreach ($this->options as $option) {
            $this->prepareOption($option);
        }

        return $this;
    }

    /**
     * Сохраняет и ищет опции
     * @param array $option
     * @return Product
     */
    private function prepareOption(array $option): Product
    {
        $this->log->info('Начинаю подготовку опций');
        /** @var msOption|null $find */
        $find = $this->modx->getObject('msOption', [
            'key' => $option['alias']
        ]);

        if ($find instanceof msOption) {
            $this->log->info('Опция найдена, устанавливаю категория', [
                'key' => $option['alias'],
                'parent' => $this->parent,
            ]);
            $find->setCategories([$this->parent]);
            return $this;
        }

        $find = $this->modx->newObject('msOption', [
            'caption' => $option['name'],
            'category' => 0,
            'key' => $option['alias'],
            'type' => 'textfield'
        ]);
        $find->save();
        $find->setCategories([$this->parent]);
        return $this;
    }

    public function save(): SimpleObject
    {
        $this->log->info('Начинаю сохранение продукта');
        if (!$this->insertProduct()) {
            return $this;
        }

        return $this;
    }

    protected function insertProduct(): bool
    {
        $parent = $this->parent ?: 5;
        $this->log->info('Начинаю добавление в БД', ['parent' => $parent]);

        $find = $this->modx->getObject('msProduct', [
            'parent' => $parent,
            'pagetitle' => $this->name,
        ]);

        if ($find instanceof msProduct) {
            $this->log->error('Такой продукт уже есть');
            return false;
        }

        $defaultData = [
            'type' => 'document',
            'contentType' => 'text/html',
            'description' => '',
            'alias_visible' => 1,
            'published' => 1,
            'isfolder' => 0, //TODO: Change
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
            'class_key' => 'msProduct', //TODO: Change
            'context_key' => 'web',
            'content_type' => 1,
            'uri_override' => 1, //TODO: Change
            'hide_children_in_tree' => 0,
            'show_in_tree' => 0, //TODO: Change
        ];

        $data = [
            'pagetitle' => $this->name,
            'alias' => str_replace('/', '', $this->uri),
            'parent' => $parent,
            'uri' => str_replace('/', '', $this->uri),
            'content' => $this->content,
            'price' => $this->price,
            'made_in' => $this->country,
            'vendor' => $this->vendor,
            'tv9' => $this->title,
            'tv10' => $this->description,
        ];

        //Установка в товар опций
        if (count($this->options)) {
            foreach ($this->options as $option) {
                $data['options-' . $option['alias']] = $option['value'];
            }
        }


        $this->modx->error->reset();
        /** @var \modProcessorResponse $response */
        $response = $this->modx->runProcessor('resource/create', array_merge($defaultData, $data));
        if ($response->isError()) {
            $this->log->error('Ошибка создания товара', $response->getResponse());
            return false;
        }

        $this->id = $response->response['object']['id'];
        $this->log->info('Продукт создался', ['id' => $this->id]);

        if (!$this->addImages()) {
            return false;
        }

        return true;
    }

    protected function addImages()
    {
        if (!$this->image) {
            $this->log->error('Не найдно изображение выхожу');
            return true;
        }

        $gallery = array(
            'id' => $this->id,
            'file' => $this->image
        );

        $this->modx->error->reset();
        $upload = $this->modx->runProcessor('gallery/upload', $gallery, array(
            'processors_path' => MODX_CORE_PATH . 'components/minishop2/processors/mgr/'
        ));

        if ($upload->isError()) {
            $this->log->error('Ошибка добавления изображения', $upload->getResponse());
        }

        $this->log->info('Изображение добавлено');
        return true;
    }

    /**
     * Ищет и устанавливает производителя
     * @return Product
     */
    protected function findVendor(): Product
    {
        if (!count($this->options)) {
            $this->log->error('Не найдены опции, производитель не может быть найден');
            return $this;
        }
        $this->log->info('Начинаю поиск производителя');
        foreach ($this->options as $key => $val) {
            if ($val['name'] === 'Производитель') {
                $this->vendor = $val['value'];
                unset($this->options[$key]);
                $this->addVendor();
                $this->log->info('Проивзодитель найден', ['vendor' => $this->vendor]);
                return $this;
            }
        }
        $this->log->error('Производитель не найден');
        return $this;
    }

    /**
     * Ищет или создает в бд производителя
     * @return Product
     */
    private function addVendor(): Product
    {
        $find = $this->modx->getObject('msVendor', [
            'name' => $this->vendor
        ]);

        if ($find instanceof msVendor) {
            $this->vendor = $find->id;
            return $this;
        }

        /** @var msVendor $find */
        $find = $this->modx->newObject('msVendor', [
            'name' => $this->vendor
        ]);

        $find->save();
        $this->vendor = $find->id;
        return $this;
    }

    /**
     * Ищет и устанавливает производителя
     * @return Product
     */
    protected function findCountry(): Product
    {
        if (!count($this->options)) {
            $this->log->error('Не найдены опции, страна не может быть найдена');
            return $this;
        }
        $this->log->info('Начинаю поиск страны');
        foreach ($this->options as $key => $val) {
            if ($val['name'] === 'Страна сборки') {
                $this->country = $val['value'];
                unset($this->options[$key]);
                $this->log->info('Страна найдена', ['vendor' => $this->country]);
                return $this;
            }
        }
        $this->log->error('Производитель не найден');
        return $this;
    }

}