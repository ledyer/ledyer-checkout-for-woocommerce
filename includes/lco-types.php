<?php

\defined( 'ABSPATH' ) || die();

abstract class LedyerPaymentStatus {
	const orderInitiated   = 'orderInitiated';
	const orderPending     = 'orderPending';
	const paymentPending   = 'paymentPending';
	const paymentConfirmed = 'paymentConfirmed';
	const orderCaptured    = 'orderCaptured';
	const orderRefunded    = 'orderRefunded';
	const orderCancelled   = 'orderCancelled';
	const unknown          = 'unknown';
}
