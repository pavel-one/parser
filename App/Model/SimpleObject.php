<?php

namespace App\Model;

use modX;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class SimpleObject implements SimpleModel
{
    protected $log;
    protected $base_path;
    protected $attributes = [];
    protected $properties = [];
    /** @var modX $modx */
    protected $modx;

    public function __construct(modX $modx, array $data = [])
    {
        $this->modx = $modx;
        foreach ($this->properties as $var) {
            $this->$var = null;
        }

        $this->base_path = dirname(__DIR__, 2) . '/';

        if (count($data)) {
            $this->fill($data);
        }

        $this->log = new Logger('Parser');
        $classname = (new \ReflectionClass($this))->getShortName();
        $stream = new StreamHandler(
            $this->base_path . 'logs/parser/' . $classname . '.log',
            Logger::INFO
        );
        $stream->setFormatter(new LineFormatter(LineFormatter::SIMPLE_FORMAT, 'H:i:s d.m.Y'));
        $this->log->pushHandler(
            $stream
        );
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

    public function save(): SimpleObject
    {
        return $this;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return (array)$this;
    }
}