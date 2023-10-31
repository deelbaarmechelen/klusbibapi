<?php

namespace Api\Loan;

use Api\Inventory\Inventory;
use Api\Inventory\SnipeitInventory;
use Api\Mail\MailManager;
use Api\Model\Contact;
use Api\Model\Lending;
use Api\Model\Loan;
use Api\Model\LoanRow;
use Api\Model\Note;
use Api\Model\Reservation;
use Api\Model\ReservationState;
use Api\User\UserManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
//use Api\ModelMapper\UserMapper;
use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Log\LoggerInterface;
use DateTimeImmutable;

/**
 * Class LoanManager
 * Keeps Loan model in sync with inventory
 * @package Api\Loan
 */
class LoanManager
{
    public static function instance(LoggerInterface $logger, MailManager $mailManager) {
        return new LoanManager(SnipeitInventory::instance($logger), $logger, $mailManager);
    }
    private Inventory $inventory;
    private LoggerInterface $logger;
    private MailManager $mailManager;
    private $lastSyncAttempt;
    private $lastSyncedLoans;

    /**
     * LoanManager constructor.
     */
    public function __construct(Inventory $inventory, LoggerInterface $logger, MailManager $mailManager)
    {
        $this->inventory = $inventory;
        $this->logger = $logger;
        $this->mailManager = $mailManager;
        $this->lastSyncAttempt = null;
        $this->lastSyncedLoans = array();
    }

    /**
     * @param string $query query string
     * @param bool $isOpen when true, only return open lendings ??
     * @param string $sortfield sort result on this field
     * @param string $sortdir sort direction ('asc' or 'desc')
     */
    public function getAllLendings($query = null, $isOpen = false, $sortfield = 'id', $sortdir = 'asc') : \Illuminate\Support\Collection {
        $lendings = $this->getLoans($query, $isOpen, $sortfield, $sortdir);
        return $lendings->map($this->mapLoanItemToLending(...));
    }

    /**
     * @param string $query query string
     * @param bool $isOpen when true, only return open reservations
     * @param string $sortfield sort result on this field
     * @param string $sortdir sort direction ('asc' or 'desc')
     * @return reservations based on loan and loan_row tables
     */
    public function getAllReservations($query = null, $isOpen = false, $sortfield = 'id', $sortdir = 'asc') : \Illuminate\Support\Collection {
        if ($sortfield == "username") {
            $sortfield = 'contact.first_name';
        } else if ($sortfield == "reservation_id") {
            $sortfield = 'loan.id';
        } else if ($sortfield == "tool_id") {
            $sortfield = 'loan_row.inventory_item_id';
        } else if ($sortfield == "user_id") {
            $sortfield = 'loan.contact_id';
        } else if ($sortfield == "startsAt") {
            $sortfield = 'loan.datetime_out';
        } else if ($sortfield == "endsAt") {
            $sortfield = 'loan.datetime_in';
        } else if ($sortfield == "state") {
            $sortfield = 'loan.status';
        }
        $reservations = $this->getLoans($query, $isOpen, $sortfield, $sortdir);
        // callback notation: see https://www.php.net/manual/en/functions.first_class_callable_syntax.php
        return $reservations->map($this->mapLoanItemToReservation(...));
    }

