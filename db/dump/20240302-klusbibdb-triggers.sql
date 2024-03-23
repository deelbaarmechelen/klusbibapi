--
-- Triggers `contact`
--
DROP TRIGGER IF EXISTS klusbibdb.`contact_bi`$$

CREATE TRIGGER klusbibdb.`contact_bi` BEFORE INSERT ON klusbibdb.`contact` FOR EACH ROW BEGIN 
IF NEW.role = 'admin' THEN
  SET NEW.roles = 'a:2:{i:0;s:10:"ROLE_ADMIN";i:1;s:15:"ROLE_SUPER_USER";}';
ELSE
  SET NEW.roles = 'a:0:{}';
END IF;
IF NEW.password IS NULL THEN
  SET NEW.password = '$2y$13$JJRAiAUQgjIg1bkskpf6fuyFaGvW4DrVKXnqZ/iPjqZTHxzGbZ3Xe';
END IF;
IF NEW.country_iso_code IS NULL THEN
  SET NEW.country_iso_code = 'BE';
END IF;
IF NEW.balance IS NULL THEN
  SET NEW.balance = '0.00';
END IF;
IF NEW.subscriber IS NULL THEN
  SET NEW.subscriber = 0;
END IF;
IF NEW.locale IS NULL THEN
  SET NEW.locale = 'nl';
END IF;
IF NEW.enabled IS NULL THEN
  SET NEW.enabled = 1;
END IF;
IF NEW.is_active IS NULL THEN
  SET NEW.is_active = 1;
END IF;
IF NEW.email_canonical IS NULL THEN
  SET NEW.email_canonical = NEW.email;
END IF;
IF NEW.username IS NULL THEN
  SET NEW.username = NEW.email;
END IF;
IF NEW.username_canonical IS NULL THEN
  SET NEW.username_canonical = NEW.email;
END IF;
END
$$
DROP TRIGGER IF EXISTS klusbibdb.`contact_bu`$$
CREATE TRIGGER klusbibdb.`contact_bu` BEFORE UPDATE ON klusbibdb.`contact` FOR EACH ROW BEGIN 
IF NEW.role = 'admin' THEN
  SET NEW.roles = 'a:2:{i:0;s:10:"ROLE_ADMIN";i:1;s:15:"ROLE_SUPER_USER";}';
ELSE
  SET NEW.roles = 'a:0:{}';
END IF;
IF OLD.country_iso_code IS NULL AND NEW.country_iso_code IS NULL THEN
  SET NEW.country_iso_code = 'BE';
END IF;
IF OLD.balance IS NULL AND NEW.balance IS NULL THEN
  SET NEW.balance = '0.00';
END IF;
IF OLD.subscriber IS NULL AND NEW.subscriber IS NULL THEN
  SET NEW.subscriber = 0;
END IF;
IF OLD.locale IS NULL AND NEW.locale IS NULL THEN
  SET NEW.locale = 'nl';
END IF;
IF OLD.enabled IS NULL AND NEW.enabled IS NULL THEN
  SET NEW.enabled = 1;
END IF;
IF OLD.is_active IS NULL AND NEW.is_active IS NULL THEN
  SET NEW.is_active = 1;
END IF;
IF NEW.email_canonical IS NULL THEN
  SET NEW.email_canonical = NEW.email;
END IF;
IF NEW.username IS NULL THEN
  SET NEW.username = NEW.email;
END IF;
IF NEW.username_canonical IS NULL THEN
  SET NEW.username_canonical = NEW.email;
END IF;
END
$$
DROP TRIGGER IF EXISTS klusbibdb.`kb_contact_bd`$$
CREATE TRIGGER klusbibdb.`kb_contact_bd` BEFORE DELETE ON klusbibdb.`contact` FOR EACH ROW BEGIN
    declare msg varchar(128);
    IF (SELECT balance from klusbibdb.contact WHERE id = OLD.id) > 0 THEN
        set msg = concat('kb_contact_ad trigger: Trying to delete a contact with positive balance - ID: ', cast(OLD.id as char));
        signal sqlstate '45000' set message_text = msg;
    ELSE
        update attendee set contact_id = null where contact_id = OLD.id;
        update attendee set created_by = null where created_by = OLD.id;
        update child set contact_id = null where contact_id = OLD.id;
        update deposit set contact_id = null where contact_id = OLD.id;
        update deposit set created_by = null where created_by = OLD.id;
        update loan set contact_id = null where contact_id = OLD.id;
        update loan set created_by = null where created_by = OLD.id;
        update membership set contact_id = null where contact_id = OLD.id;
        update membership set created_by = null where created_by = OLD.id;
        update note set contact_id = null where contact_id = OLD.id;
        update payment set contact_id = null where contact_id = OLD.id;
        update payment set created_by = null where created_by = OLD.id;
        update page set created_by = null where created_by = OLD.id;
        update page set updated_by = null where updated_by = OLD.id;
        update maintenance set completed_by = null where completed_by = OLD.id;
        update item_movement set created_by = null where created_by = OLD.id;
        update inventory_item set created_by = null where created_by = OLD.id;
        update event set created_by = null where created_by = OLD.id;
        
        delete from waiting_list_item where contact_id = OLD.id;
        delete from file_attachment where contact_id = OLD.id;
        delete from contact_field_value where contact_id = OLD.id;
    END IF;

