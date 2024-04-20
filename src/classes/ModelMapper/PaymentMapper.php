<?php
namespace Api\ModelMapper;

class PaymentMapper
{
	static public function mapPaymentToArray($payment) {
	
		$paymentArray = [
      "payment_id" => $payment->id,
      "contact_id" => $payment->contact_id,
      "state" => $payment->kb_state,
      "mode" => $payment->kb_mode,
      "payment_date" => $payment->payment_date ? $payment->payment_date->format('Y-m-d') : null,
      "order_id" => $payment->psp_code,
      "amount" => $payment->amount,
      //"currency" => $payment->currency,
      "note" => $payment->note,
      //"payment_ext_id" => $payment->payment_ext_id,
      "expiration_date" => $payment->kb_expiration_date ? $payment->kb_expiration_date->format('Y-m-d') : null,
      "membership_id" => $payment->membership_id,
      "loan_id" => $payment->loan_id,
      "created_at" => $payment->created_at,
  ];
		return $paymentArray;
	}
	static public function mapArrayToPayment($data, $payment, $isAdmin = false, $logger = null) {
		if (isset($data["id"]) && $isAdmin) {
            $payment->id= $data["id"];
		}
		if (isset($data["contact_id"]) && $isAdmin) {
            $payment->contact_id = $data["contact_id"];
		}
		if (isset($data["state"]) && $isAdmin) {
            $payment->kb_state = $data["state"];
		}
        if (isset($data["mode"]) && $isAdmin) {
            $payment->kb_mode = $data["mode"];
        }
        if (isset($data["payment_date"]) && $isAdmin) {
            $payment->payment_date = $data["payment_date"];
        }
        if (isset($data["order_id"]) && $isAdmin) {
            $payment->psp_code = $data["order_id"];
        }
        if (isset($data["amount"]) && $isAdmin) {
            $payment->amount = $data["amount"];
        }
        // if (isset($data["currency"]) && $isAdmin) {
        //     $payment->currency = $data["currency"];
        // }
        if (isset($data["note"]) && $isAdmin) {
            $payment->note = $data["note"];
        }
        // if (isset($data["payment_ext_id"]) && $isAdmin) {
        //     $payment->payment_ext_id = $data["payment_ext_id"];
        // }
        if (isset($data["expiration_date"]) && $isAdmin) {
            $payment->kb_expiration_date = $data["expiration_date"];
        }
        if (isset($data["membership_id"]) && $isAdmin) {
            $payment->membership_id = $data["membership_id"];
        }
        if (isset($data["loan_id"]) && $isAdmin) {
            $payment->loan_id = $data["loan_id"];
        }
	}
}