    private function getLoans($query = null, $isOpen = false, $sortfield = 'id', $sortdir = 'asc') {
        $queryBuilder = Capsule::table('loan')
            ->join('contact', 'loan.contact_id', '=', 'contact.id')
            ->join('loan_row', 'loan.id', '=', 'loan_row.loan_id')
            //->leftJoin('note', 'loan.id', '=', 'note.loan_id')
            ->select('loan.*', 'loan_row.inventory_item_id', 'contact.first_name', 'contact.last_name')
            ->whereNull('loan_row.checked_out_at');
        if ($isOpen) {
            $queryBuilder = $queryBuilder->whereIn('loan.status', array(Loan::STATUS_PENDING, Loan::STATUS_RESERVED));
        }
        if (isset($query)) {
            $queryBuilder = $queryBuilder->where('contact.first_name', 'LIKE', '%'.$query.'%' )
                ->orWhere('contact.last_name', 'LIKE', '%'.$query.'%' );
        }
        $this->logger->info("SQL: " . $queryBuilder->toSql());
        return $queryBuilder->orderBy($sortfield, $sortdir)->get();
    }
    /**
     * Method to map a loan item (loan + loan_row + note) to a lending
     */
    private function mapLoanItemToLending($item) : Reservation {
        $lending = new Lending();
		$lending->lending_id = $item->id;
		$lending->start_date = $item->datetime_out;
		$lending->due_date = $item->datetime_in;
		$lending->returned_date = $item->checked_in_at;
		$lending->tool_id = $item->inventory_item_id;
		$lending->tool_type = "TOOL";
		$lending->user_id = $item->contact_id;
		// $lending->comments = $note->text;
		// $lending->active = $item->;
		// $lending->created_by = ;
		// $lending->created_at = ;
		// $lending->updated_at = ;
        /*
BEGIN
 IF EXISTS (SELECT 1 FROM inventory_item WHERE id = NEW.`tool_id`) 
 AND EXISTS (SELECT 1 FROM contact WHERE id = NEW.`user_id`) THEN
 
  INSERT INTO loan (
        id, contact_id, datetime_out, datetime_in, status, total_fee, reference, created_at)
  SELECT
  NEW.`lending_id`, NEW.`user_id`, ifnull(NEW.`start_date`, CURRENT_TIMESTAMP), ifnull(NEW.`due_date`, ifnull(NEW.`start_date`, CURRENT_TIMESTAMP)), 
  CASE WHEN (NEW.`returned_date` IS NULL) THEN 'ACTIVE' ELSE 'CLOSED' END,
  0, NULL, ifnull(NEW.`created_at`, CURRENT_TIMESTAMP);

  INSERT INTO loan_row (
        id, loan_id, inventory_item_id, product_quantity, due_out_at, due_in_at, checked_out_at, checked_in_at, fee, site_from, site_to)
  SELECT NEW.`lending_id`, NEW.`lending_id`, NEW.`tool_id`, 1, NEW.`start_date`, ifnull(NEW.`due_date`, ifnull(NEW.`start_date`, CURRENT_TIMESTAMP)), 
         NEW.`start_date`, NEW.`returned_date`, 0, 1, 1;

  IF NEW.`start_date` IS NOT NULL THEN
      INSERT INTO item_movement (
            inventory_item_id, inventory_location_id, loan_row_id, assigned_to_contact_id, created_at, quantity)
      SELECT NEW.`tool_id`, 1, NEW.`lending_id`, NEW.`user_id`, CURRENT_TIMESTAMP, 1;
  END IF;
  IF NOT NEW.`returned_date` IS NULL THEN
    INSERT INTO item_movement (
        inventory_item_id, inventory_location_id, loan_row_id, assigned_to_contact_id, created_at, quantity)
    SELECT NEW.`tool_id`, 2, null, null, CURRENT_TIMESTAMP, 1;
  END IF;
  IF NOT NEW.`comments` IS NULL THEN
    INSERT INTO note (contact_id, loan_id, inventory_item_id, text, admin_only, created_at)
    SELECT NEW.`user_id`, NEW.`lending_id`, NEW.`tool_id`, NEW.`comments`, 1, CURRENT_TIMESTAMP;
  END IF;  
 END IF;
 END        
        */
        return $lending;
    }
    /**
     * Method to map a loan item (loan + loan_row + note) to a reservation
     */
    private function mapLoanItemToReservation($item) : Reservation {
        $reservation = new Reservation();
        $reservation->reservation_id = $item->id;
        $reservation->type = 'reservation';
        $reservation->user_id = $item->contact_id;
        $reservation->tool_id = $item->inventory_item_id;
        $reservation->state = $this->convertLoanStatusToReservationState($item->status);
        $reservation->startsAt = $item->datetime_out;
        $reservation->endsAt = $item->datetime_in;
        $reservation->first_name = $item->first_name;
        $reservation->last_name = $item->last_name;

        return $reservation;
    }    
    public function getReservationById($id) : Reservation|null {
        // TODO: return reservation based on loan and loan_row tables
        $loan = Loan::isReservation()->find($id);
        if (!$loan) {
            return null; // no loan/reservation found
        }
        $loanRows = $loan->rows();
        if ($loanRows->count() == 0) {
            return null; // empty loan, no actual reservation
        }
        $reservation = new Reservation();
        $reservation->reservation_id = $loan->id;
        $reservation->type = 'reservation';
        $reservation->user_id = $loan->contact_id;
        $reservation->tool_id = $loanRows->first()->inventory_item_id;
        $reservation->state = $this->convertLoanStatusToReservationState($loan->status);
        $reservation->startsAt = $loan->datetime_out;
        $reservation->endsAt = $loan->datetime_in;
        if ($loan->notes()->count() > 0) {
            // TODO: concatenate all comments?
            $reservation->comment = $loan->notes()->first()->text;
        }
        $reservation->created_at = $loan->created_at;
        return $reservation;
        //return Reservation::find($id);
    }
    /**
     * @return bool true when reservation successfully created
     */
    public function createReservation(Reservation $reservation) {
        $this->logger->info("Creating loan");
        try {
            Capsule::transaction(function() use ($reservation) {
                 // create loan
                $loan = new Loan();
                $loan->contact_id = $reservation->user_id;
                $now = new DateTimeImmutable();
                $startsAt = isset($reservation->startsAt) ? $reservation->startsAt : $now;
                $endsAt = isset($reservation->endsAt) ? $reservation->endsAt : $startsAt;
                $loan->datetime_out = $startsAt;
                $loan->datetime_in = $endsAt; // last return date for all items in loan (loan row can have different due_in_at dates)
                $loan->status = $this->convertReservationStateToLoanStatus($reservation->state);
                $loan->total_fee = 0;
                // $loan->created_at_site = null;
                $loan->collect_from = 1;
                // $loan->reference = null;
                $loan->save();
                
                // create loan row
                $this->logger->info("Creating loan row");
                $loanRow = new LoanRow();
                $loanRow->inventory_item_id = $reservation->tool_id;
                $loanRow->product_quantity = 1;
                $loanRow->due_out_at = $startsAt;
                $loanRow->due_in_at = $endsAt;
                // $loanRow->checked_out_at = null; // null is default
                // $loanRow->checked_in_at = null; // null is default
                $loanRow->fee = 0; // no default -> force to 0
                $loanRow->site_from = 1;
                $loanRow->site_to = 1;
                $loan->rows()->save($loanRow);
                // create note
                if (isset($reservation->comment)) {
                    $this->logger->info("Creating note");
                    $loanRow->addNote($reservation->comment);
                }
                $reservation->reservation_id = $loan->id;
            });
        } catch (\Exception $ex) {
            $this->logger->error("Unable to create reservation: " . $ex->getMessage());
            return false;
        }
        return true;
    }