END
$$

--
-- Triggers `inventory_item`
--
DROP TRIGGER IF EXISTS klusbibdb.`inventory_item_bd`$$
CREATE TRIGGER klusbibdb.`inventory_item_bd` BEFORE DELETE ON klusbibdb.`inventory_item` FOR EACH ROW BEGIN
DECLARE disable_sync_result TINYINT(1);
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    IF klusbibdb.is_sync_le2inventory_enabled() THEN
        SELECT klusbibdb.disable_sync_le2inventory() INTO disable_sync_result;
    END IF;
    call kb_log_msg(concat('Error in inventory_item_bd: inventory asset sync skipped for inventory item with id: ', ifnull(OLD.id, 'null') ));
    RESIGNAL;
END;
IF (OLD.id < 100000 AND NOT klusbibdb.is_sync_inventory2le_enabled() ) THEN

    IF klusbibdb.enable_sync_le2inventory() THEN
        DELETE FROM inventory.assets WHERE id = OLD.id;
        SELECT klusbibdb.disable_sync_le2inventory() INTO disable_sync_result;
    ELSE
        call kb_log_msg(concat('Warning: inventory asset delete failed - ongoing inventory to api sync upon inventory_item delete for id: ', OLD.id));
        signal sqlstate '45000' set message_text = 'Unable to delete inventory asset: sync (inventory -> api) ongoing (check @sync_inventory2le value if this is an error).';
    END IF;
END IF;
END
$$
DROP TRIGGER IF EXISTS klusbibdb.`inventory_item_bi`$$
CREATE TRIGGER klusbibdb.`inventory_item_bi` BEFORE INSERT ON klusbibdb.`inventory_item` FOR EACH ROW BEGIN
DECLARE disable_sync_result TINYINT(1);
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    IF klusbibdb.is_sync_le2inventory_enabled() THEN
        SELECT klusbibdb.disable_sync_le2inventory() INTO disable_sync_result;
    END IF;
    call kb_log_msg(concat('Error in inventory_item_bi: inventory asset sync skipped for inventory item with id: ', ifnull(NEW.id, 'null'), ' sku: ', ifnull(NEW.sku, 'null') ));
    RESIGNAL;
END;
    IF NEW.created_at IS NULL THEN
      SET NEW.created_at = CURRENT_TIMESTAMP;
    END IF;
    IF NEW.updated_at IS NULL THEN
      SET NEW.updated_at = CURRENT_TIMESTAMP;
    END IF;
    SET NEW.short_url = substring(NEW.short_url,0,64);

    -- do not sync accessories
    IF (NEW.id < 100000 AND NOT klusbibdb.is_sync_inventory2le_enabled() ) THEN
        IF klusbibdb.enable_sync_le2inventory() THEN
            IF NOT EXISTS (SELECT 1 FROM inventory.assets WHERE id = NEW.id) THEN
            INSERT INTO inventory.assets  (
            id, name, asset_tag, model_id, serial, created_at, updated_at)
            SELECT 
            NEW.`id`, NEW.name, NEW.sku, null, NEW.serial, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP);
            ELSE
                call kb_log_msg(concat('Warning: inventory asset already exists - inventory_item insert not reported to inventory.assets for id: ', NEW.id));
            END IF;
            SELECT klusbibdb.disable_sync_le2inventory() INTO disable_sync_result;
        ELSE
            call kb_log_msg(concat('Warning: inventory asset insert failed - ongoing inventory to api sync upon inventory_item insert for id: ', NEW.id));
            signal sqlstate '45000' set message_text = 'Unable to create inventory asset: sync (inventory -> api) ongoing (check @sync_inventory2le value if this is an error).';
        END IF;
    END IF;
END
$$
DROP TRIGGER IF EXISTS klusbibdb.`inventory_item_bu`$$
CREATE TRIGGER klusbibdb.`inventory_item_bu` BEFORE UPDATE ON klusbibdb.`inventory_item` FOR EACH ROW BEGIN
DECLARE disable_sync_result TINYINT(1);
DECLARE old_status_id INT(11);
DECLARE new_status_id INT(11);
DECLARE log_meta_json text;
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    GET DIAGNOSTICS CONDITION 1
    @SQLState = RETURNED_SQLSTATE, @SQLMessage = MESSAGE_TEXT;
    IF klusbibdb.is_sync_le2inventory_enabled() THEN
        SELECT klusbibdb.disable_sync_le2inventory() INTO disable_sync_result;
    END IF;
    call kb_log_msg(concat('Error in inventory_item_bu: inventory asset sync skipped for inventory item with id: ', ifnull(OLD.id, 'null') ));
    call kb_log_msg(concat('sqlstate - ', @SQLState, '; error msg - ', @SQLMessage));
    RESIGNAL;
