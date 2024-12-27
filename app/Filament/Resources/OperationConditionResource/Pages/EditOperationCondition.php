<?php

namespace App\Filament\Resources\OperationConditionResource\Pages;

use App\Filament\Resources\OperationConditionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOperationCondition extends EditRecord
{
    protected static string $resource = OperationConditionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
