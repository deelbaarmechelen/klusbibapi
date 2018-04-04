<?php

use PHPUnit\Framework\TestCase;
use Api\Model\Reservation;
use Api\ModelMapper\ReservationMapper;

require_once __DIR__ . '/../../test_env.php';


final class ReservationMapperTest extends TestCase
{
    public function testMapEmptyReservation()
    {
        $reservation = new Reservation();
        $reservationArray = ReservationMapper::mapReservationToArray($reservation);
        $this->assertTrue(is_array($reservationArray));
    }
}