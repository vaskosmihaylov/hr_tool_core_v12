<?php
 App::setLocale('bg');
// Legacy Viki routes - moved to /legacy prefix to avoid conflicts with Filament /service panel
Route::group(['middleware' => ['web','auth','application.permission']], function () {
    Route::get('legacy/service/index', 'IndexController@index')->name('service.index');

	Route::get('legacy/service/worker', 'WorkerController@index')->name('service.worker');
	Route::get('legacy/service/worker/create', 'WorkerController@viewFormWorker')->name('service.worker.create');
	Route::get('legacy/service/worker/insert_holidays', 'WorkerController@insertHolidays')->name('service.worker.insert.holidays');
	Route::post('legacy/service/worker/vacation/{id}', 'WorkerController@createVacation')->name('service.worker.vacation');
	Route::get('legacy/service/worker/vacation/{id}', 'WorkerController@viewFormVacation')->name('service.worker.vacation');
	Route::delete('legacy/service/worker/vacation/delete/{id}', 'WorkerController@destroyVacation')->name('service.worker.vacation.delete');
	Route::post('legacy/service/worker', 'WorkerController@createWorker')->name('service.worker.create');
	Route::get('legacy/service/worker/edit/{id}', 'WorkerController@edit')->name('service.worker.edit');
	Route::patch('legacy/service/worker/edit/{id}', 'WorkerController@update')->name('service.worker.update');
	Route::delete('legacy/service/worker/delete/{id}', 'WorkerController@destroy')->name('service.worker.delete');
	
    Route::get('legacy/service/worker/bonus/{id}', 'WorkerController@bonus')->name('service.worker.bonus');
	Route::patch('legacy/service/worker/bonus/{id}', 'WorkerController@saveBonus')->name('service.worker.saveBonus');
    Route::delete('legacy/service/worker/bonus/delete/{id}', 'WorkerController@deleteBonus')->name('service.worker.deleteBonus');
  
    Route::get('legacy/service/region', 'RegionController@index')->name('service.region');
    Route::get('legacy/service/region/create', 'RegionController@viewFormRegion')->name('service.region.create');
    Route::post('legacy/service/region', 'RegionController@createRegion')->name('service.region.create');
	Route::get('legacy/service/region/edit/{id}', 'RegionController@edit')->name('service.region.edit');
    Route::patch('legacy/service/region/edit/{id}', 'RegionController@update')->name('service.region.update');
    Route::delete('legacy/service/region/delete/{id}', 'RegionController@destroy')->name('service.region.delete');
 
    Route::get('legacy/service/client', 'ClientController@index')->name('service.client');
    Route::get('legacy/service/client/create', 'ClientController@viewFormClient')->name('service.client.create');
    Route::post('legacy/service/client', 'ClientController@createClient')->name('service.client.create');
    Route::get('legacy/service/client/edit/{id}', 'ClientController@edit')->name('service.client.edit');
    Route::patch('legacy/service/client/edit/{id}', 'ClientController@update')->name('service.client.update');
    Route::delete('legacy/service/client/delete/{id}', 'ClientController@destroy')->name('service.client.delete');
    
    Route::get('legacy/service/workplace', 'WorkPlaceController@index')->name('service.workplace');
    Route::get('legacy/service/workplace/create', 'WorkPlaceController@viewFormWorkPlace')->name('service.workplace.create');
    Route::post('legacy/service/workplace', 'WorkPlaceController@createWorkPlace')->name('service.workplace.create');
    Route::get('legacy/service/workplace/edit/{id}', 'WorkPlaceController@edit')->name('service.workplace.edit');
    Route::patch('legacy/service/workplace/edit/{id}', 'WorkPlaceController@update')->name('service.workplace.update');
    Route::delete('legacy/service/workplace/delete/{id}', 'WorkPlaceController@destroy')->name('service.workplace.delete');

    Route::post('legacy/service/workplace/activity/{id}', 'WorkPlaceController@createWorkPlaceActivity')->name('service.workplace.activity');
    Route::get('legacy/service/workplace/activity/{id}', 'WorkPlaceController@viewFormWorkPlaceActivity')->name('service.workplace.activity');
    Route::delete('legacy/service/workplace/activity/delete/{id}', 'WorkPlaceController@destroyActivity')->name('service.workplace.activity.delete'); 
    Route::get('legacy/service/workplace/activity/edit/{id}', 'WorkPlaceController@editActivity')->name('service.workplace.activity.edit');
    Route::post('legacy/service/workplace/activity/edit/{id}', 'WorkPlaceController@updateActivity')->name('service.workplace.activity.update');
    
    


    // Users Routes
    Route::get('legacy/service/users', 'UserController@index')->name('service.users');
    Route::get('legacy/service/users/show/{id}', 'UserController@show')->name('service.users.show');
    Route::get('legacy/service/users/create', 'UserController@create')->name('service.users.create');
    Route::post('legacy/service/users/create', 'UserController@store')->name('service.users.create');
    Route::get('legacy/service/users/edit/{id}', 'UserController@edit')->name('service.users.edit');
    Route::patch('legacy/service/users/edit/{id}', 'UserController@update')->name('service.users.edit');
    Route::delete('legacy/service/users/delete/{id}', 'UserController@destroy')->name('service.users.delete');
    Route::patch('legacy/service/users/restore/{id}', 'UserController@restore')->name('service.users.restore');

    Route::get('legacy/service/presence', 'PresenceController@index')->name('service.presence');
    Route::post('legacy/service/presence/finish', 'PresenceController@finish')->name('service.presence.finish');
    Route::post('legacy/service/presence/unfinish', 'PresenceController@unfinish')->name('service.presence.unfinish');
    Route::get('legacy/service/presence/addWorker/{workPlaceId}/{date}', 'PresenceController@viewAddWorker')->name('service.presence.addworker');
    Route::post('legacy/service/presence/addWorker/{workPlaceId}/{date}', 'PresenceController@storeAddWorkerRecords')->name('service.presence.add.worker');
	
	Route::get('legacy/service/presence/deleteWorker/{workPlaceId}/{date}', 'PresenceController@viewdeleteWorker')->name('service.presence.deleteworker');
	Route::post('legacy/service/presence/removeWorker', 'PresenceController@storedeleteWorkerRecords')->name('service.presence.remove.worker');
  
    Route::post('legacy/service/presence/table/save', 'PresenceController@saveTableData')->name('service.presence.table.save');
    Route::get('legacy/service/presence/show/{workPlaceId}', 'PresenceController@index')->name('service.presence.show.workplace');
    Route::get('legacy/service/presence/show/{workPlaceId}/{date}', 'PresenceController@index')->name('service.presence.show.workplace.date');
    Route::get('legacy/service/presence/config/{workPlaceId}/{date}', 'PresenceController@viewConfigForm')->name('service.presence.config');
    Route::get('legacy/service/presence/activity/add/{workPlaceId}/{date}', 'PresenceController@viewFormWorkPlaceActivityAdd')->name('service.presence.activity.add');
    Route::post('legacy/service/presence/activity/add/{workPlaceId}/{date}', 'PresenceController@createWorkPlaceActivityByMonth')->name('service.presence.activity.add');
    Route::get('legacy/service/presence/activity/edit/{id}/{date}', 'PresenceController@editActivity')->name('service.presence.activity.edit');
    Route::get('legacy/service/presence/export/{id}/{date}', 'PresenceController@exportDetailedPdf')->name('service.presence.export');
    Route::post('legacy/service/presence/activity/edit/{id}/{date}', 'PresenceController@updateActivity')->name('service.presence.activity.update');
    Route::delete('legacy/service/presence/activity/delete/{id}', 'PresenceController@destroyActivity')->name('service.presence.activity.delete');
	
	//одобрения
	Route::get('legacy/service/approvement', 'ApprovementController@index')->name('service.approvement');
	Route::get('/legacy/service/approvement/comment/{id}', 'ApprovementController@viewCommentForm')->name('service.approvement.comment');
	Route::post('/legacy/service/approvement/comment/add', 'ApprovementController@storeComment')->name('service.approvement.comment.add');
	Route::post('/legacy/service/approvement/approve/{id}', 'ApprovementController@approve')->name('service.approvement.approve');
	Route::post('/legacy/service/approvement/disapprove/{id}', 'ApprovementController@disapprove')->name('service.approvement.disapprove');
	
	//история
	Route::get('legacy/service/history', 'HistoryController@index')->name('service.history');
	
	//репорти
	Route::POST('legacy/service/report/exportPdf', 'ReportController@exportDetailedPdf')->name('service.exportpdf');
	Route::POST('legacy/service/report/exportExcel', 'ReportController@exportDetailedExcel')->name('service.exportExcel');
	Route::get('legacy/service/reports', 'ReportController@index')->name('service.reports');
	Route::get('legacy/service/reports/workers', 'ReportController@index')->name('service.reports');
	Route::get('legacy/service/reports/workerplace', 'ReportController@viewWorkerPlaceReport')->name('service.reports.workerplace');
	Route::get('legacy/service/reports/worplace', 'ReportController@viewWorkerPlaceReport');
	Route::get('legacy/service/reports/exportWorkerExcel/{month_id}/{year_id}/{egn?}', 'ReportController@exportWorkerExcel')->name('service.reports.worker.excel');
	//Route::get('legacy/service/report/exportPdf/{month_id}/{year_id}/{workplace_id}/{region_id}/{client_id}/{worker_id}', //'ReportController@exportDetailedPdf')->name('service.report.export');
	//Route::get('legacy/service/report/exportPdf/{month_id}/{year_id}/{workplace_id}/{region_id}/{client_id}/{worker_id}', //'ReportController@exportDetailedPdf')->name('service.report.export');
	//Route::get('legacy/service/report/exportExcel/{month_id}/{year_id}/{workplace_id}/{region_id}/{client_id}/{worker_id}', //'ReportController@exportDetailedExcel')->name('service.report.exportExcel');

	//archive
    Route::get('legacy/service/archive', 'ArchiveController@index')->name('service.archive');
    Route::get('legacy/service/archive/{workPlaceId}', 'ArchiveController@index')->name('service.archive.show.workplace');
    Route::get('legacy/service/archive/{workPlaceId}/{date}', 'ArchiveController@index')->name('service.archive.show.workplace.date');
});