END;
    IF NEW.updated_at IS NULL THEN
      SET NEW.updated_at = CURRENT_TIMESTAMP;
    END IF;
    SET NEW.short_url = substring(NEW.short_url,0,64);

    IF (OLD.id < 100000 AND NOT klusbibdb.is_sync_inventory2le_enabled() ) THEN
        IF klusbibdb.enable_sync_le2inventory() THEN
            IF EXISTS (SELECT 1 FROM inventory.assets WHERE id = NEW.id) THEN
                IF NOT OLD.name <=> NEW.name THEN
                    UPDATE inventory.assets 
                    SET name = NEW.name,
                    updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
                    WHERE id = OLD.id
                    AND NOT name <=> NEW.name;
                END IF;
                IF NOT OLD.sku <=> NEW.sku THEN
                    UPDATE inventory.assets 
                    SET asset_tag = NEW.sku,
                    updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
                    WHERE id = OLD.id
                    AND asset_tag <> NEW.sku;
                END IF;
                IF NOT OLD.serial <=> NEW.serial THEN
                    UPDATE inventory.assets 
                    SET serial = NEW.serial,
                    updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
                    WHERE id = OLD.id
                    AND serial <> NEW.serial;
                END IF;
                IF NOT OLD.current_location_id <=> NEW.current_location_id 
                  AND NEW.current_location_id IN (0, 2, 3) THEN
                    SELECT status_id, CASE 
                        WHEN NEW.current_location_id = 0 THEN 3
                        WHEN NEW.current_location_id = 2 THEN 2
                        WHEN NEW.current_location_id = 3 THEN 1
                      END
                      INTO old_status_id, new_status_id
                      FROM inventory.assets
                      WHERE id = OLD.id;
                    IF (old_status_id <> new_status_id) THEN
                        UPDATE inventory.assets 
                        SET status_id = new_status_id,
                        updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
                        WHERE id = OLD.id;

                        -- Insert action log
                        -- {"status_id":{"old":2,"new":"4"}}
                        SET log_meta_json := concat('{\"status_id\":{\"old\":\"', old_status_id, '\",\"new\":\"', new_status_id, '\"}}');
                        INSERT INTO inventory.action_logs (user_id, action_type, note, item_type, item_id, created_at, updated_at, company_id, log_meta)
                        SELECT 1, 'update', null, 'App\\Models\\Asset', OLD.id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1, log_meta_json;
                    END IF;
                END IF;
            ELSE
                call kb_log_msg(concat('Warning: inventory asset missing - created on the fly upon inventory_item update for id: ', NEW.id));
                INSERT INTO inventory.assets  (
                    id, name, asset_tag, model_id, serial, created_at, updated_at)
                    SELECT 
                    NEW.`id`, NEW.name, NEW.sku, null, NEW.serial, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP);
            END IF;
            SELECT klusbibdb.disable_sync_le2inventory() INTO disable_sync_result;
        ELSE
            call kb_log_msg(concat('Warning: inventory asset update failed - ongoing inventory to api sync upon inventory_item update for id: ', NEW.id));
            signal sqlstate '45000' set message_text = 'Unable to update inventory asset: sync (inventory -> api) ongoing (check @sync_inventory2le value if this is an error).';
        END IF;
    END IF;
END
$$

--
-- Triggers `kb_sync_assets`
--
DROP TRIGGER IF EXISTS klusbibdb.`kb_sync_assets_bd`$$
CREATE TRIGGER klusbibdb.`kb_sync_assets_bd` BEFORE DELETE ON klusbibdb.`kb_sync_assets` FOR EACH ROW BEGIN
    DECLARE disable_sync_result TINYINT(1);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        IF klusbibdb.is_sync_inventory2le_enabled() THEN
            SELECT klusbibdb.disable_sync_inventory2le() INTO disable_sync_result;
        END IF;
        GET DIAGNOSTICS CONDITION 1
        @SQLState = RETURNED_SQLSTATE, @SQLMessage = MESSAGE_TEXT;
        call kb_log_msg(concat('Error in klusbibdb.kb_sync_assets_bd: sqlstate - ', @SQLState, '; error msg - ', @SQLMessage));
        RESIGNAL;
    END;
    IF klusbibdb.enable_sync_inventory2le() THEN
        -- TODO: replace delete by archive?
        DELETE FROM klusbibdb.item_movement WHERE inventory_item_id = OLD.id;
        DELETE FROM klusbibdb.loan_row WHERE inventory_item_id = OLD.id;
        DELETE FROM klusbibdb.product_field_value WHERE inventory_item_id = OLD.id;
        DELETE FROM klusbibdb.image WHERE inventory_item_id = OLD.id;
        DELETE FROM klusbibdb.inventory_item_product_tag WHERE inventory_item_id = OLD.id;
        DELETE FROM klusbibdb.inventory_item WHERE id = OLD.id;
    END IF;
