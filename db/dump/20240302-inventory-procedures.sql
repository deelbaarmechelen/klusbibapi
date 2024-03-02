--
-- Database: `inventory`
--

--
-- Procedures
--
DROP PROCEDURE IF EXISTS inventory.`kb_checkin`$$
CREATE PROCEDURE inventory.`kb_checkin` (IN `item_id` INT, IN `checkout_datetime` DATETIME, IN `checkin_datetime` DATETIME, IN `comment` VARCHAR(255))   BEGIN 
DECLARE user_id INT DEFAULT 0;
DECLARE log_meta_json text;
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    GET DIAGNOSTICS CONDITION 1
        @SQLState = RETURNED_SQLSTATE, @SQLMessage = MESSAGE_TEXT;
    call kb_log_msg(concat('Error in inventory.kb_checkin: sqlstate - ', @SQLState, '; error msg - ', @SQLMessage));
    RESIGNAL;
END;
    -- only update inventory if checkin does not exist yet
    IF (inventory.get_checkin_date(item_id, checkout_datetime) IS NULL) THEN
        call klusbibdb.kb_log_msg(concat('Info: Checkin - Updating assets.last_checkin for inventory item with id: ', ifnull(item_id, 'null')));
        SET user_id := (SELECT assigned_to FROM inventory.assets where id = item_id);
        UPDATE inventory.assets
        SET last_checkin = checkin_datetime,
            last_checkout = NULL,
            expected_checkin = NULL,
            checkin_counter = checkin_counter + 1,
            assigned_to = NULL,
            assigned_type = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = item_id
        AND last_checkin < checkin_datetime;

        -- Insert action log with comment
        -- TODO: update log_meta if old.expected_checkin is not null (requires old_checkin_datetime as input parameter
        -- SET log_meta_json := concat('{\"expected_checkin\":{\"old\":\"', DATE_FORMAT(old_checkin_datetime, '%Y-%m-%d'), '\",\"new\":\"null\"}}');
        INSERT INTO action_logs (user_id, action_type, target_id, target_type, note, item_type, item_id, created_at, updated_at, company_id, action_date)
        SELECT 1, 'checkin from', user_id, 'App\\Models\\User', comment, 'App\\Models\\Asset', item_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1, CURRENT_TIMESTAMP;
    ELSE
        call klusbibdb.kb_log_msg(concat('Info: Checkin - Skipped as checkin already exists in action_logs for inventory item with id: ', ifnull(item_id, 'null')));
    END IF;
END$$

DROP PROCEDURE IF EXISTS inventory.`kb_checkout`$$
CREATE PROCEDURE inventory.`kb_checkout` (IN `inventory_item_id` INT, IN `loan_contact_id` INT, IN `datetime_out` DATETIME, IN `datetime_in` DATETIME, IN `comment` VARCHAR(255))   BEGIN 
DECLARE user_id INT DEFAULT 0;
DECLARE log_meta_json text;
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    GET DIAGNOSTICS CONDITION 1
        @SQLState = RETURNED_SQLSTATE, @SQLMessage = MESSAGE_TEXT;
    call kb_log_msg(concat('Error in inventory.kb_checkout: sqlstate - ', @SQLState, '; error msg - ', @SQLMessage));
    RESIGNAL;
END;
    -- only update inventory if no more recent checkout exists
    IF (inventory.get_checkout_date(inventory_item_id, datetime_out) IS NULL) THEN
        call klusbibdb.kb_log_msg(concat('Info: Checkout - Updating assets.last_checkout and expected_checkin for inventory item with id: ', ifnull(inventory_item_id, 'null')));
        SET user_id := (SELECT id FROM inventory.users where employee_num = loan_contact_id AND deleted_at IS NULL);
        UPDATE inventory.assets
        SET last_checkout = datetime_out,
            expected_checkin = datetime_in,
            checkout_counter = checkout_counter + 1,
            assigned_to = user_id,
            assigned_type = 'App\\Models\\User',
            updated_at = CURRENT_TIMESTAMP
        WHERE id = inventory_item_id;

        -- Insert action log with comment
        -- TODO: update log_meta if old.expected_checkin is not null (requires old_checkin_datetime as input parameter)
        -- TODO: update log_meta if old.location_id is not null (requires old_location_id as input parameter)
        -- SET log_meta_json := concat('{\"expected_checkin\":{\"old\":\"null\",\"new\":\"',DATE_FORMAT(datetime_in, '%Y-%m-%d'),'\"},\"location_id\":{\"old\":2,\"new\":null}}}');
        INSERT INTO action_logs (user_id, action_type, target_id, target_type, note, item_type, item_id, expected_checkin, created_at, updated_at, company_id, action_date)
        SELECT 1, 'checkout', user_id, 'App\\Models\\User', comment, 'App\\Models\\Asset', inventory_item_id, datetime_in, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1, CURRENT_TIMESTAMP;
    END IF;
END$$

DROP PROCEDURE IF EXISTS inventory.`kb_extend`$$
CREATE PROCEDURE inventory.`kb_extend` (IN `item_id` INT, IN `checkout_datetime` DATETIME, IN `old_checkin_datetime` DATETIME, IN `new_checkin_datetime` DATETIME, IN `comment` VARCHAR(255))   BEGIN 
DECLARE user_id INT DEFAULT 0;
DECLARE orig_last_checkout DATE;
DECLARE orig_expected_checkin DATE;
DECLARE log_meta_json text;
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    GET DIAGNOSTICS CONDITION 1
        @SQLState = RETURNED_SQLSTATE, @SQLMessage = MESSAGE_TEXT;
    call kb_log_msg(concat('Error in inventory.kb_extend: sqlstate - ', @SQLState, '; error msg - ', @SQLMessage));
    RESIGNAL;
END;
    -- Only report extend if itme already checked out with same checkout time
    SELECT assigned_to, last_checkout, expected_checkin INTO user_id, orig_last_checkout, orig_expected_checkin FROM inventory.assets where id = item_id;
    IF ( (NOT user_id IS NULL) AND (checkout_datetime = orig_last_checkout) AND (orig_expected_checkin <> new_checkin_datetime)) THEN
        call klusbibdb.kb_log_msg(concat('Info: Extend - Updating assets.expected_checkin for inventory item with id: ', ifnull(item_id, 'null')));
        UPDATE inventory.assets
        SET expected_checkin = new_checkin_datetime,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = item_id;

        -- Insert action log
        SET log_meta_json := concat('{\"expected_checkin\":{\"old\":\"', DATE_FORMAT(old_checkin_datetime, '%Y-%m-%d'), '\",\"new\":\"', DATE_FORMAT(new_checkin_datetime, '%Y-%m-%d 00:00:00'), '\"}}');
        INSERT INTO action_logs (user_id, action_type, note, item_type, item_id, created_at, updated_at, company_id, log_meta)
        SELECT 1, 'update', comment, 'App\\Models\\Asset', item_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1, log_meta_json;
    END IF;

END$$

DROP PROCEDURE IF EXISTS inventory.`kb_register_loan_no_sync`$$
CREATE PROCEDURE inventory.`kb_register_loan_no_sync` (IN `inventory_item_id` INT, IN `loan_contact_id` INT, IN `datetime_out` DATETIME, IN `datetime_in` DATETIME, IN `comment` VARCHAR(255))   BEGIN 
DECLARE disable_sync_result TINYINT(1);
DECLARE new_loan_id INT DEFAULT 0;
DECLARE new_loan_row_id INT DEFAULT 0;
DECLARE lr_checked_out_at DATETIME;
DECLARE lr_checked_in_at DATETIME;
DECLARE location_on_loan INT DEFAULT 1;
DECLARE location_stock INT DEFAULT 2;
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    IF klusbibdb.is_sync_inventory2le_enabled() THEN
        SELECT klusbibdb.disable_sync_inventory2le() INTO disable_sync_result;
    END IF;
    call klusbibdb.kb_log_msg(concat('Error in kb_register_loan_no_sync'));
    RESIGNAL;
END;
-- only run if no sync ongoing
-- prevent loan_row trigger to execute by enabling sync inventory -> le
IF NOT klusbibdb.is_sync_inventory2le_enabled() AND NOT klusbibdb.is_sync_le2inventory_enabled() 
 AND klusbibdb.enable_sync_inventory2le() THEN

IF EXISTS (SELECT 1 FROM klusbibdb.inventory_item WHERE id = inventory_item_id) 
AND EXISTS (SELECT 1 FROM klusbibdb.contact WHERE id = loan_contact_id) THEN
        
    -- check if loan already exists: based on contact_id, start_date
    IF EXISTS (SELECT 1 FROM klusbibdb.loan l WHERE l.contact_id = loan_contact_id AND l.datetime_out = datetime_out AND (l.status = 'ACTIVE' OR l.status = 'OVERDUE' OR l.status = 'CLOSED') ) THEN
        SET new_loan_id := (SELECT l.id FROM klusbibdb.loan l WHERE l.contact_id = loan_contact_id AND l.datetime_out = datetime_out AND (l.status = 'ACTIVE' OR l.status = 'OVERDUE' OR l.status = 'CLOSED'));
        UPDATE klusbibdb.loan l SET l.datetime_in = datetime_in
            WHERE l.id = new_loan_id AND l.datetime_in < datetime_in;
    ELSE
        INSERT INTO klusbibdb.loan (
            contact_id, datetime_out, datetime_in, status, total_fee, created_at)
        SELECT
        loan_contact_id, ifnull(datetime_out, CURRENT_TIMESTAMP), ifnull(datetime_in, CURRENT_TIMESTAMP), 'CLOSED', 0, 
        CASE WHEN DATE(datetime_out) < DATE(CURRENT_TIMESTAMP) THEN datetime_out ELSE CURRENT_TIMESTAMP END;
        SET new_loan_id := LAST_INSERT_ID();

    END IF;
    
    -- check if loan row already exists: based on loan_id, inventory_item
    IF EXISTS (SELECT 1 FROM klusbibdb.loan_row lr WHERE lr.loan_id = new_loan_id AND lr.inventory_item_id = inventory_item_id) THEN
        SET new_loan_row_id := (SELECT lr.id FROM klusbibdb.loan_row lr WHERE lr.loan_id = new_loan_id AND lr.inventory_item_id = inventory_item_id);
        SET lr_checked_out_at := (SELECT lr.checked_out_at FROM klusbibdb.loan_row lr WHERE lr.id = new_loan_row_id);
        SET lr_checked_in_at := (SELECT lr.checked_in_at FROM klusbibdb.loan_row lr WHERE lr.id = new_loan_row_id);
        IF (lr_checked_out_at IS NULL) THEN
            UPDATE klusbibdb.loan_row SET checked_out_at = datetime_out
	            WHERE id = new_loan_row_id;
            INSERT INTO klusbibdb.item_movement(
                inventory_item_id, inventory_location_id, loan_row_id, assigned_to_contact_id, created_at, quantity)
                SELECT inventory_item_id, location_on_loan, new_loan_row_id, loan_contact_id, ifnull(datetime_out, CURRENT_TIMESTAMP), 1;
        END IF;
        IF (lr_checked_in_at IS NULL) THEN
            UPDATE klusbibdb.loan_row SET checked_in_at = datetime_in
	            WHERE id = new_loan_row_id;
            INSERT INTO klusbibdb.item_movement(
                inventory_item_id, inventory_location_id, loan_row_id, assigned_to_contact_id, created_at, quantity)
                SELECT inventory_item_id, location_stock, NULL, NULL, ifnull(datetime_in, CURRENT_TIMESTAMP), NULL;
        END IF;
    ELSE
        INSERT INTO klusbibdb.loan_row (
            loan_id, inventory_item_id, product_quantity, due_out_at, due_in_at, checked_out_at, checked_in_at, fee, site_from, site_to)
            SELECT new_loan_id, inventory_item_id, 1, datetime_out, ifnull(datetime_in, ifnull(datetime_out, CURRENT_TIMESTAMP)), 
            datetime_out, ifnull(datetime_in, ifnull(datetime_out, CURRENT_TIMESTAMP)), 0, 1, 1;
        SET new_loan_row_id := LAST_INSERT_ID();
        -- create item movements: on loan and back in stock
        INSERT INTO klusbibdb.item_movement(
            inventory_item_id, inventory_location_id, loan_row_id, assigned_to_contact_id, created_at, quantity)
            SELECT inventory_item_id, location_on_loan, new_loan_row_id, loan_contact_id, ifnull(datetime_out, CURRENT_TIMESTAMP), 1;
        INSERT INTO klusbibdb.item_movement(
            inventory_item_id, inventory_location_id, loan_row_id, assigned_to_contact_id, created_at, quantity)
            SELECT inventory_item_id, location_stock, NULL, NULL, ifnull(datetime_in, CURRENT_TIMESTAMP), NULL;
	END IF;

    IF NOT EXISTS (SELECT 1 FROM klusbibdb.loan_row WHERE loan_id = new_loan_id AND checked_in_at IS NULL) THEN
       -- all items have been checked in
        UPDATE klusbibdb.loan l SET l.status = 'CLOSED', l.datetime_in = datetime_in 
        WHERE id = new_loan_id;
    END IF;

    IF NOT comment IS NULL THEN
        INSERT INTO klusbibdb.note (contact_id, loan_id, inventory_item_id, `text`, admin_only, created_at)
        SELECT loan_contact_id, new_loan_id, inventory_item_id, comment, 1, ifnull(datetime_in, CURRENT_TIMESTAMP);
    END IF;  

ELSE
    call klusbibdb.kb_log_msg(concat('Warning: inventory_item or contact missing in kb_register_loan - loan creation skipped for inventory item with id: ', inventory_item_id));
END IF;
SELECT klusbibdb.disable_sync_inventory2le() INTO disable_sync_result;

ELSE
    call klusbibdb.kb_log_msg(concat('Warning: sync ongoing in kb_register_loan - loan creation skipped for inventory item with id: ', inventory_item_id));
END IF;
END$$

--
-- Functies
--
DROP FUNCTION IF EXISTS inventory.`get_checkin_date`$$
CREATE FUNCTION inventory.`get_checkin_date` (`asset_id` INT, `checked_out_at` DATETIME) RETURNS DATETIME  BEGIN
IF NOT EXISTS (SELECT 1 FROM inventory.action_logs WHERE action_type = 'checkout' AND item_id = asset_id AND action_date = checked_out_at) THEN
    RETURN NULL;
END IF;

RETURN (SELECT MIN(action_date) FROM inventory.action_logs 
  WHERE action_type = 'checkin from' 
    AND item_id = asset_id
    AND target_type = 'App\\Models\\User'
    AND action_date > checked_out_at );
END$$

DROP FUNCTION IF EXISTS inventory.`get_checkout_date`$$
CREATE FUNCTION inventory.`get_checkout_date` (`asset_id` INT, `checked_out_at` DATETIME) RETURNS DATETIME  BEGIN
RETURN (SELECT MIN(action_date) FROM inventory.action_logs 
  WHERE action_type = 'checkout' 
    AND item_id = asset_id
    AND target_type = 'App\\Models\\User'
    AND action_date >= checked_out_at );
END$$

DROP FUNCTION IF EXISTS inventory.`is_on_loan`$$
CREATE FUNCTION inventory.`is_on_loan` (`asset_id` INT(11)) RETURNS TINYINT(1)  BEGIN
    IF EXISTS (SELECT 1 FROM inventory.assets  WHERE id = asset_id AND assigned_type = 'App\\Models\\User' AND NOT assigned_to IS NULL) THEN
        RETURN 1;
    ELSE
      RETURN 0;
    END IF;
END$$
