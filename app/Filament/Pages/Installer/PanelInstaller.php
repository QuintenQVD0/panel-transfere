<?php

namespace App\Filament\Pages\Installer;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Installer\Steps\AdminUserStep;
use App\Filament\Pages\Installer\Steps\CompletedStep;
use App\Filament\Pages\Installer\Steps\DatabaseStep;
use App\Filament\Pages\Installer\Steps\EnvironmentStep;
use App\Filament\Pages\Installer\Steps\RedisStep;
use App\Filament\Pages\Installer\Steps\RequirementsStep;
use App\Models\User;
use App\Services\Users\UserCreationService;
use App\Traits\CheckMigrationsTrait;
use App\Traits\EnvironmentWriterTrait;
use Exception;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Support\Enums\MaxWidth;
use Filament\Support\Exceptions\Halt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * @property Form $form
 */
class PanelInstaller extends SimplePage implements HasForms
{
    use CheckMigrationsTrait;
    use EnvironmentWriterTrait;
    use InteractsWithForms;

    public array $data = [];

    protected static string $view = 'filament.pages.installer';

    private User $user;

    public function getMaxWidth(): MaxWidth|string
    {
        return MaxWidth::SevenExtraLarge;
    }

    public static function isInstalled(): bool
    {
        // This defaults to true so existing panels count as "installed"
        return env('APP_INSTALLED', true);
    }

    public function mount(): void
    {
        abort_if(self::isInstalled(), 404);

        $this->form->fill();
    }

    protected function getFormSchema(): array
    {
        return [
            Wizard::make([
                RequirementsStep::make(),
                EnvironmentStep::make($this),
                DatabaseStep::make($this),
                RedisStep::make($this)
                    ->hidden(fn (Get $get) => $get('env_general.SESSION_DRIVER') != 'redis' && $get('env_general.QUEUE_CONNECTION') != 'redis' && $get('env_general.CACHE_STORE') != 'redis'),
                AdminUserStep::make($this),
                CompletedStep::make(),
            ])
                ->persistStepInQueryString()
                ->nextAction(fn (Action $action) => $action->keyBindings('enter'))
                ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                    <x-filament::button
                        type="submit"
                        size="sm"
                        wire:loading.attr="disabled"
                    >
                        Finish
                        <span wire:loading><x-filament::loading-indicator class="h-4 w-4" /></span>
                    </x-filament::button>
                BLADE))),
        ];
    }

    protected function getFormStatePath(): ?string
    {
        return 'data';
    }

    public function submit(): Redirector|RedirectResponse
    {
        // Disable installer
        $this->writeToEnvironment(['APP_INSTALLED' => 'true']);

        // Login user
        $this->user ??= User::all()->filter(fn ($user) => $user->isRootAdmin())->first();
        auth()->guard()->login($this->user, true);

        // Redirect to admin panel
        return redirect(Dashboard::getUrl());
    }

    public function writeToEnv(string $key): void
    {
        try {
            $variables = array_get($this->data, $key);
            $this->writeToEnvironment($variables);
        } catch (Exception $exception) {
            report($exception);

            Notification::make()
                ->title('Could not write to .env file')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            throw new Halt('Error while writing .env file');
        }

        Artisan::call('config:clear');
    }

    public function runMigrations(string $driver): void
    {
        try {
            Artisan::call('migrate', [
                '--force' => true,
                '--seed' => true,
                '--database' => $driver,
            ]);
        } catch (Exception $exception) {
            report($exception);

            Notification::make()
                ->title('Migrations failed')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            throw new Halt('Error while running migrations');
        }

        if (!$this->hasCompletedMigrations()) {
            Notification::make()
                ->title('Migrations failed')
                ->danger()
                ->persistent()
                ->send();

            throw new Halt('Migrations failed');
        }
    }

    public function createAdminUser(UserCreationService $userCreationService): void
    {
        try {
            $userData = array_get($this->data, 'user');
            $userData['root_admin'] = true;
            $this->user = $userCreationService->handle($userData);
        } catch (Exception $exception) {
            report($exception);

            Notification::make()
                ->title('Could not create admin user')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            throw new Halt('Error while creating admin user');
        }
    }
}
