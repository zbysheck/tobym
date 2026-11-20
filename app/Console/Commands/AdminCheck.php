<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class AdminCheck extends Command
{
    protected $signature = 'tobym:admin-check';

    protected $description = 'Sprawdza ustawienie ADMIN_EMAILS (bez wypisywania adresów)';

    public function handle(): int
    {
        $admins = config('tobym.admin_emails');
        $admins = is_array($admins) ? $admins : [];

        $this->line('Adminów w konfiguracji: '.count($admins));

        foreach (array_values($admins) as $index => $email) {
            $user = User::query()->whereRaw('lower(email) = ?', [(string) $email])->first();
            $this->line(sprintf(
                'Admin #%d: %s, konto %s',
                $index + 1,
                str_contains((string) $email, '@') ? 'wygląda na mail' : 'NIE wygląda na mail',
                $user instanceof User ? 'istnieje' : 'jeszcze nie istnieje (powstanie przy pierwszym logowaniu)',
            ));
        }

        $this->line('Konfiguracja z cache: '.(app()->configurationIsCached() ? 'tak' : 'nie'));

        return self::SUCCESS;
    }
}
