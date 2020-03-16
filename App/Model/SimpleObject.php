<?php

namespace App\Model;

class SimpleObject implements SimpleModel
{
    protected $attributes = [];

    protected $properties = [];

    public function __construct(array $data = [])
    {
        foreach ($this->properties as $var) {
            $this->$var = null;
        }

        if (count($data)) {
            $this->fill($data);
        }
    }

    /**
     * @inheritDoc
     */
    public function fill(array $data)
    {
        if (!count($this->attributes)) {
            $this->attributes = $data;
        }

        foreach ($data as $var => $value) {

            if (in_array($var, $this->properties, true)) {
                $this->$var = $value;
            }

        }

        return $this;
    }

    /**
     * TODO: Сделать
     */
    public function toArray()
    {

    }
}