END
$$
DROP TRIGGER IF EXISTS klusbibdb.`kb_sync_assets_bi`$$
CREATE TRIGGER klusbibdb.`kb_sync_assets_bi` BEFORE INSERT ON klusbibdb.`kb_sync_assets` FOR EACH ROW BEGIN
    DECLARE default_item_name varchar(255) DEFAULT ' ';
    -- Set location to 2 = 'In stock'
    DECLARE location_id_unknown INT DEFAULT 0;
    DECLARE location_id_in_stock INT DEFAULT 2;
    DECLARE location_id_repair INT DEFAULT 3;
    DECLARE location_id INT DEFAULT 2;
    DECLARE is_enabled TINYINT DEFAULT 1;
    DECLARE disable_sync_result TINYINT(1);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        IF klusbibdb.is_sync_inventory2le_enabled() THEN
            SELECT klusbibdb.disable_sync_inventory2le() INTO disable_sync_result;
        END IF;
        GET DIAGNOSTICS CONDITION 1
        @SQLState = RETURNED_SQLSTATE, @SQLMessage = MESSAGE_TEXT;
        call kb_log_msg(concat('Error in klusbibdb.kb_sync_assets_bi: sqlstate - ', @SQLState, '; error msg - ', @SQLMessage));
        RESIGNAL;
    END;
    IF klusbibdb.enable_sync_inventory2le() THEN
        IF NOT EXISTS (SELECT 1 FROM klusbibdb.inventory_item WHERE id = NEW.id) THEN
            SET location_id := location_id_in_stock;
            SET is_enabled := 1;
            IF (NEW.status_id = 3) THEN
                SET location_id := location_id_unknown;
                SET is_enabled := 0;
            END IF;
            IF (NEW.status_id = 3) THEN
                SET location_id := location_id_repair;
            END IF;
            IF (NEW.status_id = 3) THEN
                SET location_id := location_id_unknown;
            END IF;
            SET default_item_name := (SELECT concat(ifnull(name, 'unknown'), '-', ifnull(model_number, 'none')) FROM inventory.models WHERE id = NEW.model_id);
            INSERT INTO klusbibdb.inventory_item (
            id, created_by, assigned_to, current_location_id, item_condition, created_at, updated_at,
            name, sku, description, keywords, brand, care_information, component_information, 
            loan_fee, max_loan_days, is_active, show_on_website, serial, note, price_cost, price_sell, short_url, 
            item_sector, is_reservable, deposit_amount, item_type, donated_by, owned_by)
            SELECT 
            NEW.`id`, null, NEW.`kb_assigned_to`, location_id, null, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP), 
            ifnull(NEW.`name`, default_item_name), NEW.`asset_tag`, null, null, null, null, null,
            null, null, is_enabled, is_enabled, LEFT(NEW.serial, 64), null, null, null, null, 
            null, is_enabled, null, 'loan', null, null;

        ELSE
            call kb_log_msg(concat('Detected missing inventory_item with id: ', NEW.id, ' upon insert in kb_sync_assets'));
        END IF;
    END IF;
