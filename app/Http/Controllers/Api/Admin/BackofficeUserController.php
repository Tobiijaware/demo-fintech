<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserType;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreBackofficeUserRequest;
use App\Http\Requests\Admin\UpdateBackofficeUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class BackofficeUserController extends ApiController
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->where('user_type', UserType::Staff)
            ->with('backofficeRole')
            ->orderByDesc('created_at')
            ->paginate(20);

        $users->getCollection()->transform(fn (User $user) => $this->formatUser($user));

        return $this->success($users);
    }

    public function store(StoreBackofficeUserRequest $request): JsonResponse
    {
        $user = User::query()->create([
            'firstname' => $request->validated('firstname'),
            'lastname' => $request->validated('lastname'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'user_type' => UserType::Staff,
            'backoffice_role_id' => $request->validated('backoffice_role_id'),
            'job_title' => $request->validated('job_title'),
            'hub' => $request->validated('hub'),
            'role' => 'admin',
            'status' => $request->validated('status', 'active'),
            'email_verified_at' => now(),
        ]);

        $user->load('backofficeRole');

        return $this->success($this->formatUser($user), 'User created.', 201);
    }

    public function update(UpdateBackofficeUserRequest $request, User $user): JsonResponse
    {
        if ($user->user_type !== UserType::Staff) {
            return $this->error('Not a back-office user.', 422);
        }

        $data = $request->safe()->only([
            'firstname', 'lastname', 'email', 'backoffice_role_id', 'job_title', 'hub', 'status',
        ]);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->validated('password'));
        }

        $user->update($data);
        $user->load('backofficeRole');

        return $this->success($this->formatUser($user), 'User updated.');
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->user_type !== UserType::Staff) {
            return $this->error('Not a back-office user.', 422);
        }

        $user->delete();

        return $this->success(null, 'User removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'status' => $user->status?->value ?? $user->status,
            'job_title' => $user->job_title,
            'hub' => $user->hub,
            'backoffice_role_id' => $user->backoffice_role_id,
            'role' => $user->backofficeRole ? [
                'id' => $user->backofficeRole->id,
                'slug' => $user->backofficeRole->slug,
                'name' => $user->backofficeRole->name,
            ] : null,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
