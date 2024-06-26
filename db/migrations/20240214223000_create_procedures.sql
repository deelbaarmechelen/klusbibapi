-- uses DELIMITER $$
DROP TABLE IF EXISTS klusbibdb.kb_log$$
CREATE TABLE  klusbibdb.kb_log (
    id int(11) NOT NULL auto_increment,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    log_msg text,
    PRIMARY KEY  (id)
) ENGINE=MYISAM$$

DROP PROCEDURE IF EXISTS klusbibdb.`kb_log_msg`$$
CREATE PROCEDURE `kb_log_msg`(msg TEXT)
BEGIN
    insert into kb_log (log_msg) select msg;
END$$

-- enables (flag to) sync from lend engine -> inventory
-- flag is not enabled if sync from inventory -> lend engine is ongoing
-- returns true (1) upon success, or false (0) upon failure
DROP FUNCTION IF EXISTS klusbibdb.enable_sync_le2inventory$$
CREATE FUNCTION klusbibdb.enable_sync_le2inventory ()
RETURNS TINYINT(1)
BEGIN
    IF @sync_inventory2le IS NULL THEN
        SET @sync_le2inventory = 1;
        RETURN 1;
    END IF;
    RETURN 0;
END$$
-- disable (flag to) sync from lend engine -> inventory
-- returns true (1) if flag was enabled and has been disabled
-- returns false (0) if flag was already disabled
DROP FUNCTION IF EXISTS klusbibdb.disable_sync_le2inventory$$
CREATE FUNCTION klusbibdb.disable_sync_le2inventory ()
RETURNS TINYINT(1)
BEGIN
    IF @sync_le2inventory = 1 THEN
        SET @sync_le2inventory = NULL;
        RETURN 1;
    END IF;
    RETURN 0;
END$$

-- enables (flag to) sync from inventory -> lend engine
-- flag is not enabled if sync from lend engine -> inventory is ongoing
-- returns true (1) upon success, or false (0) upon failure
DROP FUNCTION IF EXISTS klusbibdb.enable_sync_inventory2le$$
CREATE FUNCTION klusbibdb.enable_sync_inventory2le ()
RETURNS TINYINT(1)
BEGIN
    IF @sync_le2inventory IS NULL THEN
        SET @sync_inventory2le = 1;
        RETURN 1;
    END IF;
    RETURN 0;
END$$
-- disable (flag to) sync from inventory -> lend engine
-- returns true (1) if flag was enabled and has been disabled
-- returns false (0) if flag was already disabled
DROP FUNCTION IF EXISTS klusbibdb.disable_sync_inventory2le$$
CREATE FUNCTION klusbibdb.disable_sync_inventory2le ()
RETURNS TINYINT(1)
BEGIN
    IF @sync_inventory2le = 1 THEN
        SET @sync_inventory2le = NULL;
        RETURN 1;
    END IF;
    RETURN 0;
END$$

-- returns true if (flag to) sync from lend engine -> inventory is enabled
DROP FUNCTION IF EXISTS klusbibdb.is_sync_le2inventory_enabled$$
CREATE FUNCTION klusbibdb.is_sync_le2inventory_enabled ()
RETURNS TINYINT(1)
BEGIN
    IF @sync_le2inventory = 1 THEN
        RETURN 1;
    ELSE
      RETURN 0;
    END IF;
END$$

-- returns true if (flag to) sync from inventory -> lend engine is enabled
DROP FUNCTION IF EXISTS klusbibdb.is_sync_inventory2le_enabled$$
CREATE FUNCTION klusbibdb.is_sync_inventory2le_enabled ()
RETURNS TINYINT(1)
BEGIN
    IF @sync_inventory2le = 1 THEN
        RETURN 1;
    ELSE
      RETURN 0;
    END IF;
END$$

-- returns true if inventory item is on loan
DROP FUNCTION IF EXISTS klusbibdb.is_on_loan$$
CREATE FUNCTION klusbibdb.is_on_loan (item_id INT(11))
RETURNS TINYINT(1)
BEGIN
    IF EXISTS (SELECT 1 FROM klusbibdb.loan_row LEFT JOIN loan ON loan_row.loan_id = loan.id WHERE inventory_item_id = item_id AND loan.status IN ('ACTIVE', 'OVERDUE')) THEN
        RETURN 1;
    ELSE
      RETURN 0;
    END IF;
END$$

