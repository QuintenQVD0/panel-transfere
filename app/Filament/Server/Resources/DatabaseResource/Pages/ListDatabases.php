<?php

namespace App\Filament\Server\Resources\DatabaseResource\Pages;

use App\Filament\Server\Resources\DatabaseResource;
use App\Models\DatabaseHost;
use App\Models\Server;
use App\Services\Databases\DatabaseManagementService;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\IconSize;

class ListDatabases extends ListRecords
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = DatabaseResource::class;

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return [
            CreateAction::make('new')
                ->hiddenLabel()->iconButton()->iconSize(IconSize::Large)
                ->icon(fn () => $server->databases()->count() >= $server->database_limit ? 'tabler-database-x' : 'tabler-database-plus')
                ->tooltip(fn () => $server->databases()->count() >= $server->database_limit ? 'Database limit reached' : 'Create Database')
                ->disabled(fn () => $server->databases()->count() >= $server->database_limit)
                ->color(fn () => $server->databases()->count() >= $server->database_limit ? 'danger' : 'primary')
                ->createAnother(false)
                ->form([
                    Grid::make()
                        ->columns(2)
                        ->schema([
                            Select::make('database_host_id')
                                ->label('Database Host')
                                ->columnSpan(2)
                                ->required()
                                ->placeholder('Select Database Host')
                                ->options(fn () => $server->node->databaseHosts->mapWithKeys(fn (DatabaseHost $databaseHost) => [$databaseHost->id => $databaseHost->name])),
                            TextInput::make('database')
                                ->columnSpan(1)
                                ->label('Database Name')
                                ->prefix('s'. $server->id . '_')
                                ->hintIcon('tabler-question-mark')
                                ->hintIconTooltip('Leaving this blank will auto generate a random name'),
                            TextInput::make('remote')
                                ->columnSpan(1)
                                ->label('Connections From')
                                ->default('%'),
                        ]),
                ])
                ->action(function ($data, DatabaseManagementService $service) use ($server) {
                    if (empty($data['database'])) {
                        $data['database'] = str_random(12);
                    }
                    $data['database'] = 's'. $server->id . '_' . $data['database'];

                    $service->create($server, $data);
                }),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
