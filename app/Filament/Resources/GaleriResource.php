<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GaleriResource\Pages;
use App\Models\Link;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Section; 
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;  
use Carbon\Carbon;
use Illuminate\Support\Str;

class GaleriResource extends Resource
{
    protected static ?string $model = Link::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationGroup = 'Postingan';
    protected static ?string $label = 'Postingan';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->rules(['string', 'max:255']),
                   
             


 DatePicker::make('published_at')
    ->label('Tanggal')
    ->formatStateUsing(fn ($state) => 
        Str::lower(Carbon::parse($state)->translatedFormat('l, d F Y'))
    ),

              
              
                
                 Forms\Components\MarkdownEditor::make('description')
                            ->required()
                            ->columnSpan('full'),

                Forms\Components\TextInput::make('url')
                    ->label('URL')
                    ->required()
                    ->url()
                    ->maxLength(255)
                    ->rules(['url', 'max:255'])
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('image')
                    ->label('Gambar')
                    ->directory('user-blog-links')
                    ->image()
                    ->preserveFilenames()
                    ->imagePreviewHeight('150')
                    ->downloadable()
                    ->maxSize(2048)
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/gif'])
                    ->disk('public')
                    ->visibility('public')
                    ->nullable()
                    ->columnSpanFull(),

                
                
            ]);
    }

 public static function table(Table $table): Table
{
    return $table
        ->columns([
            // Image Column
            Tables\Columns\ImageColumn::make('image'),
                

            // Title Column
            Tables\Columns\TextColumn::make('title')
                ->weight(FontWeight::Bold)
                ->default('Tanpa Judul'), // Fallback if title is null

            // URL Column
            Tables\Columns\TextColumn::make('url')
                ->formatStateUsing(fn (string $state): string => str($state)->after('://')->ltrim('www.')->trim('/'))
                ->color('gray')
                ->limit(30)
                ->default('Tidak ada URL'), // Fallback if URL is null

            // Description Column
            Tables\Columns\TextColumn::make('description')
                ->color('gray')
                ->default('Tanpa Deskripsi'), // Fallback if description is null

            // Status Column (Approval Status)
            Tables\Columns\TextColumn::make('status')
                ->label('Status Approval')
                ->formatStateUsing(fn ($state) => ucfirst(strtolower($state))) // Formats status to be capitalized
                ->color('gray')
                ->default('Pending'), // Fallback if status is null
        ])
        ->emptyStateHeading('Tidak ada data')
        ->emptyStateDescription('Silakan tambahkan link baru.')
        ->filters([])
        ->paginated([18, 36, 72, 'all'])
       ->actions([

        
            // Action Group for all actions (Visit, Edit, Delete, Terima, Tolak)
            Tables\Actions\ActionGroup::make([
                
                   Tables\Actions\ViewAction::make(),

                // Visit link action
                Tables\Actions\Action::make('visit')
                    ->label('Visit link')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Link $record): string => $record->url ?? '#'),

                // Edit action
                Tables\Actions\EditAction::make(),

                // Delete action
                Tables\Actions\DeleteAction::make(),

                // Accept Button (Terima) - only visible if status is 'Pending' and user is Kepala Dinas
                Tables\Actions\Action::make('accept')
                    ->label('Terima')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->hidden(fn (Link $record) => $record->status !== 'Pending' || !Auth::user()->hasRole('kepala_dinas')) // Hide if status is not 'Pending' or user is not Kepala Dinas
                    ->action(function (Link $record) {
                        // Update the status to 'Approved'
                        $record->update(['status' => 'Approved']);
                        Notification::make()
                            ->title('Status updated to Approved')
                            ->success()
                            ->send();
                    }),

                // Reject Button (Tolak) - only visible if status is 'Pending' and user is Kepala Dinas
                Tables\Actions\Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->hidden(fn (Link $record) => $record->status !== 'Pending' || !Auth::user()->hasRole('kepala_dinas')) // Hide if status is not 'Pending' or user is not Kepala Dinas
                    ->action(function (Link $record) {
                        // Update the status to 'Rejected'
                        $record->update(['status' => 'Rejected']);
                        Notification::make()
                            ->title('Status updated to Rejected')
                            ->danger()
                            ->send();
                    }),

            ])
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make()
                    ->action(function () {
                        Notification::make()
                            ->title('Now, now, don\'t be cheeky, leave some records for others to play with!')
                            ->warning()
                            ->send();
                    }),
            ]),
        ]);
}


    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGaleris::route('/'),
            'create' => Pages\CreateGaleri::route('/create'),
            'edit' => Pages\EditGaleri::route('/{record}/edit'),
        ];
    }
}