-- returns true if (inventory) asset is on loan
DROP FUNCTION IF EXISTS inventory.is_on_loan$$
CREATE FUNCTION inventory.is_on_loan (asset_id INT(11))
RETURNS TINYINT(1)
BEGIN
    IF EXISTS (SELECT 1 FROM inventory.assets  WHERE id = asset_id AND assigned_type = 'App\\Models\\User' AND NOT assigned_to IS NULL) THEN
        RETURN 1;
    ELSE
      RETURN 0;
    END IF;
END$$

-- returns first checkout date for given asset id and start search date, or null if not checked out yet
DROP FUNCTION IF EXISTS inventory.get_checkout_date$$
CREATE FUNCTION inventory.get_checkout_date(`asset_id` INT, `checked_out_at` DATETIME) 
RETURNS datetime
BEGIN
RETURN (SELECT MIN(action_date) FROM inventory.action_logs 
  WHERE action_type = 'checkout' 
    AND item_id = asset_id
    AND target_type = 'App\\Models\\User'
    AND action_date >= checked_out_at );
END$$

-- returns checkin date for given asset id and checkout date, or null if not checked in yet, or loan not found
DROP FUNCTION IF EXISTS inventory.get_checkin_date$$
CREATE FUNCTION inventory.get_checkin_date(`asset_id` INT, `checked_out_at` DATETIME) 
RETURNS datetime
BEGIN
IF NOT EXISTS (SELECT 1 FROM inventory.action_logs WHERE action_type = 'checkout' AND item_id = asset_id AND action_date = checked_out_at) THEN
    RETURN NULL;
END IF;

RETURN (SELECT MIN(action_date) FROM inventory.action_logs 
  WHERE action_type = 'checkin from' 
    AND item_id = asset_id
    AND target_type = 'App\\Models\\User'
    AND action_date > checked_out_at );
END$$

DROP PROCEDURE IF EXISTS klusbibdb.`kb_checkout`$$
CREATE PROCEDURE klusbibdb.`kb_checkout` 
        (IN inventory_item_id INT, IN loan_contact_id INT, IN datetime_out DATETIME, IN datetime_in DATETIME, IN `comment` VARCHAR(255) ) 
BEGIN 
DECLARE new_loan_id INT DEFAULT 0;
DECLARE new_loan_row_id INT DEFAULT 0;
-- Set location to 1 = 'On loan'
DECLARE location_id INT DEFAULT 1;
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    GET DIAGNOSTICS CONDITION 1
        @SQLState = RETURNED_SQLSTATE, @SQLMessage = MESSAGE_TEXT;
    call kb_log_msg(concat('Error in klusbibdb.kb_checkout: sqlstate - ', @SQLState, '; error msg - ', @SQLMessage));
    RESIGNAL;
END;
IF EXISTS (SELECT 1 FROM inventory_item WHERE id = inventory_item_id) 
AND EXISTS (SELECT 1 FROM contact WHERE id = loan_contact_id) THEN
        
    -- Set location to 1 = 'On loan'
    UPDATE inventory_item 
    SET current_location_id = location_id
    WHERE id = inventory_item_id;

    INSERT INTO loan (
        contact_id, datetime_out, datetime_in, status, total_fee, created_at)
    SELECT
    loan_contact_id, ifnull(datetime_out, CURRENT_TIMESTAMP), ifnull(datetime_in, CURRENT_TIMESTAMP), 'ACTIVE', 0, 
    CASE WHEN DATE(datetime_out) < DATE(CURRENT_TIMESTAMP) THEN datetime_out ELSE CURRENT_TIMESTAMP END;
    SET new_loan_id = LAST_INSERT_ID();
    
    INSERT INTO loan_row (
        loan_id, inventory_item_id, product_quantity, due_out_at, due_in_at, checked_out_at, checked_in_at, fee, site_from, site_to)
        SELECT new_loan_id, inventory_item_id, 1, datetime_out, ifnull(datetime_in, ifnull(datetime_out, CURRENT_TIMESTAMP)), 
        datetime_out, null, 0, 1, 1;
    SET new_loan_row_id = LAST_INSERT_ID();

    INSERT INTO item_movement(
        inventory_item_id, inventory_location_id, loan_row_id, assigned_to_contact_id, created_at, quantity)
        SELECT inventory_item_id, location_id, new_loan_row_id, loan_contact_id, CURRENT_TIMESTAMP, 1;

    IF NOT comment IS NULL THEN
        INSERT INTO note (contact_id, loan_id, inventory_item_id, `text`, admin_only, created_at)
        SELECT loan_contact_id, new_loan_id, inventory_item_id, comment, 1, CURRENT_TIMESTAMP;
    END IF;  
