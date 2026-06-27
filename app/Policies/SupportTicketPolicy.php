<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;

class SupportTicketPolicy
{
    public function view(User $user, SupportTicket $supportTicket): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->client_id !== null
            && $supportTicket->project()->where('client_id', $user->client_id)->exists();
    }

    public function reply(User $user, SupportTicket $supportTicket): bool
    {
        return $this->view($user, $supportTicket);
    }
}
