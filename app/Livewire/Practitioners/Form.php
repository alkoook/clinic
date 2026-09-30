<?php

namespace App\Livewire\Practitioners;

use App\Models\Practitioner;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Form extends Component
{
    public ?Practitioner $practitioner = null;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $phone = '';

    public string $telegramChatId = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    /** @var array<int, int|string> */
    public array $specialtyIds = [];

    public string $administrativeNotes = '';

    public bool $isActive = true;

    public function mount(?Practitioner $practitioner = null): void
    {
        Gate::authorize('practitioners.manage');
        $this->practitioner = $practitioner?->exists ? $practitioner->load('user', 'specialties') : null;

        if ($this->practitioner) {
            $this->name = $this->practitioner->name;
            $this->username = $this->practitioner->user->username;
            $this->email = $this->practitioner->user->email ?? '';
            $this->phone = $this->practitioner->phone ?? '';
            $this->telegramChatId = $this->practitioner->telegram_chat_id ?? '';
            $this->specialtyIds = $this->practitioner->specialties->modelKeys();
            $this->administrativeNotes = $this->practitioner->administrative_notes ?? '';
            $this->isActive = $this->practitioner->is_active;
        }
    }

    #[Computed]
    public function specialties(): Collection
    {
        return Specialty::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function save(): mixed
    {
        Gate::authorize('practitioners.manage');

        $userId = $this->practitioner?->user_id;
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:64', 'alpha_dash:ascii', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:32'],
            'telegramChatId' => ['nullable', 'string', 'max:128'],
            'password' => [$this->practitioner ? 'nullable' : 'required', 'string', 'min:6', 'same:passwordConfirmation'],
            'passwordConfirmation' => [$this->password !== '' ? 'required' : 'nullable', 'string'],
            'specialtyIds' => ['required', 'array', 'min:1'],
            'specialtyIds.*' => ['integer', 'distinct', 'exists:specialties,id'],
            'administrativeNotes' => ['nullable', 'string', 'max:3000'],
            'isActive' => ['boolean'],
        ]);

        DB::transaction(function () use ($validated): void {
            $role = Role::query()->where('name', 'practitioner')->firstOrFail();
            $userAttributes = [
                'role_id' => $role->id,
                'name' => trim($validated['name']),
                'username' => mb_strtolower(trim($validated['username'])),
                'email' => filled($validated['email']) ? mb_strtolower(trim($validated['email'])) : null,
                'is_active' => $validated['isActive'],
            ];

            if ($validated['password'] !== '') {
                $userAttributes['password'] = $validated['password'];
            }

            if ($this->practitioner) {
                $user = User::query()->lockForUpdate()->findOrFail($this->practitioner->user_id);
                $user->update($userAttributes);
                $this->practitioner->update([
                    'name' => trim($validated['name']),
                    'phone' => filled($validated['phone']) ? trim($validated['phone']) : null,
                    'telegram_chat_id' => filled($validated['telegramChatId']) ? trim($validated['telegramChatId']) : null,
                    'administrative_notes' => filled($validated['administrativeNotes']) ? trim($validated['administrativeNotes']) : null,
                    'is_active' => $validated['isActive'],
                ]);
            } else {
                $user = User::query()->create($userAttributes);
                $this->practitioner = Practitioner::query()->create([
                    'user_id' => $user->id,
                    'name' => trim($validated['name']),
                    'phone' => filled($validated['phone']) ? trim($validated['phone']) : null,
                    'telegram_chat_id' => filled($validated['telegramChatId']) ? trim($validated['telegramChatId']) : null,
                    'administrative_notes' => filled($validated['administrativeNotes']) ? trim($validated['administrativeNotes']) : null,
                    'is_active' => $validated['isActive'],
                ]);
            }

            $this->practitioner->specialties()->sync(array_map('intval', $validated['specialtyIds']));
        });

        session()->flash('success', 'تم حفظ حساب الطبيب / المعالج.');

        return $this->redirectRoute('practitioners.show', $this->practitioner, navigate: true);
    }

    public function render()
    {
        return view('livewire.practitioners.form')->title($this->practitioner ? 'تعديل الطبيب / المعالج' : 'إضافة طبيب / معالج');
    }
}
