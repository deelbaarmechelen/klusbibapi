<?php

namespace Api\Tool;

use Api\Inventory\Inventory;
use Api\Inventory\SnipeitInventory;
use Api\Model\InventoryItem;
use Api\Model\Tool;
use Api\Model\ToolType;
use Api\Util\ImageResizer;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Collection;

class ToolManager
{
    public static function instance($logger = null) {
        return new ToolManager(SnipeitInventory::instance(), $logger);
    }
    private $inventory;
    private $logger;
    private $imageResizer;

    /**
     * ToolManager constructor.
     */
    public function __construct(Inventory $inventory, $logger)
    {
        $this->inventory = $inventory;
        $this->logger = $logger;
        $this->imageResizer = new ImageResizer();
    }

    public function getAll($showAll = false, $category = null, $sortfield = "code", $sortdir = "asc",
        $page=1, $perPage = 1000, $query = null) {
        $tools = $this->getAllFromInventory($showAll, $category, $page, $perPage, $query, $sortfield, $sortdir);
//        $tools = $this->getAllFromDatabase($showAll, $category, $sortfield, $sortdir);
        return $tools;
    }
    public function getAllAccessories($showAll = false, $category = null, $sortfield = "code", $sortdir = "asc",
        $page=1, $perPage = 1000) {
        $accessories = $this->getAllAccessoriesFromInventory($showAll, $category, $page, $perPage, $sortfield, $sortdir);
        return $accessories;
    }
    public function toolExists($toolId) : bool
    {
        return $this->inventory->toolExists($toolId);
    }
    public function accessoryExists($accessoryId) : bool
    {
        return $this->inventory->accessoryExists($accessoryId);
    }
    public function getByIdAndType($id, $type)
    {
        if ($type == ToolType::TOOL) {
            return $this->getById($id);
        }
        if ($type == ToolType::ACCESSORY) {
            return $this->getAccessoryById($id);
        }
    }
    public function getById($id) {
        $tool = $this->getByIdFromInventory($id);
//        $tool = \Api\Model\Tool::find($id);

        // TODO: create or update corresponding tool in local db??
        // needed to store multiple images and handle reservations
        return $tool;
    }
    public function getAccessoryById($id) {
        $accessory = $this->getAccessoryByIdFromInventory($id);
        return $accessory;
    }

    public function sync() {
        $syncTime = new \DateTime();
        // sync assets
        echo "Syncing assets\n";
        $toolItems = $this->inventory->getInventoryItems(ToolType::TOOL);
        foreach($toolItems as $item) {
            echo "Syncing tool with id " . $item->id . "\n";
            $existingItem = InventoryItem::find($item->id);
            if ($existingItem === null) {
                // save will create new item
                echo "creating new tool item " . $item->id . "\n";
                if (isset($item->image_name)) {
                    $this->syncImage($item->image_name, $item);
                }
                $item->note = (isset($item->note) && strlen($item->note) > 128) ?
                    substr($item->note, 0, 125) . "..." : $item->note;
                $item->last_sync_date = $syncTime;
                $item->save();
            } else {
                // update item values
                echo "updating tool item " . $item->id . "\n";
                $this->updateExistingItem($item, $existingItem);

                $existingItem->last_sync_date = $syncTime;
                $existingItem->save();
            }

        }
        // sync accessories
        echo "Syncing accessories\n";
        $accessories = $this->inventory->getInventoryItems(ToolType::ACCESSORY);
//        $accessories = $this->getAllAccessories();
        foreach($accessories as $accessory) {
            echo "Syncing accessory with id " . $accessory->accessory_id . "\n";
            $existingAccessory = InventoryItem::find($accessory->id);
            if ($existingAccessory === null) {
                // save will create new item
                echo "creating new accessory item " . $accessory->id . "\n";
                $accessory->note = (isset($accessory->note) && strlen($accessory->note) > 128) ?
                    substr($accessory->note, 0, 125) . "..." : $accessory->note;
                $accessory->last_sync_date = $syncTime;
                $accessory->save();
            } else {
                // update item values
                echo "updating accessory item " . $accessory->id . "\n";
                $this->updateExistingItem($accessory, $existingAccessory);

                $existingAccessory->last_sync_date = $syncTime;
                $existingAccessory->save();
            }
        }

        // Delete all other items
        echo "Deleting other items\n";
        InventoryItem::outOfSync($syncTime)->delete();
    }

    protected function getByIdFromInventory($id) {
        return $this->inventory->getToolById($id);
    }
    protected function getAccessoryByIdFromInventory($id) {
        return $this->inventory->getAccessoryById($id);
    }