ELSE
    call kb_log_msg(concat('Warning: inventory_item or contact missing in kb_checkout - loan creation skipped for inventory item with id: ', inventory_item_id));
END IF;
END$$

DROP PROCEDURE IF EXISTS klusbibdb.`kb_checkin`$$
CREATE PROCEDURE klusbibdb.`kb_checkin` 
            (IN item_id INT, IN checkin_datetime DATETIME, IN `comment` VARCHAR(255) ) 
BEGIN 
DECLARE existing_loan_id INT DEFAULT 0;
DECLARE loan_contact_id INT DEFAULT 0;
-- Set location to 2 = 'In stock'
DECLARE location_id INT DEFAULT 2;
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    GET DIAGNOSTICS CONDITION 1
        @SQLState = RETURNED_SQLSTATE, @SQLMessage = MESSAGE_TEXT;
    call kb_log_msg(concat('Error in klusbibdb.kb_checkin: sqlstate - ', @SQLState, '; error msg - ', @SQLMessage));
    RESIGNAL;
END;
IF EXISTS (SELECT 1 FROM inventory_item WHERE id = item_id) 
    AND EXISTS (SELECT 1 FROM loan_row LEFT JOIN loan ON loan.id = loan_row.loan_id WHERE inventory_item_id = item_id AND loan.status IN ('ACTIVE', 'OVERDUE')) THEN
    
    SET existing_loan_id := (SELECT MAX(loan_id) FROM loan_row LEFT JOIN loan ON loan.id = loan_row.loan_id WHERE inventory_item_id = item_id AND loan.status IN ('ACTIVE', 'OVERDUE'));
    SET loan_contact_id := (SELECT contact_id FROM loan WHERE id = existing_loan_id);


    UPDATE inventory_item 
    SET current_location_id = location_id
    WHERE id = item_id;

    INSERT INTO item_movement(
        inventory_item_id, inventory_location_id, loan_row_id, assigned_to_contact_id, created_at, quantity)
        SELECT item_id, location_id, NULL, NULL, CURRENT_TIMESTAMP, NULL;
        
    IF EXISTS (SELECT 1 FROM loan_row WHERE inventory_item_id = item_id AND loan_id = existing_loan_id) THEN
    
        UPDATE loan_row SET checked_in_at = checkin_datetime
        WHERE loan_id = existing_loan_id AND inventory_item_id = item_id;

        IF NOT EXISTS (SELECT 1 FROM loan_row WHERE loan_id = existing_loan_id AND checked_in_at IS NULL) THEN
            -- all items have been checked in
            UPDATE loan SET status = 'CLOSED', datetime_in = checkin_datetime 
            WHERE id = existing_loan_id;
        END IF;

    END IF;

    IF NOT comment IS NULL THEN
        INSERT INTO note (contact_id, loan_id, inventory_item_id, `text`, admin_only, created_at)
        SELECT loan_contact_id, existing_loan_id, item_id, comment, 1, CURRENT_TIMESTAMP;
    END IF;  
ELSE
    call kb_log_msg(concat('Warning: inventory_item or loan missing in kb_checkin - loan_row update skipped for inventory item with id: ', item_id));
END IF;
END$$

DROP PROCEDURE IF EXISTS klusbibdb.`kb_extend`$$
CREATE PROCEDURE klusbibdb.`kb_extend` 
            (IN item_id INT, IN expected_checkin_datetime DATETIME) 
BEGIN 
DECLARE existing_loan_id INT DEFAULT 0;
DECLARE new_loan_datetime_in DATETIME;
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    GET DIAGNOSTICS CONDITION 1
        @SQLState = RETURNED_SQLSTATE, @SQLMessage = MESSAGE_TEXT;
    call kb_log_msg(concat('Error in klusbibdb.kb_extend: sqlstate - ', @SQLState, '; error msg - ', @SQLMessage));
    RESIGNAL;
END;
IF EXISTS (SELECT 1 FROM inventory_item WHERE id = item_id) 
    AND EXISTS (SELECT 1 FROM loan_row LEFT JOIN loan ON loan.id = loan_row.loan_id WHERE inventory_item_id = item_id AND (loan.status = 'ACTIVE' OR loan.status = 'OVERDUE')) THEN
    
    SELECT MAX(loan_id) INTO existing_loan_id FROM loan_row LEFT JOIN loan ON loan.id = loan_row.loan_id 
    WHERE inventory_item_id = item_id AND (loan.status = 'ACTIVE' OR loan.status = 'OVERDUE');

    UPDATE loan_row SET due_in_at = expected_checkin_datetime
    WHERE loan_id = existing_loan_id AND inventory_item_id = item_id;
    SELECT MAX(due_in_at) INTO new_loan_datetime_in FROM loan_row 
    WHERE loan_id = existing_loan_id;
    UPDATE loan 
    SET datetime_in = new_loan_datetime_in,
        status = (CASE WHEN DATE(new_loan_datetime_in) >= CURRENT_DATE THEN 'ACTIVE' ELSE 'OVERDUE' END)
    WHERE id = existing_loan_id;

