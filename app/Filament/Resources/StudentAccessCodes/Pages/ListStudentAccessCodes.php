<?php

namespace App\Filament\Resources\StudentAccessCodes\Pages;

use App\Filament\Resources\StudentAccessCodes\StudentAccessCodeResource;
use App\Models\ClassSession;
use App\Models\StudentAccessCode;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class ListStudentAccessCodes extends ListRecords
{
    protected static string $resource = StudentAccessCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateCodes')
                ->label('Generate Access Codes')
                ->icon(Heroicon::OutlinedPlus)
                ->modalHeading('Generate Student Access Codes')
                ->modalDescription('Create one-time access codes for students in a selected class batch.')
                ->modalSubmitActionLabel('Generate Codes')
                ->schema([
                    Select::make('class_session_id')
                        ->label('Class Batch')
                        ->options(fn (): array => ClassSession::query()
                            ->orderByDesc('starts_on')
                            ->pluck('title', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('quantity')
                        ->label('Number of Codes')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->default(30)
                        ->required(),
                    // Bangkok time in, UTC in the database. This one matters
                    // most of the three: an access code that expires seven
                    // hours early locks a paying student out of the course.
                    DateTimePicker::make('expires_at')
                        ->label('Expiry Date and Time (Thailand)')
                        ->helperText('Leave empty for no expiry.')
                        ->timezone(config('app.display_timezone')),
                    Textarea::make('notes')
                        ->label('Notes')
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    $quantity = (int) $data['quantity'];
                    $classSessionId = (int) $data['class_session_id'];
                    $created = 0;

                    DB::transaction(function () use ($data, $quantity, $classSessionId, &$created): void {
                        for ($i = 0; $i < $quantity; $i++) {
                            do {
                                $plainCode = self::makeCode($classSessionId);
                                $fingerprint = StudentAccessCode::fingerprint($plainCode);
                            } while (StudentAccessCode::query()->where('code_hash', $fingerprint)->exists());

                            StudentAccessCode::query()->create([
                                'class_session_id' => $classSessionId,
                                'code_hash' => $fingerprint,
                                'code_encrypted' => StudentAccessCode::encryptCode($plainCode),
                                'code_last4' => substr(StudentAccessCode::normalize($plainCode), -4),
                                'status' => 'available',
                                'expires_at' => $data['expires_at'] ?? null,
                                'created_by_user_id' => auth()->id(),
                                'notes' => $data['notes'] ?? null,
                            ]);

                            $created++;
                        }
                    });

                    Notification::make()
                        ->title("{$created} access codes generated")
                        ->body('The codes are ready to copy from the table.')
                        ->success()
                        ->send();
                }),
        ];
    }

    private static function makeCode(int $classSessionId): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $randomPart = '';

        for ($i = 0; $i < 6; $i++) {
            $randomPart .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return 'PBR-B'.str_pad((string) $classSessionId, 2, '0', STR_PAD_LEFT).'-'.$randomPart;
    }
}
