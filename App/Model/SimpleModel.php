<?php

namespace App\Model;

interface SimpleModel
{

    /**
     * Заполняет модель свойствами
     * @param array $data
     * @return $this
     */
    public function fill(array $data);

}