END
$$
DROP TRIGGER IF EXISTS klusbibdb.`kb_sync_assets_bu`$$
CREATE TRIGGER klusbibdb.`kb_sync_assets_bu` BEFORE UPDATE ON klusbibdb.`kb_sync_assets` FOR EACH ROW BEGIN
    DECLARE dummy_ INT(11);
    DECLARE inventory_item_name varchar(255) CHARSET utf8 COLLATE utf8_unicode_ci DEFAULT ' ';
    DECLARE inventory_item_sku varchar(255) CHARSET utf8 COLLATE utf8_unicode_ci DEFAULT ' ';
    DECLARE inventory_item_serial varchar(64) CHARSET utf8 COLLATE utf8_unicode_ci DEFAULT ' ';
    DECLARE default_item_name varchar(255) CHARSET utf8 COLLATE utf8_unicode_ci DEFAULT ' ';
    DECLARE item_checked_out_at datetime;
    DECLARE asset_checked_out_at datetime;
    DECLARE asset_checkin_date datetime;
    DECLARE disable_sync_result TINYINT(1);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1
        @SQLState = RETURNED_SQLSTATE, @SQLMessage = MESSAGE_TEXT;
        IF klusbibdb.is_sync_inventory2le_enabled() THEN
            SELECT klusbibdb.disable_sync_inventory2le() INTO disable_sync_result;
        END IF;
        call kb_log_msg(concat('Error in klusbibdb.kb_sync_assets_bu: sqlstate - ', @SQLState, '; error msg - ', @SQLMessage));
        RESIGNAL;
    END;
    IF klusbibdb.enable_sync_inventory2le() THEN
        SET default_item_name := (SELECT concat(ifnull(name, 'unknown'), '-', ifnull(model_number, 'none')) FROM inventory.models WHERE id = NEW.model_id);
        IF EXISTS (SELECT 1 FROM klusbibdb.inventory_item WHERE id = OLD.id) THEN
            -- (also?) compare new.name with inventory_item name
            SET inventory_item_name := (SELECT ifnull(name, 'unknown') FROM klusbibdb.inventory_item WHERE id = OLD.id);
            IF ((NOT OLD.name <=> NEW.name) OR (NEW.name <> inventory_item_name)) THEN
                UPDATE klusbibdb.`inventory_item`
                SET name = ifnull(NEW.`name`, default_item_name),
                updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
                WHERE id = OLD.id
                AND name <> NEW.`name`;
            END IF;
            -- (also?) compare new.asset_tag with inventory_item sku
            -- => always update sku if different of new.asset_tag
            SET inventory_item_sku := (SELECT sku FROM klusbibdb.inventory_item WHERE id = OLD.id);
            IF ((NOT OLD.asset_tag <=> NEW.asset_tag) OR (NEW.asset_tag <> inventory_item_sku)) THEN
                UPDATE klusbibdb.`inventory_item`
                SET sku = NEW.asset_tag,
                updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
                WHERE id = OLD.id
                AND sku <> NEW.asset_tag;
            END IF;
            SELECT serial INTO inventory_item_serial FROM klusbibdb.inventory_item WHERE id = OLD.id;
            IF ( (NOT OLD.serial <=> NEW.serial) OR (LEFT(NEW.serial, 64) <> inventory_item_serial ) ) THEN
                UPDATE klusbibdb.`inventory_item`
                SET serial = NEW.serial,
                updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
                WHERE id = OLD.id
                AND serial <> LEFT(NEW.serial, 64);
            END IF;
        ELSE
            INSERT INTO klusbibdb.inventory_item (
            id, assigned_to, created_at, updated_at,
            name, sku, item_type, serial)
            SELECT 
            NEW.`id`, NEW.`kb_assigned_to`, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP), ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP), 
            ifnull(NEW.`name`, default_item_name), NEW.`asset_tag`, 'loan', LEFT(NEW.serial, 64);
        END IF;

        IF (NOT NEW.model_id <=> OLD.model_id) THEN
            call kb_log_msg(concat('Warning: kb_sync_assets model_id update not reported to inventory_item: ', ifnull(OLD.model_id, 'null'), ' -> ', ifnull(NEW.model_id, 'null')));
        END IF;

        -- image sync handled by sync_inventory for tools (requires creation of large and thumb image)
        -- IF (NOT NEW.image <=> OLD.image) THEN
        --     call kb_log_msg(concat('Warning: kb_sync_assets (id=', OLD.id ,') image update not reported to inventory_item: ', ifnull(OLD.image, 'null'), ' -> ', ifnull(NEW.image, 'null')));
        -- END IF;

        IF (NOT NEW.status_id <=> OLD.status_id) THEN
            IF (NEW.status_id = 1 OR NEW.status_id = 2 OR NEW.status_id = 3) THEN
                IF (NEW.status_id = 1) THEN
                    -- NEW.status_id = 1 => maintenance, thus set current_location_id to 3 (repair) + add movement
                    UPDATE klusbibdb.`inventory_item`
                    SET current_location_id = 3,
                    updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
                    WHERE id = OLD.id
                    AND current_location_id <> 3;
                    INSERT INTO item_movement(
                        inventory_item_id, inventory_location_id, loan_row_id, assigned_to_contact_id, created_at, quantity)
                        SELECT OLD.id, 3, NULL, NULL, CURRENT_TIMESTAMP, 1;                
                END IF;
                IF (NEW.status_id = 2) THEN
                    -- NEW.status_id = 2 => available, thus set current_location_id to 2 (in stock) + add movement
                    UPDATE klusbibdb.`inventory_item`
                    SET current_location_id = 2,
                    updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
                    WHERE id = OLD.id
                    AND current_location_id <> 2;
                    INSERT INTO item_movement(
                        inventory_item_id, inventory_location_id, loan_row_id, assigned_to_contact_id, created_at, quantity)
                        SELECT OLD.id, 2, NULL, NULL, CURRENT_TIMESTAMP, 1;                
                END IF;
                IF (NEW.status_id = 3) THEN
                    -- NEW.status_id = 3 => archived, thus set current_location_id to 0 
                    UPDATE klusbibdb.`inventory_item`
                    SET current_location_id = 0,
                        is_active = 0,
                        show_on_website = 0,
                        is_reservable = 0,
                        updated_at = ifnull(NEW.`updated_at`, CURRENT_TIMESTAMP)
                    WHERE id = OLD.id
                    AND current_location_id <> 0;
                    INSERT INTO item_movement(
                        inventory_item_id, inventory_location_id, loan_row_id, assigned_to_contact_id, created_at, quantity)
                        SELECT OLD.id, 0, NULL, NULL, CURRENT_TIMESTAMP, 1;                
                END IF;
            ELSE
                call kb_log_msg(concat('Warning: kb_sync_assets (id=', OLD.id ,')  status_id update not reported to inventory_item: ', ifnull(OLD.status_id, 'null'), ' -> ', ifnull(NEW.status_id, 'null')));
            END IF;
        END IF;

        IF ((NOT NEW.last_checkout <=> OLD.last_checkout) 
        AND (NOT NEW.last_checkout IS NULL)) THEN
            IF ((NOT NEW.kb_assigned_to IS NULL)
            AND (NEW.assigned_type = 'App\\Models\\User'))  THEN
                CALL klusbibdb.kb_checkout (NEW.id, NEW.kb_assigned_to, NEW.last_checkout, NEW.expected_checkin, 'Checkout from inventory' );
            ELSE
                call kb_log_msg(concat('Warning: kb_sync_assets (id=', OLD.id ,') last_checkout (assigned to ', ifnull(NEW.kb_assigned_to, 'null'), ', assigned type ', ifnull(NEW.assigned_type, 'null'),') update not reported to inventory_item: ', ifnull(OLD.last_checkout, 'null'), ' -> ', ifnull(NEW.last_checkout, 'null')));
            END IF;
        END IF;

        IF ((NOT NEW.last_checkin <=> OLD.last_checkin)
            AND (NOT NEW.last_checkin IS NULL)) THEN
            IF (NEW.kb_assigned_to IS NULL) THEN
                CALL klusbibdb.kb_checkin (NEW.id, NEW.last_checkin, 'Checkin from inventory' );
            ELSE
                call kb_log_msg(concat('Warning: kb_sync_assets (id=', OLD.id ,')  last_checkin (assigned to ', ifnull(NEW.kb_assigned_to, 'null'), ') update not reported to inventory_item: ', ifnull(OLD.last_checkin, 'null'), ' -> ', ifnull(NEW.last_checkin, 'null')));
            END IF;
        END IF;

        IF ((NOT NEW.expected_checkin <=> OLD.expected_checkin)
        AND (NOT NEW.expected_checkin IS NULL)) THEN
            CALL klusbibdb.kb_extend (NEW.id, NEW.expected_checkin);
        END IF;

        -- IF (NOT NEW.assigned_to <=> OLD.assigned_to) THEN
        --     call kb_log_msg(concat('Warning: kb_sync_assets (id=', OLD.id ,')  assigned_to update not reported to inventory_item (inventory.asset values): ', ifnull(OLD.assigned_to, 'null'), ' -> ', ifnull(NEW.assigned_to, 'null')));
        -- END IF;

        -- Extra checks for recovery from inconsistent situations (only when triggering extra sync)
        IF NEW.last_sync_timestamp > ifnull(NEW.`updated_at`, NEW.created_at) THEN
            -- if asset is assigned to a user, a matching ACTIVE/OVERDUE loan should exist
            IF (inventory.is_on_loan(NEW.id)) THEN
                -- inventory asset on loan -> check LE item consistency
                IF (klusbibdb.is_on_loan(NEW.id) ) THEN
                    -- does a more recent checkout exist on inventory? -> checkin LE loan based on inventory.action_logs
                    SET item_checked_out_at := (SELECT MAX(checked_out_at) FROM loan_row WHERE inventory_item_id = NEW.id AND NOT checked_out_at IS NULL AND checked_in_at IS NULL);
                    SET asset_checked_out_at := (SELECT inventory.get_checkout_date(NEW.id, item_checked_out_at));
                    IF (NOT asset_checked_out_at IS NULL) AND (DATE(item_checked_out_at) = DATE(item_checked_out_at)) THEN
                        -- lookup checkin date based on action_logs query
                        SET asset_checkin_date := (SELECT inventory.get_checkin_date(NEW.id, asset_checked_out_at));
                        IF (NOT asset_checkin_date IS NULL) THEN
                            CALL klusbibdb.kb_checkin (NEW.id, asset_checkin_date, 'Checkin from inventory' );
                        END IF;
                    END IF;

                    -- TODO: same start date and user? -> update checkin date if necessary
                END IF;
                IF (NOT klusbibdb.is_on_loan(NEW.id) ) THEN
                    -- create a new loan on klusbibdb
                    CALL klusbibdb.kb_checkout (NEW.id, NEW.kb_assigned_to, NEW.last_checkout, NEW.expected_checkin, 'Checkout from inventory' );
                END IF;
            ELSE
                -- if asset is not assigned to a user, no matching ACTIVE/OVERDUE loan may exist
                IF (klusbibdb.is_on_loan(NEW.id)) THEN
                    -- check if a matching checkin exists in inventory activity, if it does then it has already been checked in
                    SET item_checked_out_at := (SELECT MAX(checked_out_at) FROM loan_row WHERE inventory_item_id = NEW.id AND NOT checked_out_at IS NULL AND checked_in_at IS NULL);
                    SET asset_checkin_date := (SELECT inventory.get_checkin_date(NEW.id, item_checked_out_at));
                    IF NOT asset_checkin_date IS NULL THEN
                        CALL klusbibdb.kb_checkin (NEW.id, asset_checkin_date, 'Checkin from inventory' );
                    ELSE
                        call kb_log_msg(concat('Warning: kb_sync_assets (id=', OLD.id ,') outdated - a more recent loan exists on lend engine (checked out on ', ifnull(item_checked_out_at, 'null'), ')' ));
                    END IF;
                END IF;
            END IF;
        END IF;
    END IF;
