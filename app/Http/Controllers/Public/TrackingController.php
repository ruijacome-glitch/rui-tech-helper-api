<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tickets\TicketController;
use App\Models\Ticket;

class TrackingController extends Controller
{
    public function show(string $token)
    {
        $ticket = Ticket::where('tracking_token', $token)->firstOrFail();

        $ticketArray = (new TicketController)->serializeTicketDetailCliente($ticket);

        return response()->json(['ticket' => $ticketArray]);
    }
}
