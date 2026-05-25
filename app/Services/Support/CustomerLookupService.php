<?php

namespace App\Services\Support;

use App\Models\User;
use Illuminate\Support\Collection;

class CustomerLookupService
{
    /**
     * @return Collection<int, User>
     */
    public function search(?string $query, int $limit = 20): Collection
    {
        if (! $query || trim($query) === '') {
            return collect();
        }

        $term = trim($query);

        return User::query()
            ->with(['wallet'])
            ->whereHas('wallet')
            ->where(function ($q) use ($term) {
                $q->where('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhereRaw("concat(firstname, ' ', lastname) like ?", ["%{$term}%"])
                    ->orWhereHas('wallet', fn ($wallet) => $wallet->where('account_number', 'like', "%{$term}%"));
            })
            ->orderBy('firstname')
            ->limit($limit)
            ->get();
    }
}
