<?php

namespace App\Model\Category;

use App\Model\Product\Product;
use App\Model\SimpleObject;
use App\Parser;
use DiDom\Document;

/**
 * Class Category
 *
 * @property string $link
 * @property string $uri
 * @property string $name
 * @property string $image
 * @property string $real_image
 * @property array $product_links
 * @property int $id
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

    public function parseProduct()
    {
        if (!$this->dom instanceof Document) {
            $this->prepareDocument();
        }


    }

    public function prepareDocument() {
        $this->dom = new Document($this->link, true);
    }

    public function preparePage()
    {
        if (!$this->dom instanceof Document) {
            $this->prepareDocument();
        }

        $paginators = $this->dom->find('.content__pagination .paginator__item');

        if (!count($paginators)) {
            $this->product_links = [
                'page' => [
                    'min' => 1,
                    'max' => 1
                ]
            ];
            return false;
        }

        $last = $paginators[count($paginators) - 1];
        $lastText = $last->text();

        if (!$lastText) {
            $last = $paginators[count($paginators) - 2];
        }

        $this->product_links = [
            'page' => [
                'min' => $this->dom->first('.paginator__item--active')->text(),
                'max' => str_replace('... ', '', trim($last->text()))
            ]
        ];

        return $this;
    }

    public function getProductsLinks() {
        if (!$this->dom instanceof Document) {
            $this->prepareDocument();
        }


    }
}