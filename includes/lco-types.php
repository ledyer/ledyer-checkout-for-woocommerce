<?php

\defined( 'ABSPATH' ) || die();

abstract class LedyerPaymentStatus {
	const registered 		= "registered";
	const pendingOrder      = "pendingOrder";
	const pendingPayment    = "pendingPayment";
	const paid       		= "paid";
	const captured   		= "captured";
	const refunded   		= "refunded";
	const cancelled  		= "cancelled";
	const unknown    		= "unknown";
}

abstract class LedyerStatus {
    const partiallyCaptured = "partiallyCaptured";
	const fullyCaptured     = "fullyCaptured";
	const partiallyRefunded = "partiallyRefunded";
	const fullyRefunded     = "fullyRefunded";
	const uncaptured        = "uncaptured";
	const cancelled         = "cancelled";
	const unacknowledged    = "unacknowledged";
}

abstract class LedyerEventType {
    const authorize       = "authorize";
	const fullCapture     = "fullCapture";
	const partialCapture  = "partialCapture";
	const edit            = "edit";
	const cancel          = "cancel";
	const fullRefund      = "fullRefund";
	const partialRefund   = "partialRefund";
	const extendExpiry    = "extendExpiry";
	const create          = "create";
	const readyForCapture = "readyForCapture";
	const setReference    = "setReference";
	const acknowledge     = "acknowledge";
}

abstract class LedyerNotificationEventType {
    const create = "com.ledyer.order.create";
    const readyForCapture = "com.ledyer.order.ready_for_capture";
    const fullCapture = "com.ledyer.order.full_capture";
    const partialCapture = "com.ledyer.order.part_capture";
    const fullRefund = "com.ledyer.order.full_refund";
    const partialRefund = "com.ledyer.order.part_refund";
    const cancel = "com.ledyer.order.cancel";
}