    /**
     * @param $showAll
     * @param $categoryFilter
     * @param $sortfield
     * @param $sortdir
     * @return mixed
     */
    protected function getAllFromInventory($showAll, $categoryFilter, $page, $perPage, $query,
                                           $sortfield = 'code', $sortdir = 'asc' )
    {
        $tools = new Collection();
        $inventoryTools = $this->inventory->getTools();
        // only apply pagination if filter can be applied directly to assets
//        $assets = $this->inventory->getAssets(($page - 1) * $perPage, $perPage);

        foreach ($inventoryTools as $tool) {
            if ( ($this->isVisible($showAll, $tool))
                && $this->applyCategoryFilter($categoryFilter, $tool)
                && $this->applyQueryFilter($query, $tool)) {
                $tools->add($tool);
            }
        }
        if ($sortdir == 'desc') {
            return $tools->sortByDesc($sortfield);
        } else {
            return $tools->sortBy($sortfield);
        }
    }
    /**
     * @param $showAll
     * @param $categoryFilter
     * @param $sortfield
     * @param $sortdir
     * @return mixed
     */
    protected function getAllAccessoriesFromInventory($showAll, $categoryFilter, $page, $perPage, $sortfield = 'accessory_id', $sortdir = 'asc')
    {
        $accessories = new Collection();
        $inventoryAccessories = $this->inventory->getAccessories();
        // only apply pagination if filter can be applied directly to assets
//        $assets = $this->inventory->getAssets(($page - 1) * $perPage, $perPage);

        foreach ($inventoryAccessories as $inventoryAccessory) {
//            if ( ($this->isVisible($showAll, $tool))
//                && $this->applyCategoryFilter($categoryFilter, $tool)) {
                $accessories->add($inventoryAccessory);
//            }
        }
        if ($sortdir == 'desc') {
            return $accessories->sortByDesc($sortfield);
        } else {
            return $accessories->sortBy($sortfield);
        }
    }
    /**
     * @param $showAll
     * @param $category
     * @param $sortfield
     * @param $sortdir
     * @return mixed
     */
    protected function getAllFromDatabase($showAll, $category, $sortfield, $sortdir)
    {
        if ($showAll === true) {
            $builder = Capsule::table('tools');
        } else {
            $builder = Capsule::table('tools')
                ->where('visible', true);
        }
        if (isset($category)) {
            $builder = $builder->where('category', $category);
        }
        $tools = $builder->orderBy($sortfield, $sortdir)->get();
        return $tools;
    }

    /**
     * @param $showAll
     * @param $tool
     * @return bool
     */
    protected function isVisible($showAll, $tool): bool
    {
        return $showAll || $tool->visible == TRUE;
    }

    /**
     * Returns true if the tool belongs to the category or if no category filter should be applied
     * @param $categoryFilter
     * @param $tool
     * @return bool
     */
    protected function applyCategoryFilter($categoryFilter, $tool): bool
    {
        if (!isset($categoryFilter) || empty($categoryFilter)) {
            return TRUE;
        }
        return $this->isInCategory($categoryFilter, $tool);
    }
    protected function applyQueryFilter($query, $tool): bool
    {
        if (!isset($query) || empty($query)) {
            return TRUE;
        }
        return (strpos(strtoupper($tool->name), strtoupper($query)) !== false);
    }
    protected function isInCategory($category, $tool) {
        if (!isset($category) || empty($category)) {
            return false;
        }
        return $tool->category == $category;
    }

    /**
     * @param $item
     * @param $existingItem
     */
    private function updateExistingItem($item, $existingItem): void
    {
        $existingItem->name = $item->name;
        $existingItem->item_type = $item->item_type;
        $existingItem->created_by = $item->created_by;
        $existingItem->assigned_to = $item->assigned_to;
        $existingItem->current_location_id = $item->current_location_id;
        $existingItem->item_condition = $item->item_condition;
        $existingItem->sku = $item->sku;
        $existingItem->description = $item->description;
        $existingItem->keywords = $item->keywords;
        $existingItem->brand = $item->brand;
        $existingItem->care_information = $item->care_information;
        $existingItem->component_information = $item->component_information;
        $existingItem->loan_fee = $item->loan_fee;
        $existingItem->max_loan_days = $item->max_loan_days;
        $existingItem->is_active = $item->is_active;
        $existingItem->show_on_website = $item->show_on_website;
        $existingItem->serial = $item->serial;
        if (isset($item->note)) {
            echo "item note: " . $item->note . "\n";
            $existingItem->note = (strlen($item->note) > 128) ? substr($item->note, 0, 125) . "..." : $item->note;
            echo "updated item note: " . $existingItem->note . "\n";
        } else {
            $existingItem->note = null;
        }
        $existingItem->price_cost = $item->price_cost;
        $existingItem->price_sell = $item->price_sell;
        if ($existingItem->image_name != $item->image_name) {
            $this->syncImage($item->image_name, $existingItem);
        }
        $existingItem->image_name = $item->image_name;
        $existingItem->short_url = $item->short_url;
        $existingItem->item_sector = $item->item_sector;
        $existingItem->is_reservable = $item->is_reservable;
        $existingItem->deposit_amount = $item->deposit_amount;
        $existingItem->donated_by = $item->donated_by;
        $existingItem->owned_by = $item->owned_by;
        $existingItem->experience_level = $item->experience_level;
        $existingItem->safety_risk = $item->safety_risk;
        $existingItem->deliverable = isset($item->deliverable) ? $item->deliverable : false;
        $existingItem->size = $item->size;
    }

    /**
     * Resize image and assign it to inventory item
     */
    private function syncImage($fullFilePath, InventoryItem $item) {
        echo "full file path=" . $fullFilePath;
        $basename = basename($fullFilePath);
        echo "basename=" . $basename;
        $productImagePath = '/app/public/uploads/products';
        $thumb_path = $productImagePath.'/thumbs/';
        $large_path = $productImagePath.'/large/';
        $this->logger->info("thumb_path " . $thumb_path);
        $this->logger->info("large_path " . $large_path);
        
        // Create a thumbmail
        $this->imageResizer->resizeImage($fullFilePath, $thumb_path, 100, 100);

        // Resize the original to something sensible
        $this->imageResizer->resizeImage($fullFilePath, $large_path, 600, 600);

        $item->image_name = $baseName;
        // TODO: update image table
    }
}