    public function updateReservation(Reservation $reservation, $newToolId, $newUserId, $newTitle, $newType, $newState, $newStartsAt, $newEndsAt, $newComment) {
        $loan = Loan::isReservation()->find($reservation->reservation_id);
        if ($loan == null) {
            $this->logger->warning("Reservation with id " . $reservation->reservation_id . " could not be found -> update skipped");
            return;
        }
        $loanRow = $loan->rows()->first();
        if ($newToolId != null && $loanRow != null) {
            $loanRow->inventory_item_id = $newToolId;
            $reservation->tool_id = $loanRow->inventory_item_id;
        }
        if ($newUserId != null) {
            $loan->contact_id = $newUserId;
            $reservation->user_id = $loan->contact_id;
        }
        if ($newTitle != null) { // ignored -> do nothing
            // $loan-> = $newTitle;
        }
        if ($newType != null) { // ignored -> do nothing
            // $loan-> = $newType;
        }
        if ($newState != null) {
            $loan->status = $this->convertReservationStateToLoanStatus($newState);
            $reservation->state = $newState;
        }
        if ($newStartsAt != null) {
            $loan->datetime_out = $newStartsAt;
            $loanRow->due_out_at = $newStartsAt;
            $reservation->startsAt = $newStartsAt;
        }
        if ($newEndsAt != null) {
            $loan->datetime_in = $newEndsAt;
            $loanRow->due_in_at = $newEndsAt;
            $reservation->endsAt = $newEndsAt;
        }
        if ($newComment != null) {
            $note = $loan->notes()->first();
            if ($note != null) {
                $note->text = $newComment;
                $note->save();
            } else {
                $loanRow->addNote($newComment);
            }
            $reservation->comment = $newComment;
        }
        $loanRow->save();
        $loan->save();
        return $reservation;
    }

