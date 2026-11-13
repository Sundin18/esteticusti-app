<?php

use App\Actions\Fortify\CreateNewUser;
use App\Core\Authorization\UserRole;
use Flux\Flux;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Register')] class extends Component {
    public string $firstname = '';
    public string $lastname = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /** @var list<string> */
    public array $roles = [];

    /**
     * Vytvoří nového uživatele s vybranými rolemi; správce zůstane přihlášený.
     */
    public function register(CreateNewUser $createNewUser): void
    {
        $this->authorize('users.create');

        $this->validate([
            'roles' => ['array'],
            'roles.*' => [Rule::enum(UserRole::class)],
        ]);

        $user = DB::transaction(function () use ($createNewUser) {
            $user = $createNewUser->create($this->only([
                'firstname', 'lastname', 'email', 'password', 'password_confirmation',
            ]));

            foreach ($this->roles as $role) {
                $user->assignRole(UserRole::from($role));
            }

            return $user;
        });

        event(new Registered($user));

        $this->reset();

        Flux::toast(variant: 'success', text: __('User created.'));
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('New user') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Create an account for a new user and choose their roles') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <form wire:submit="register" class="w-full max-w-lg space-y-6">
        <flux:input wire:model="firstname" :label="__('First name')" type="text" required autofocus autocomplete="off" />

        <flux:input wire:model="lastname" :label="__('Last name')" type="text" required autocomplete="off" />

        <flux:input wire:model="email" :label="__('Email address')" type="email" required autocomplete="off" placeholder="email@example.com" />

        <flux:checkbox.group wire:model="roles" :label="__('Roles')">
            @foreach (UserRole::cases() as $role)
                <flux:checkbox wire:key="role-{{ $role->value }}" :value="$role->value" :label="$role->label()" />
            @endforeach
        </flux:checkbox.group>

        <flux:input
            wire:model="password"
            :label="__('Password')"
            type="password"
            required
            autocomplete="new-password"
            passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
            viewable
        />

        <flux:input
            wire:model="password_confirmation"
            :label="__('Confirm password')"
            type="password"
            required
            autocomplete="new-password"
            viewable
        />

        <flux:button type="submit" variant="primary" data-test="register-user-button">
            {{ __('Create account') }}
        </flux:button>
    </form>
</section>
