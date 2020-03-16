<?php

namespace App\Model\Category;

use App\Model\SimpleObject;

/**
 * Class Category
 * @property string $link
 * @property string $uri
 * @property string $name
 * @property string $image
 * @package App\Model\Category
 */
class Category extends SimpleObject
{
    protected $properties = [
        'link',
        'uri',
        'name',
        'image',
        'id',
        'inner'
    ];

    public function saveImage()
    {

    }
}