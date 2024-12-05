<?php

namespace App\Filament\Server\Resources\DatabaseResource\Pages;

use App\Filament\Server\Resources\DatabaseResource;
use App\Models\Database;
use App\Models\DatabaseHost;
use App\Models\Permission;
use App\Models\Server;
use App\Services\Databases\DatabaseManagementService;
use App\Services\Databases\DatabasePasswordService;
use App\Tables\Columns\DateTimeColumn;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Webbingbrasil\FilamentCopyActions\Forms\Actions\CopyAction;

class ListDatabases extends ListRecords
{
    protected static string $resource = DatabaseResource::class;

    public function form(Form $form): Form
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $form
            ->schema([
                TextInput::make('database')
                    ->columnSpanFull()
                    ->suffixAction(CopyAction::make()),
                TextInput::make('username')
                    ->suffixAction(CopyAction::make()),
                TextInput::make('password')
                    ->password()->revealable()
                    ->hidden(fn () => !auth()->user()->can(Permission::ACTION_DATABASE_VIEW_PASSWORD, $server))
                    ->hintAction(
                        Action::make('rotate')
                            ->authorize(fn () => auth()->user()->can(Permission::ACTION_DATABASE_UPDATE, $server))
                            ->icon('tabler-refresh')
                            ->requiresConfirmation()
                            ->action(function (DatabasePasswordService $service, Database $database, $set, $get) {
                                $newPassword = $service->handle($database);

                                $set('password', $newPassword);
                                $set('JDBC', 'jdbc:mysql://' . $get('username') . ':' . urlencode($newPassword) . '@' . $database->host->host . ':' . $database->host->port . '/' . $get('database'));
                            })
                    )
                    ->suffixAction(CopyAction::make())
                    ->formatStateUsing(fn (Database $database) => $database->password),
                TextInput::make('remote')
                    ->label('Connections From'),
                TextInput::make('max_connections')
                    ->formatStateUsing(fn (Database $database) => $database->max_connections === 0 ? $database->max_connections : 'Unlimited'),
                TextInput::make('JDBC')
                    ->label('JDBC Connection String')
                    ->password()->revealable()
                    ->hidden(!auth()->user()->can(Permission::ACTION_DATABASE_VIEW_PASSWORD, $server))
                    ->suffixAction(CopyAction::make())
                    ->columnSpanFull()
                    ->formatStateUsing(fn (Get $get, Database $database) => 'jdbc:mysql://' . $get('username') . ':' . urlencode($database->password) . '@' . $database->host->host . ':' . $database->host->port . '/' . $get('database')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('database'),
                TextColumn::make('username'),
                TextColumn::make('remote'),
                DateTimeColumn::make('created_at')
                    ->sortable(),
            ])
            ->actions([
                ViewAction::make()
                    ->modalHeading(fn (Database $database) => 'Viewing ' . $database->database),
                DeleteAction::make(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return [
            CreateAction::make('new')
                ->label(fn () => $server->databases()->count() >= $server->database_limit ? 'Database limit reached' : 'Create Database')
                ->disabled(fn () => $server->databases()->count() >= $server->database_limit)
                ->color(fn () => $server->databases()->count() >= $server->database_limit ? 'danger' : 'primary')
                ->createAnother(false)
                ->form([
                    Grid::make()
                        ->columns(3)
                        ->schema([
                            TextInput::make('database')
                                ->columnSpan(2)
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

                    $data['database_host_id'] = DatabaseHost::where('node_id', $server->node_id)->first()->id;
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
