<?php

use App\Filament\Service\Resources\ServiceUserResource;

it('includes the read-only HR role in service user role options', function () {
    expect(ServiceUserResource::getServiceUserRoleOptions())
        ->toMatchArray([
            'manager' => 'Мениджър',
            'supervisor' => 'Супервайзор',
            'hr' => 'HR',
        ])
        ->and(ServiceUserResource::getServiceUserRoleNames())
        ->toContain('hr');
});
