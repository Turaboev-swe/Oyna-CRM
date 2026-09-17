<?php

namespace App\Enums;

enum OrderDraftStep: string
{
    case AwaitingCalcChoice = 'awaiting_calc_choice';
    case AwaitingSquareMeters = 'awaiting_square_meters';
    case AwaitingWidth = 'awaiting_width';
    case AwaitingHeight = 'awaiting_height';
    case AwaitingCustomerChoice = 'awaiting_customer_choice';
    case AwaitingCustomerSelection = 'awaiting_customer_selection';
    case AwaitingCustomerName = 'awaiting_customer_name';
    case AwaitingCustomerPhone = 'awaiting_customer_phone';
    case AwaitingConfirmation = 'awaiting_confirmation';
}
