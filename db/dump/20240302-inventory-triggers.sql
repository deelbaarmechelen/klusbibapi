--
-- Triggers `assets`
--
DROP TRIGGER IF EXISTS `assets_ad`$$
CREATE TRIGGER `assets_ad` AFTER DELETE ON `assets` FOR EACH ROW BEGIN
        DECLARE disable_sync_result TINYINT(1);
        IF klusbibdb.enable_sync_inventory2le() THEN
            DELETE FROM klusbibdb.kb_sync_assets WHERE id = OLD.id;
            SELECT klusbibdb.disable_sync_inventory2le() INTO disable_sync_result;
        END IF;
        END
$$
DROP TRIGGER IF EXISTS `assets_ai`$$
CREATE TRIGGER `assets_ai` AFTER INSERT ON `assets` FOR EACH ROW BEGIN
        DECLARE disable_sync_result TINYINT(1);
        IF klusbibdb.enable_sync_inventory2le() THEN
           INSERT INTO klusbibdb.kb_sync_assets (
            id, name, asset_tag, model_id, image, status_id, assigned_to, kb_assigned_to, assigned_type, last_checkout, last_checkin, expected_checkin, created_at, updated_at, deleted_at, last_sync_timestamp)
           VALUES (
            NEW.id, NEW.name, NEW.asset_tag, NEW.model_id, NEW.image, NEW.status_id, NEW.assigned_to, 
            (SELECT employee_num FROM inventory.users where id = NEW.assigned_type),
            NEW.assigned_type, NEW.last_checkout, NEW.last_checkin, NEW.expected_checkin, NEW.created_at, NEW.updated_at, NEW.deleted_at, NEW.created_at);
           SELECT klusbibdb.disable_sync_inventory2le() INTO disable_sync_result;
        END IF;
        END
$$
DROP TRIGGER IF EXISTS `assets_au`$$
CREATE TRIGGER `assets_au` AFTER UPDATE ON `assets` FOR EACH ROW BEGIN 
        DECLARE disable_sync_result TINYINT(1);
        IF klusbibdb.enable_sync_inventory2le() THEN
            UPDATE klusbibdb.kb_sync_assets 
            SET name = NEW.name,
            asset_tag = NEW.asset_tag,
            model_id = NEW.model_id,
            image = NEW.image,
            status_id = NEW.status_id,
            assigned_to = NEW.assigned_to,
            kb_assigned_to = (SELECT employee_num FROM inventory.users where id = NEW.assigned_to),
            assigned_type = NEW.assigned_type, 
            last_checkout = NEW.last_checkout,
            last_checkin = NEW.last_checkin, 
            expected_checkin = NEW.expected_checkin, 
            created_at = NEW.created_at, 
            updated_at = NEW.updated_at, 
            deleted_at = NEW.deleted_at,
            last_sync_timestamp = NEW.updated_at
            WHERE id = NEW.id;

            SELECT klusbibdb.disable_sync_inventory2le() INTO disable_sync_result;
        END IF;
        END
$$