END
$$

--
-- Triggers `loan_row`
--
DROP TRIGGER IF EXISTS klusbibdb.`loan_row_bi`$$
CREATE TRIGGER klusbibdb.`loan_row_bi` BEFORE INSERT ON klusbibdb.`loan_row` FOR EACH ROW BEGIN
DECLARE disable_sync_result TINYINT(1);
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    IF klusbibdb.is_sync_le2inventory_enabled() THEN
        SELECT klusbibdb.disable_sync_le2inventory() INTO disable_sync_result;
    END IF;
    call kb_log_msg(concat('Error in loan_row_bi: inventory asset sync skipped for inventory item with id: ', ifnull(NEW.inventory_item_id, 'null'), ' and loan id ', ifnull(NEW.loan_id, 'null')));
    RESIGNAL;
END;
IF klusbibdb.enable_sync_le2inventory() THEN
    IF EXISTS (SELECT 1 FROM inventory.assets WHERE id = NEW.inventory_item_id) THEN
        IF (EXISTS (SELECT 1 FROM klusbibdb.loan WHERE id = NEW.loan_id AND (status = 'ACTIVE' OR STATUS = 'OVERDUE' OR STATUS = 'PENDING') ) 
        AND NOT NEW.checked_out_at IS NULL
        AND NEW.checked_in_at IS NULL) THEN

            call kb_log_msg(concat('Info: Triggering inventory checkout upon loan_row insert for inventory item with id: ', ifnull(NEW.inventory_item_id, 'null'), ' and loan id ', ifnull(NEW.loan_id, 'null')));
            CALL inventory.`kb_checkout`(NEW.inventory_item_id, 
                (SELECT contact_id FROM loan WHERE id = NEW.loan_id), 
                NEW.checked_out_at, NEW.due_in_at, 'Checkout from lend engine');
        END IF;
    ELSE
        call kb_log_msg(concat('Warning: inventory asset missing upon loan_row insert - inventory asset update skipped for inventory item with id: ', ifnull(NEW.inventory_item_id, 'null')));
        signal sqlstate '45000' set message_text = 'Unable to insert loan row: inventory asset missing.';
    END IF;
    SELECT klusbibdb.disable_sync_le2inventory() INTO disable_sync_result;

