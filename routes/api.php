<?php

// Global Search...
Route::get('/search', \Binaryk\LaravelRestify\Http\Controllers\GlobalSearchController::class);

Route::get('/profile', \Binaryk\LaravelRestify\Http\Controllers\ProfileController::class);
Route::put('/profile', \Binaryk\LaravelRestify\Http\Controllers\ProfileUpdateController::class);
Route::post('/profile', \Binaryk\LaravelRestify\Http\Controllers\ProfileUpdateController::class);

// RestifyJS
Route::get('/restifyjs/setup', \Binaryk\LaravelRestify\Http\Controllers\RestifyJsSetupController::class)->withoutMiddleware(
    Binaryk\LaravelRestify\Http\Middleware\RestifySanctumAuthenticate::class,
);

// Filters
Route::get('/{repository}/filters', \Binaryk\LaravelRestify\Http\Controllers\RepositoryFilterController::class);

// Actions
Route::get('/{repository}/actions', \Binaryk\LaravelRestify\Http\Controllers\ListActionsController::class)->name('restify.actions.index');
Route::get('/{repository}/{repositoryId}/actions', \Binaryk\LaravelRestify\Http\Controllers\ListRepositoryActionsController::class)->name('restify.actions.repository.index');
Route::post('/{repository}/action', \Binaryk\LaravelRestify\Http\Controllers\PerformActionController::class)->name('restify.actions.perform');
Route::post('/{repository}/actions', \Binaryk\LaravelRestify\Http\Controllers\PerformActionController::class); // alias to the previous route
Route::post('/{repository}/{repositoryId}/action', \Binaryk\LaravelRestify\Http\Controllers\PerformRepositoryActionController::class)->name('restify.actions.repository.perform');
Route::post('/{repository}/{repositoryId}/actions', \Binaryk\LaravelRestify\Http\Controllers\PerformRepositoryActionController::class); // alias to the previous route

// API CRUD
Route::get('/{repository}', \Binaryk\LaravelRestify\Http\Controllers\RepositoryIndexController::class)->name('restify.index');
Route::post('/{repository}', \Binaryk\LaravelRestify\Http\Controllers\RepositoryStoreController::class)->name('restify.store');
Route::post('/{repository}/bulk', \Binaryk\LaravelRestify\Http\Controllers\RepositoryStoreBulkController::class)->name('restify.store.bulk');
Route::post('/{repository}/bulk/update', \Binaryk\LaravelRestify\Http\Controllers\RepositoryUpdateBulkController::class)->name('restify.update.bulk');
Route::get('/{repository}/{repositoryId}', \Binaryk\LaravelRestify\Http\Controllers\RepositoryShowController::class)->name('restify.show');
Route::patch('/{repository}/{repositoryId}', \Binaryk\LaravelRestify\Http\Controllers\RepositoryPatchController::class)->name('restify.patch');
Route::put('/{repository}/{repositoryId}', \Binaryk\LaravelRestify\Http\Controllers\RepositoryUpdateController::class)->name('restify.put');
Route::post('/{repository}/{repositoryId}', \Binaryk\LaravelRestify\Http\Controllers\RepositoryUpdateController::class)->name('restify.update');
Route::delete('/{repository}/{repositoryId}', \Binaryk\LaravelRestify\Http\Controllers\RepositoryDestroyController::class)->name('restify.destroy');

// Fields
Route::delete('/{repository}/{repositoryId}/field/{field}', \Binaryk\LaravelRestify\Http\Controllers\FieldDestroyController::class);

// Attach related repository id
Route::post('/{repository}/{repositoryId}/attach/{relatedRepository}', \Binaryk\LaravelRestify\Http\Controllers\RepositoryAttachController::class);
Route::post('/{repository}/{repositoryId}/detach/{relatedRepository}', \Binaryk\LaravelRestify\Http\Controllers\RepositoryDetachController::class);

// Relatable
Route::get('/{parentRepository}/{parentRepositoryId}/{repository}', \Binaryk\LaravelRestify\Http\Controllers\RepositoryIndexController::class);
Route::post('/{parentRepository}/{parentRepositoryId}/{repository}', \Binaryk\LaravelRestify\Http\Controllers\RepositoryStoreController::class);
Route::get('/{parentRepository}/{parentRepositoryId}/{repository}/{repositoryId}', \Binaryk\LaravelRestify\Http\Controllers\RepositoryShowController::class);
Route::post('/{parentRepository}/{parentRepositoryId}/{repository}/{repositoryId}', \Binaryk\LaravelRestify\Http\Controllers\RepositoryUpdateController::class);
Route::put('/{parentRepository}/{parentRepositoryId}/{repository}/{repositoryId}', \Binaryk\LaravelRestify\Http\Controllers\RepositoryUpdateController::class);
Route::delete('/{parentRepository}/{parentRepositoryId}/{repository}/{repositoryId}', \Binaryk\LaravelRestify\Http\Controllers\RepositoryDestroyController::class);
