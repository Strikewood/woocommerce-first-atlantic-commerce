<?php

use Omnipay\Common\Exception\OmnipayException;

/**
 * FAC Transaction exception.
 *
 * Thrown when a gateway responded with an unsuccesful response.
 */
class FacTransactionException extends \Exception implements OmnipayException
{
    public function __construct($message = "Unsuccesful response from payment gateway", $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
