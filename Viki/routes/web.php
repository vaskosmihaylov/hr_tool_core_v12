<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

App::setLocale('bg');

Route::group(['middleware' => ['web', 'auth']], function () {
    Route::get('service/index', 'IndexController@index')->name('service.index');

    // Worker Routes
    Route::get('service/worker', 'WorkerController@index')->name('service.worker');
    Route::get('service/worker/create', 'WorkerController@viewFormWorker')->name('service.worker.create');
    Route::get('service/worker/insert_holidays', 'WorkerController@insertHolidays')->name('service.worker.insert.holidays');
    Route::post('service/worker/vacation/{id}', 'WorkerController@createVacation')->name('service.worker.vacation');
    Route::get('service/worker/vacation/{id}', 'WorkerController@viewFormVacation')->name('service.worker.vacation');
    Route::delete('service/worker/vacation/delete/{id}', 'WorkerController@destroyVacation')->name('service.worker.vacation.delete');
    Route::post('service/worker', 'WorkerController@createWorker')->name('service.worker.create');
    Route::get('service/worker/edit/{id}', 'WorkerController@edit')->name('service.worker.edit');
    Route::patch('service/worker/edit/{id}', 'WorkerController@update')->name('service.worker.update');
    Route::delete('service/worker/delete/{id}', 'WorkerController@destroy')->name('service.worker.delete');

    Route::get('service/worker/bonus/{id}', 'WorkerController@bonus')->name('service.worker.bonus');
    Route::patch('service/worker/bonus/{id}', 'WorkerController@saveBonus')->name('service.worker.saveBonus');
    Route::delete('service/worker/bonus/delete/{id}', 'WorkerController@deleteBonus')->name('service.worker.deleteBonus');

    // Region Routes
    Route::get('service/region', 'RegionController@index')->name('service.region');
    Route::get('service/region/create', 'RegionController@viewFormRegion')->name('service.region.create');
    Route::post('service/region', 'RegionController@createRegion')->name('service.region.create');
    Route::get('service/region/edit/{id}', 'RegionController@edit')->name('service.region.edit');
    Route::patch('service/region/edit/{id}', 'RegionController@update')->name('service.region.update');
    Route::delete('service/region/delete/{id}', 'RegionController@destroy')->name('service.region.delete');

    // Client Routes
    Route::get('service/client', 'ClientController@index')->name('service.client');
    Route::get('service/client/create', 'ClientController@viewFormClient')->name('service.client.create');
    Route::post('service/client', 'ClientController@createClient')->name('service.client.create');
    Route::get('service/client/edit/{id}', 'ClientController@edit')->name('service.client.edit');
    Route::patch('service/client/edit/{id}', 'ClientController@update')->name('service.client.update');
    Route::delete('service/client/delete/{id}', 'ClientController@destroy')->name('service.client.delete');

    // Workplace Routes
    Route::get('service/workplace', 'WorkPlaceController@index')->name('service.workplace');
    Route::get('service/workplace/create', 'WorkPlaceController@viewFormWorkPlace')->name('service.workplace.create');
    Route::post('service/workplace', 'WorkPlaceController@createWorkPlace')->name('service.workplace.create');
    Route::get('service/workplace/edit/{id}', 'WorkPlaceController@edit')->name('service.workplace.edit');
    Route::patch('service/workplace/edit/{id}', 'WorkPlaceController@update')->name('service.workplace.update');
    Route::delete('service/workplace/delete/{id}', 'WorkPlaceController@destroy')->name('service.workplace.delete');

    Route::post('service/workplace/activity/{id}', 'WorkPlaceController@createWorkPlaceActivity')->name('service.workplace.activity');
    Route::get('service/workplace/activity/{id}', 'WorkPlaceController@viewFormWorkPlaceActivity')->name('service.workplace.activity');
    Route::delete('service/workplace/activity/delete/{id}', 'WorkPlaceController@destroyActivity')->name('service.workplace.activity.delete');
    Route::get('service/workplace/activity/edit/{id}', 'WorkPlaceController@editActivity')->name('service.workplace.activity.edit');
    Route::post('service/workplace/activity/edit/{id}', 'WorkPlaceController@updateActivity')->name('service.workplace.activity.update');

    // Users Routes
    Route::get('service/users', 'UserController@index')->name('service.users');
    Route::get('service/users/show/{id}', 'UserController@show')->name('service.users.show');
    Route::get('service/users/create', 'UserController@create')->name('service.users.create');
    Route::post('service/users/create', 'UserController@store')->name('service.users.create');
    Route::get('service/users/edit/{id}', 'UserController@edit')->name('service.users.edit');
    Route::patch('service/users/edit/{id}', 'UserController@update')->name('service.users.edit');
    Route::delete('service/users/delete/{id}', 'UserController@destroy')->name('service.users.delete');
    Route::patch('service/users/restore/{id}', 'UserController@restore')->name('service.users.restore');

    // Presence Routes (Time Tracking)
    Route::get('service/presence', 'PresenceController@index')->name('service.presence');
    Route::post('service/presence/finish', 'PresenceController@finish')->name('service.presence.finish');
    Route::post('service/presence/unfinish', 'PresenceController@unfinish')->name('service.presence.unfinish');
    Route::get('service/presence/addWorker/{workPlaceId}/{date}', 'PresenceController@viewAddWorker')->name('service.presence.addworker');
    Route::post('service/presence/addWorker/{workPlaceId}/{date}', 'PresenceController@storeAddWorkerRecords')->name('service.presence.add.worker');

    Route::get('service/presence/deleteWorker/{workPlaceId}/{date}', 'PresenceController@viewdeleteWorker')->name('service.presence.deleteworker');
    Route::post('service/presence/removeWorker', 'PresenceController@storedeleteWorkerRecords')->name('service.presence.remove.worker');

    Route::post('service/presence/table/save', 'PresenceController@saveTableData')->name('service.presence.table.save');
    Route::get('service/presence/show/{workPlaceId}', 'PresenceController@index')->name('service.presence.show.workplace');
    Route::get('service/presence/show/{workPlaceId}/{date}', 'PresenceController@index')->name('service.presence.show.workplace.date');
    Route::get('service/presence/config/{workPlaceId}/{date}', 'PresenceController@viewConfigForm')->name('service.presence.config');
    Route::get('service/presence/activity/add/{workPlaceId}/{date}', 'PresenceController@viewFormWorkPlaceActivityAdd')->name('service.presence.activity.add');
    Route::post('service/presence/activity/add/{workPlaceId}/{date}', 'PresenceController@createWorkPlaceActivityByMonth')->name('service.presence.activity.add');
    Route::get('service/presence/activity/edit/{id}/{date}', 'PresenceController@editActivity')->name('service.presence.activity.edit');
    Route::get('service/presence/export/{id}/{date}', 'PresenceController@exportDetailedPdf')->name('service.presence.export');
    Route::post('service/presence/activity/edit/{id}/{date}', 'PresenceController@updateActivity')->name('service.presence.activity.update');
    Route::delete('service/presence/activity/delete/{id}', 'PresenceController@destroyActivity')->name('service.presence.activity.delete');

    // Approval Routes (одобрения)
    Route::get('service/approvement', 'ApprovementController@index')->name('service.approvement');
    Route::get('/service/approvement/comment/{id}', 'ApprovementController@viewCommentForm')->name('service.approvement.comment');
    Route::post('/service/approvement/comment/add', 'ApprovementController@storeComment')->name('service.approvement.comment.add');
    Route::post('/service/approvement/approve/{id}', 'ApprovementController@approve')->name('service.approvement.approve');
    Route::post('/service/approvement/disapprove/{id}', 'ApprovementController@disapprove')->name('service.approvement.disapprove');

    // History Routes (история)
    Route::get('service/history', 'HistoryController@index')->name('service.history');

    // Report Routes (репорти)
    Route::post('service/report/exportPdf', 'ReportController@exportDetailedPdf')->name('service.exportpdf');
    Route::post('service/report/exportExcel', 'ReportController@exportDetailedExcel')->name('service.exportExcel');
    Route::get('service/reports', 'ReportController@index')->name('service.reports');
    Route::get('service/reports/workers', 'ReportController@index')->name('service.reports');
    Route::get('service/reports/workerplace', 'ReportController@viewWorkerPlaceReport')->name('service.reports.workerplace');
    Route::get('service/reports/worplace', 'ReportController@viewWorkerPlaceReport');
    Route::get('service/reports/exportWorkerExcel/{month_id}/{year_id}/{egn?}', 'ReportController@exportWorkerExcel')->name('service.reports.worker.excel');

    // Archive Routes
    Route::get('service/archive', 'ArchiveController@index')->name('service.archive');
    Route::get('service/archive/{workPlaceId}', 'ArchiveController@index')->name('service.archive.show.workplace');
    Route::get('service/archive/{workPlaceId}/{date}', 'ArchiveController@index')->name('service.archive.show.workplace.date');
});
