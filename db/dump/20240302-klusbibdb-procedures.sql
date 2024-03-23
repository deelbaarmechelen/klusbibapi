--
-- Database: `klusbibdb`
--

--
-- Procedures
--
DROP PROCEDURE IF EXISTS klusbibdb.`kb_checkin`$$
CREATE PROCEDURE klusbibdb.`kb_checkin` (IN `item_id` INT, IN `checkin_datetime` DATETIME, IN `comment` VARCHAR(255))   BEGIN 
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

DROP PROCEDURE IF EXISTS klusbibdb.`kb_checkout`$$
CREATE PROCEDURE klusbibdb.`kb_checkout` (IN `inventory_item_id` INT, IN `loan_contact_id` INT, IN `datetime_out` DATETIME, IN `datetime_in` DATETIME, IN `comment` VARCHAR(255))   BEGIN 
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

DROP PROCEDURE IF EXISTS klusbibdb.`kb_extend`$$
CREATE PROCEDURE klusbibdb.`kb_extend` (IN `item_id` INT, IN `expected_checkin_datetime` DATETIME)   BEGIN 
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

DROP PROCEDURE IF EXISTS klusbibdb.`kb_log_msg`$$
CREATE PROCEDURE klusbibdb.`kb_log_msg` (`msg` TEXT)   BEGIN
    insert into kb_log (log_msg) select msg;
END$$

DROP PROCEDURE IF EXISTS klusbibdb.`kb_sync_assets_2le`$$
CREATE PROCEDURE klusbibdb.`kb_sync_assets_2le` ()   BEGIN 
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

--
-- Functies
--
DROP FUNCTION IF EXISTS klusbibdb.`disable_sync_inventory2le`$$
CREATE FUNCTION klusbibdb.`disable_sync_inventory2le` () RETURNS TINYINT(1)  BEGIN
    IF @sync_inventory2le = 1 THEN
        SET @sync_inventory2le = NULL;
        RETURN 1;
    END IF;
    RETURN 0;
END$$

DROP FUNCTION IF EXISTS klusbibdb.`disable_sync_le2inventory`$$
CREATE FUNCTION klusbibdb.`disable_sync_le2inventory` () RETURNS TINYINT(1)  BEGIN
    IF @sync_le2inventory = 1 THEN
        SET @sync_le2inventory = NULL;
        RETURN 1;
    END IF;
    RETURN 0;
END$$

DROP FUNCTION IF EXISTS klusbibdb.`enable_sync_inventory2le`$$
CREATE FUNCTION klusbibdb.`enable_sync_inventory2le` () RETURNS TINYINT(1)  BEGIN
    IF @sync_le2inventory IS NULL THEN
        SET @sync_inventory2le = 1;
        RETURN 1;
    END IF;
    RETURN 0;
END$$

DROP FUNCTION IF EXISTS klusbibdb.`enable_sync_le2inventory`$$
CREATE FUNCTION klusbibdb.`enable_sync_le2inventory` () RETURNS TINYINT(1)  BEGIN
    IF @sync_inventory2le IS NULL THEN
        SET @sync_le2inventory = 1;
        RETURN 1;
    END IF;
    RETURN 0;
END$$

DROP FUNCTION IF EXISTS klusbibdb.`is_on_loan`$$
CREATE FUNCTION klusbibdb.`is_on_loan` (`item_id` INT(11)) RETURNS TINYINT(1)  BEGIN
    IF EXISTS (SELECT 1 FROM klusbibdb.loan_row LEFT JOIN loan ON loan_row.loan_id = loan.id WHERE inventory_item_id = item_id AND loan.status IN ('ACTIVE', 'OVERDUE')) THEN
        RETURN 1;
    ELSE
      RETURN 0;
    END IF;
END$$

DROP FUNCTION IF EXISTS klusbibdb.`is_sync_inventory2le_enabled`$$
CREATE FUNCTION klusbibdb.`is_sync_inventory2le_enabled` () RETURNS TINYINT(1)  BEGIN
    IF @sync_inventory2le = 1 THEN
        RETURN 1;
    ELSE
      RETURN 0;
    END IF;
END$$

DROP FUNCTION IF EXISTS klusbibdb.`is_sync_le2inventory_enabled`$$
CREATE FUNCTION klusbibdb.`is_sync_le2inventory_enabled` () RETURNS TINYINT(1)  BEGIN
    IF @sync_le2inventory = 1 THEN
        RETURN 1;
    ELSE
      RETURN 0;
    END IF;
END$$
