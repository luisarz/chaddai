<?php

namespace App\Filament\Resources\OperationConditionResource\Pages;

use App\Filament\Resources\OperationConditionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOperationConditions extends ListRecords
{
    protected static string $resource = OperationConditionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
