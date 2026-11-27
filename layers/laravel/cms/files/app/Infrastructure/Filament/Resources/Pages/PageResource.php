<?php

declare(strict_types=1);

namespace App\Infrastructure\Filament\Resources\Pages;

use App\Application\Contract\Cms\PublicMediaStore;
use App\Domain\Cms\ContentExport;
use App\Infrastructure\Filament\Resources\Pages\Pages\CreatePage;
use App\Infrastructure\Filament\Resources\Pages\Pages\EditPage;
use App\Infrastructure\Filament\Resources\Pages\Pages\ListPages;
use App\Infrastructure\Filament\Resources\Pages\RelationManagers\RevisionsRelationManager;
use App\Infrastructure\Models\Page;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

final class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Page')->columns(2)->schema([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(static function (Set $set, ?string $state, ?string $old, ?Page $record): void {
                        // Suggest a slug for a new page only; the URL of an existing page must not change by accident.
                        if ($record === null && $state !== null) {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(100)
                    ->regex(ContentExport::SLUG_PATTERN)
                    ->unique(ignoreRecord: true)
                    ->helperText('URL path segment: a-z, 0-9 and single hyphens. Ignored for the home page, which lives at /.'),
                Textarea::make('description')
                    ->rows(2)
                    ->maxLength(300)
                    ->helperText('Meta description for search engines.')
                    ->columnSpanFull(),
                Toggle::make('is_home')->label('Home page (/)')->helperText('Exactly one published page must be the home page.'),
                Toggle::make('is_published')->label('Published')->default(true),
                TextInput::make('sort')->numeric()->integer()->minValue(0)->default(0),
                Toggle::make('noindex')
                    ->label('Hide from search engines')
                    ->helperText('The page gets noindex and is left out of the sitemap, llms.txt and the Markdown versions (thank-you pages, for example).'),
                FileUpload::make('og_image')
                    ->label('Social image')
                    ->helperText('Open Graph image of this page (1200 x 630 px recommended). The site image is used when empty.')
                    ->disk(PublicMediaStore::MEDIA_DISK)
                    ->directory('images')
                    ->visibility('public')
                    ->image()
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/avif'])
                    ->maxSize(10240)
                    ->columnSpanFull(),
            ]),
            Section::make('Content')->schema([
                PageBlocks::builder(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('slug')->searchable(),
                IconColumn::make('is_home')->label('Home')->boolean(),
                IconColumn::make('is_published')->label('Published')->boolean(),
                TextColumn::make('sort')->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('sort')
            ->filters([TrashedFilter::make()])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    /** @return Builder<Model> */
    public static function getEloquentQuery(): Builder
    {
        // Deleted pages stay reachable (TrashedFilter): their history can bring one back.
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    /** @return array<class-string<RelationManager>> */
    public static function getRelations(): array
    {
        return [RevisionsRelationManager::class];
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