    public function deleteReservation(int $reservationId) {
        $loan = Loan::isReservation()->find($reservationId);
        if ($loan == null) {
            // reservation already deleted, or converted to a lending -> nothing to do
            return;
        }
        $this->deleteLoan($loan);
    }
    public function deleteLoan($loan) {
        Capsule::transaction(function() use ($loan) {
            $loan->notes()->delete();
            $loan->rows()->delete();
            $loan->delete();
        });
    }

    private function convertReservationStateToLoanStatus($reservationState) : string {
        if ($reservationState == ReservationState::REQUESTED) {
            return Loan::STATUS_PENDING;
        } else if ($reservationState == ReservationState::CONFIRMED) {
            return Loan::STATUS_RESERVED;
        } else if ($reservationState == ReservationState::CANCELLED) {
            return Loan::STATUS_CANCELLED;
        } else if ($reservationState == ReservationState::CLOSED) {
            return Loan::STATUS_CLOSED;
        } else {
            return Loan::STATUS_PENDING;
        }
    }
    private function convertLoanStatusToReservationState($loanStatus) : string {
        if ($loanStatus == Loan::STATUS_PENDING) {
            return ReservationState::REQUESTED;
        } else if ($loanStatus == Loan::STATUS_RESERVED) {
            return ReservationState::CONFIRMED;
        } else if ($loanStatus == Loan::STATUS_CANCELLED) {
            return ReservationState::CANCELLED;
        } else if ($loanStatus == Loan::STATUS_CLOSED) {
            return ReservationState::CLOSED;
        } else { // should not be possible -> throw an exception?
            return ReservationState::REQUESTED;
        }
    }

    public function sync() {
        $syncTime = new \DateTime();
        // Get last synced action id from kb_sync
        $syncData = Capsule::table('kb_sync')->first();
        $lastActionId = isset($syncData->last_inventory_action_id) ? $syncData->last_inventory_action_id : 0;
        $lastActionTimestamp = isset($syncData->last_inventory_action_timestamp) 
          ? Carbon::createFromFormat('Y-m-d H:i:s',  $syncData->last_inventory_action_timestamp) : null;
        
        // TODO: also update LE payments from API kb_payments
        // All action logs are replayed chronologically, syncing all types of activity at once
        echo "Syncing loans from inventory activity starting from $syncData->last_inventory_action_timestamp\n";
        //->format('Y-m-d H:i:s')
        if (isset($lastActionTimestamp)) {
          echo "Deleting all lendings after sync date " . $lastActionTimestamp->toDateTimeString() . "\n";
          Lending::whereDate('last_sync_date', '>', $lastActionTimestamp->toDateTimeString())->delete();
        } else {
            echo "Deleting all lendings\n";
            Lending::query()->delete();  
        }

        $limit = 100;
        // dummy call to get actions count
        try {
            $activityBody = $this->inventory->getActivity(0, 2);
        } catch (\Api\Exception\NotFoundException $ex) {
          echo "No activity (or insufficient rights to access it)\n";
          return;
        }
        $actionsCount = $activityBody->total;
        $offset = $actionsCount > $limit ? $actionsCount - $limit : 0;
        while ($offset >= 0) {
            $activityBody = $this->inventory->getActivity($offset, $limit);
            $newActionsCount = $activityBody->total;
            // TO TEST: make sure no actions are skipped when extra actions are added during sync
            if ($newActionsCount > $actionsCount) {
                // new activity entries have been added since previous query
                $extraActions = $newActionsCount - $actionsCount;
                $offset += $extraActions;
                $actionsCount = $newActionsCount;
                if ($extraActions > 0) {
                    // we need to repeat the query with a different offset to avoid missing entries
                    continue;
                }
            }

            $actions = $activityBody->rows;
            if (is_array($actions)) {
                // Action logs are retrieved in descending chronological order -> order needs to be reversed
                $actions = array_reverse($actions);
                foreach($actions as $item) {
                    
                    if ($this->processActionLogItem($item, $lastActionTimestamp, $lastActionId)) {
                        $lastActionId = $item->id;
                        $createdAtString = isset($item->created_at) ? $item->created_at->datetime : null;
                        $lastActionTimestamp = \DateTime::createFromFormat("Y-m-d H:i:s", $createdAtString);
                        $affected = Capsule::table('kb_sync')
                        ->update([
                            'last_inventory_action_id' => $lastActionId,
                            'last_inventory_action_timestamp' => $lastActionTimestamp
                        ]);
                    }
                }
            }
            if ($offset > 0 && $offset < $limit) {
                // last activity lookup: use offset 0 to retrieve most recent activity
                $offset = 0;
            } else {
                $offset = $offset - $limit;
            }
            usleep(500 * 1000);
        }

    }

