<?php

namespace App\Livewire\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('إدارة المستخدمين')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'بحث')]
    public string $search = '';

    public ?int $editingId = null;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public ?int $roleId = null;

    public string $password = '';

    public string $passwordConfirmation = '';

    public bool $isActive = true;

    public function mount(): void
    {
        Gate::authorize('users.manage');
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->with('role:id,name,label')
            ->when($this->search !== '', function ($query): void {
                $term = trim($this->search);
                $query->where(fn ($nested) => $nested->where('name', 'like', $term.'%')->orWhere('username', $term));
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);
    }

    #[Computed]
    public function roles(): Collection
    {
        return Role::query()->where('name', '!=', 'practitioner')->orderBy('id')->get(['id', 'name', 'label']);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function edit(int $userId): void
    {
        Gate::authorize('users.manage');
        $user = User::query()->whereDoesntHave('practitioner')->findOrFail($userId);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->username = $user->username;
        $this->email = $user->email ?? '';
        $this->roleId = $user->role_id;
        $this->isActive = $user->is_active;
    }

    public function save(): void
    {
        Gate::authorize('users.manage');
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:64', 'alpha_dash:ascii', Rule::unique('users', 'username')->ignore($this->editingId)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'roleId' => ['required', Rule::exists('roles', 'id')->where(fn ($query) => $query->where('name', '!=', 'practitioner'))],
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:6', 'same:passwordConfirmation'],
            'passwordConfirmation' => [$this->password !== '' ? 'required' : 'nullable', 'string'],
            'isActive' => ['boolean'],
        ]);

        $attributes = [
            'role_id' => $validated['roleId'],
            'name' => trim($validated['name']),
            'username' => mb_strtolower(trim($validated['username'])),
            'email' => filled($validated['email']) ? mb_strtolower(trim($validated['email'])) : null,
            'is_active' => $validated['isActive'],
        ];

        if ($validated['password'] !== '') {
            $attributes['password'] = $validated['password'];
        }

        $saved = DB::transaction(function () use ($attributes, $validated): bool {
            $user = $this->editingId === null
                ? new User
                : User::query()->whereDoesntHave('practitioner')->lockForUpdate()->findOrFail($this->editingId);
            $newRole = Role::query()->findOrFail($validated['roleId']);

            if (! $this->activeAdministratorRemains($user, $newRole, $validated['isActive'])) {
                return false;
            }

            $user->fill($attributes)->save();

            return true;
        }, 3);

        if (! $saved) {
            $this->addError('roleId', 'يجب أن يبقى مدير عام نشط واحد على الأقل قبل تغيير هذا الحساب.');

            return;
        }

        $this->cancelEditing();
        $this->dispatch('app-toast', type: 'success', message: 'تم حفظ المستخدم بنجاح.');
    }

    public function toggle(int $userId): void
    {
        Gate::authorize('users.manage');
        abort_if($userId === auth()->id(), 422, 'لا يمكنك تعطيل حسابك الحالي.');
        $toggled = DB::transaction(function () use ($userId): bool {
            $user = User::query()->whereDoesntHave('practitioner')->lockForUpdate()->findOrFail($userId);
            $role = Role::query()->findOrFail($user->role_id);

            if (! $this->activeAdministratorRemains($user, $role, ! $user->is_active)) {
                return false;
            }

            $user->update(['is_active' => ! $user->is_active]);

            return true;
        }, 3);

        if (! $toggled) {
            $this->addError('users', 'يجب أن يبقى مدير عام نشط واحد على الأقل قبل تعطيل هذا الحساب.');
        }
    }

    public function cancelEditing(): void
    {
        $this->reset(['editingId', 'name', 'username', 'email', 'roleId', 'password', 'passwordConfirmation']);
        $this->isActive = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.users.index');
    }

    private function activeAdministratorRemains(User $user, Role $newRole, bool $willBeActive): bool
    {
        $superAdministratorRoleId = Role::query()->where('name', 'super-admin')->value('id');

        if (! $user->exists || ! $user->is_active || $user->role_id !== $superAdministratorRoleId) {
            return true;
        }

        if ($willBeActive && $newRole->name === 'super-admin') {
            return true;
        }

        $otherActiveAdministrators = User::query()
            ->whereKeyNot($user->getKey())
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('name', 'super-admin'))
            ->lockForUpdate()
            ->get(['users.id']);

        if ($otherActiveAdministrators->isEmpty()) {
            return false;
        }

        return true;
    }
}
