<?php

namespace Symfony\Framework\ProfilerBundle;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * 
 *
 * @package    symfony
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class RequestDebugData
{
  protected $token;
  protected $data;
  protected $store;

  public function __construct($token, $store)
  {
    $this->token = $token;
    $this->store = $store;
    $this->data = null;
  }

  public function hasData()
  {
    return null !== $this->data;
  }

  public function getData($name = null)
  {
    if (null === $this->data)
    {
      $this->data = $this->read();
    }

    if (null === $name)
    {
      return $this->data;
    }

    return isset($this->data[$name]) ? $this->data[$name] : null;
  }

  public function getToken()
  {
    return $this->token;
  }

  protected function read()
  {
    $db = $this->initDb(SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READ);
    $this->data = $db->querySingle(sprintf("SELECT data FROM data WHERE token = '%s' LIMIT 1 ORDER BY created_at DESC", $db->escapeString($this->token)));
    $db->close();
  }

  public function write($data)
  {
    $db = $this->initDb(SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
    $db->exec(sprintf("INSERT INTO data (token, data, created_at) VALUES ('%s', '%s', %s)", $db->escapeString($this->token), $db->escapeString(serialize($data)), time()));
    $db->close();
  }

  protected function initDb($flags)
  {
    $db = new \SQLite3($this->store, $flags);
    $db->exec('CREATE TABLE IF NOT EXISTS data (token STRING, data STRING, created_at TIMESTAMP)');
    $db->exec('CREATE INDEX IF NOT EXISTS data_data ON data (created_at)');

    return $db;
  }

  public function purge($lifetime)
  {
    $db = $this->initDb(SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
    $db->exec(sprintf("DELETE FROM data WHERE strftime('%%s', 'now') - created_at > %d", $lifetime));
  }
}
