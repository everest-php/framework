<?php


namespace hooks\DataBase;


class DBConnection extends \PDO
{
    private $dsn, $user, $pass;

    public function __construct($dsn = null, $user = null, $pass = null)
    {
        $this->dsn = $dsn ?? self::defaultDSN();
        $this->user = $user ?? self::defaultUser();
        $this->pass = $pass ?? self::defaultPassword();

        try{
            parent::__construct($this->dsn, $this->user, $this->pass, [ \PDO::ATTR_PERSISTENT => true ]);
            $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
            $this->setAttribute(\PDO::ATTR_PERSISTENT, true);
        } catch (\Exception $e){
            die("Database Connection Failed");
        }
    }

    private static function defaultDSN(){
        return ( defined('DB_DEFAULT_DSN') ?? 'localhost' );
    }

    private static function defaultUser(){
        return ( defined('DB_DEFAULT_USER') ?? 'root' );
    }


    private static function defaultPassword(){
        return ( defined('DB_DEFAULT_PASS') ?? 'root' );
    }

}