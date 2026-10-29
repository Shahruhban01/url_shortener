<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShortUrlController;
use App\Http\Controllers\InvitationController;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Role-based access control routes
Route::middleware(['auth', 'role:super_admin'])->group(function () {
    Route::get('/companies/create', function () {
        return view('invitations.create-company');
    })->name('invitations.company.create');

    Route::post('/companies/invite-admin', [
        InvitationController::class,
        'storeCompanyInvitation',
    ])->name('invitations.company.store');
});

Route::middleware(['auth', 'role:admin'])
    ->get('/admin-area', function () {
        return 'Admin access granted';
    });

    
Route::middleware(['auth', 'role:member'])
    ->get('/member-area', function () {
        return 'Member access granted';
    });

// Public redirect
Route::get('/s/{code}', [ShortUrlController::class, 'redirect'])
    ->name('short-urls.redirect');

// Create short URLs: Admin and Member only
Route::middleware(['auth', 'role:admin,member'])->group(function () {
    Route::post('/short-urls', [ShortUrlController::class, 'store'])
        ->name('short-urls.store');
});

// Send invitation to a new Admin iser
Route::get('/invitations/{token}', [
    InvitationController::class,
    'showAcceptanceForm',
])->name('invitations.accept');


// Url creator route for Admin and Member users
Route::get('/short-urls/create', function () {
    return view('short-urls.create');
})->name('short-urls.create');

require __DIR__.'/auth.php';
