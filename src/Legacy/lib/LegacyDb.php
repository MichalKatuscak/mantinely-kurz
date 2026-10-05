<?php
/**
 * Tenky obal nad PDO.
 *
 * Puvodne obal nad mysql_* funkcemi (2014), v roce 2016 prepsano na PDO,
 * protoze hosting prestal podporovat ext/mysql. Rozhrani zustalo stejne,
 * aby se nemusely prepisovat vsechny stranky v administraci.
 *
 * @author petr
 * @author jana.k (PDO prepis)
 */

namespace App\Legacy\lib;

class LegacyDb
{
    /** @var \PDO */
    private $pdo;

    private $dsn;

    // pocitadlo dotazu pro debug lištu (viz templates/partials/footer.php)
    public $queryCount = 0;

    public $lastSql = '';

    // TODO: logovani pomalych dotazu, nekdy...
    public $log = array();

    public function __construct($dsn, $user = null, $pass = null)
    {
        $this->dsn = $dsn;
        $this->pdo = new \PDO($dsn, $user, $pass);
        // jana.k 2016: zapnuto, at to nepada potichu jako driv
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        if (strpos($dsn, 'mysql:') === 0) {
            // puvodne bezelo na MySQL 5.5, kodovani se nastavovalo rucne
            $this->pdo->exec("SET NAMES utf8");
        }
    }

    /**
     * Vrati vsechny radky jako pole asociativnich poli.
     */
    public function query($sql)
    {
        $this->queryCount++;
        $this->lastSql = $sql;
        $stmt = $this->pdo->query($sql);
        if ($stmt === false) {
            return array();
        }
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $rows;
    }

    /**
     * Prvni radek nebo null.
     */
    public function one($sql)
    {
        $rows = $this->query($sql);
        if (count($rows) == 0) {
            return null;
        }

        return $rows[0];
    }

    /**
     * Prvni sloupec prvniho radku (napr. COUNT(*)).
     */
    public function value($sql)
    {
        $row = $this->one($sql);
        if ($row === null) {
            return null;
        }
        $vals = array_values($row);

        return $vals[0];
    }

    /**
     * INSERT/UPDATE/DELETE, vraci pocet ovlivnenych radku.
     */
    public function exec($sql)
    {
        $this->queryCount++;
        $this->lastSql = $sql;
        $res = $this->pdo->exec($sql);

        return $res === false ? 0 : $res;
    }

    public function quote($value)
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $this->pdo->quote((string) $value);
    }

    public function lastId()
    {
        return $this->pdo->lastInsertId();
    }

    public function begin()
    {
        return $this->pdo->beginTransaction();
    }

    public function commit()
    {
        return $this->pdo->commit();
    }

    public function rollback()
    {
        if ($this->pdo->inTransaction()) {
            return $this->pdo->rollBack();
        }

        return false;
    }

    public function isSqlite()
    {
        return strpos($this->dsn, 'sqlite:') === 0;
    }

    /**
     * Pro pripad nouze. Nepouzivat v novem kodu!
     */
    public function getPdo()
    {
        return $this->pdo;
    }

    // stara metoda z mysql doby, uz se nevola (snad)
    public function escape($value)
    {
        return substr($this->quote($value), 1, -1);
    }
}