ELSE
    call kb_log_msg(concat('Warning: inventory_item or loan missing in kb_extend - loan_row update skipped for inventory item with id: ', item_id));
END IF;
END$$

DROP PROCEDURE IF EXISTS inventory.`kb_checkout`$$
CREATE PROCEDURE inventory.`kb_checkout` 
        (IN inventory_item_id INT, IN loan_contact_id INT, IN datetime_out DATETIME, IN datetime_in DATETIME, IN `comment` VARCHAR(255) ) 
BEGIN 
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

DROP PROCEDURE IF EXISTS inventory.`kb_checkin`$$
CREATE PROCEDURE inventory.`kb_checkin` 
            (IN item_id INT, IN checkout_datetime DATETIME, IN checkin_datetime DATETIME, IN `comment` VARCHAR(255) ) 
BEGIN 
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

DROP PROCEDURE IF EXISTS inventory.`kb_extend`$$
CREATE PROCEDURE inventory.`kb_extend` 
            (IN item_id INT, IN checkout_datetime DATETIME, IN old_checkin_datetime DATETIME, IN new_checkin_datetime DATETIME, IN `comment` VARCHAR(255) ) 
BEGIN 
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
CREATE PROCEDURE inventory.`kb_register_loan_no_sync` 
        (IN inventory_item_id INT, IN loan_contact_id INT, IN datetime_out DATETIME, IN datetime_in DATETIME, IN `comment` VARCHAR(255) ) 
BEGIN 
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


DROP PROCEDURE IF EXISTS klusbibdb.`kb_sync_assets_2le`$$
CREATE PROCEDURE klusbibdb.`kb_sync_assets_2le` () 
BEGIN 
DECLARE disable_sync_result TINYINT(1);
DECLARE new_loan_id INT DEFAULT 0;
DECLARE EXIT HANDLER FOR SQLEXCEPTION
BEGIN
    IF klusbibdb.is_sync_inventory2le_enabled() THEN
        SELECT klusbibdb.disable_sync_inventory2le() INTO disable_sync_result;
    END IF;
    GET DIAGNOSTICS CONDITION 1
        @SQLState = RETURNED_SQLSTATE, @SQLMessage = MESSAGE_TEXT;
    call kb_log_msg(concat('Error in klusbibdb.kb_sync_assets_2le: sqlstate - ', @SQLState, '; error msg - ', @SQLMessage));
    RESIGNAL;
END;

IF klusbibdb.enable_sync_inventory2le() THEN
  -- remove deleted assets
  DELETE FROM klusbibdb.kb_sync_assets WHERE id IN (SELECT id FROM inventory.assets WHERE (NOT inventory.assets.deleted_at is null) OR inventory.assets.status_id = 3);

  -- insert missing assets
  INSERT INTO klusbibdb.kb_sync_assets 
  (id, name, asset_tag, model_id, serial, image, status_id, assigned_to, kb_assigned_to,
   assigned_type, last_checkout, last_checkin, expected_checkin, created_at, updated_at, deleted_at)
  SELECT inventory.assets.id, inventory.assets.name, asset_tag, model_id, inventory.assets.serial, inventory.assets.image, status_id, assigned_to, employee_num,
   assigned_type, last_checkout, last_checkin, expected_checkin, inventory.assets.created_at, inventory.assets.updated_at, inventory.assets.deleted_at 
  FROM inventory.assets LEFT JOIN inventory.users ON inventory.assets.assigned_to = inventory.users.id
  WHERE inventory.assets.deleted_at is null AND NOT (inventory.assets.status_id = 3) AND inventory.assets.id NOT IN (SELECT id FROM klusbibdb.kb_sync_assets);

  -- simply update all rows to sync (enables update trigger on each row)
  UPDATE klusbibdb.kb_sync_assets
    SET last_sync_timestamp = CURRENT_TIMESTAMP;

  -- TODO: also update loan status (from OVERDUE to ACTIVE when applicable)?
  SELECT klusbibdb.disable_sync_inventory2le() INTO disable_sync_result;
END IF;

END$$