END IF;
END
$$
DROP TRIGGER IF EXISTS klusbibdb.`loan_row_bu`$$
CREATE TRIGGER klusbibdb.`loan_row_bu` BEFORE UPDATE ON klusbibdb.`loan_row` FOR EACH ROW BEGIN
DECLARE disable_sync_result TINYINT(1);
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    IF klusbibdb.is_sync_le2inventory_enabled() THEN
        SELECT klusbibdb.disable_sync_le2inventory() INTO disable_sync_result;
    END IF;
    call kb_log_msg(concat('Error in loan_row_bu: inventory asset sync skipped for inventory item with id: ', ifnull(NEW.inventory_item_id, 'null'), ' and loan id ', ifnull(NEW.loan_id, 'null')));
    RESIGNAL;
END;
IF klusbibdb.enable_sync_le2inventory() THEN
    IF EXISTS (SELECT 1 FROM inventory.assets WHERE id = NEW.inventory_item_id) THEN
        IF (EXISTS (SELECT 1 FROM klusbibdb.loan WHERE id = NEW.loan_id AND (status = 'ACTIVE' OR STATUS = 'OVERDUE' OR STATUS = 'PENDING') ) 
        AND OLD.checked_out_at IS NULL AND NOT NEW.checked_out_at IS NULL
        AND NEW.checked_in_at IS NULL) THEN
        
            call kb_log_msg(concat('Info: Triggering inventory checkout upon loan_row update for inventory item with id: ', ifnull(NEW.inventory_item_id, 'null'), ' and loan id ', ifnull(NEW.loan_id, 'null')));
            CALL inventory.`kb_checkout`(NEW.inventory_item_id, 
                (SELECT contact_id FROM loan WHERE id = NEW.loan_id), 
                NEW.checked_out_at, NEW.due_in_at, 'Checkout from lend engine');
        END IF;
        IF (OLD.checked_in_at IS NULL AND NOT NEW.checked_in_at IS NULL) THEN
            call kb_log_msg(concat('Info: Updating assets.last_checkin for inventory item with id: ', ifnull(NEW.inventory_item_id, 'null'), ' and loan id ', ifnull(NEW.loan_id, 'null')));
            CALL inventory.`kb_checkin`(NEW.inventory_item_id, NEW.checked_out_at, NEW.checked_in_at, 'Checkin from lend engine');
        END IF;
        IF (OLD.checked_in_at IS NULL AND NEW.checked_in_at IS NULL
        AND NOT OLD.checked_out_at IS NULL AND NOT NEW.checked_out_at IS NULL
        AND NOT NEW.due_in_at IS NULL AND NOT OLD.due_in_at <=> NEW.due_in_at) THEN
            call kb_log_msg(concat('Info: Updating assets.expected_checkin for inventory item with id: ', ifnull(NEW.inventory_item_id, 'null'), ' and loan id ', ifnull(NEW.loan_id, 'null')));
            CALL inventory.`kb_extend`(NEW.inventory_item_id, NEW.checked_out_at, OLD.due_in_at, NEW.due_in_at, 'Extend from lend engine');
        END IF;

    ELSE
        call kb_log_msg(concat('Error: inventory asset missing upon loan_row update for inventory item with id: ', ifnull(NEW.inventory_item_id, 'null'), ' and loan id ', ifnull(NEW.loan_id, 'null')));
        signal sqlstate '45000' set message_text = 'Unable to update loan row: inventory asset is missing and required for inventory checkout.';
    END IF;
    SELECT klusbibdb.disable_sync_le2inventory() INTO disable_sync_result;
