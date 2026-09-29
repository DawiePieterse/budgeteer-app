<?php

use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CategoriseController;
use App\Http\Controllers\GmailController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\RecurringController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\TransactionController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::view('/privacy', 'privacy')->name('privacy');

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('login', [
        'devUsers' => app()->isLocal() && config('budgeteer.dev_login') ? User::all() : collect(),
    ]))->name('login');
    Route::get('/auth/google', [GoogleController::class, 'redirect'])->middleware('throttle:10,1')->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->middleware('throttle:10,1')->name('google.callback');
    Route::post('/dev-login/{user}', DevLoginController::class)->name('dev-login');
});

Route::middleware(['auth', 'throttle:120,1'])->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::post('/logout', [GoogleController::class, 'logout'])->name('logout');

    Route::get('/statements', [StatementController::class, 'index'])->name('statements.index');
    Route::post('/statements/preview', [StatementController::class, 'preview'])->middleware('throttle:20,1')->name('statements.preview');
    Route::post('/statements', [StatementController::class, 'store'])->name('statements.store');

    Route::get('/categorise', [CategoriseController::class, 'index'])->name('categorise');
    Route::post('/categorise', [CategoriseController::class, 'store'])->name('categorise.store');

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'edit'])->name('transactions.edit');
    Route::post('/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');

    Route::get('/gmail/link', [GmailController::class, 'link'])->middleware('throttle:10,1')->name('gmail.link');
    Route::get('/gmail/callback', [GmailController::class, 'callback'])->middleware('throttle:10,1')->name('gmail.callback');
    Route::post('/gmail/{connection}/sync', [GmailController::class, 'sync'])->middleware('throttle:10,1')->name('gmail.sync');
    Route::post('/gmail/{connection}/delete', [GmailController::class, 'destroy'])->name('gmail.destroy');

    Route::get('/budget', [BudgetController::class, 'edit'])->name('budget');
    Route::post('/budget', [BudgetController::class, 'update'])->name('budget.update');
    Route::post('/budget/paste', [BudgetController::class, 'paste'])->name('budget.paste');
    Route::post('/budget/merge', [BudgetController::class, 'merge'])->name('budget.merge');
    Route::post('/budget/reset', [BudgetController::class, 'reset'])->name('budget.reset');
    Route::post('/budget/tidy', [BudgetController::class, 'tidy'])->name('budget.tidy');

    Route::get('/recurring', [RecurringController::class, 'index'])->name('recurring.index');
    Route::post('/recurring', [RecurringController::class, 'store'])->name('recurring.store');
    Route::post('/recurring/{recurring}', [RecurringController::class, 'update'])->name('recurring.update');
    Route::post('/recurring/{recurring}/delete', [RecurringController::class, 'destroy'])->name('recurring.destroy');
    Route::post('/recurring/{recurring}/mark', [RecurringController::class, 'mark'])->name('recurring.mark');
    Route::post('/recurring/{recurring}/use-amount/{transaction}', [RecurringController::class, 'useAmount'])->name('recurring.use-amount');

    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::post('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::post('/projects/{project}/forget/{key}', [ProjectController::class, 'forgetMerchant'])->name('projects.forget');

    Route::post('/cards/{card}', [CardController::class, 'update'])->name('cards.update');

    Route::get('/people/{person}', [PersonController::class, 'show'])->name('people.show');
    Route::post('/people/{person}', [PersonController::class, 'update'])->name('people.update');
    Route::post('/people/{person}/settlements', [PersonController::class, 'storeSettlement'])->name('people.settlements.store');
    Route::post('/people/{person}/settlements/in-full', [PersonController::class, 'settleInFull'])->name('people.settlements.in-full');
    Route::post('/people/{person}/settlements/from/{transaction}', [PersonController::class, 'settleFromTransaction'])->name('people.settlements.from');
    Route::post('/people/{person}/settlements/{settlement}/delete', [PersonController::class, 'destroySettlement'])->name('people.settlements.destroy');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::post('/push/subscriptions', [PushController::class, 'store'])->middleware('throttle:20,1')->name('push.store');
    Route::post('/push/subscriptions/delete', [PushController::class, 'destroy'])->name('push.destroy');
    Route::post('/push/subscriptions/{subscription}/delete', [PushController::class, 'remove'])->name('push.remove');
    Route::post('/push/preferences', [PushController::class, 'preferences'])->name('push.preferences');
    Route::post('/push/test', [PushController::class, 'test'])->middleware('throttle:5,1')->name('push.test');
});
