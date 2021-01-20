<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Phinx\Migration\AbstractMigration;


class AbstractCapsuleMigration extends AbstractMigration
{
    protected $capsule;

    protected function initCapsule() {
        $host = $this->adapter->getOption("host");
        $database = $this->adapter->getOption("name");
        $user = $this->adapter->getOption("user");
        $pass = $this->adapter->getOption("pass");

        $this->capsule = new Capsule;

        $this->capsule->addConnection([
            'driver'    => 'mysql',
            'host' => $host,
            'database' => $database,
            'username' => $user,
            'password' => $pass,
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ]);

        // Set the event dispatcher used by Eloquent models... (optional)
        $this->capsule->setEventDispatcher(new Dispatcher(new Container));

        // Make this Capsule instance available globally via static methods... (optional)
        $this->capsule->setAsGlobal();

        // Setup the Eloquent ORM... (optional; unless you've used setEventDispatcher())
        $this->capsule->bootEloquent();
    }
}