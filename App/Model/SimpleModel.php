<?php

namespace App\Model;

class SimpleModel
{

    /**
     * @return SimpleModel
     */
    public static function create()
    {
        return new self();
    }

}