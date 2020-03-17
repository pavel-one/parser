<?php

namespace App\Components\Database;

use App\Model\SimpleObject;
use mysqli;

class Database
{
    protected $config;
    protected $root_path;
    protected $row_sql;

    public $mysql;

    public function __construct()
    {
        $this->root_path = dirname(__DIR__, 3);
        $this->config = json_decode(json_encode(include($this->root_path . '/config/database.php')));
        $this->mysql = new mysqli($this->config->host, $this->config->user, $this->config->password, $this->config->db);
    }

    public function insert(string $table, $data)
    {
//        $this->row_sql = "INSERT INTO modx_site_content VALUES ()";
    }
}