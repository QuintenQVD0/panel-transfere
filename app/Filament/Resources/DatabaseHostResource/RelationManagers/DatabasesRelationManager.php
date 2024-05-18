<?php

namespace App\Filament\Resources\DatabaseHostResource\RelationManagers;

use App\Models\Database;
use App\Services\Databases\DatabasePasswordService;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DatabasesRelationManager extends RelationManager
{
    protected static string $relationship = 'databases';

    protected $listeners = ['refresh' => 'refreshForm'];

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('database')->columnSpanFull(),
                Forms\Components\TextInput::make('username'),
                Forms\Components\TextInput::make('password')
                    ->hintAction(
                        Action::make('rotate')
                            ->icon('tabler-refresh')
                            ->requiresConfirmation()
                            ->action(fn (DatabasePasswordService $service, Database $database) => $service->handle($database))
                    )
                    ->formatStateUsing(fn (Database $database) => decrypt($database->password)),
                Forms\Components\TextInput::make('remote')->label('Connections From'),
                Forms\Components\TextInput::make('max_connections'),
                Forms\Components\TextInput::make('JDBC')
                    ->label('JDBC Connection String')
                    ->columnSpanFull()
                    ->formatStateUsing(fn (Forms\Get $get, Database $database) => 'jdbc:mysql://' . $get('username') . ':' . urlencode(decrypt($database->password)) . '@' . $database->host->host . ':' . $database->host->port . '/' . $get('database')),
            ]);
    }
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('servers')
            ->columns([
                Tables\Columns\TextColumn::make('database')->icon('tabler-database'),
                Tables\Columns\TextColumn::make('username')->icon('tabler-user'),
                //Tables\Columns\TextColumn::make('password'),
                Tables\Columns\TextColumn::make('remote'),
                Tables\Columns\TextColumn::make('server.name')
                    ->icon('tabler-brand-docker')
                    ->url(fn (Database $database) => route('filament.admin.resources.servers.edit', ['record' => $database->server_id])),
                Tables\Columns\TextColumn::make('max_connections'),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ViewAction::make()->color('primary'),
                //Tables\Actions\EditAction::make(),
            ]);
    }
}