END IF;
END
$$

--
-- Triggers `membership`
--
DROP TRIGGER IF EXISTS klusbibdb.`kb_membership_ad`$$
CREATE TRIGGER klusbibdb.`kb_membership_ad` AFTER DELETE ON klusbibdb.`membership` FOR EACH ROW BEGIN
    UPDATE klusbibdb.`contact` c
    SET c.`active_membership` = NULL
    WHERE id = OLD.contact_id;
 END
$$
DROP TRIGGER IF EXISTS klusbibdb.`kb_membership_ai`$$
CREATE TRIGGER klusbibdb.`kb_membership_ai` AFTER INSERT ON klusbibdb.`membership` FOR EACH ROW BEGIN
IF EXISTS (SELECT 1 FROM klusbibdb.`contact` WHERE id = NEW.contact_id) THEN
     IF NEW.status = 'ACTIVE' THEN
        UPDATE klusbibdb.`contact` c
        SET c.`active_membership` = NEW.id
        WHERE id = NEW.contact_id;
    END IF;
 END IF;
END
$$
DROP TRIGGER IF EXISTS klusbibdb.`kb_membership_au`$$
CREATE TRIGGER klusbibdb.`kb_membership_au` AFTER UPDATE ON klusbibdb.`membership` FOR EACH ROW BEGIN
 IF EXISTS (SELECT 1 FROM klusbibdb.`contact` WHERE id = NEW.contact_id) THEN
    IF NEW.status = 'ACTIVE' THEN
        UPDATE klusbibdb.`contact` c
        SET c.`active_membership` = NEW.id
        WHERE id = NEW.contact_id;
    END IF;
 END IF;
END
$$
DROP TRIGGER IF EXISTS klusbibdb.`membership_bi`$$
CREATE TRIGGER klusbibdb.`membership_bi` BEFORE INSERT ON klusbibdb.`membership` FOR EACH ROW BEGIN 
IF NEW.created_at IS NULL THEN
  SET NEW.created_at = CURRENT_TIMESTAMP;
END IF;
END
$$
DROP TRIGGER IF EXISTS klusbibdb.`membership_bu`$$
CREATE TRIGGER klusbibdb.`membership_bu` BEFORE UPDATE ON klusbibdb.`membership` FOR EACH ROW BEGIN 
IF NEW.updated_at IS NULL THEN
  SET NEW.updated_at = CURRENT_TIMESTAMP;
END IF;
END
$$