    /**
     * @return true when log item is successfully processed
     */
    private function processActionLogItem($item, ?\DateTime $lastActionTimestamp , $lastActionId) : bool {

        $createdAtString = isset($item->created_at) ? $item->created_at->datetime : null;
        $createdAt = Carbon::createFromFormat("Y-m-d H:i:s", $createdAtString);
        // Filter already synced actions
        // Note multiple actions with same timestamp are possible (e.g. in case of bulk update of expected checkin), 
        // thus processing same action twice should not lead to errors
        // how to handle multiple log items with same creation timestamp? 
        if ($createdAt && isset($lastActionTimestamp) 
            && ($createdAt < $lastActionTimestamp || $item->id == $lastActionId)) {
            // already processed
            $carbonLastActionTime = Carbon::instance($lastActionTimestamp);
            echo "skipping action $item->id : already processed (last action on " . $carbonLastActionTime->toDateTimeString() 
              . ", id = $lastActionId)\n";
            return false;
        }

        $itemAction = $item->action_type; // checkout, update or 'checkin from'
        if ($itemAction !== "checkout" && $itemAction !== "update" && $itemAction !== "checkin from") {
            // nothing to do, consider action item as successfully processed
            return true;
        }
        echo "Syncing $itemAction action with id $item->id\n";
        //echo \json_encode($item) . "\n";
        // TODO: check if loan or lending should be used. As a start, use lending and update loan through triggers

        $inventoryUserId = isset($item->target) ? $item->target->id : null;
        $contact = Contact::where(['user_ext_id' => $inventoryUserId])->first();
        if (isset($item->target) && !isset($contact)) {
            echo "$itemAction action ($item->id) for unkown user (user inventory id " . $inventoryUserId . " deleted?) -> ignored\n";
            return false;
        }
        $inventoryItemId = isset($item->item) ? $item->item->id : null; // available in lending, but not on loan (only on loan row)!
        // FIXME: itemActionDateTime not yet supported in our version of snipe it?
        //$itemActionDatetime = isset($item->action_date) ? $item->action_date->datetime : null;
        $itemActionDatetime = $createdAt;
        if (! ($itemActionDatetime && isset($inventoryItemId)) ) {
            echo "invalid $itemAction action, missing item action date (or created_at) and/or inventory item id -> ignored\n";
            return false;
        }

        // At this point, we have a valid action with action datetime, target user (checkout and checkin only) and inventory item id

        // find loan (=lending)
        // Note: Same user can lend a tool multiple times
        // for checkout -> include start_date
        // for checkin & update -> only select from active lendings
        if ($itemAction === "checkout") {
            $lending = Lending::where(['start_date' => $createdAt, 'tool_id' => $inventoryItemId, 'user_id' => $contact->id])->first();
        } else if ($itemAction === "checkin from") {
            $lending = Lending::active()->where(['tool_id' => $inventoryItemId, 'user_id' => $contact->id])->first();
        } else { // target id (and thus contact id) not known on update
            $lending = Lending::active()->where(['tool_id' => $inventoryItemId])->first();
        }
        //$loanRow = LoanRow::where(['checked_out_at' => $createdAt, 'inventory_item_id' => $inventoryItemId])->first();
        ////$loan = Loan::where(['created_at' => $createdAt, 'contact_id' => $contact->id])->first();
        //$existingItem = isset($loanRow) ? Loan::find($loanRow->loan_id) : null;

        // For a checkout: if record exists, it is already synced
        if ($lending === null) {
            if ($itemAction === "checkout") {
                // Check if no active lending exists
                if (Lending::active()->where(['tool_id' => $inventoryItemId])->exists()) {
                    // FIXME: this check needs all action logs to be processed in chronological order (regardless of action type)!
                    echo "Cannot create lending for checkout: an active lending already exists for tool $inventoryItemId\n";
                    return false;
                }
                echo "creating new loan for checkout action id $item->id (checkout date: $createdAtString)\n";
                $lending = new Lending();
                $lending->user_id = isset($contact) ? $contact->id : null;
                $lending->tool_id = $inventoryItemId;
                $lending->start_date = $itemActionDatetime;
                //$lending->due_date = $createdAt + 7 days?? Does not seem included in api response, but stored in inventory.asset.expected_checkin
                $dueDate = clone $itemActionDatetime;
                $dueDate->add(new \DateInterval('P7D'));
                $lending->due_date = $dueDate;
                $lending->last_sync_date = $createdAt;
                $lending->save();
                return true;
            } else {
                echo "lending not found or already closed\n";
                return false;
            }
        } else {
            // Note: no need for a classic last sync timestamp check here, as action_log is inserted, but never updated afterwards
            //       but we do need to keep track of last action_log processed, and last_sync_timestamp is used here to keep last action_log created_at_ timestamp
            // For update and checkin: compare update timestamp with item action datetime. Only apply action log, if action datetime is more recent than last update
            // FIXME: should use special function to compare dates?
            if ($itemActionDatetime
                && $lending->last_sync_date < $itemActionDatetime
                && ($itemAction === "update" || $itemAction === "checkin from") ) {
                // update item values if needed

                if ($itemAction === "update") {
                    // expected checkin date is received through log_meta as json e.g. {"expected_checkin":{"old":"2021-10-24","new":"2021-10-27 00:00:00"}}
                    $logMeta = $item->log_meta;
                    if (isset($logMeta) && isset($logMeta->expected_checkin) && isset($logMeta->expected_checkin->new)) {
                        echo "updating due date of lending $lending->lending_id from " . $logMeta->expected_checkin->old . 
                        " to " . $logMeta->expected_checkin->new . "\n";
                        $expectedCheckin = \DateTime::createFromFormat("Y-m-d H:i:s", $logMeta->expected_checkin->new);
                        $lending->due_date = $expectedCheckin;
                        $lending->last_sync_date = $createdAt;
                        $lending->save();
                        return true;
                    } else {
                        echo "Not an update of expected checkin -> update action $item->id ignored\n";
                    }
                }
                if ($itemAction === "checkin from") {
                    echo "processing checkin for lending $lending->lending_id from action id $item->id (checkin date: $createdAtString)\n";
                    // FIXME: where to get actual returned date?
                    // actual returned date doesn't seem to be stored in snipe it repo (bug?) Only way to get it, is to process a notification at checkin event
                    $lending->returned_date = $createdAt;
                    $lending->last_sync_date = $createdAt;
                    $lending->save();
                    return true;
                }
            }

        }
        return false;
    }

}
