<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\ChangeUserRoleRequest;
use App\Http\Requests\User\ResetUserPasswordRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\AuditActions;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }

    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->serializeUser($user));

        return Inertia::render('Users/Index', [
            'users' => $users,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Create', [
            'roles' => Roles::ALL,
            'statuses' => User::STATUSES,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validated();
        $role = $data['role'];
        unset($data['role']);

        $user = DB::transaction(function () use ($data, $role) {
            $user = User::query()->create([
                ...$data,
                'email_verified_at' => now(),
                'locale' => $data['locale'] ?? 'en',
                'status' => $data['status'] ?? User::STATUS_ACTIVE,
            ]);
            $user->syncRoles([$role]);

            return $user;
        });

        $this->audit->log(
            AuditActions::USER_CREATED,
            "Created user {$user->email}",
            $user,
            [
                'email' => $user->email,
                'role' => $role,
                'status' => $user->status,
            ],
        );

        return redirect()
            ->route('users.show', $user)
            ->with('success', __('User created.'));
    }

    public function show(User $user): Response
    {
        $this->authorize('view', $user);

        $user->load('roles:id,name');

        return Inertia::render('Users/Show', [
            'userRecord' => $this->serializeUser($user, detailed: true),
            'roles' => Roles::ALL,
        ]);
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        $user->load('roles:id,name');

        return Inertia::render('Users/Edit', [
            'userRecord' => $this->serializeUser($user, detailed: true),
            'roles' => Roles::ALL,
            'statuses' => User::STATUSES,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validated();
        $newRole = $data['role'] ?? null;
        unset($data['role'], $data['password']);

        $oldRole = $user->getRoleNames()->first();
        $before = $user->only(['name', 'email', 'phone', 'status']);

        DB::transaction(function () use ($user, $data, $newRole) {
            $user->fill($data)->save();
            if ($newRole && $newRole !== $user->getRoleNames()->first()) {
                $this->assertNotLastSuperAdmin($user, $newRole);
                $user->syncRoles([$newRole]);
            }
        });

        $user->refresh()->load('roles:id,name');
        $afterRole = $user->getRoleNames()->first();

        $this->audit->log(
            AuditActions::USER_UPDATED,
            "Updated user {$user->email}",
            $user,
            [
                'before' => $before,
                'after' => $user->only(['name', 'email', 'phone', 'status']),
                'role' => $afterRole,
            ],
        );

        if ($oldRole !== $afterRole) {
            $this->audit->log(
                AuditActions::USER_ROLE_CHANGED,
                "Changed role for {$user->email} from {$oldRole} to {$afterRole}",
                $user,
                ['from' => $oldRole, 'to' => $afterRole],
            );
        }

        return redirect()
            ->route('users.show', $user)
            ->with('success', __('User updated.'));
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        return $this->disable($user);
    }

    public function disable(User $user): RedirectResponse
    {
        $this->authorize('disable', $user);
        $this->assertNotSelf($user);

        if ($user->isDisabled()) {
            return redirect()
                ->route('users.show', $user)
                ->with('success', __('User already disabled.'));
        }

        $user->forceFill(['status' => User::STATUS_DISABLED])->save();

        $this->audit->log(
            AuditActions::USER_DISABLED,
            "Disabled user {$user->email}",
            $user,
            ['email' => $user->email],
        );

        return redirect()
            ->route('users.show', $user)
            ->with('success', __('User disabled.'));
    }

    public function enable(User $user): RedirectResponse
    {
        $this->authorize('enable', $user);

        if ($user->isActive()) {
            return redirect()
                ->route('users.show', $user)
                ->with('success', __('User already active.'));
        }

        $user->forceFill(['status' => User::STATUS_ACTIVE])->save();

        $this->audit->log(
            AuditActions::USER_ENABLED,
            "Enabled user {$user->email}",
            $user,
            ['email' => $user->email],
        );

        return redirect()
            ->route('users.show', $user)
            ->with('success', __('User enabled.'));
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $this->authorize('resetPassword', $user);

        $user->forceFill([
            'password' => $request->validated('password'),
        ])->save();

        $this->audit->log(
            AuditActions::USER_PASSWORD_RESET,
            "Reset password for {$user->email}",
            $user,
            ['email' => $user->email],
        );

        return redirect()
            ->route('users.show', $user)
            ->with('success', __('Password reset.'));
    }

    public function changeRole(ChangeUserRoleRequest $request, User $user): RedirectResponse
    {
        $this->authorize('changeRole', $user);

        $newRole = $request->validated('role');
        $oldRole = $user->getRoleNames()->first();

        if ($newRole === $oldRole) {
            return redirect()
                ->route('users.show', $user)
                ->with('success', __('Role unchanged.'));
        }

        $this->assertNotLastSuperAdmin($user, $newRole);
        $user->syncRoles([$newRole]);

        $this->audit->log(
            AuditActions::USER_ROLE_CHANGED,
            "Changed role for {$user->email} from {$oldRole} to {$newRole}",
            $user,
            ['from' => $oldRole, 'to' => $newRole],
        );

        return redirect()
            ->route('users.show', $user)
            ->with('success', __('Role updated.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user, bool $detailed = false): array
    {
        $payload = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status ?? User::STATUS_ACTIVE,
            'role' => $user->getRoleNames()->first(),
            'roles' => $user->getRoleNames()->values()->all(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $payload['locale'] = $user->locale;
            $payload['email_verified_at'] = $user->email_verified_at?->toIso8601String();
            $payload['updated_at'] = $user->updated_at?->toIso8601String();
        }

        return $payload;
    }

    private function assertNotSelf(User $user): void
    {
        if (auth()->id() === $user->id) {
            throw ValidationException::withMessages([
                'user' => __('You cannot disable your own account.'),
            ]);
        }
    }

    private function assertNotLastSuperAdmin(User $user, string $newRole): void
    {
        if (! $user->hasRole(Roles::SUPER_ADMIN) || $newRole === Roles::SUPER_ADMIN) {
            return;
        }

        $otherAdmins = User::query()
            ->role(Roles::SUPER_ADMIN)
            ->where('id', '!=', $user->id)
            ->where('status', User::STATUS_ACTIVE)
            ->count();

        if ($otherAdmins === 0) {
            throw ValidationException::withMessages([
                'role' => __('Cannot remove the last active Super Admin.'),
            ]);
        }
    }
}
