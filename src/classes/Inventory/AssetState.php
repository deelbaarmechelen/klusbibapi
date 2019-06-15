<?php

namespace Api\Inventory;


class AssetState
{
    public $id;
    public $name;
    public $type;

    /**
     * AssetState constructor.
     * @param $id
     * @param $name
     * @param $type
     */
    private function __construct($id, $name, $type)
    {
        $this->id = $id;
        $this->name = $name;
        $this->type = $type;
    }

    public static function readyToDeploy() : AssetState {
      return new AssetState(2, "Beschikbaar", "deployable");
    }
    public static function deployed() : AssetState {
      return new AssetState(2, "In Gebruik", "deployable");
    }
    public static function archived() : AssetState {
      return new AssetState(3, "Gearchiveerd", "archived");
    }
    public static function undeployable() : AssetState {
      return new AssetState(4, "Niet uitrolbaar", "undeployable");
    }
    public static function maintenanceRepair() : AssetState {
      return new AssetState(1, "Onderhoud-Herstel", "pending");
    }

}