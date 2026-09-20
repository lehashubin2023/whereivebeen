<?php

namespace App\Http\Controllers\Admin;

use App\Actions\User\CreateUser;
use App\Actions\User\UpdateUser;
use App\DTOs\User\CreateUserDTO;
use App\DTOs\User\UpdateUserDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\DeleteUserRequest;
use App\Http\Requests\User\IndexUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Group;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Patch;
use Spatie\RouteAttributes\Attributes\Post;

#[Middleware(['auth', 'admin'])]
#[Group(prefix: 'admin/users', as: 'admin.users.')]
class AdminUserController extends Controller
{
    public function __construct(
        private readonly CreateUser $createUser,
        private readonly UpdateUser $updateUser,
    ) {}

    #[Get(uri: '', name: 'index')]
    public function index(IndexUserRequest $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        $search = $request->search();

        $users = User::query()
            ->when($search !== null, fn ($query) => $query->where('email', 'like', '%'.$search.'%'))
            ->withCount('gameSessions')
            ->latest('id')
            ->paginate((int) config('pagination.per_page'))
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'email' => $user->email,
                'is_admin' => $user->isAdmin(),
                'is_main_admin' => $user->isMainAdmin(),
                'sessions_count' => $user->game_sessions_count,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'can_edit' => $actor->can('update', $user),
                'can_delete' => $actor->can('delete', $user),
            ]);

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'search' => $search,
            'currentUserId' => $actor->id,
        ]);
    }

    #[Get(uri: 'create', name: 'create')]
    public function create(): Response
    {
        return Inertia::render('admin/users/Create', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'canAssignAdmin' => Gate::allows('manageAdmins', User::class),
        ]);
    }

    #[Post(uri: '', name: 'store')]
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->createUser->exec(CreateUserDTO::fromArray($request->validated()));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User created.'),
        ]);

        return to_route('admin.users.index');
    }

    #[Get(uri: '{managedUser}/edit', name: 'edit')]
    public function edit(User $managedUser): Response
    {
        Gate::authorize('update', $managedUser);

        return Inertia::render('admin/users/Edit', [
            'user' => [
                'id' => $managedUser->id,
                'email' => $managedUser->email,
                'is_admin' => $managedUser->isAdmin(),
                'is_main_admin' => $managedUser->isMainAdmin(),
            ],
            'isSelf' => $managedUser->id === auth()->id(),
            'canAssignAdmin' => Gate::allows('manageAdmins', User::class),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    #[Patch(uri: '{managedUser}', name: 'update')]
    public function update(UpdateUserRequest $request, User $managedUser): RedirectResponse
    {
        $this->updateUser->exec($managedUser, UpdateUserDTO::fromArray($request->validated()));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User updated.'),
        ]);

        return to_route('admin.users.index');
    }

    #[Delete(uri: '{managedUser}', name: 'destroy')]
    public function destroy(DeleteUserRequest $request, User $managedUser): RedirectResponse
    {
        $managedUser->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User deleted.'),
        ]);

        return to_route('admin.users.index');
    }
}
