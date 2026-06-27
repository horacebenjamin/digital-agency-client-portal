<?php

namespace App\Policies;

use App\Models\ProjectFile;
use App\Models\User;

class ProjectFilePolicy
{
    public function download(User $user, ProjectFile $projectFile): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->client_id !== null
            && $projectFile->project()->where('client_id', $user->client_id)->exists();
    }
}
